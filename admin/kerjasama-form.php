<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$duplicate_from = (int)($_GET['duplicate'] ?? 0);
$edit = null;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM kerjasama WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$edit) {
        flash_message('error', '❌ Mitra tidak ditemukan.');
        header('Location: kerjasama.php');
        exit;
    }
} elseif ($duplicate_from > 0) {
    $stmt = $pdo->prepare("SELECT * FROM kerjasama WHERE id = ?");
    $stmt->execute([$duplicate_from]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($source) {
        $edit = $source;
        $edit['id'] = null;
        $edit['nama_institusi'] = $source['nama_institusi'] . ' (Copy)';
        $edit['logo'] = null;
        flash_message('info', '📋 Menduplikasi mitra: ' . htmlspecialchars($source['nama_institusi']));
    }
}

// Cek kolom optional di DB
$has_moa = false;
$has_pic = false;
$has_website = false;
$has_kontak = false;
try { $pdo->query("SELECT moa_number FROM kerjasama LIMIT 1"); $has_moa = true; } catch (Exception $e) {}
try { $pdo->query("SELECT pic FROM kerjasama LIMIT 1"); $has_pic = true; } catch (Exception $e) {}
try { $pdo->query("SELECT website FROM kerjasama LIMIT 1"); $has_website = true; } catch (Exception $e) {}
try { $pdo->query("SELECT kontak FROM kerjasama LIMIT 1"); $has_kontak = true; } catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $nama = trim($_POST['nama_institusi'] ?? '');
    $negara = trim($_POST['negara'] ?? 'Indonesia');
    $jenis = $_POST['jenis'] ?? 'Lainnya';
    $bentuk = trim($_POST['bentuk_kerjasama'] ?? '');
    $tgl_mulai = $_POST['tanggal_mulai'] ?: null;
    $tgl_selesai = $_POST['tanggal_selesai'] ?: null;
    $status = $_POST['status'] ?? 'Aktif';
    $website = trim($_POST['website'] ?? '');
    $kontak = trim($_POST['kontak'] ?? '');
    $pic = trim($_POST['pic'] ?? '');
    $moa_number = trim($_POST['moa_number'] ?? '');
    $logo = $edit['logo'] ?? null;

    // Validasi
    if ($nama === '') {
        flash_message('error', '❌ Nama institusi wajib diisi.');
        header('Location: kerjasama-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }
    if ($negara === '') $negara = 'Indonesia';

    // Validasi jenis
    $valid_jenis = ['Universitas', 'Industri', 'Pemerintah', 'Lainnya'];
    if (!in_array($jenis, $valid_jenis)) $jenis = 'Lainnya';

    // Validasi tanggal
    if ($tgl_mulai && $tgl_selesai && strtotime($tgl_selesai) < strtotime($tgl_mulai)) {
        flash_message('error', '❌ Tanggal selesai tidak boleh sebelum tanggal mulai.');
        header('Location: kerjasama-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }

    // Upload logo
    if (!empty($_FILES['logo']['name'])) {
        if (function_exists('upload_image')) {
            $up = upload_image($_FILES['logo'], 'kerjasama', 3 * 1024 * 1024);
            if (!$up['ok']) {
                flash_message('error', $up['error']);
                header('Location: kerjasama-form.php' . ($id ? "?id=$id" : ''));
                exit;
            }
            if ($up['name']) {
                if ($logo && function_exists('delete_upload')) delete_upload($logo, 'kerjasama');
                $logo = $up['name'];
            }
        } else {
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['logo']['tmp_name']);
            if (!in_array($mime, $allowed)) {
                flash_message('error', '❌ Hanya file gambar (JPG, PNG, WEBP) yang diizinkan.');
                header('Location: kerjasama-form.php' . ($id ? "?id=$id" : ''));
                exit;
            }
            if ($_FILES['logo']['size'] > 3 * 1024 * 1024) {
                flash_message('error', '❌ Ukuran logo maksimal 3MB.');
                header('Location: kerjasama-form.php' . ($id ? "?id=$id" : ''));
                exit;
            }
            $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
            $new_name = 'mitra_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $dir = APP_DIR . '/uploads/kerjasama';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $dir . '/' . $new_name)) {
                if ($logo && function_exists('delete_upload')) delete_upload($logo, 'kerjasama');
                $logo = $new_name;
            }
        }
    }

    try {
        if ($edit && $id > 0) {
            if ($has_moa && $has_pic && $has_website && $has_kontak) {
                $pdo->prepare("UPDATE kerjasama SET nama_institusi=?, negara=?, jenis=?, bentuk_kerjasama=?, tanggal_mulai=?, tanggal_selesai=?, logo=?, status=?, website=?, kontak=?, pic=?, moa_number=? WHERE id=?")
                    ->execute([$nama, $negara, $jenis, $bentuk, $tgl_mulai, $tgl_selesai, $logo, $status, $website, $kontak, $pic, $moa_number, $id]);
            } else {
                $pdo->prepare("UPDATE kerjasama SET nama_institusi=?, negara=?, jenis=?, bentuk_kerjasama=?, tanggal_mulai=?, tanggal_selesai=?, logo=?, status=? WHERE id=?")
                    ->execute([$nama, $negara, $jenis, $bentuk, $tgl_mulai, $tgl_selesai, $logo, $status, $id]);
            }
            flash_message('success', '✅ Data mitra berhasil diperbarui.');
        } else {
            if ($has_moa && $has_pic && $has_website && $has_kontak) {
                $pdo->prepare("INSERT INTO kerjasama (nama_institusi, negara, jenis, bentuk_kerjasama, tanggal_mulai, tanggal_selesai, logo, status, website, kontak, pic, moa_number) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$nama, $negara, $jenis, $bentuk, $tgl_mulai, $tgl_selesai, $logo, $status, $website, $kontak, $pic, $moa_number]);
            } else {
                $pdo->prepare("INSERT INTO kerjasama (nama_institusi, negara, jenis, bentuk_kerjasama, tanggal_mulai, tanggal_selesai, logo, status) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$nama, $negara, $jenis, $bentuk, $tgl_mulai, $tgl_selesai, $logo, $status]);
            }
            flash_message('success', '✅ Mitra baru berhasil ditambahkan.');
        }
    } catch (PDOException $e) {
        flash_message('error', '❌ Gagal menyimpan: ' . $e->getMessage());
        header('Location: kerjasama-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }
    header('Location: kerjasama.php');
    exit;
}

$csrf = generate_csrf_token();
$active_menu = 'kerjasama';
$page_heading = $edit ? 'Edit Mitra' : 'Tambah Mitra';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Kerjasama', 'kerjasama.php'], [$page_heading, null]];

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
    background: linear-gradient(90deg, #3b82f6, #8b5cf6, #ec4899);
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
    background: linear-gradient(135deg, #3b82f6, #1e40af);
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

/* ===== COUNTRY SELECTOR ===== */
.country-input-wrap {
    position: relative;
}
.country-flag-preview {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 1.25rem;
    pointer-events: none;
}
.country-input-wrap input {
    padding-left: 3rem;
}

/* ===== DATE CALCULATOR ===== */
.date-calculator {
    margin-top: 1rem;
    padding: 1rem;
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
    border: 1px solid #93c5fd;
    border-radius: var(--radius-md);
    display: none;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.88rem;
    color: #1e40af;
    font-weight: 600;
}
[data-theme="dark"] .date-calculator {
    background: linear-gradient(135deg, #1e3a8a22, #1e40af33);
    border-color: #3b82f6;
    color: #93c5fd;
}
.date-calculator.show { display: flex; }
.date-calc-icon { font-size: 1.5rem; }
.date-calc-text { flex: 1; }
.date-calc-text strong { display: block; margin-bottom: 0.15rem; }
.date-calc-text small { font-weight: 500; opacity: 0.8; }

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
.upload-zone-icon {
    font-size: 3rem;
    margin-bottom: 0.75rem;
    animation: float 3s ease-in-out infinite;
}
@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}
.upload-zone-text { font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem; }
.upload-zone-sub { font-size: 0.85rem; color: var(--text-muted); }
.upload-zone-info {
    display: flex;
    gap: 0.75rem;
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
.upload-zone input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
}

.logo-preview {
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
.logo-preview.show { display: flex; }
.logo-preview-img {
    width: 100px;
    height: 100px;
    border-radius: 12px;
    background: var(--bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 1px solid var(--border);
    flex-shrink: 0;
}
.logo-preview-img img { width: 100%; height: 100%; object-fit: contain; padding: 0.5rem; background: white; }
.logo-preview-info { flex: 1; min-width: 0; }
.logo-preview-info h4 {
    font-size: 0.9rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.logo-preview-info small { font-size: 0.78rem; color: var(--text-muted); display: block; }
.logo-preview-dim {
    font-size: 0.7rem;
    color: var(--text-muted);
    margin-top: 0.2rem;
    font-family: monospace;
}
.logo-preview-remove {
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
.logo-preview-remove:hover {
    background: #dc2626;
    color: white;
    transform: rotate(90deg);
}

.current-logo-box {
    margin-top: 1rem;
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border: 1px solid var(--border);
}

/* ===== SMART KERJASAMA BUILDER ===== */
.kerjasama-builder {
    margin-top: 0.75rem;
    padding: 0.85rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
}
.kerjasama-builder-header {
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
.kerjasama-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}
.kerjasama-chip {
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
.kerjasama-chip:hover {
    background: #dbeafe;
    border-color: #93c5fd;
    color: #1e40af;
    transform: translateY(-1px);
}

/* ===== SUBMIT BUTTON ===== */
.submit-btn-extreme {
    width: 100%;
    padding: 1.1rem;
    background: linear-gradient(135deg, #3b82f6, #1e40af);
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

/* Mitra Card Preview */
.mitra-card-preview {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-bottom: 1rem;
    position: relative;
}
.mitra-card-preview::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: var(--card-accent, #3b82f6);
    z-index: 2;
}

.preview-logo-box {
    width: 100%;
    height: 160px;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 5rem;
    overflow: hidden;
    position: relative;
}
.preview-logo-box img { 
    max-width: 70%; 
    max-height: 70%; 
    object-fit: contain; 
    padding: 1rem;
    background: white;
    border-radius: 12px;
}
.preview-badge {
    position: absolute;
    top: 1rem;
    left: 1rem;
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(8px);
    padding: 0.4rem 1rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #1e40af;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 0.35rem;
    z-index: 2;
}
.preview-status-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    padding: 0.35rem 0.85rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
    z-index: 2;
}
.preview-status-badge.aktif { background: #dcfce7; color: #166534; }
.preview-status-badge.non-aktif { background: #fee2e2; color: #991b1b; }

.preview-body {
    padding: 1.25rem;
}
.preview-title {
    font-family: 'Georgia', serif;
    font-size: 1.25rem;
    font-weight: 800;
    margin-bottom: 1rem;
    line-height: 1.3;
    letter-spacing: -0.01em;
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
    width: 26px;
    height: 26px;
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

/* Partnership duration */
.preview-duration {
    margin-top: 1rem;
    padding: 1rem;
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
    border: 1px solid #93c5fd;
    border-radius: var(--radius-md);
}
[data-theme="dark"] .preview-duration {
    background: linear-gradient(135deg, #1e3a8a22, #1e40af33);
    border-color: #3b82f6;
}
.preview-duration-label {
    font-size: 0.72rem;
    color: #1e40af;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.preview-duration-value {
    font-family: 'Georgia', serif;
    font-size: 1.5rem;
    font-weight: 800;
    color: #1e40af;
    line-height: 1;
}
.preview-duration-detail {
    font-size: 0.78rem;
    color: #1e40af;
    margin-top: 0.25rem;
    opacity: 0.8;
}

/* Partnership stats */
.preview-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
    margin-top: 1rem;
    padding: 1rem;
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
    border: 1px solid #93c5fd;
    border-radius: var(--radius-md);
}
[data-theme="dark"] .preview-stats {
    background: linear-gradient(135deg, #1e3a8a22, #1e40af33);
    border-color: #3b82f6;
}
.preview-stat-item {
    text-align: center;
    padding: 0.5rem;
    background: white;
    border-radius: 8px;
    border: 1px solid #93c5fd;
}
[data-theme="dark"] .preview-stat-item {
    background: rgba(30,64,175,0.3);
    border-color: #3b82f6;
}
.preview-stat-value {
    font-size: 1.1rem;
    font-weight: 800;
    color: #1e40af;
    line-height: 1;
    margin-bottom: 0.2rem;
    font-family: 'Georgia', serif;
}
.preview-stat-label {
    font-size: 0.65rem;
    color: #1e40af;
    text-transform: uppercase;
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
    .preview-stats { grid-template-columns: 1fr; }
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
        <span class="progress-label">Kelengkapan Mitra</span>
    </div>
</div>

<div class="form-layout-extreme">
    <!-- LEFT: FORM -->
    <div class="form-card-extreme" data-aos="fade-right">
        <div class="form-header-extreme">
            <h2><?= $edit ? '✏️ Edit' : '➕ Tambah' ?> Mitra Kerjasama</h2>
            <p><?= $edit ? 'Perbarui detail mitra di bawah ini.' : 'Isi detail mitra untuk ditambahkan ke network kerjasama.' ?></p>
        </div>

        <!-- Quick Templates -->
        <div class="template-section" data-aos="fade-up">
            <div class="template-label">⚡ Template Cepat (Klik untuk auto-fill)</div>
            <div class="template-buttons">
                <button type="button" class="template-btn" onclick="applyTemplate('universitas_intl')">🎓 Universitas Intl</button>
                <button type="button" class="template-btn" onclick="applyTemplate('universitas_lokal')">🎓 Universitas Lokal</button>
                <button type="button" class="template-btn" onclick="applyTemplate('industri_tech')">🏭 Industri Tech</button>
                <button type="button" class="template-btn" onclick="applyTemplate('industri_edu')">🏭 Industri Edukasi</button>
                <button type="button" class="template-btn" onclick="applyTemplate('pemerintah_dinas')">🏛️ Dinas Pendidikan</button>
                <button type="button" class="template-btn" onclick="applyTemplate('pemerintah_kemendikbud')">🏛️ Kemendikbud</button>
                <button type="button" class="template-btn" onclick="applyTemplate('yayasan')"> Yayasan</button>
                <button type="button" class="template-btn" onclick="applyTemplate('sekolah')">🏫 Sekolah Mitra</button>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="kerjasamaForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

            <!-- Section 1: Informasi Institusi -->
            <div class="form-section">
                <div class="form-section-title">🏢 Informasi Institusi</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🏢</span>
                            Nama Institusi <span class="required">*</span>
                        </label>
                        <input type="text" id="nama_institusi" name="nama_institusi" class="form-input" required
                               placeholder="Contoh: Universiti Malaya"
                               value="<?= sanitize($edit['nama_institusi'] ?? '') ?>"
                               maxlength="200">
                        <div class="form-hint" style="display: flex; justify-content: space-between;">
                            <span>Nama lengkap institusi mitra</span>
                            <span><span id="namaCounter"><?= strlen($edit['nama_institusi'] ?? '') ?></span>/200</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🌍</span>
                            Negara <span class="required">*</span>
                        </label>
                        <div class="country-input-wrap">
                            <span class="country-flag-preview" id="countryFlag">🇮🇩</span>
                            <input type="text" id="negara" name="negara" class="form-input" required
                                   placeholder="Contoh: Malaysia"
                                   value="<?= sanitize($edit['negara'] ?? 'Indonesia') ?>">
                        </div>
                        <div class="form-hint">Negara asal institusi</div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📂</span>
                            Jenis Mitra <span class="required">*</span>
                        </label>
                        <select id="jenis" name="jenis" class="form-select" required>
                            <?php
                            $jenis_icons = ['Universitas' => '🎓', 'Industri' => '🏭', 'Pemerintah' => '🏛️', 'Lainnya' => '📌'];
                            foreach(['Universitas', 'Industri', 'Pemerintah', 'Lainnya'] as $j): ?>
                            <option value="<?= $j ?>" <?= ($edit['jenis'] ?? 'Lainnya') === $j ? 'selected' : '' ?>>
                                <?= $jenis_icons[$j] ?> <?= $j ?>
                            </option>
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
            </div>

            <!-- Section 2: Logo Institusi -->
            <div class="form-section">
                <div class="form-section-title">🏛️ Logo Institusi</div>
                <div class="upload-zone" id="uploadZone">
                    <input type="file" id="logo" name="logo" accept="image/*">
                    <div class="upload-zone-icon">📤</div>
                    <div class="upload-zone-text">Drag & drop logo di sini</div>
                    <div class="upload-zone-sub">atau klik untuk memilih file</div>
                    <div class="upload-zone-info">
                        <span>🖼️ JPG/PNG/WEBP</span>
                        <span>📦 Max 3MB</span>
                        <span>📐 Kotak ideal</span>
                    </div>
                </div>
                <div class="logo-preview" id="logoPreview">
                    <div class="logo-preview-img" id="logoPreviewImg"><span style="font-size: 2rem;">🏛️</span></div>
                    <div class="logo-preview-info">
                        <h4 id="logoName">-</h4>
                        <small id="logoSize">-</small>
                        <div class="logo-preview-dim" id="logoDim"></div>
                    </div>
                    <button type="button" class="logo-preview-remove" onclick="removeLogo()" title="Hapus logo">✕</button>
                </div>
                <?php if (!empty($edit['logo'])): ?>
                    <div class="current-logo-box">
                        <img src="<?= asset('uploads/kerjasama/' . basename($edit['logo'])) ?>" style="width: 60px; height: 60px; object-fit: contain; border-radius: 8px; background: white; padding: 0.25rem;">
                        <div style="flex: 1; min-width: 0;">
                            <strong>Logo saat ini:</strong>
                            <div style="font-size: 0.78rem; color: var(--text-muted); font-family: monospace; word-break: break-all; margin-top: 0.15rem;">
                                <?= sanitize($edit['logo']) ?>
                            </div>
                            <small style="color: var(--text-muted);">Upload logo baru untuk mengganti</small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Section 3: Periode Kerjasama -->
            <div class="form-section">
                <div class="form-section-title">📅 Periode Kerjasama</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📅</span>
                            Tanggal Mulai
                        </label>
                        <input type="date" id="tanggal_mulai" name="tanggal_mulai" class="form-input"
                               value="<?= $edit['tanggal_mulai'] ?? '' ?>">
                        <div class="form-hint">Awal masa kerjasama</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📅</span>
                            Tanggal Selesai
                        </label>
                        <input type="date" id="tanggal_selesai" name="tanggal_selesai" class="form-input"
                               value="<?= $edit['tanggal_selesai'] ?? '' ?>">
                        <div class="form-hint">Akhir masa kerjasama (opsional)</div>
                    </div>
                </div>
                <div class="date-calculator" id="dateCalculator">
                    <span class="date-calc-icon">⏱️</span>
                    <div class="date-calc-text">
                        <strong id="durationText">-</strong>
                        <small id="durationDetail">-</small>
                    </div>
                </div>
            </div>

            <!-- Section 4: Bentuk Kerjasama -->
            <div class="form-section">
                <div class="form-section-title">🤝 Bentuk Kerjasama</div>
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">📝</span>
                        Deskripsi Kerjasama
                    </label>
                    <textarea id="bentuk_kerjasama" name="bentuk_kerjasama" class="form-textarea" rows="5"
                              placeholder="Jelaskan bentuk kerjasama secara detail..."
                              maxlength="2000"><?= sanitize($edit['bentuk_kerjasama'] ?? '') ?></textarea>
                    <div class="form-hint" style="display: flex; justify-content: space-between;">
                        <span>Deskripsi akan muncul di halaman publik</span>
                        <span>
                            <span id="bentukWords">0</span> kata •
                            <span id="bentukCounter"><?= strlen($edit['bentuk_kerjasama'] ?? '') ?></span>/2000 karakter
                        </span>
                    </div>
                </div>

                <!-- Smart Kerjasama Builder -->
                <div class="kerjasama-builder">
                    <div class="kerjasama-builder-header">💡 Klik untuk menambahkan frase umum:</div>
                    <div class="kerjasama-chips">
                        <span class="kerjasama-chip" onclick="addKerjasamaPhrase('Kerjasama dalam bentuk MoU (Memorandum of Understanding) yang ditandatangani oleh kedua belah pihak.')">+ MoU Formal</span>
                        <span class="kerjasama-chip" onclick="addKerjasamaPhrase('Program pertukaran mahasiswa (student exchange) selama 1-2 semester.')">+ Student Exchange</span>
                        <span class="kerjasama-chip" onclick="addKerjasamaPhrase('Penelitian bersama (joint research) di bidang pendidikan dan sains.')">+ Joint Research</span>
                        <span class="kerjasama-chip" onclick="addKerjasamaPhrase('Program magang mahasiswa di institusi mitra selama 3-6 bulan.')">+ Magang/Internship</span>
                        <span class="kerjasama-chip" onclick="addKerjasamaPhrase('Pertukaran dosen (faculty exchange) untuk pengajaran dan kolaborasi riset.')">+ Faculty Exchange</span>
                        <span class="kerjasama-chip" onclick="addKerjasamaPhrase('Program dual degree dengan transfer kredit antar institusi.')">+ Dual Degree</span>
                        <span class="kerjasama-chip" onclick="addKerjasamaPhrase('Kunjungan studi (study visit) untuk berbagi best practices.')">+ Study Visit</span>
                        <span class="kerjasama-chip" onclick="addKerjasamaPhrase('Kolaborasi dalam seminar, workshop, dan konferensi internasional.')">+ Event Kolaborasi</span>
                    </div>
                </div>
            </div>

            <!-- Section 5: Informasi Kontak & Dokumen (Optional) -->
            <?php if ($has_website || $has_kontak || $has_pic || $has_moa): ?>
            <div class="form-section">
                <div class="form-section-title">📞 Kontak & Dokumen</div>
                <?php if ($has_website): ?>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🌐</span>
                            Website
                        </label>
                        <input type="url" id="website" name="website" class="form-input"
                               placeholder="https://www.example.edu"
                               value="<?= sanitize($edit['website'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📞</span>
                            Kontak
                        </label>
                        <input type="text" id="kontak" name="kontak" class="form-input"
                               placeholder="Email / Telepon"
                               value="<?= sanitize($edit['kontak'] ?? '') ?>">
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($has_pic || $has_moa): ?>
                <div class="form-row">
                    <?php if ($has_pic): ?>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">👤</span>
                            Person in Charge
                        </label>
                        <input type="text" id="pic" name="pic" class="form-input"
                               placeholder="Nama PIC di institusi mitra"
                               value="<?= sanitize($edit['pic'] ?? '') ?>">
                    </div>
                    <?php endif; ?>
                    <?php if ($has_moa): ?>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📄</span>
                            Nomor MoA/MoU
                        </label>
                        <input type="text" id="moa_number" name="moa_number" class="form-input"
                               placeholder="Contoh: MoU-2026-001"
                               value="<?= sanitize($edit['moa_number'] ?? '') ?>">
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Submit Button -->
            <button type="submit" class="submit-btn-extreme" id="submitBtn">
                <span class="btn-text"><?= $edit ? '💾 Perbarui Data' : '✨ Simpan Mitra' ?></span>
                <span class="spinner"></span>
            </button>

            <!-- Secondary Actions -->
            <div class="secondary-actions">
                <?php if ($edit && $id > 0): ?>
                <a href="?duplicate=<?= $id ?>" class="secondary-btn" onclick="return confirm('Duplikasi data ini?')">
                    📋 Duplikasi
                </a>
                <?php endif; ?>
                <a href="kerjasama.php" class="secondary-btn">← Kembali</a>
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
                <p style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem;">Tampilan kartu mitra</p>
            </div>
            <div class="preview-actions">
                <button class="preview-action-btn" onclick="togglePreviewMode()" title="Toggle mode">🔄</button>
                <button class="preview-action-btn" onclick="sharePreview()" title="Share">🔗</button>
                <?php if ($edit && $id > 0): ?>
                <a href="kerjasama.php" class="preview-action-btn" target="_blank" title="Lihat di list">📋</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Mitra Card Preview -->
        <div class="mitra-card-preview" id="previewCard">
            <div class="preview-logo-box" id="previewLogoBox">
                <span id="previewIcon">🤝</span>
                <span class="preview-badge" id="previewBadge">📌 LAINNYA</span>
                <span class="preview-status-badge aktif" id="previewStatusBadge">✅ Aktif</span>
            </div>
            <div class="preview-body">
                <h2 class="preview-title" id="previewTitle">Nama institusi akan muncul di sini...</h2>

                <div class="preview-meta">
                    <div class="preview-meta-item">
                        <span class="meta-icon" id="previewCountryIcon">🌍</span>
                        <strong id="previewNegara">-</strong>
                    </div>
                    <div class="preview-meta-item" id="previewDateItem" style="display: none;">
                        <span class="meta-icon">📅</span>
                        <strong id="previewDate">-</strong>
                    </div>
                    <div class="preview-meta-item" id="previewWebsiteItem" style="display: none;">
                        <span class="meta-icon">🌐</span>
                        <strong id="previewWebsite">-</strong>
                    </div>
                    <div class="preview-meta-item" id="previewMoaItem" style="display: none;">
                        <span class="meta-icon">📄</span>
                        <strong id="previewMoa">-</strong>
                    </div>
                </div>

                <div class="preview-desc" id="previewDesc">
                    <span class="preview-empty">Deskripsi kerjasama akan muncul di sini...</span>
                </div>

                <!-- Partnership duration -->
                <div class="preview-duration" id="previewDuration" style="display: none;">
                    <div class="preview-duration-label">⏱️ Durasi Kerjasama</div>
                    <div class="preview-duration-value" id="previewDurationValue">-</div>
                    <div class="preview-duration-detail" id="previewDurationDetail">-</div>
                </div>

                <!-- Stats Preview -->
                <?php if ($edit): ?>
                <div class="preview-stats" id="previewStats">
                    <div class="preview-stat-item">
                        <div class="preview-stat-value" id="previewDaysOld">0</div>
                        <div class="preview-stat-label">Hari</div>
                    </div>
                    <div class="preview-stat-item">
                        <div class="preview-stat-value" id="previewScope">-</div>
                        <div class="preview-stat-label">Scope</div>
                    </div>
                    <div class="preview-stat-item">
                        <div class="preview-stat-value" id="previewLevel">-</div>
                        <div class="preview-stat-label">Level</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- QR Preview -->
        <div class="share-preview">
            <div class="share-preview-label">🔗 QR Mitra</div>
            <div class="share-preview-qr">
                <img id="qrCode" src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=FKIP-UNIMOF-Mitra" alt="QR">
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
    universitas_intl: {
        nama_institusi: 'Universiti Malaya',
        negara: 'Malaysia',
        jenis: 'Universitas',
        bentuk_kerjasama: 'Kerjasama dalam bentuk MoU (Memorandum of Understanding) yang ditandatangani oleh kedua belah pihak.\n\nProgram kerjasama:\n• Pertukaran mahasiswa (student exchange) selama 1-2 semester\n• Pertukaran dosen (faculty exchange) untuk pengajaran\n• Penelitian bersama (joint research) di bidang pendidikan\n• Program dual degree dengan transfer kredit\n\nDurasi kerjasama: 5 tahun dengan evaluasi tahunan.',
        status: 'Aktif'
    },
    universitas_lokal: {
        nama_institusi: 'Universitas Gadjah Mada',
        negara: 'Indonesia',
        jenis: 'Universitas',
        bentuk_kerjasama: 'Kerjasama dalam bentuk MoU (Memorandum of Understanding) yang ditandatangani oleh kedua belah pihak.\n\nProgram kerjasama:\n• Program sandwich degree S2/S3\n• Joint supervision untuk tesis dan disertasi\n• Kolaborasi penelitian antar fakultas\n• Workshop dan seminar bersama\n• Benchmarking kurikulum\n\nFokus: Peningkatan kualitas akademik dan riset.',
        status: 'Aktif'
    },
    industri_tech: {
        nama_institusi: 'PT. Telkom Indonesia',
        negara: 'Indonesia',
        jenis: 'Industri',
        bentuk_kerjasama: 'Kerjasama strategis dalam pengembangan SDM dan teknologi.\n\nBentuk kerjasama:\n• Program magang mahasiswa selama 3-6 bulan\n• Digital literacy training untuk dosen dan mahasiswa\n• Sponsorship untuk kegiatan akademik\n• Kurikulum industri (industry-based curriculum)\n• Sertifikasi profesional\n\nFokus: Bridging gap antara akademisi dan industri.',
        status: 'Aktif'
    },
    industri_edu: {
        nama_institusi: 'Ruangguru',
        negara: 'Indonesia',
        jenis: 'Industri',
        bentuk_kerjasama: 'Kerjasama dalam pengembangan platform edukasi dan pelatihan.\n\nBentuk kerjasama:\n• Akses premium ke platform pembelajaran\n• Pelatihan teknologi pendidikan untuk dosen\n• Magang di divisi content dan technology\n• Joint webinar dan workshop\n• Beasiswa untuk mahasiswa berprestasi\n\nFokus: Transformasi digital pendidikan.',
        status: 'Aktif'
    },
    pemerintah_dinas: {
        nama_institusi: 'Dinas Pendidikan Kabupaten Sikka',
        negara: 'Indonesia',
        jenis: 'Pemerintah',
        bentuk_kerjasama: 'Kerjasama dalam bidang pendidikan dan pelatihan guru.\n\nBentuk kerjasama:\n• Program magang mahasiswa di sekolah-sekolah\n• Pelatihan guru (teacher training)\n• Pengabdian masyarakat\n• Penelitian kolaboratif\n• Program Kampus Mengajar\n\nFokus: Peningkatan kualitas pendidikan dasar dan menengah.',
        status: 'Aktif'
    },
    pemerintah_kemendikbud: {
        nama_institusi: 'Kemendikbudristek RI',
        negara: 'Indonesia',
        jenis: 'Pemerintah',
        bentuk_kerjasama: 'Kerjasama dalam program MBKM (Merdeka Belajar Kampus Merdeka).\n\nProgram:\n• Kampus Mengajar\n• Magang Merdeka\n• Studi Independen\n• Pertukaran Mahasiswa Merdeka\n• Penelitian dan Pengabdian\n\nFokus: Implementasi kebijakan pendidikan nasional.',
        status: 'Aktif'
    },
    yayasan: {
        nama_institusi: 'Yayasan Pendidikan Muhammadiyah',
        negara: 'Indonesia',
        jenis: 'Lainnya',
        bentuk_kerjasama: 'Kerjasama dalam pengembangan pendidikan Islam dan kemuhammadiyahan.\n\nBentuk kerjasama:\n• Beasiswa untuk mahasiswa kurang mampu\n• Program dakwah kampus\n• Pelatihan kader Muhammadiyah\n• Pertukaran pelajar antar PTM\n• Kolaborasi kegiatan keagamaan\n\nFokus: Penguatan nilai-nilai keislaman.',
        status: 'Aktif'
    },
    sekolah: {
        nama_institusi: 'SMA Negeri 1 Maumere',
        negara: 'Indonesia',
        jenis: 'Lainnya',
        bentuk_kerjasama: 'Kerjasama dalam program PPL (Praktik Pengalaman Lapangan) dan penelitian.\n\nBentuk kerjasama:\n• Tempat magang/PPL mahasiswa\n• Penelitian kolaboratif guru-mahasiswa\n• Program tutoring oleh mahasiswa\n• Pengabdian masyarakat\n• Observasi kelas\n\nFokus: Link and match antara kampus dan sekolah.',
        status: 'Aktif'
    }
};

function applyTemplate(key) {
    const t = templates[key];
    if (!t) return;

    document.getElementById('nama_institusi').value = t.nama_institusi;
    document.getElementById('namaCounter').textContent = t.nama_institusi.length;
    document.getElementById('negara').value = t.negara;
    updateCountryFlag(t.negara);
    document.getElementById('jenis').value = t.jenis;
    document.getElementById('bentuk_kerjasama').value = t.bentuk_kerjasama;
    document.getElementById('status').value = t.status;
    updateBentukCount();

    updatePreview();
    updateProgress();
    triggerAutosave();

    showToast('Template Diterapkan', `Template "${key.replace(/_/g, ' ')}" berhasil diisi`, 'success');
}

// ===== SMART KERJASAMA BUILDER =====
function addKerjasamaPhrase(phrase) {
    const textarea = document.getElementById('bentuk_kerjasama');
    const current = textarea.value.trim();
    const newDesc = current ? current + '\n\n' + phrase : phrase;
    textarea.value = newDesc;
    updateBentukCount();
    updatePreview();
    triggerAutosave();
    updateProgress();
    textarea.focus();
    showToast('Frase Ditambahkan', phrase.substring(0, 40) + '...', 'success');
}

// ===== COUNTRY FLAG DETECTION =====
const countryFlags = {
    'indonesia': '🇮🇩', 'malaysia': '🇲🇾', 'singapura': '🇸🇬', 'thailand': '🇹🇭',
    'filipina': '🇵🇭', 'vietnam': '🇻🇳', 'brunei': '🇧🇳', 'myanmar': '🇲🇲',
    'kamboja': '🇰🇭', 'laos': '🇱🇦', 'timor leste': '🇹🇱',
    'jepang': '🇯🇵', 'korea': '🇰🇷', 'korea selatan': '🇰🇷', 'cina': '🇨🇳', 'china': '🇨🇳',
    'india': '🇮🇳', 'pakistan': '🇵🇰', 'bangladesh': '🇧🇩',
    'australia': '🇦🇺', 'new zealand': '🇳🇿', 'selandia baru': '🇳🇿',
    'amerika': '🇺🇸', 'amerika serikat': '🇺🇸', 'usa': '🇺🇸',
    'kanada': '🇨🇦', 'meksiko': '🇲🇽', 'brazil': '🇧🇷', 'brasil': '🇧🇷',
    'argentina': '🇦🇷', 'chili': '🇨🇱', 'peru': '🇵🇪', 'colombia': '🇨🇴',
    'inggris': '🇬🇧', 'british': '🇬🇧', 'uk': '🇬🇧',
    'prancis': '🇫🇷', 'jerman': '🇩🇪', 'belanda': '🇳🇱', 'italia': '🇮🇹',
    'spain': '🇪🇸', 'spanyol': '🇪🇸', 'portugis': '🇵🇹', 'belgia': '🇧🇪',
    'swiss': '🇨🇭', 'swedia': '🇸🇪', 'norwegia': '🇳🇴', 'denmark': '🇩🇰', 'finlandia': '🇫🇮',
    'rusia': '🇷🇺', 'polandia': '🇵🇱', 'ukraina': '🇺🇦', 'turki': '🇹🇷',
    'mesir': '🇪🇬', 'arab saudi': '🇸🇦', 'saudi': '🇸🇦', 'uae': '🇦🇪', 'qatar': '🇶🇦',
    'iran': '🇮🇷', 'irak': '🇮🇶', 'israel': '🇮🇱', 'yordania': '🇯🇴',
    'afrika selatan': '🇿🇦', 'nigeria': '🇳🇬', 'kenya': '🇰🇪', 'maroko': '🇲🇦',
};

function updateCountryFlag(country) {
    const flag = countryFlags[country.toLowerCase()] || '🌍';
    const flagEl = document.getElementById('countryFlag');
    if (flagEl) flagEl.textContent = flag;
}

document.getElementById('negara')?.addEventListener('input', function() {
    updateCountryFlag(this.value);
    updatePreview();
    triggerAutosave();
});

// ===== DRAG & DROP LOGO =====
const uploadZone = document.getElementById('uploadZone');
const logoInput = document.getElementById('logo');
const logoPreview = document.getElementById('logoPreview');

['dragenter', 'dragover'].forEach(ev => {
    uploadZone.addEventListener(ev, e => { e.preventDefault(); uploadZone.classList.add('dragover'); });
});
['dragleave', 'drop'].forEach(ev => {
    uploadZone.addEventListener(ev, e => { e.preventDefault(); uploadZone.classList.remove('dragover'); });
});
uploadZone.addEventListener('drop', e => {
    if (e.dataTransfer.files.length > 0) handleLogo(e.dataTransfer.files[0]);
});
logoInput.addEventListener('change', e => {
    if (e.target.files.length > 0) handleLogo(e.target.files[0]);
});

function handleLogo(file) {
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
    logoInput.files = dt.files;

    document.getElementById('logoName').textContent = file.name;
    document.getElementById('logoSize').textContent = formatFileSize(file.size);

    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('logoPreviewImg').innerHTML = `<img src="${e.target.result}" alt="">`;

        const img = new Image();
        img.onload = () => {
            const logoDim = document.getElementById('logoDim');
            logoDim.textContent = `📐 ${img.width} × ${img.height} px`;
            
            // Check if square-ish for logo
            const ratio = img.width / img.height;
            if (Math.abs(ratio - 1) > 0.3) {
                showToast('Info', 'Logo sebaiknya berbentuk kotak (1:1) untuk tampilan terbaik', 'info');
            }
        };
        img.src = e.target.result;

        // Update preview card
        document.getElementById('previewLogoBox').innerHTML = `
            <img src="${e.target.result}" alt="">
            <span class="preview-badge" id="previewBadge">📌 LAINNYA</span>
            <span class="preview-status-badge aktif" id="previewStatusBadge">✅ Aktif</span>
        `;
    };
    reader.readAsDataURL(file);

    logoPreview.classList.add('show');
    uploadZone.style.display = 'none';

    updatePreview();
    updateProgress();
    triggerAutosave();
}

function removeLogo() {
    logoInput.value = '';
    logoPreview.classList.remove('show');
    document.getElementById('logoPreviewImg').innerHTML = '<span style="font-size: 2rem;">🏛️</span>';
    uploadZone.style.display = 'block';

    // Reset preview box
    const icon = getIconForJenis(document.getElementById('jenis').value);
    document.getElementById('previewLogoBox').innerHTML = `
        <span id="previewIcon">${icon}</span>
        <span class="preview-badge" id="previewBadge">📌 LAINNYA</span>
        <span class="preview-status-badge aktif" id="previewStatusBadge">✅ Aktif</span>
    `;

    updatePreview();
    updateProgress();
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// ===== LIVE PREVIEW =====
function getIconForJenis(jenis) {
    const icons = {
        'Universitas': '🎓',
        'Industri': '🏭',
        'Pemerintah': '🏛️',
        'Lainnya': '📌'
    };
    return icons[jenis] || '📌';
}

const jenisAccentMap = {
    'Universitas': '#1e40af',
    'Industri': '#92400e',
    'Pemerintah': '#4338ca',
    'Lainnya': '#4b5563'
};

function updatePreview() {
    const nama = document.getElementById('nama_institusi').value || 'Nama institusi akan muncul di sini...';
    const negara = document.getElementById('negara').value || 'Indonesia';
    const jenis = document.getElementById('jenis').value || 'Lainnya';
    const bentuk = document.getElementById('bentuk_kerjasama').value;
    const tglMulai = document.getElementById('tanggal_mulai').value;
    const tglSelesai = document.getElementById('tanggal_selesai').value;
    const status = document.getElementById('status').value || 'Aktif';

    const icon = getIconForJenis(jenis);
    const accent = jenisAccentMap[jenis] || '#4b5563';

    // Set card accent
    document.getElementById('previewCard').style.setProperty('--card-accent', accent);

    document.getElementById('previewTitle').textContent = nama;

    const badge = document.getElementById('previewBadge');
    if (badge) badge.textContent = `${icon} ${jenis.toUpperCase()}`;

    const iconEl = document.getElementById('previewIcon');
    if (iconEl && !document.getElementById('previewLogoBox').querySelector('img')) {
        iconEl.textContent = icon;
    }

    // Country with flag
    const flag = countryFlags[negara.toLowerCase()] || '🌍';
    document.getElementById('previewCountryIcon').textContent = flag;
    document.getElementById('previewNegara').textContent = negara;

    // Date
    const dateItem = document.getElementById('previewDateItem');
    if (tglMulai) {
        dateItem.style.display = 'flex';
        let dateStr = 'Sejak ' + new Date(tglMulai).getFullYear();
        if (tglSelesai) dateStr += ' - ' + new Date(tglSelesai).getFullYear();
        document.getElementById('previewDate').textContent = dateStr;
    } else {
        dateItem.style.display = 'none';
    }

    // Status badge
    const statusBadge = document.getElementById('previewStatusBadge');
    if (statusBadge) {
        if (status === 'Aktif') {
            statusBadge.className = 'preview-status-badge aktif';
            statusBadge.textContent = '✅ Aktif';
        } else {
            statusBadge.className = 'preview-status-badge non-aktif';
            statusBadge.textContent = '❌ Non-Aktif';
        }
    }

    // Website preview
    const website = document.getElementById('website')?.value || '';
    const websiteItem = document.getElementById('previewWebsiteItem');
    if (websiteItem) {
        if (website) {
            websiteItem.style.display = 'flex';
            document.getElementById('previewWebsite').textContent = website.replace(/^https?:\/\//, '');
        } else {
            websiteItem.style.display = 'none';
        }
    }

    // MoA preview
    const moa = document.getElementById('moa_number')?.value || '';
    const moaItem = document.getElementById('previewMoaItem');
    if (moaItem) {
        if (moa) {
            moaItem.style.display = 'flex';
            document.getElementById('previewMoa').textContent = moa;
        } else {
            moaItem.style.display = 'none';
        }
    }

    // Deskripsi
    if (bentuk) {
        document.getElementById('previewDesc').innerHTML = bentuk.replace(/\n/g, '<br>');
        document.getElementById('previewDesc').style.color = 'var(--text-secondary)';
    } else {
        document.getElementById('previewDesc').innerHTML = '<span class="preview-empty">Deskripsi kerjasama akan muncul di sini...</span>';
    }

    // Duration preview
    updateDurationPreview();

    <?php if ($edit): ?>
    // Stats preview
    const daysOld = Math.floor((new Date() - new Date('<?= $edit['created_at'] ?? 'now' ?>')) / (1000 * 60 * 60 * 24));
    document.getElementById('previewDaysOld').textContent = daysOld;
    document.getElementById('previewScope').textContent = negara === 'Indonesia' ? '🇮🇩 Lokal' : '🌍 Intl';
    const level = jenis === 'Universitas' ? '🎓 Univ' : (jenis === 'Industri' ? '🏭 Ind' : (jenis === 'Pemerintah' ? '🏛️ Gov' : '📌 Lain'));
    document.getElementById('previewLevel').textContent = level;
    <?php endif; ?>

    // QR Code
    const qrData = `MITRA:${nama}|${jenis}|${negara}`;
    document.getElementById('qrCode').src = `https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=${encodeURIComponent(qrData)}`;
}

function updateDurationPreview() {
    const tglMulai = document.getElementById('tanggal_mulai').value;
    const tglSelesai = document.getElementById('tanggal_selesai').value;
    const durationBox = document.getElementById('previewDuration');
    
    if (!tglMulai && !tglSelesai) {
        durationBox.style.display = 'none';
        return;
    }

    durationBox.style.display = 'block';
    
    const startDate = tglMulai ? new Date(tglMulai) : null;
    const endDate = tglSelesai ? new Date(tglSelesai) : new Date();
    const daysSinceStart = startDate ? Math.floor((new Date() - startDate) / (1000 * 60 * 60 * 24)) : 0;
    const yearsSinceStart = Math.floor(daysSinceStart / 365);
    const monthsSinceStart = Math.floor((daysSinceStart % 365) / 30);

    let durationText = '';
    let durationDetail = '';

    if (tglMulai && tglSelesai) {
        const totalDays = Math.ceil((new Date(tglSelesai) - new Date(tglMulai)) / (1000 * 60 * 60 * 24));
        const years = Math.floor(totalDays / 365);
        const months = Math.floor((totalDays % 365) / 30);
        durationText = `${years > 0 ? years + ' tahun' : ''} ${months > 0 ? months + ' bulan' : ''}`.trim();
        durationDetail = `${totalDays} hari total • ${daysSinceStart} hari berjalan`;
    } else if (tglMulai) {
        durationText = `${yearsSinceStart > 0 ? yearsSinceStart + 'y' : ''} ${monthsSinceStart}m`;
        durationDetail = `Sudah berjalan ${daysSinceStart} hari`;
    }

    document.getElementById('previewDurationValue').textContent = durationText || '-';
    document.getElementById('previewDurationDetail').textContent = durationDetail || '-';

    // Update date calculator in form
    updateDateCalculator();
}

// ===== DATE CALCULATOR =====
function updateDateCalculator() {
    const tglMulai = document.getElementById('tanggal_mulai').value;
    const tglSelesai = document.getElementById('tanggal_selesai').value;
    const calc = document.getElementById('dateCalculator');
    const textEl = document.getElementById('durationText');
    const detailEl = document.getElementById('durationDetail');

    if (!tglMulai && !tglSelesai) {
        calc.classList.remove('show');
        return;
    }

    calc.classList.add('show');

    if (tglMulai && tglSelesai) {
        const days = Math.ceil((new Date(tglSelesai) - new Date(tglMulai)) / (1000 * 60 * 60 * 24));
        const years = Math.floor(days / 365);
        const months = Math.floor((days % 365) / 30);
        
        textEl.textContent = `Durasi: ${years > 0 ? years + ' tahun ' : ''}${months} bulan`;
        detailEl.textContent = `Total ${days} hari kerjasama`;
    } else if (tglMulai) {
        const days = Math.ceil((new Date() - new Date(tglMulai)) / (1000 * 60 * 60 * 24));
        const years = Math.floor(days / 365);
        const months = Math.floor((days % 365) / 30);
        
        textEl.textContent = `Sudah berjalan: ${years > 0 ? years + ' tahun ' : ''}${months} bulan`;
        detailEl.textContent = `Total ${days} hari sejak ${new Date(tglMulai).toLocaleDateString('id-ID')}`;
    }
}

// ===== CHARACTER & WORD COUNTER =====
function updateBentukCount() {
    const textarea = document.getElementById('bentuk_kerjasama');
    const counter = document.getElementById('bentukCounter');
    const wordsEl = document.getElementById('bentukWords');
    const len = textarea.value.length;
    const words = textarea.value.trim().split(/\s+/).filter(w => w).length;
    counter.textContent = len;
    wordsEl.textContent = words;
}

document.getElementById('nama_institusi').addEventListener('input', function() {
    document.getElementById('namaCounter').textContent = this.value.length;
    updatePreview();
    updateProgress();
    triggerAutosave();
});

document.getElementById('bentuk_kerjasama').addEventListener('input', function() {
    updateBentukCount();
    updatePreview();
    updateProgress();
    triggerAutosave();
});

// ===== PROGRESS INDICATOR =====
function updateProgress() {
    const fields = [
        { el: 'nama_institusi', weight: 25 },
        { el: 'negara', weight: 10 },
        { el: 'jenis', weight: 15 },
        { el: 'status', weight: 10 },
        { el: 'bentuk_kerjasama', weight: 20 },
        { check: () => logoInput.files[0] || <?= $edit && $edit['logo'] ? 'true' : 'false' ?>, weight: 15 },
        { check: () => document.getElementById('tanggal_mulai').value, weight: 5 },
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
const storageKey = 'fkip_kerjasama_draft_<?= $id ?: "new" ?>';
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
            nama_institusi: document.getElementById('nama_institusi').value,
            negara: document.getElementById('negara').value,
            jenis: document.getElementById('jenis').value,
            bentuk_kerjasama: document.getElementById('bentuk_kerjasama').value,
            tanggal_mulai: document.getElementById('tanggal_mulai').value,
            tanggal_selesai: document.getElementById('tanggal_selesai').value,
            status: document.getElementById('status').value,
            <?php if ($has_website): ?>
            website: document.getElementById('website')?.value || '',
            kontak: document.getElementById('kontak')?.value || '',
            <?php endif; ?>
            <?php if ($has_pic): ?>
            pic: document.getElementById('pic')?.value || '',
            <?php endif; ?>
            <?php if ($has_moa): ?>
            moa_number: document.getElementById('moa_number')?.value || '',
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
                document.getElementById('nama_institusi').value = data.nama_institusi || '';
                document.getElementById('namaCounter').textContent = (data.nama_institusi || '').length;
                document.getElementById('negara').value = data.negara || 'Indonesia';
                updateCountryFlag(data.negara || 'Indonesia');
                document.getElementById('jenis').value = data.jenis || 'Lainnya';
                document.getElementById('bentuk_kerjasama').value = data.bentuk_kerjasama || '';
                document.getElementById('status').value = data.status || 'Aktif';
                document.getElementById('tanggal_mulai').value = data.tanggal_mulai || '';
                document.getElementById('tanggal_selesai').value = data.tanggal_selesai || '';
                
                <?php if ($has_website): ?>
                if (document.getElementById('website')) document.getElementById('website').value = data.website || '';
                if (document.getElementById('kontak')) document.getElementById('kontak').value = data.kontak || '';
                <?php endif; ?>
                <?php if ($has_pic): ?>
                if (document.getElementById('pic')) document.getElementById('pic').value = data.pic || '';
                <?php endif; ?>
                <?php if ($has_moa): ?>
                if (document.getElementById('moa_number')) document.getElementById('moa_number').value = data.moa_number || '';
                <?php endif; ?>

                updateBentukCount();
                updatePreview();
                updateProgress();
                showToast('Draft Dimuat', 'Data draft berhasil dimuat', 'success');
            } else {
                localStorage.removeItem(storageKey);
            }
        }
    } catch (e) {}
})();
<?php endif; ?>

// ===== EVENT LISTENERS =====
['nama_institusi', 'negara', 'jenis', 'bentuk_kerjasama', 'status', 'tanggal_mulai', 'tanggal_selesai'
<?php if ($has_website): ?>, 'website', 'kontak'<?php endif; ?>
<?php if ($has_pic): ?>, 'pic'<?php endif; ?>
<?php if ($has_moa): ?>, 'moa_number'<?php endif; ?>
].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input', () => { updatePreview(); triggerAutosave(); updateProgress(); });
    el.addEventListener('change', () => { updatePreview(); triggerAutosave(); updateProgress(); });
});

// ===== FORM SUBMIT (FIXED - NO DISABLE!) =====
document.getElementById('kerjasamaForm').addEventListener('submit', function(e) {
    const nama = document.getElementById('nama_institusi').value.trim();
    const negara = document.getElementById('negara').value.trim();

    if (!nama) {
        e.preventDefault();
        document.getElementById('nama_institusi').classList.add('error');
        showToast('Validasi Error', 'Nama Institusi wajib diisi!', 'error');
        return;
    }

    if (!negara) {
        e.preventDefault();
        document.getElementById('negara').classList.add('error');
        showToast('Validasi Error', 'Negara wajib diisi!', 'error');
        return;
    }

    // Check date validation
    const tglMulai = document.getElementById('tanggal_mulai').value;
    const tglSelesai = document.getElementById('tanggal_selesai').value;
    if (tglMulai && tglSelesai && new Date(tglSelesai) < new Date(tglMulai)) {
        e.preventDefault();
        showToast('Validasi Error', 'Tanggal selesai tidak boleh sebelum tanggal mulai!', 'error');
        return;
    }

    const btn = document.getElementById('submitBtn');
    btn.classList.add('loading');
    // JANGAN disable! Agar user bisa resubmit jika ada error

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
    const nama = document.getElementById('nama_institusi').value || 'Mitra';
    const negara = document.getElementById('negara').value || 'Indonesia';
    const jenis = document.getElementById('jenis').value || 'Lainnya';
    const flag = countryFlags[negara.toLowerCase()] || '🌍';

    let text = `🤝 ${nama}\n📂 ${jenis}\n${flag} ${negara}`;
    
    const tglMulai = document.getElementById('tanggal_mulai').value;
    if (tglMulai) text += `\n📅 Sejak ${new Date(tglMulai).getFullYear()}`;
    
    text += `\n\nMitra Kerjasama FKIP UNIMOF`;

    if (navigator.share) {
        navigator.share({ title: nama, text: text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        showToast('Disalin', 'Info mitra disalin ke clipboard', 'success');
    }
}

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('kerjasamaForm').dispatchEvent(new Event('submit'));
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.querySelector('.template-section')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        showToast('Template', 'Pilih template di atas', 'info');
    }
    if (e.key === 'Escape') {
        if (confirm('Batalkan perubahan dan kembali?')) {
            window.location.href = 'kerjasama.php';
        }
    }
});

// ===== INITIAL =====
updateCountryFlag(document.getElementById('negara').value || 'Indonesia');
updatePreview();
updateBentukCount();
updateProgress();
updateDateCalculator();

console.log('%c🤝 Form Kerjasama FKIP UNIMOF - Super Extreme', 'color: #3b82f6; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: Ctrl+S (Simpan), Ctrl+K (Template), ESC (Batal)', 'color: #64748b;');
console.log('%cFitur: 8 Templates, Smart Kerjasama Builder, Country Flags, Duration Calculator, Live Preview', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>