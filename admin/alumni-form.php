<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$duplicate_from = (int)($_GET['duplicate'] ?? 0);
$edit = null;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM alumni WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($duplicate_from > 0) {
    $stmt = $pdo->prepare("SELECT * FROM alumni WHERE id = ?");
    $stmt->execute([$duplicate_from]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($source) {
        $edit = $source;
        $edit['id'] = null;
        $edit['nama'] = $source['nama'] . ' (Copy)';
        $edit['foto'] = null; // Jangan duplikat file
        flash_message('info', '📋 Menduplikasi data alumni: ' . htmlspecialchars($source['nama']));
    }
}

// Ambil daftar prodi untuk dropdown
$prodi_list = [];
try {
    $prodi_list = $pdo->query("SELECT id, nama FROM program_studi WHERE status='Aktif' ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Ambil data untuk autocomplete (lokasi & perusahaan existing)
$autocomplete_data = [
    'lokasi' => [],
    'perusahaan' => [],
    'pekerjaan' => []
];
try {
    $autocomplete_data['lokasi'] = $pdo->query("SELECT DISTINCT lokasi FROM alumni WHERE lokasi IS NOT NULL AND lokasi != '' ORDER BY lokasi LIMIT 30")->fetchAll(PDO::FETCH_COLUMN);
    $autocomplete_data['perusahaan'] = $pdo->query("SELECT DISTINCT perusahaan FROM alumni WHERE perusahaan IS NOT NULL AND perusahaan != '' ORDER BY perusahaan LIMIT 30")->fetchAll(PDO::FETCH_COLUMN);
    $autocomplete_data['pekerjaan'] = $pdo->query("SELECT DISTINCT pekerjaan FROM alumni WHERE pekerjaan IS NOT NULL AND pekerjaan != '' ORDER BY pekerjaan LIMIT 30")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $nama = trim($_POST['nama'] ?? '');
    $program_studi_id = (int)($_POST['program_studi_id'] ?? 0);
    $tahun_lulus = (int)($_POST['tahun_lulus'] ?? date('Y'));
    $pekerjaan = trim($_POST['pekerjaan'] ?? '');
    $perusahaan = trim($_POST['perusahaan'] ?? '');
    $lokasi = trim($_POST['lokasi'] ?? '');
    $testimonial = trim($_POST['testimonial'] ?? '');
    $prestasi = trim($_POST['prestasi'] ?? '');
    $linkedin = trim($_POST['linkedin'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'Aktif';
    $foto = $edit['foto'] ?? null;

    // Validasi dasar
    if ($nama === '') {
        flash_message('error', '❌ Nama alumni wajib diisi.');
        header('Location: alumni-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }

    if ($tahun_lulus < 1950 || $tahun_lulus > (int)date('Y') + 5) {
        flash_message('error', '❌ Tahun lulus tidak valid (1950 - ' . (date('Y') + 5) . ').');
        header('Location: alumni-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }

    // Normalisasi URL LinkedIn
    if ($linkedin && !preg_match('~^https?://~', $linkedin)) {
        $linkedin = 'https://' . $linkedin;
    }

    // Upload foto
    if (!empty($_FILES['foto']['name'])) {
        if (function_exists('upload_image')) {
            $up = upload_image($_FILES['foto'], 'alumni', 3 * 1024 * 1024);
            if (!$up['ok']) {
                flash_message('error', $up['error']);
                header('Location: alumni-form.php' . ($id ? "?id=$id" : ''));
                exit;
            }
            if ($up['name']) {
                if ($foto && function_exists('delete_upload')) delete_upload($foto, 'alumni');
                $foto = $up['name'];
            }
        } else {
            // Fallback manual upload
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['foto']['tmp_name']);
            if (!in_array($mime, $allowed)) {
                flash_message('error', '❌ Hanya file gambar (JPG, PNG, WEBP) yang diizinkan.');
                header('Location: alumni-form.php' . ($id ? "?id=$id" : ''));
                exit;
            }
            if ($_FILES['foto']['size'] > 3 * 1024 * 1024) {
                flash_message('error', '❌ Ukuran foto maksimal 3MB.');
                header('Location: alumni-form.php' . ($id ? "?id=$id" : ''));
                exit;
            }
            $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
            $new_name = 'alumni_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $dir = APP_DIR . '/uploads/alumni';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $dir . '/' . $new_name)) {
                if ($foto && function_exists('delete_upload')) delete_upload($foto, 'alumni');
                $foto = $new_name;
            }
        }
    }

    try {
        if ($edit && $id > 0) {
            $pdo->prepare("UPDATE alumni SET nama=?, program_studi_id=?, tahun_lulus=?, pekerjaan=?, perusahaan=?, lokasi=?, testimonial=?, prestasi=?, linkedin=?, foto=?, status=? WHERE id=?")
                ->execute([$nama, $program_studi_id ?: null, $tahun_lulus, $pekerjaan, $perusahaan, $lokasi, $testimonial, $prestasi, $linkedin, $foto, $status, $id]);
            flash_message('success', '✅ Data alumni berhasil diperbarui.');
        } else {
            $pdo->prepare("INSERT INTO alumni (nama, program_studi_id, tahun_lulus, pekerjaan, perusahaan, lokasi, testimonial, prestasi, linkedin, foto, status) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$nama, $program_studi_id ?: null, $tahun_lulus, $pekerjaan, $perusahaan, $lokasi, $testimonial, $prestasi, $linkedin, $foto, $status]);
            flash_message('success', '✅ Alumni baru berhasil ditambahkan.');
        }
        header('Location: alumni.php');
        exit;
    } catch (PDOException $e) {
        flash_message('error', '❌ Gagal menyimpan: ' . $e->getMessage());
        header('Location: alumni-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }
}

$csrf = generate_csrf_token();
$active_menu = 'alumni';
$page_heading = $edit ? 'Edit Alumni' : 'Tambah Alumni';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Alumni', 'alumni.php'], [$page_heading, null]];

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
    background: linear-gradient(90deg, #ef4444 0%, #f59e0b 50%, #8b5cf6 100%);
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
    color: #8b5cf6;
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
    background: linear-gradient(90deg, #8b5cf6, #7c3aed);
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
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
    border-color: #8b5cf6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(139,92,246,0.25);
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
.form-section:focus-within { border-color: #8b5cf6; }
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
    background: rgba(139,92,246,0.1);
    color: #8b5cf6;
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
    border-color: #8b5cf6;
    box-shadow: 0 0 0 4px rgba(139,92,246,0.1);
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

/* ===== YEAR CALCULATOR ===== */
.year-calc {
    background: linear-gradient(135deg, #ede9fe, #ddd6fe);
    border: 1px solid #c4b5fd;
    border-radius: var(--radius-md);
    padding: 0.85rem 1rem;
    margin-top: 0.5rem;
    display: none;
    align-items: center;
    gap: 0.65rem;
    font-size: 0.85rem;
    color: #5b21b6;
    font-weight: 600;
}
.year-calc.show { display: flex; }
.year-calc.recent {
    background: linear-gradient(135deg, #dcfce7, #bbf7d0);
    border-color: #86efac;
    color: #166534;
}

/* ===== LINKEDIN VALIDATOR ===== */
.linkedin-validator {
    margin-top: 0.5rem;
    padding: 0.6rem 0.85rem;
    border-radius: var(--radius-md);
    font-size: 0.82rem;
    display: none;
    align-items: center;
    gap: 0.5rem;
}
.linkedin-validator.show { display: flex; }
.linkedin-validator.valid {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #86efac;
}
.linkedin-validator.invalid {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

/* ===== SMART SUGGESTIONS ===== */
.smart-suggestions {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border: 1px solid #fcd34d;
    border-radius: var(--radius-md);
    padding: 0.85rem 1rem;
    margin-top: 0.5rem;
    display: none;
    font-size: 0.85rem;
    color: #92400e;
}
.smart-suggestions.show { display: block; }
.suggestion-header {
    font-weight: 700;
    margin-bottom: 0.35rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.suggestion-item {
    padding: 0.3rem 0;
    cursor: pointer;
    transition: all 0.15s;
    font-size: 0.82rem;
}
.suggestion-item:hover { color: #78350f; transform: translateX(4px); }

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
    border-color: #8b5cf6;
    background: rgba(139,92,246,0.03);
}
.upload-zone.dragover {
    border-style: solid;
    box-shadow: 0 0 0 4px rgba(139,92,246,0.1);
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
.image-preview-info small { font-size: 0.78rem; color: var(--text-muted); }
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

/* Aspect ratio warning */
.aspect-warning {
    margin-top: 0.5rem;
    padding: 0.5rem 0.75rem;
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fcd34d;
    border-radius: 6px;
    font-size: 0.78rem;
    display: none;
}
.aspect-warning.show { display: flex; align-items: center; gap: 0.4rem; }

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
    background: #8b5cf6;
    color: white;
    border-color: #8b5cf6;
    transform: translateY(-2px);
}

/* Alumni Card Preview (seperti LinkedIn card) */
.alumni-card-preview {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-bottom: 1rem;
    position: relative;
}
.alumni-card-preview::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 70px;
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
}
.alumni-card-preview-body {
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
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    font-weight: 800;
    border: 4px solid var(--bg-primary);
    box-shadow: 0 8px 20px rgba(139,92,246,0.3);
    overflow: hidden;
}
.preview-avatar img { width: 100%; height: 100%; object-fit: cover; }
.preview-title {
    text-align: center;
    font-size: 1.15rem;
    font-weight: 800;
    margin-bottom: 0.25rem;
    margin-top: 0.5rem;
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
.preview-prodi {
    text-align: center;
    font-size: 0.78rem;
    color: #8b5cf6;
    font-weight: 600;
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

.preview-testimoni {
    background: var(--bg-secondary);
    padding: 1rem;
    border-radius: var(--radius-md);
    font-size: 0.85rem;
    line-height: 1.6;
    color: var(--text-secondary);
    font-style: italic;
    border-left: 3px solid #8b5cf6;
    text-align: left;
    min-height: 60px;
    margin-top: 1rem;
}
.preview-testimoni::before {
    content: '"';
    font-size: 2rem;
    color: #8b5cf6;
    line-height: 0.5;
    display: block;
    margin-bottom: 0.5rem;
    opacity: 0.5;
}
.preview-prestasi {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    padding: 0.75rem;
    border-radius: var(--radius-md);
    font-size: 0.82rem;
    color: #92400e;
    font-weight: 600;
    text-align: left;
    margin-top: 0.75rem;
    border: 1px solid #fcd34d;
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
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
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
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
    box-shadow: 0 10px 25px rgba(139,92,246,0.35);
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
    border-color: #8b5cf6;
    color: #8b5cf6;
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
    <div class="form-card-extreme" data-aos="fade-right">
        <div class="form-header-extreme">
            <h2><?= $edit ? '✏️ Edit' : '➕ Tambah' ?> Alumni</h2>
            <p><?= $edit ? 'Perbarui data alumni di bawah ini.' : 'Isi data alumni baru untuk ditambahkan ke jejaring.' ?></p>
        </div>

        <!-- Quick Templates -->
        <div class="template-section" data-aos="fade-up">
            <div class="template-label">⚡ Template Cepat (Klik untuk auto-fill)</div>
            <div class="template-buttons">
                <button type="button" class="template-btn" onclick="applyTemplate('guru_pns')">👨‍🏫 Guru PNS</button>
                <button type="button" class="template-btn" onclick="applyTemplate('guru_swasta')">🏫 Guru Swasta</button>
                <button type="button" class="template-btn" onclick="applyTemplate('dosen')">🎓 Dosen</button>
                <button type="button" class="template-btn" onclick="applyTemplate('pengusaha')">💼 Pengusaha</button>
                <button type="button" class="template-btn" onclick="applyTemplate('wirausaha')">🚀 Wirausaha</button>
                <button type="button" class="template-btn" onclick="applyTemplate('lanjut_studi')">📚 Lanjut Studi</button>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="alumniForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

            <div class="form-section">
                <div class="form-section-title">👤 Informasi Pribadi</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">👤</span>
                            Nama Lengkap <span class="required">*</span>
                        </label>
                        <input type="text" id="nama" name="nama" class="form-input" required
                               placeholder="Contoh: Yohanes Berchmans"
                               value="<?= sanitize($edit['nama'] ?? '') ?>"
                               maxlength="150">
                        <div class="form-hint"><span id="namaCounter"><?= strlen($edit['nama'] ?? '') ?></span>/150 karakter</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🎓</span>
                            Program Studi
                        </label>
                        <select id="program_studi_id" name="program_studi_id" class="form-select">
                            <option value="">-- Pilih Prodi --</option>
                            <?php foreach ($prodi_list as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($edit['program_studi_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= sanitize($p['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📅</span>
                            Tahun Lulus <span class="required">*</span>
                        </label>
                        <input type="number" id="tahun_lulus" name="tahun_lulus" class="form-input" required
                               min="1950" max="<?= date('Y') + 5 ?>"
                               value="<?= $edit['tahun_lulus'] ?? date('Y') ?>">
                        <div class="year-calc" id="yearCalc">
                            <span style="font-size: 1.25rem;">🎓</span>
                            <span id="yearCalcText">-</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📊</span>
                            Status
                        </label>
                        <select id="status" name="status" class="form-select">
                            <option value="Aktif" <?= ($edit['status'] ?? 'Aktif') === 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
                            <option value="Non-Aktif" <?= ($edit['status'] ?? '') === 'Non-Aktif' ? 'selected' : '' ?>>📴 Non-Aktif</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title">💼 Karir & Lokasi</div>
                <div class="form-row">
                    <div class="form-group autocomplete-wrapper">
                        <label class="form-label">
                            <span class="label-icon">💼</span>
                            Pekerjaan
                        </label>
                        <input type="text" id="pekerjaan" name="pekerjaan" class="form-input"
                               placeholder="Contoh: Guru Matematika"
                               value="<?= sanitize($edit['pekerjaan'] ?? '') ?>"
                               autocomplete="off">
                        <div class="autocomplete-list" id="pekerjaanAutocomplete">
                            <?php foreach ($autocomplete_data['pekerjaan'] as $p): ?>
                            <div class="autocomplete-item" data-value="<?= sanitize($p) ?>"><?= sanitize($p) ?></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="smart-suggestions" id="pekerjaanSuggestion"></div>
                    </div>
                    <div class="form-group autocomplete-wrapper">
                        <label class="form-label">
                            <span class="label-icon">🏢</span>
                            Perusahaan/Instansi
                        </label>
                        <input type="text" id="perusahaan" name="perusahaan" class="form-input"
                               placeholder="Contoh: SMA Negeri 1 Maumere"
                               value="<?= sanitize($edit['perusahaan'] ?? '') ?>"
                               autocomplete="off">
                        <div class="autocomplete-list" id="perusahaanAutocomplete">
                            <?php foreach ($autocomplete_data['perusahaan'] as $p): ?>
                            <div class="autocomplete-item" data-value="<?= sanitize($p) ?>"><?= sanitize($p) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group autocomplete-wrapper">
                        <label class="form-label">
                            <span class="label-icon">📍</span>
                            Lokasi
                        </label>
                        <input type="text" id="lokasi" name="lokasi" class="form-input"
                               placeholder="Contoh: Maumere, NTT"
                               value="<?= sanitize($edit['lokasi'] ?? '') ?>"
                               autocomplete="off">
                        <div class="autocomplete-list" id="lokasiAutocomplete">
                            <?php foreach ($autocomplete_data['lokasi'] as $p): ?>
                            <div class="autocomplete-item" data-value="<?= sanitize($p) ?>"><?= sanitize($p) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🔗</span>
                            LinkedIn URL
                        </label>
                        <input type="url" id="linkedin" name="linkedin" class="form-input"
                               placeholder="https://linkedin.com/in/..."
                               value="<?= sanitize($edit['linkedin'] ?? '') ?>">
                        <div class="linkedin-validator" id="linkedinValidator"></div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title">🏆 Prestasi</div>
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">⭐</span>
                        Prestasi/Capaian
                    </label>
                    <textarea id="prestasi" name="prestasi" class="form-textarea" rows="3"
                              placeholder="Contoh: Juara 1 Olimpiade Matematika Tingkat Provinsi 2023"
                              maxlength="1000"><?= sanitize($edit['prestasi'] ?? '') ?></textarea>
                    <div class="form-hint" style="display: flex; justify-content: space-between;">
                        <span>Prestasi menonjol selama atau setelah kuliah</span>
                        <span><span id="prestasiCounter"><?= strlen($edit['prestasi'] ?? '') ?></span>/1000 karakter</span>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title">💬 Testimoni</div>
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">📝</span>
                        Testimoni Alumni
                    </label>
                    <textarea id="testimonial" name="testimonial" class="form-textarea" rows="4"
                              placeholder="Tuliskan pengalaman atau kesan selama kuliah di FKIP UNIMOF..."
                              maxlength="2000"><?= sanitize($edit['testimonial'] ?? '') ?></textarea>
                    <div class="form-hint" style="display: flex; justify-content: space-between;">
                        <span>Testimoni akan tampil di halaman alumni publik</span>
                        <span>
                            <span id="testimoniWords">0</span> kata •
                            <span id="testimoniCounter"><?= strlen($edit['testimonial'] ?? '') ?></span>/2000 karakter
                        </span>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title">📷 Foto Alumni (Opsional)</div>
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
                        <img src="<?= asset('uploads/alumni/' . basename($edit['foto'])) ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                        <div style="flex: 1; min-width: 0;">
                            <strong>Foto saat ini:</strong>
                            <div style="font-size: 0.78rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-family: monospace;">
                                <?= sanitize($edit['foto']) ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="submit-btn-extreme" id="submitBtn">
                <span class="btn-text"><?= $edit ? '💾 Perbarui Data' : '✨ Simpan Alumni' ?></span>
                <span class="spinner"></span>
            </button>

            <!-- Secondary Actions -->
            <div class="secondary-actions">
                <?php if ($edit && $id > 0): ?>
                <a href="?duplicate=<?= $id ?>" class="secondary-btn" onclick="return confirm('Duplikasi data ini?')">
                    📋 Duplikasi
                </a>
                <?php endif; ?>
                <a href="alumni.php" class="secondary-btn">← Kembali</a>
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
                <p style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem;">Tampilan kartu alumni</p>
            </div>
            <div class="preview-actions">
                <button class="preview-action-btn" onclick="togglePreviewMode()" title="Toggle mode">🔄</button>
                <button class="preview-action-btn" onclick="sharePreview()" title="Share">🔗</button>
                <?php if ($edit && $id > 0): ?>
                <a href="alumni.php" class="preview-action-btn" target="_blank" title="Lihat di list">📋</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- LinkedIn-style Card -->
        <div class="alumni-card-preview">
            <div class="alumni-card-preview-body">
                <div class="preview-avatar" id="previewAvatar">
                    <span id="previewInitials">🎓</span>
                </div>
                <h2 class="preview-title" id="previewTitle">Nama Alumni</h2>
                <div class="preview-role" id="previewRole">Posisi @ Perusahaan</div>
                <div class="preview-prodi" id="previewProdi">Program Studi</div>

                <div class="preview-meta">
                    <div class="preview-meta-item">
                        <span class="meta-icon">🎓</span>
                        <strong id="previewTahun">Tahun -</strong>
                    </div>
                    <div class="preview-meta-item">
                        <span class="meta-icon">📍</span>
                        <strong id="previewLokasi">-</strong>
                    </div>
                </div>

                <div class="preview-status-badge aktif" id="previewStatus">✅ Aktif</div>
            </div>
        </div>

        <!-- Prestasi -->
        <div class="preview-prestasi" id="previewPrestasi" style="display: none;">
            <span>🏆</span>
            <span id="previewPrestasiText"></span>
        </div>

        <!-- Testimoni -->
        <div class="preview-testimoni" id="previewTestimoni">
            <span style="color: var(--text-muted);">Testimoni akan muncul di sini...</span>
        </div>

        <!-- Share QR -->
        <div class="share-preview">
            <div class="share-preview-label">🔗 QR Share</div>
            <div class="share-preview-qr">
                <img id="qrCode" src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=FKIP-UNIMOF-Alumni" alt="QR">
            </div>
        </div>
    </div>
</div>

<div class="autosave-indicator" id="autosaveIndicator">
    <span id="autosaveIcon">💾</span>
    <span id="autosaveText">Menyimpan...</span>
</div>

<script>
// ===== DATA untuk autocomplete =====
const autocompleteData = <?= json_encode($autocomplete_data) ?>;

// ===== TEMPLATES =====
const templates = {
    guru_pns: { pekerjaan: 'Guru PNS', perusahaan: 'Dinas Pendidikan', status: 'Aktif' },
    guru_swasta: { pekerjaan: 'Guru', perusahaan: 'Sekolah Swasta', status: 'Aktif' },
    dosen: { pekerjaan: 'Dosen', perusahaan: 'Universitas', status: 'Aktif' },
    pengusaha: { pekerjaan: 'Pengusaha', perusahaan: 'PT/CV', status: 'Aktif' },
    wirausaha: { pekerjaan: 'Wirausaha', perusahaan: 'Usaha Mandiri', status: 'Aktif' },
    lanjut_studi: { pekerjaan: 'Mahasiswa S2', perusahaan: 'Universitas', status: 'Aktif' }
};

// Smart suggestions berdasarkan pekerjaan
const pekerjaanSuggestions = {
    'guru': ['📚 Sertifikasi Guru', '🎯 PPG (Pendidikan Profesi Guru)', '📖 Guru Honorer → CPNS'],
    'dosen': ['🎓 S3 (Doktoral)', '📚 Jabatan Fungsional', '🔬 Penelitian'],
    'pengusaha': ['🚀 Inkubator Bisnis', '🏆 UKM Award', '💼 Kemitraan'],
    'wirausaha': ['📈 Scale-up Bisnis', '🏆 Penghargaan UMKM', '🌐 Ekspor']
};

function applyTemplate(key) {
    const t = templates[key];
    if (!t) return;

    if (t.pekerjaan) document.getElementById('pekerjaan').value = t.pekerjaan;
    if (t.perusahaan) document.getElementById('perusahaan').value = t.perusahaan;
    if (t.status) document.getElementById('status').value = t.status;

    updatePreview();
    updateProgress();
    triggerAutosave();
    checkSmartSuggestions();

    showToast('Template Diterapkan', `Template "${key.replace('_', ' ')}" berhasil diisi`, 'success');
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
        if (inputId === 'pekerjaan') checkSmartSuggestions();
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

setupAutocomplete('pekerjaan', 'pekerjaanAutocomplete');
setupAutocomplete('perusahaan', 'perusahaanAutocomplete');
setupAutocomplete('lokasi', 'lokasiAutocomplete');

// ===== SMART SUGGESTIONS =====
function checkSmartSuggestions() {
    const pekerjaan = document.getElementById('pekerjaan').value.toLowerCase();
    const sugBox = document.getElementById('pekerjaanSuggestion');

    for (const [keyword, suggestions] of Object.entries(pekerjaanSuggestions)) {
        if (pekerjaan.includes(keyword)) {
            sugBox.innerHTML = `
                <div class="suggestion-header">💡 Saran untuk ${keyword}:</div>
                ${suggestions.map(s => `<div class="suggestion-item" onclick="document.getElementById('prestasi').value += '\\n- ${s}'; updatePreview(); triggerAutosave();">+ ${s}</div>`).join('')}
            `;
            sugBox.classList.add('show');
            return;
        }
    }
    sugBox.classList.remove('show');
}

// ===== YEAR CALCULATOR =====
function calculateYear() {
    const tahun = parseInt(document.getElementById('tahun_lulus').value);
    const calc = document.getElementById('yearCalc');
    const text = document.getElementById('yearCalcText');

    if (!tahun || tahun < 1950) {
        calc.classList.remove('show');
        return;
    }

    const currentYear = new Date().getFullYear();
    const yearsAgo = currentYear - tahun;

    calc.classList.add('show', 'recent');
    calc.classList.remove('recent');

    if (yearsAgo < 0) {
        text.textContent = `🔮 Akan lulus ${Math.abs(yearsAgo)} tahun lagi`;
    } else if (yearsAgo === 0) {
        text.textContent = `🎉 Lulus tahun ini! (Fresh graduate)`;
        calc.classList.add('recent');
    } else if (yearsAgo <= 2) {
        text.textContent = `🎓 Baru lulus ${yearsAgo} tahun lalu (Fresh graduate)`;
        calc.classList.add('recent');
    } else if (yearsAgo <= 5) {
        text.textContent = `🎓 Lulus ${yearsAgo} tahun lalu`;
    } else if (yearsAgo <= 10) {
        text.textContent = `👨‍💼 Alumni ${yearsAgo} tahun - Early career`;
    } else if (yearsAgo <= 20) {
        text.textContent = `💼 Alumni ${yearsAgo} tahun - Mid career`;
    } else {
        text.textContent = `🏆 Alumni senior ${yearsAgo} tahun`;
    }

    updatePreview();
}

document.getElementById('tahun_lulus').addEventListener('input', calculateYear);
document.getElementById('tahun_lulus').addEventListener('change', calculateYear);

// ===== LINKEDIN VALIDATOR =====
document.getElementById('linkedin').addEventListener('input', function() {
    const url = this.value.trim();
    const validator = document.getElementById('linkedinValidator');

    if (!url) {
        validator.classList.remove('show');
        return;
    }

    let fullUrl = url;
    if (!/^https?:\/\//.test(url)) fullUrl = 'https://' + url;

    const linkedinPattern = /^https?:\/\/(www\.)?linkedin\.com\/in\/[\w-]+\/?/i;

    if (linkedinPattern.test(fullUrl)) {
        validator.className = 'linkedin-validator show valid';
        validator.innerHTML = '✅ URL LinkedIn valid';
    } else if (url.includes('linkedin')) {
        validator.className = 'linkedin-validator show invalid';
        validator.innerHTML = '⚠️ Format: linkedin.com/in/username';
    } else {
        validator.className = 'linkedin-validator show invalid';
        validator.innerHTML = '❌ Bukan URL LinkedIn';
    }

    updatePreview();
    triggerAutosave();
});

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

        // Check aspect ratio
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
    const nama = document.getElementById('nama').value || 'Nama Alumni';
    const tahun = document.getElementById('tahun_lulus').value || '-';
    const pekerjaan = document.getElementById('pekerjaan').value;
    const perusahaan = document.getElementById('perusahaan').value;
    const lokasi = document.getElementById('lokasi').value || '-';
    const prestasi = document.getElementById('prestasi').value;
    const testimonial = document.getElementById('testimonial').value;
    const status = document.getElementById('status').value || 'Aktif';

    const prodiSelect = document.getElementById('program_studi_id');
    const prodiText = prodiSelect.options[prodiSelect.selectedIndex]?.text || 'Program Studi';

    // Title & Role
    document.getElementById('previewTitle').textContent = nama;

    // Role (pekerjaan @ perusahaan)
    let role = '-';
    if (pekerjaan && perusahaan) role = `${pekerjaan} @ ${perusahaan}`;
    else if (pekerjaan) role = pekerjaan;
    else if (perusahaan) role = `@ ${perusahaan}`;
    document.getElementById('previewRole').textContent = role;

    document.getElementById('previewProdi').textContent = prodiText === '-- Pilih Prodi --' ? 'Program Studi' : prodiText;
    document.getElementById('previewTahun').textContent = tahun !== '-' ? `Angkatan ${tahun}` : 'Tahun -';
    document.getElementById('previewLokasi').textContent = lokasi;

    // Initials (jika belum ada foto)
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

    // Prestasi
    const prestasiBox = document.getElementById('previewPrestasi');
    if (prestasi) {
        document.getElementById('previewPrestasiText').textContent = prestasi;
        prestasiBox.style.display = 'flex';
    } else {
        prestasiBox.style.display = 'none';
    }

    // Testimoni
    const testimoniEl = document.getElementById('previewTestimoni');
    if (testimonial) {
        testimoniEl.textContent = testimonial;
        testimoniEl.style.color = 'var(--text-secondary)';
        testimoniEl.style.fontStyle = 'italic';
    } else {
        testimoniEl.innerHTML = '<span style="color: var(--text-muted);">Testimoni akan muncul di sini...</span>';
    }

    // Update QR Code
    const qrData = `ALUMNI:${nama}|${tahun}|${prodiText}|${pekerjaan}`;
    document.getElementById('qrCode').src = `https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=${encodeURIComponent(qrData)}`;
}

function updateInitials() {
    const nama = document.getElementById('nama').value || '';
    const avatar = document.getElementById('previewAvatar');
    if (avatar.querySelector('img')) return; // Ada foto

    const initials = nama.split(' ')
        .filter(n => n.trim())
        .slice(0, 2)
        .map(n => n[0])
        .join('')
        .toUpperCase();

    document.getElementById('previewInitials').textContent = initials || '🎓';
}

// ===== CHARACTER & WORD COUNTERS =====
document.getElementById('nama').addEventListener('input', function() {
    document.getElementById('namaCounter').textContent = this.value.length;
});

document.getElementById('prestasi').addEventListener('input', function() {
    document.getElementById('prestasiCounter').textContent = this.value.length;
    updatePreview();
    triggerAutosave();
});

document.getElementById('testimonial').addEventListener('input', function() {
    document.getElementById('testimoniCounter').textContent = this.value.length;
    const words = this.value.trim().split(/\s+/).filter(w => w).length;
    document.getElementById('testimoniWords').textContent = words;
    updatePreview();
    triggerAutosave();
});

// ===== PROGRESS INDICATOR =====
function updateProgress() {
    const fields = [
        { el: 'nama', weight: 20 },
        { el: 'program_studi_id', weight: 15 },
        { el: 'tahun_lulus', weight: 15 },
        { el: 'pekerjaan', weight: 15 },
        { el: 'perusahaan', weight: 10 },
        { el: 'lokasi', weight: 10 },
        { el: 'testimonial', weight: 10 },
        { el: 'prestasi', weight: 5 }
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
const storageKey = 'fkip_alumni_draft_<?= $id ?: "new" ?>';
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
            program_studi_id: document.getElementById('program_studi_id').value,
            tahun_lulus: document.getElementById('tahun_lulus').value,
            pekerjaan: document.getElementById('pekerjaan').value,
            perusahaan: document.getElementById('perusahaan').value,
            lokasi: document.getElementById('lokasi').value,
            linkedin: document.getElementById('linkedin').value,
            prestasi: document.getElementById('prestasi').value,
            testimonial: document.getElementById('testimonial').value,
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
                document.getElementById('program_studi_id').value = data.program_studi_id || '';
                document.getElementById('tahun_lulus').value = data.tahun_lulus || new Date().getFullYear();
                document.getElementById('pekerjaan').value = data.pekerjaan || '';
                document.getElementById('perusahaan').value = data.perusahaan || '';
                document.getElementById('lokasi').value = data.lokasi || '';
                document.getElementById('linkedin').value = data.linkedin || '';
                document.getElementById('prestasi').value = data.prestasi || '';
                document.getElementById('testimonial').value = data.testimonial || '';
                document.getElementById('status').value = data.status || 'Aktif';

                document.getElementById('namaCounter').textContent = (data.nama || '').length;
                document.getElementById('prestasiCounter').textContent = (data.prestasi || '').length;
                document.getElementById('testimoniCounter').textContent = (data.testimonial || '').length;
                const words = (data.testimonial || '').trim().split(/\s+/).filter(w => w).length;
                document.getElementById('testimoniWords').textContent = words;

                updatePreview();
                calculateYear();
                updateProgress();

                // Trigger LinkedIn validator
                if (data.linkedin) {
                    document.getElementById('linkedin').dispatchEvent(new Event('input'));
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
['nama', 'program_studi_id', 'tahun_lulus', 'pekerjaan', 'perusahaan', 'lokasi', 'linkedin', 'prestasi', 'testimonial', 'status'].forEach(id => {
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
document.getElementById('alumniForm').addEventListener('submit', function(e) {
    const nama = document.getElementById('nama').value.trim();
    const tahun = document.getElementById('tahun_lulus').value;

    if (!nama) {
        e.preventDefault();
        document.getElementById('nama').classList.add('error');
        showToast('Validasi Error', 'Nama alumni wajib diisi!', 'error');
        return;
    }

    if (!tahun || tahun < 1950 || tahun > new Date().getFullYear() + 5) {
        e.preventDefault();
        document.getElementById('tahun_lulus').classList.add('error');
        showToast('Validasi Error', 'Tahun lulus tidak valid!', 'error');
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
    const nama = document.getElementById('nama').value || 'Alumni';
    const pekerjaan = document.getElementById('pekerjaan').value || '-';
    const perusahaan = document.getElementById('perusahaan').value || '-';
    const tahun = document.getElementById('tahun_lulus').value || '-';

    const text = `🎓 ${nama}\n💼 ${pekerjaan} @ ${perusahaan}\n🎓 Alumni FKIP UNIMOF ${tahun}`;

    if (navigator.share) {
        navigator.share({ title: nama, text: text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        showToast('Disalin', 'Profil alumni disalin ke clipboard', 'success');
    }
}

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', function(e) {
    // Ctrl+S: Save
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('alumniForm').dispatchEvent(new Event('submit'));
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
            window.location.href = 'alumni.php';
        }
    }
});

// ===== INITIAL =====
updatePreview();
calculateYear();
updateProgress();
<?php if (!empty($edit['linkedin'])): ?>
document.getElementById('linkedin').dispatchEvent(new Event('input'));
<?php endif; ?>

console.log('%c🎓 Form Alumni FKIP UNIMOF - Super Extreme', 'color: #8b5cf6; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: Ctrl+S (Simpan), Ctrl+K (Template), ESC (Batal)', 'color: #64748b;');
console.log('%cFitur: Autocomplete, Smart Suggestions, Year Calculator, LinkedIn Validator, Templates, QR Code', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>