<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$duplicate_from = (int)($_GET['duplicate'] ?? 0);
$edit = null;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM prestasi WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$edit) {
        flash_message('error', '❌ Prestasi tidak ditemukan.');
        header('Location: prestasi.php');
        exit;
    }
} elseif ($duplicate_from > 0) {
    $stmt = $pdo->prepare("SELECT * FROM prestasi WHERE id = ?");
    $stmt->execute([$duplicate_from]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($source) {
        $edit = $source;
        $edit['id'] = null;
        $edit['judul'] = $source['judul'] . ' (Copy)';
        $edit['foto'] = null;
        flash_message('info', '📋 Menduplikasi prestasi: ' . htmlspecialchars($source['judul']));
    }
}

// Ambil data prodi untuk dropdown
$prodi_list = $pdo->query("SELECT id, nama, singkatan FROM program_studi WHERE status='Aktif' ORDER BY nama")->fetchAll();

// Cek kolom optional di DB
$has_poin = false;
$has_pembimbing = false;
$has_hadiah = false;
try { $pdo->query("SELECT poin FROM prestasi LIMIT 1"); $has_poin = true; } catch (Exception $e) {}
try { $pdo->query("SELECT dosen_pembimbing FROM prestasi LIMIT 1"); $has_pembimbing = true; } catch (Exception $e) {}
try { $pdo->query("SELECT hadiah FROM prestasi LIMIT 1"); $has_hadiah = true; } catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $judul = trim($_POST['judul'] ?? '');
    $mahasiswa = trim($_POST['mahasiswa'] ?? '');
    $lomba = trim($_POST['lomba'] ?? '');
    $juara = $_POST['juara'] ?? 'Juara 1';
    $tingkat = $_POST['tingkat'] ?? 'Nasional';
    $tahun = (int)($_POST['tahun'] ?? date('Y'));
    $program_studi_id = (int)($_POST['program_studi_id'] ?? 0);
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $poin = (int)($_POST['poin'] ?? 0);
    $pembimbing = trim($_POST['dosen_pembimbing'] ?? '');
    $hadiah = trim($_POST['hadiah'] ?? '');
    $foto = $edit['foto'] ?? null;

    // Validasi
    if ($judul === '') {
        flash_message('error', '❌ Judul prestasi wajib diisi.');
        header('Location: prestasi-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }
    if ($mahasiswa === '') {
        flash_message('error', '❌ Nama mahasiswa wajib diisi.');
        header('Location: prestasi-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }
    if ($tahun < 1900 || $tahun > 2100) {
        flash_message('error', '❌ Tahun tidak valid.');
        header('Location: prestasi-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }

    // Validasi juara
    $valid_juara = ['Juara 1', 'Juara 2', 'Juara 3', 'Harapan 1', 'Harapan 2', 'Harapan 3', 'Peserta', 'Best Paper', 'Best Presentation'];
    if (!in_array($juara, $valid_juara)) $juara = 'Peserta';

    // Validasi tingkat
    $valid_tingkat = ['Internasional', 'Nasional', 'Provinsi', 'Kabupaten', 'Sekolah'];
    if (!in_array($tingkat, $valid_tingkat)) $tingkat = 'Nasional';

    // Upload foto
    if (!empty($_FILES['foto']['name'])) {
        if (function_exists('upload_image')) {
            $up = upload_image($_FILES['foto'], 'prestasi', 3 * 1024 * 1024);
            if (!$up['ok']) {
                flash_message('error', $up['error']);
                header('Location: prestasi-form.php' . ($id ? "?id=$id" : ''));
                exit;
            }
            if ($up['name']) {
                if ($foto && function_exists('delete_upload')) delete_upload($foto, 'prestasi');
                $foto = $up['name'];
            }
        } else {
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['foto']['tmp_name']);
            if (!in_array($mime, $allowed)) {
                flash_message('error', '❌ Hanya file gambar (JPG, PNG, WEBP) yang diizinkan.');
                header('Location: prestasi-form.php' . ($id ? "?id=$id" : ''));
                exit;
            }
            if ($_FILES['foto']['size'] > 3 * 1024 * 1024) {
                flash_message('error', '❌ Ukuran foto maksimal 3MB.');
                header('Location: prestasi-form.php' . ($id ? "?id=$id" : ''));
                exit;
            }
            $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
            $new_name = 'prestasi_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $dir = APP_DIR . '/uploads/prestasi';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $dir . '/' . $new_name)) {
                if ($foto && function_exists('delete_upload')) delete_upload($foto, 'prestasi');
                $foto = $new_name;
            }
        }
    }

    try {
        if ($edit && $id > 0) {
            if ($has_poin && $has_pembimbing && $has_hadiah) {
                $pdo->prepare("UPDATE prestasi SET judul=?, mahasiswa=?, lomba=?, juara=?, tingkat=?, tahun=?, program_studi_id=?, deskripsi=?, foto=?, poin=?, dosen_pembimbing=?, hadiah=? WHERE id=?")
                    ->execute([$judul, $mahasiswa, $lomba, $juara, $tingkat, $tahun, $program_studi_id, $deskripsi, $foto, $poin, $pembimbing, $hadiah, $id]);
            } else {
                $pdo->prepare("UPDATE prestasi SET judul=?, mahasiswa=?, lomba=?, juara=?, tingkat=?, tahun=?, program_studi_id=?, deskripsi=?, foto=? WHERE id=?")
                    ->execute([$judul, $mahasiswa, $lomba, $juara, $tingkat, $tahun, $program_studi_id, $deskripsi, $foto, $id]);
            }
            flash_message('success', '✅ Data prestasi berhasil diperbarui.');
        } else {
            if ($has_poin && $has_pembimbing && $has_hadiah) {
                $pdo->prepare("INSERT INTO prestasi (judul, mahasiswa, lomba, juara, tingkat, tahun, program_studi_id, deskripsi, foto, poin, dosen_pembimbing, hadiah) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$judul, $mahasiswa, $lomba, $juara, $tingkat, $tahun, $program_studi_id, $deskripsi, $foto, $poin, $pembimbing, $hadiah]);
            } else {
                $pdo->prepare("INSERT INTO prestasi (judul, mahasiswa, lomba, juara, tingkat, tahun, program_studi_id, deskripsi, foto) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([$judul, $mahasiswa, $lomba, $juara, $tingkat, $tahun, $program_studi_id, $deskripsi, $foto]);
            }
            flash_message('success', '✅ Prestasi baru berhasil ditambahkan.');
        }
    } catch (PDOException $e) {
        flash_message('error', '❌ Gagal menyimpan: ' . $e->getMessage());
        header('Location: prestasi-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }
    header('Location: prestasi.php');
    exit;
}

$csrf = generate_csrf_token();
$active_menu = 'prestasi';
$page_heading = $edit ? 'Edit Prestasi' : 'Tambah Prestasi';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Prestasi', 'prestasi.php'], [$page_heading, null]];

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
    background: linear-gradient(90deg, #ef4444 0%, #f59e0b 50%, #fbbf24 100%);
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
    background: linear-gradient(90deg, #b45309, #d97706, #f59e0b);
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
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    border-color: #f59e0b;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(245,158,11,0.25);
}

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
.form-hint {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.35rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

/* ===== JUARA & TINGKAT SELECTOR ===== */
.trophy-selector {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.5rem;
    margin-top: 0.5rem;
}
.trophy-option {
    padding: 0.65rem 0.5rem;
    background: var(--bg-primary);
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.78rem;
    font-weight: 600;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}
.trophy-option:hover {
    border-color: #f59e0b;
    transform: translateY(-2px);
}
.trophy-option.selected {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border-color: #f59e0b;
    color: #92400e;
}
.trophy-option .trophy-icon {
    font-size: 1.5rem;
    line-height: 1;
}
.trophy-option input[type="radio"] { display: none; }

.tingkat-selector {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
    margin-top: 0.5rem;
}
.tingkat-option {
    padding: 0.65rem 0.5rem;
    background: var(--bg-primary);
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.78rem;
    font-weight: 600;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}
.tingkat-option:hover {
    border-color: #f59e0b;
    transform: translateY(-2px);
}
.tingkat-option.selected {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border-color: #f59e0b;
    color: #92400e;
}
.tingkat-option .tingkat-icon {
    font-size: 1.5rem;
    line-height: 1;
}
.tingkat-option input[type="radio"] { display: none; }

/* ===== POINTS INPUT ===== */
.points-input-wrap {
    position: relative;
}
.points-badge {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 800;
    pointer-events: none;
}
.points-visual {
    margin-top: 0.75rem;
    padding: 0.75rem;
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border: 1px solid #fcd34d;
    border-radius: var(--radius-md);
    display: none;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.85rem;
    color: #92400e;
    font-weight: 600;
}
[data-theme="dark"] .points-visual {
    background: linear-gradient(135deg, #78350f22, #92400e33);
    border-color: #f59e0b;
    color: #fcd34d;
}
.points-visual.show { display: flex; }
.points-visual-icon { font-size: 1.5rem; }

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
    border-color: #f59e0b;
    background: rgba(245,158,11,0.03);
}
.upload-zone.dragover {
    border-style: solid;
    box-shadow: 0 0 0 4px rgba(245,158,11,0.1);
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
.image-preview-dim {
    font-size: 0.7rem;
    color: var(--text-muted);
    margin-top: 0.2rem;
    font-family: monospace;
}
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

.current-image-box {
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
    background: #fef3c7;
    border-color: #fcd34d;
    color: #92400e;
    transform: translateY(-1px);
}

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
    background: #f59e0b;
    color: white;
    border-color: #f59e0b;
    transform: translateY(-2px);
}

/* Achievement Card Preview */
.achievement-card-preview {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-bottom: 1rem;
    position: relative;
}
.achievement-card-preview::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: var(--card-accent, #f59e0b);
    z-index: 2;
}

.preview-trophy-box {
    width: 100%;
    height: 180px;
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 5rem;
    overflow: hidden;
    position: relative;
}
.preview-trophy-box img { 
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.preview-tingkat-badge {
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
    color: #b45309;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 0.35rem;
    z-index: 2;
}
.preview-juara-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    padding: 0.35rem 0.85rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
    z-index: 2;
    background: white;
    border: 1px solid #fcd34d;
}

.preview-body {
    padding: 1.25rem;
}
.preview-title {
    font-family: 'Georgia', serif;
    font-size: 1.25rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    line-height: 1.3;
    letter-spacing: -0.01em;
}
.preview-mahasiswa {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}
.preview-lomba {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
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
    border-left: 3px solid #f59e0b;
    font-style: italic;
}
.preview-desc::before {
    content: '"';
    font-size: 1.5rem;
    color: #f59e0b;
    line-height: 0.5;
    display: block;
    margin-bottom: 0.5rem;
    opacity: 0.5;
}
.preview-empty { color: var(--text-muted); font-style: italic; }

/* Points visual */
.preview-points {
    margin-top: 1rem;
    padding: 1rem;
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border: 1px solid #fcd34d;
    border-radius: var(--radius-md);
    display: none;
}
.preview-points.show { display: block; }
.preview-points-label {
    font-size: 0.72rem;
    color: #92400e;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
    margin-bottom: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.preview-points-value {
    font-family: 'Georgia', serif;
    font-size: 1.5rem;
    font-weight: 800;
    color: #b45309;
    line-height: 1;
}
.preview-points-detail {
    font-size: 0.78rem;
    color: #92400e;
    margin-top: 0.25rem;
    opacity: 0.8;
}

/* Achievement stats */
.preview-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
    margin-top: 1rem;
    padding: 1rem;
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border: 1px solid #fcd34d;
    border-radius: var(--radius-md);
}
[data-theme="dark"] .preview-stats {
    background: linear-gradient(135deg, #78350f22, #92400e33);
    border-color: #f59e0b;
}
.preview-stat-item {
    text-align: center;
    padding: 0.5rem;
    background: white;
    border-radius: 8px;
    border: 1px solid #fcd34d;
}
[data-theme="dark"] .preview-stat-item {
    background: rgba(120,53,15,0.3);
    border-color: #f59e0b;
}
.preview-stat-value {
    font-size: 1.1rem;
    font-weight: 800;
    color: #b45309;
    line-height: 1;
    margin-bottom: 0.2rem;
    font-family: 'Georgia', serif;
}
.preview-stat-label {
    font-size: 0.65rem;
    color: #92400e;
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
    .trophy-selector, .tingkat-selector { grid-template-columns: repeat(2, 1fr); }
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
    <span style="font-size: 1.25rem;">🏆</span>
    <div class="progress-bar-wrap">
        <div class="progress-bar-fill" id="progressBar" style="width: 0%"></div>
    </div>
    <div class="progress-stats">
        <span class="progress-percent" id="progressPercent">0%</span>
        <span class="progress-label">Kelengkapan Prestasi</span>
    </div>
</div>

<div class="form-layout-extreme">
    <!-- LEFT: FORM -->
    <div class="form-card-extreme" data-aos="fade-right">
        <div class="form-header-extreme">
            <h2><?= $edit ? '✏️ Edit' : '➕ Tambah' ?> Prestasi</h2>
            <p><?= $edit ? 'Perbarui detail prestasi di bawah ini.' : 'Isi detail prestasi untuk ditampilkan di Hall of Fame.' ?></p>
        </div>

        <!-- Quick Templates -->
        <div class="template-section" data-aos="fade-up">
            <div class="template-label">⚡ Template Cepat (Klik untuk auto-fill)</div>
            <div class="template-buttons">
                <button type="button" class="template-btn" onclick="applyTemplate('olimpiade')">🧮 Olimpiade Sains</button>
                <button type="button" class="template-btn" onclick="applyTemplate('lkis')">📝 LKTI</button>
                <button type="button" class="template-btn" onclick="applyTemplate('debat')">🎤 Debat Bahasa</button>
                <button type="button" class="template-btn" onclick="applyTemplate('pimnas')">🔬 PIMNAS</button>
                <button type="button" class="template-btn" onclick="applyTemplate('microteaching')">🎬 Micro Teaching</button>
                <button type="button" class="template-btn" onclick="applyTemplate('poster')">🎨 Poster Ilmiah</button>
                <button type="button" class="template-btn" onclick="applyTemplate('olahraga')">⚽ Olahraga</button>
                <button type="button" class="template-btn" onclick="applyTemplate('seni')">🎭 Seni & Budaya</button>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="prestasiForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

            <!-- Section 1: Informasi Utama -->
            <div class="form-section">
                <div class="form-section-title">🏆 Informasi Utama</div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">
                        <span class="label-icon">🏆</span>
                        Judul Prestasi <span class="required">*</span>
                    </label>
                    <input type="text" id="judul" name="judul" class="form-input" required
                           placeholder="Contoh: Juara 1 Olimpiade Matematika Nasional 2026"
                           value="<?= sanitize($edit['judul'] ?? '') ?>"
                           maxlength="200">
                    <div class="form-hint" style="display: flex; justify-content: space-between;">
                        <span>Judul yang jelas dan menarik</span>
                        <span><span id="judulCounter"><?= strlen($edit['judul'] ?? '') ?></span>/200</span>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">👤</span>
                            Nama Mahasiswa <span class="required">*</span>
                        </label>
                        <input type="text" id="mahasiswa" name="mahasiswa" class="form-input" required
                               placeholder="Contoh: Ahmad Fauzi"
                               value="<?= sanitize($edit['mahasiswa'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🎯</span>
                            Nama Lomba
                        </label>
                        <input type="text" id="lomba" name="lomba" class="form-input"
                               placeholder="Contoh: OSN Matematika 2026"
                               value="<?= sanitize($edit['lomba'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Section 2: Juara & Tingkat -->
            <div class="form-section">
                <div class="form-section-title">🥇 Klasifikasi Prestasi</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🏅</span>
                            Perolehan Juara <span class="required">*</span>
                        </label>
                        <div class="trophy-selector" id="juaraSelector">
                            <?php
                            $juara_list = [
                                'Juara 1' => '🥇',
                                'Juara 2' => '🥈',
                                'Juara 3' => '🥉',
                                'Harapan 1' => '🎯',
                                'Best Paper' => '📄',
                                'Best Presentation' => '🎤',
                                'Peserta' => '🎖️',
                                'Finalis' => '⭐'
                            ];
                            foreach ($juara_list as $j => $icon):
                                $selected = ($edit['juara'] ?? 'Juara 1') === $j;
                            ?>
                            <label class="trophy-option <?= $selected ? 'selected' : '' ?>" data-value="<?= $j ?>">
                                <input type="radio" name="juara" value="<?= $j ?>" <?= $selected ? 'checked' : '' ?>>
                                <span class="trophy-icon"><?= $icon ?></span>
                                <span><?= $j ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📊</span>
                            Tingkat <span class="required">*</span>
                        </label>
                        <div class="tingkat-selector" id="tingkatSelector">
                            <?php
                            $tingkat_list = [
                                'Internasional' => '🌍',
                                'Nasional' => '🇮🇩',
                                'Provinsi' => '🏛️',
                                'Kabupaten' => '🏘️',
                                'Sekolah' => '🏫'
                            ];
                            foreach ($tingkat_list as $t => $icon):
                                $selected = ($edit['tingkat'] ?? 'Nasional') === $t;
                            ?>
                            <label class="tingkat-option <?= $selected ? 'selected' : '' ?>" data-value="<?= $t ?>">
                                <input type="radio" name="tingkat" value="<?= $t ?>" <?= $selected ? 'checked' : '' ?>>
                                <span class="tingkat-icon"><?= $icon ?></span>
                                <span><?= $t ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📅</span>
                            Tahun <span class="required">*</span>
                        </label>
                        <input type="number" id="tahun" name="tahun" class="form-input" required
                               min="1900" max="2100"
                               value="<?= $edit['tahun'] ?? date('Y') ?>">
                        <div class="form-hint">Tahun prestasi diraih</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🏫</span>
                            Program Studi
                        </label>
                        <select id="program_studi_id" name="program_studi_id" class="form-select">
                            <option value="">-- Pilih Prodi --</option>
                            <?php foreach ($prodi_list as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($edit['program_studi_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                                <?= sanitize($p['nama']) ?> <?= $p['singkatan'] ? '(' . $p['singkatan'] . ')' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php if ($has_poin): ?>
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">⭐</span>
                        Poin Prestasi
                    </label>
                    <div class="points-input-wrap">
                        <input type="number" id="poin" name="poin" class="form-input"
                               min="0" max="1000"
                               placeholder="Contoh: 100"
                               value="<?= $edit['poin'] ?? 0 ?>">
                        <span class="points-badge" id="pointsBadge">0 pts</span>
                    </div>
                    <div class="points-visual" id="pointsVisual">
                        <span class="points-visual-icon">⭐</span>
                        <div>
                            <div id="pointsLevel">Level: -</div>
                            <div style="font-size: 0.75rem; opacity: 0.8;" id="pointsCategory">-</div>
                        </div>
                    </div>
                    <div class="form-hint">Poin untuk sistem reward mahasiswa (0-1000)</div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Section 3: Foto Prestasi -->
            <div class="form-section">
                <div class="form-section-title">📷 Foto Prestasi</div>
                <div class="upload-zone" id="uploadZone">
                    <input type="file" id="foto" name="foto" accept="image/*">
                    <div class="upload-zone-icon">📤</div>
                    <div class="upload-zone-text">Drag & drop foto di sini</div>
                    <div class="upload-zone-sub">atau klik untuk memilih file</div>
                    <div class="upload-zone-info">
                        <span>🖼️ JPG/PNG/WEBP</span>
                        <span>📦 Max 3MB</span>
                        <span>📐 16:9 ideal</span>
                    </div>
                </div>
                <div class="image-preview" id="imagePreview">
                    <div class="image-preview-img" id="imagePreviewImg"><span style="font-size: 2rem;">🏆</span></div>
                    <div class="image-preview-info">
                        <h4 id="imageName">-</h4>
                        <small id="imageSize">-</small>
                        <div class="image-preview-dim" id="imageDim"></div>
                    </div>
                    <button type="button" class="image-preview-remove" onclick="removeImage()" title="Hapus foto">✕</button>
                </div>
                <div class="aspect-warning" id="aspectWarning">
                    ⚠️ <span id="aspectWarningText">Foto sebaiknya rasio 16:9 untuk tampilan terbaik</span>
                </div>
                <?php if (!empty($edit['foto'])): ?>
                    <div class="current-image-box">
                        <img src="<?= asset('uploads/prestasi/' . basename($edit['foto'])) ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                        <div style="flex: 1; min-width: 0;">
                            <strong>Foto saat ini:</strong>
                            <div style="font-size: 0.78rem; color: var(--text-muted); font-family: monospace; word-break: break-all; margin-top: 0.15rem;">
                                <?= sanitize($edit['foto']) ?>
                            </div>
                            <small style="color: var(--text-muted);">Upload foto baru untuk mengganti</small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Section 4: Deskripsi -->
            <div class="form-section">
                <div class="form-section-title">📝 Deskripsi Prestasi</div>
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">📝</span>
                        Deskripsi Lengkap
                    </label>
                    <textarea id="deskripsi" name="deskripsi" class="form-textarea" rows="5"
                              placeholder="Ceritakan tentang pencapaian ini, proses persiapan, dan kebanggaan..."
                              maxlength="2000"><?= sanitize($edit['deskripsi'] ?? '') ?></textarea>
                    <div class="form-hint" style="display: flex; justify-content: space-between;">
                        <span>Deskripsi akan muncul di Hall of Fame</span>
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
                        <span class="deskripsi-chip" onclick="addDescPhrase('Prestasi ini diraih setelah melalui proses seleksi ketat yang diikuti oleh ratusan peserta dari berbagai universitas.')">+ Proses seleksi</span>
                        <span class="deskripsi-chip" onclick="addDescPhrase('Persiapan intensif selama 3 bulan dengan bimbingan dosen ahli di bidangnya.')">+ Persiapan</span>
                        <span class="deskripsi-chip" onclick="addDescPhrase('Membawa nama baik FKIP UNIMOF di tingkat nasional dan menjadi kebanggaan civitas akademika.')">+ Kebanggaan</span>
                        <span class="deskripsi-chip" onclick="addDescPhrase('Kompetisi ini menguji kemampuan akademik, kreativitas, dan mentalitas mahasiswa.')">+ Tantangan</span>
                        <span class="deskripsi-chip" onclick="addDescPhrase('Merupakan prestasi pertama yang diraih oleh mahasiswa FKIP UNIMOF di bidang ini.')">+ Prestasi pertama</span>
                        <span class="deskripsi-chip" onclick="addDescPhrase('Dosen pembimbing memberikan dukungan penuh dalam persiapan dan pelaksanaan lomba.')">+ Pembimbing</span>
                        <span class="deskripsi-chip" onclick="addDescPhrase('Prestasi ini membuka peluang beasiswa dan karir yang lebih luas bagi mahasiswa.')">+ Peluang</span>
                        <span class="deskripsi-chip" onclick="addDescPhrase('Acara puncak颁奖典礼 diselenggarakan dengan meriah dan dihadiri pejabat tinggi.')">+ Acara puncak</span>
                    </div>
                </div>
            </div>

            <!-- Section 5: Informasi Tambahan (Optional) -->
            <?php if ($has_pembimbing || $has_hadiah): ?>
            <div class="form-section">
                <div class="form-section-title">📌 Informasi Tambahan</div>
                <div class="form-row">
                    <?php if ($has_pembimbing): ?>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">👨‍🏫</span>
                            Dosen Pembimbing
                        </label>
                        <input type="text" id="dosen_pembimbing" name="dosen_pembimbing" class="form-input"
                               placeholder="Nama dosen pembimbing"
                               value="<?= sanitize($edit['dosen_pembimbing'] ?? '') ?>">
                    </div>
                    <?php endif; ?>
                    <?php if ($has_hadiah): ?>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🎁</span>
                            Hadiah / Reward
                        </label>
                        <input type="text" id="hadiah" name="hadiah" class="form-input"
                               placeholder="Contoh: Uang tunai + Sertifikat"
                               value="<?= sanitize($edit['hadiah'] ?? '') ?>">
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Submit Button -->
            <button type="submit" class="submit-btn-extreme" id="submitBtn">
                <span class="btn-text"><?= $edit ? '💾 Perbarui Data' : '✨ Simpan Prestasi' ?></span>
                <span class="spinner"></span>
            </button>

            <!-- Secondary Actions -->
            <div class="secondary-actions">
                <?php if ($edit && $id > 0): ?>
                <a href="?duplicate=<?= $id ?>" class="secondary-btn" onclick="return confirm('Duplikasi data ini?')">
                    📋 Duplikasi
                </a>
                <?php endif; ?>
                <a href="prestasi.php" class="secondary-btn">← Kembali</a>
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
                <p style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem;">Tampilan kartu prestasi</p>
            </div>
            <div class="preview-actions">
                <button class="preview-action-btn" onclick="togglePreviewMode()" title="Toggle mode">🔄</button>
                <button class="preview-action-btn" onclick="sharePreview()" title="Share">🔗</button>
                <?php if ($edit && $id > 0): ?>
                <a href="prestasi.php" class="preview-action-btn" target="_blank" title="Lihat di list">📋</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Achievement Card Preview -->
        <div class="achievement-card-preview" id="previewCard">
            <div class="preview-trophy-box" id="previewTrophyBox">
                <span id="previewTrophyIcon">🏆</span>
                <span class="preview-tingkat-badge" id="previewTingkatBadge">🇮🇩 NASIONAL</span>
                <span class="preview-juara-badge" id="previewJuaraBadge">🥇 Juara 1</span>
            </div>
            <div class="preview-body">
                <h2 class="preview-title" id="previewTitle">Judul prestasi akan muncul di sini...</h2>
                <div class="preview-mahasiswa" id="previewMahasiswa">👤 -</div>
                <div class="preview-lomba" id="previewLomba">🎯 -</div>

                <div class="preview-meta">
                    <div class="preview-meta-item" id="previewProdiItem" style="display: none;">
                        <span class="meta-icon">🏫</span>
                        <strong id="previewProdi">-</strong>
                    </div>
                    <div class="preview-meta-item">
                        <span class="meta-icon">📅</span>
                        <strong id="previewTahun">-</strong>
                    </div>
                    <?php if ($has_pembimbing): ?>
                    <div class="preview-meta-item" id="previewPembimbingItem" style="display: none;">
                        <span class="meta-icon">👨‍🏫</span>
                        <strong id="previewPembimbing">-</strong>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="preview-desc" id="previewDesc">
                    <span class="preview-empty">Deskripsi prestasi akan muncul di sini...</span>
                </div>

                <!-- Points visual -->
                <?php if ($has_poin): ?>
                <div class="preview-points" id="previewPoints">
                    <div class="preview-points-label">⭐ Poin Prestasi</div>
                    <div class="preview-points-value" id="previewPointsValue">0</div>
                    <div class="preview-points-detail" id="previewPointsDetail">-</div>
                </div>
                <?php endif; ?>

                <!-- Stats Preview -->
                <?php if ($edit): ?>
                <div class="preview-stats" id="previewStats">
                    <div class="preview-stat-item">
                        <div class="preview-stat-value" id="previewDaysOld">0</div>
                        <div class="preview-stat-label">Hari</div>
                    </div>
                    <div class="preview-stat-item">
                        <div class="preview-stat-value" id="previewYearsAgo">0</div>
                        <div class="preview-stat-label">Tahun Lalu</div>
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
            <div class="share-preview-label">🔗 QR Prestasi</div>
            <div class="share-preview-qr">
                <img id="qrCode" src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=FKIP-UNIMOF-Prestasi" alt="QR">
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
// ===== DATA =====
const trophyIcons = {
    'Juara 1': '🥇', 'Juara 2': '🥈', 'Juara 3': '🥉',
    'Harapan 1': '🎯', 'Harapan 2': '🎯', 'Harapan 3': '🎯',
    'Best Paper': '📄', 'Best Presentation': '🎤',
    'Peserta': '🎖️', 'Finalis': '⭐'
};

const tingkatIcons = {
    'Internasional': '🌍', 'Nasional': '🇮🇩',
    'Provinsi': '🏛️', 'Kabupaten': '🏘️', 'Sekolah': '🏫'
};

const tingkatAccents = {
    'Internasional': '#b45309',
    'Nasional': '#1e40af',
    'Provinsi': '#166534',
    'Kabupaten': '#7e22ce',
    'Sekolah': '#be185d'
};

// ===== TEMPLATES =====
const templates = {
    olimpiade: {
        judul: 'Juara 1 Olimpiade Matematika Nasional 2026',
        mahasiswa: 'Yohanes Berchmans',
        lomba: 'Olimpiade Matematika Nasional (OMN)',
        juara: 'Juara 1',
        tingkat: 'Nasional',
        deskripsi: 'Prestasi gemilang diraih oleh mahasiswa FKIP UNIMOF dalam ajang Olimpiade Matematika Nasional 2026.\n\nKompetisi ini diikuti oleh lebih dari 500 peserta dari berbagai universitas terkemuka di Indonesia. Proses seleksi ketat meliputi babak penyisihan, semifinal, dan final.\n\nPersiapan intensif selama 3 bulan dengan bimbingan dosen ahli matematika. Mahasiswa menunjukkan kemampuan problem-solving yang luar biasa.\n\nPrestasi ini membawa nama baik FKIP UNIMOF dan menjadi motivasi bagi mahasiswa lainnya.',
        poin: 100
    },
    lkis: {
        judul: 'Juara 1 Lomba Karya Tulis Ilmiah Nasional',
        mahasiswa: 'Maria Klarissa',
        lomba: 'Lomba Karya Tulis Ilmiah Mahasiswa (LKTI)',
        juara: 'Juara 1',
        tingkat: 'Nasional',
        deskripsi: 'Mahasiswa berhasil meraih juara 1 dalam Lomba Karya Tulis Ilmiah tingkat Nasional dengan judul penelitian tentang inovasi pendidikan.\n\nKarya tulis ini mengangkat tema "Pembelajaran Berbasis Kearifan Lokal di Era Digital". Penelitian dilakukan selama 6 bulan dengan metodologi kualitatif.\n\nProses seleksi meliputi review naskah oleh 10 juri pakar, presentasi di depan panel, dan tanya jawab. Karya ini diakui sebagai kontribusi signifikan bagi pengembangan pendidikan di Indonesia.',
        poin: 100
    },
    debat: {
        judul: 'Juara 1 Debat Bahasa Inggris Regional',
        mahasiswa: 'Tim Debat FKIP UNIMOF',
        lomba: 'English Debate Competition',
        juara: 'Juara 1',
        tingkat: 'Provinsi',
        deskripsi: 'Tim debat Bahasa Inggris FKIP UNIMOF meraih juara 1 dalam kompetisi debat regional.\n\nKompetisi ini diikuti oleh 30 tim dari berbagai universitas di NTT. Setiap tim terdiri dari 3 orang mahasiswa yang menunjukkan kemampuan argumentasi, public speaking, dan critical thinking.\n\nTim berlatih intensif selama 2 bulan dengan coach khusus. Prestasi ini membuktikan kualitas mahasiswa FKIP dalam bidang bahasa dan komunikasi.',
        poin: 75
    },
    pimnas: {
        judul: 'Medali Emas Pekan Ilmiah Mahasiswa Nasional (PIMNAS)',
        mahasiswa: 'Tim PKM FKIP UNIMOF',
        lomba: 'PIMNAS 2026',
        juara: 'Juara 1',
        tingkat: 'Nasional',
        deskripsi: 'Tim Program Kreativitas Mahasiswa (PKM) FKIP UNIMOF meraih medali emas dalam PIMNAS 2026.\n\nKategori: PKM Penelitian. Judul: "Pengembangan Media Pembelajaran Interaktif Berbasis Augmented Reality".\n\nTim terdiri dari 5 mahasiswa dan 1 dosen pembimbing. Persiapan dilakukan selama 1 tahun dengan pendanaan dari Kemendikbudristek.\n\nPrestasi ini merupakan pencapaian tertinggi dalam sejarah PKM FKIP UNIMOF dan membuka jalan untuk kolaborasi riset tingkat internasional.',
        poin: 150
    },
    microteaching: {
        judul: 'Juara 1 Kompetisi Micro Teaching Nasional',
        mahasiswa: 'Agnes Doa',
        lomba: 'National Micro Teaching Competition',
        juara: 'Juara 1',
        tingkat: 'Nasional',
        deskripsi: 'Mahasiswa FKIP UNIMOF meraih juara 1 dalam kompetisi micro teaching tingkat nasional.\n\nKompetisi ini menguji kemampuan mengajar praktis di depan siswa dan juri. Mahasiswa menampilkan metode pembelajaran inovatif berbasis teknologi.\n\nPersiapan meliputi: penyusunan RPP, pembuatan media pembelajaran, simulasi mengajar, dan evaluasi. Prestasi ini membuktikan kompetensi pedagogik mahasiswa FKIP UNIMOF.',
        poin: 100
    },
    poster: {
        judul: 'Best Poster dalam Konferensi Pendidikan Nasional',
        mahasiswa: 'Petrus Kleden',
        lomba: 'Konferensi Nasional Pendidikan',
        juara: 'Best Paper',
        tingkat: 'Nasional',
        deskripsi: 'Mahasiswa meraih penghargaan Best Poster dalam konferensi pendidikan nasional.\n\nPoster menampilkan hasil penelitian tentang "Efektivitas Metode Pembelajaran Problem-Based Learning". Desain poster yang informatif dan visual menarik mendapat apresiasi juri.\n\nKonferensi ini dihadiri oleh lebih dari 200 akademisi dan peneliti dari seluruh Indonesia. Prestasi ini memperkaya portofolio akademik mahasiswa.',
        poin: 75
    },
    olahraga: {
        judul: 'Juara 1 Turnamen Futsal Antar Fakultas',
        mahasiswa: 'Tim Futsal FKIP UNIMOF',
        lomba: 'Turnamen Futsal UNIMOF Cup',
        juara: 'Juara 1',
        tingkat: 'Sekolah',
        deskripsi: 'Tim futsal FKIP UNIMOF menjuarai turnamen antar fakultas di UNIMOF.\n\nTurnamen ini diikuti oleh 8 fakultas dengan sistem gugur. Tim FKIP menunjukkan kekompakan, sportivitas, dan teknik bermain yang solid.\n\nPersiapan dilakukan selama 2 bulan dengan latihan rutin 3x seminggu. Prestasi ini menunjukkan keseimbangan antara akademik dan non-akademik mahasiswa FKIP.',
        poin: 50
    },
    seni: {
        judul: 'Juara 1 Festival Tari Tradisional NTT',
        mahasiswa: 'Sanggar Tari FKIP UNIMOF',
        lomba: 'Festival Tari Tradisional NTT',
        juara: 'Juara 1',
        tingkat: 'Provinsi',
        deskripsi: 'Sanggar Tari FKIP UNIMOF meraih juara 1 dalam Festival Tari Tradisional tingkat Provinsi NTT.\n\nTim menampilkan tari kreasi baru yang mengombinasikan unsur tradisional NTT dengan koreografi modern. Penampilan memukau juri dan penonton.\n\nPersiapan melibatkan 15 penari dan 2 pelatih selama 3 bulan. Prestasi ini menjadi bukti kepedulian FKIP UNIMOF terhadap pelestarian budaya lokal.',
        poin: 75
    }
};

function applyTemplate(key) {
    const t = templates[key];
    if (!t) return;

    document.getElementById('judul').value = t.judul;
    document.getElementById('judulCounter').textContent = t.judul.length;
    document.getElementById('mahasiswa').value = t.mahasiswa;
    document.getElementById('lomba').value = t.lomba;
    document.getElementById('deskripsi').value = t.deskripsi;
    document.getElementById('tahun').value = new Date().getFullYear();
    
    // Set juara
    const juaraRadio = document.querySelector(`input[name="juara"][value="${t.juara}"]`);
    if (juaraRadio) {
        juaraRadio.checked = true;
        document.querySelectorAll('#juaraSelector .trophy-option').forEach(el => {
            el.classList.toggle('selected', el.dataset.value === t.juara);
        });
    }
    
    // Set tingkat
    const tingkatRadio = document.querySelector(`input[name="tingkat"][value="${t.tingkat}"]`);
    if (tingkatRadio) {
        tingkatRadio.checked = true;
        document.querySelectorAll('#tingkatSelector .tingkat-option').forEach(el => {
            el.classList.toggle('selected', el.dataset.value === t.tingkat);
        });
    }
    
    <?php if ($has_poin): ?>
    if (t.poin !== undefined) {
        document.getElementById('poin').value = t.poin;
        updatePointsVisual();
    }
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

// ===== TROPHY & TINGKAT SELECTOR =====
document.querySelectorAll('#juaraSelector .trophy-option').forEach(opt => {
    opt.addEventListener('click', () => {
        document.querySelectorAll('#juaraSelector .trophy-option').forEach(o => o.classList.remove('selected'));
        opt.classList.add('selected');
        opt.querySelector('input').checked = true;
        updatePreview();
        triggerAutosave();
        updateProgress();
    });
});

document.querySelectorAll('#tingkatSelector .tingkat-option').forEach(opt => {
    opt.addEventListener('click', () => {
        document.querySelectorAll('#tingkatSelector .tingkat-option').forEach(o => o.classList.remove('selected'));
        opt.classList.add('selected');
        opt.querySelector('input').checked = true;
        updatePreview();
        triggerAutosave();
        updateProgress();
    });
});

// ===== POINTS VISUAL =====
<?php if ($has_poin): ?>
function updatePointsVisual() {
    const poin = parseInt(document.getElementById('poin').value) || 0;
    const badge = document.getElementById('pointsBadge');
    const visual = document.getElementById('pointsVisual');
    const level = document.getElementById('pointsLevel');
    const category = document.getElementById('pointsCategory');

    badge.textContent = poin + ' pts';

    if (poin > 0) {
        visual.classList.add('show');
        
        let levelText = '';
        let categoryText = '';
        
        if (poin >= 100) {
            levelText = '🏆 Level LEGENDARY';
            categoryText = 'Prestasi tertinggi, dampak nasional/internasional';
        } else if (poin >= 75) {
            levelText = '🥇 Level ELITE';
            categoryText = 'Prestasi sangat baik, tingkat nasional';
        } else if (poin >= 50) {
            levelText = '🥈 Level ADVANCED';
            categoryText = 'Prestasi baik, tingkat provinsi/regional';
        } else if (poin >= 25) {
            levelText = '🥉 Level INTERMEDIATE';
            categoryText = 'Prestasi standar, tingkat kabupaten';
        } else {
            levelText = '🎖️ Level BEGINNER';
            categoryText = 'Prestasi tingkat sekolah/lokal';
        }
        
        level.textContent = levelText;
        category.textContent = categoryText;
    } else {
        visual.classList.remove('show');
    }
}
<?php endif; ?>

// ===== DRAG & DROP IMAGE =====
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

        const img = new Image();
        img.onload = () => {
            const ratio = img.width / img.height;
            const aspectWarning = document.getElementById('aspectWarning');
            const warningText = document.getElementById('aspectWarningText');
            const imageDim = document.getElementById('imageDim');
            imageDim.textContent = `📐 ${img.width} × ${img.height} px`;

            if (Math.abs(ratio - 16/9) > 0.3 && Math.abs(ratio - 4/3) > 0.3) {
                aspectWarning.classList.add('show');
                warningText.textContent = 'Rasio gambar tidak standar. Disarankan 16:9 atau 4:3 untuk tampilan terbaik';
            } else {
                aspectWarning.classList.remove('show');
            }
        };
        img.src = e.target.result;

        // Update preview trophy box
        document.getElementById('previewTrophyBox').innerHTML = `
            <img src="${e.target.result}" alt="">
            <span class="preview-tingkat-badge" id="previewTingkatBadge">🇮🇩 NASIONAL</span>
            <span class="preview-juara-badge" id="previewJuaraBadge">🥇 Juara 1</span>
        `;
    };
    reader.readAsDataURL(file);

    imagePreview.classList.add('show');
    uploadZone.style.display = 'none';

    updatePreview();
    updateProgress();
    triggerAutosave();
}

function removeImage() {
    fotoInput.value = '';
    imagePreview.classList.remove('show');
    document.getElementById('aspectWarning').classList.remove('show');
    uploadZone.style.display = 'block';

    // Reset preview box
    const trophyIcon = trophyIcons[document.querySelector('input[name="juara"]:checked')?.value || 'Juara 1'];
    const tingkat = document.querySelector('input[name="tingkat"]:checked')?.value || 'Nasional';
    const tingkatIcon = tingkatIcons[tingkat];
    const juara = document.querySelector('input[name="juara"]:checked')?.value || 'Juara 1';
    
    document.getElementById('previewTrophyBox').innerHTML = `
        <span id="previewTrophyIcon">${trophyIcon}</span>
        <span class="preview-tingkat-badge" id="previewTingkatBadge">${tingkatIcon} ${tingkat.toUpperCase()}</span>
        <span class="preview-juara-badge" id="previewJuaraBadge">${trophyIcons[juara]} ${juara}</span>
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
function updatePreview() {
    const judul = document.getElementById('judul').value || 'Judul prestasi akan muncul di sini...';
    const mahasiswa = document.getElementById('mahasiswa').value || '-';
    const lomba = document.getElementById('lomba').value || '-';
    const tahun = document.getElementById('tahun').value || '-';
    const tingkat = document.querySelector('input[name="tingkat"]:checked')?.value || 'Nasional';
    const juara = document.querySelector('input[name="juara"]:checked')?.value || 'Juara 1';
    const deskripsi = document.getElementById('deskripsi').value;

    const trophyIcon = trophyIcons[juara] || '🏆';
    const tingkatIcon = tingkatIcons[tingkat] || '🏆';
    const accent = tingkatAccents[tingkat] || '#f59e0b';

    // Set card accent
    document.getElementById('previewCard').style.setProperty('--card-accent', accent);

    document.getElementById('previewTitle').textContent = judul;
    document.getElementById('previewMahasiswa').textContent = '👤 ' + mahasiswa;
    document.getElementById('previewLomba').textContent = '🎯 ' + lomba;
    document.getElementById('previewTahun').textContent = tahun;

    // Badges
    const tingkatBadge = document.getElementById('previewTingkatBadge');
    if (tingkatBadge) tingkatBadge.textContent = `${tingkatIcon} ${tingkat.toUpperCase()}`;

    const juaraBadge = document.getElementById('previewJuaraBadge');
    if (juaraBadge) juaraBadge.textContent = `${trophyIcon} ${juara}`;

    // Trophy icon
    const trophyEl = document.getElementById('previewTrophyIcon');
    if (trophyEl && !document.getElementById('previewTrophyBox').querySelector('img')) {
        trophyEl.textContent = trophyIcon;
    }

    // Prodi
    const prodiSelect = document.getElementById('program_studi_id');
    const prodiText = prodiSelect?.options[prodiSelect.selectedIndex]?.text || '-';
    const prodiItem = document.getElementById('previewProdiItem');
    if (prodiItem) {
        if (prodiText && prodiText !== '-- Pilih Prodi --') {
            prodiItem.style.display = 'flex';
            document.getElementById('previewProdi').textContent = prodiText;
        } else {
            prodiItem.style.display = 'none';
        }
    }

    <?php if ($has_pembimbing): ?>
    // Pembimbing
    const pembimbing = document.getElementById('dosen_pembimbing')?.value || '';
    const pembimbingItem = document.getElementById('previewPembimbingItem');
    if (pembimbingItem) {
        if (pembimbing) {
            pembimbingItem.style.display = 'flex';
            document.getElementById('previewPembimbing').textContent = pembimbing;
        } else {
            pembimbingItem.style.display = 'none';
        }
    }
    <?php endif; ?>

    // Deskripsi
    if (deskripsi) {
        document.getElementById('previewDesc').innerHTML = deskripsi.replace(/\n/g, '<br>');
        document.getElementById('previewDesc').style.color = 'var(--text-secondary)';
    } else {
        document.getElementById('previewDesc').innerHTML = '<span class="preview-empty">Deskripsi prestasi akan muncul di sini...</span>';
    }

    <?php if ($has_poin): ?>
    // Points preview
    const poin = parseInt(document.getElementById('poin')?.value) || 0;
    const pointsBox = document.getElementById('previewPoints');
    if (pointsBox) {
        if (poin > 0) {
            pointsBox.classList.add('show');
            document.getElementById('previewPointsValue').textContent = poin + ' pts';
            
            let detail = '';
            if (poin >= 100) detail = '🏆 Legendary Achievement';
            else if (poin >= 75) detail = '🥇 Elite Level';
            else if (poin >= 50) detail = '🥈 Advanced Level';
            else if (poin >= 25) detail = '🥉 Intermediate';
            else detail = '🎖️ Beginner';
            
            document.getElementById('previewPointsDetail').textContent = detail;
        } else {
            pointsBox.classList.remove('show');
        }
    }
    <?php endif; ?>

    <?php if ($edit): ?>
    // Stats preview
    const daysOld = Math.floor((new Date() - new Date('<?= $edit['created_at'] ?? 'now' ?>')) / (1000 * 60 * 60 * 24));
    const yearsAgo = new Date().getFullYear() - (<?= $edit['tahun'] ?? 'new Date().getFullYear()' ?>);
    document.getElementById('previewDaysOld').textContent = daysOld;
    document.getElementById('previewYearsAgo').textContent = yearsAgo;
    document.getElementById('previewLevel').textContent = tingkatIcon + ' ' + tingkat.substring(0, 3);
    <?php endif; ?>

    // QR Code
    const qrData = `PRESTASI:${judul}|${mahasiswa}|${juara}|${tingkat}|${tahun}`;
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

document.getElementById('judul').addEventListener('input', function() {
    document.getElementById('judulCounter').textContent = this.value.length;
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

// ===== PROGRESS INDICATOR =====
function updateProgress() {
    const fields = [
        { el: 'judul', weight: 25 },
        { el: 'mahasiswa', weight: 20 },
        { el: 'lomba', weight: 10 },
        { check: () => document.querySelector('input[name="juara"]:checked'), weight: 15 },
        { check: () => document.querySelector('input[name="tingkat"]:checked'), weight: 10 },
        { el: 'tahun', weight: 5 },
        { el: 'deskripsi', weight: 10 },
        { check: () => fotoInput.files[0] || <?= $edit && $edit['foto'] ? 'true' : 'false' ?>, weight: 5 }
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
const storageKey = 'fkip_prestasi_draft_<?= $id ?: "new" ?>';
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
            mahasiswa: document.getElementById('mahasiswa').value,
            lomba: document.getElementById('lomba').value,
            juara: document.querySelector('input[name="juara"]:checked')?.value || 'Juara 1',
            tingkat: document.querySelector('input[name="tingkat"]:checked')?.value || 'Nasional',
            tahun: document.getElementById('tahun').value,
            program_studi_id: document.getElementById('program_studi_id')?.value || '',
            deskripsi: document.getElementById('deskripsi').value,
            <?php if ($has_poin): ?>
            poin: document.getElementById('poin')?.value || 0,
            <?php endif; ?>
            <?php if ($has_pembimbing): ?>
            dosen_pembimbing: document.getElementById('dosen_pembimbing')?.value || '',
            <?php endif; ?>
            <?php if ($has_hadiah): ?>
            hadiah: document.getElementById('hadiah')?.value || '',
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
                document.getElementById('judul').value = data.judul || '';
                document.getElementById('judulCounter').textContent = (data.judul || '').length;
                document.getElementById('mahasiswa').value = data.mahasiswa || '';
                document.getElementById('lomba').value = data.lomba || '';
                document.getElementById('tahun').value = data.tahun || new Date().getFullYear();
                document.getElementById('deskripsi').value = data.deskripsi || '';
                if (data.program_studi_id) document.getElementById('program_studi_id').value = data.program_studi_id;
                
                // Set juara
                const juaraRadio = document.querySelector(`input[name="juara"][value="${data.juara || 'Juara 1'}"]`);
                if (juaraRadio) {
                    juaraRadio.checked = true;
                    document.querySelectorAll('#juaraSelector .trophy-option').forEach(el => {
                        el.classList.toggle('selected', el.dataset.value === (data.juara || 'Juara 1'));
                    });
                }
                
                // Set tingkat
                const tingkatRadio = document.querySelector(`input[name="tingkat"][value="${data.tingkat || 'Nasional'}"]`);
                if (tingkatRadio) {
                    tingkatRadio.checked = true;
                    document.querySelectorAll('#tingkatSelector .tingkat-option').forEach(el => {
                        el.classList.toggle('selected', el.dataset.value === (data.tingkat || 'Nasional'));
                    });
                }
                
                <?php if ($has_poin): ?>
                if (data.poin !== undefined) {
                    document.getElementById('poin').value = data.poin;
                    updatePointsVisual();
                }
                <?php endif; ?>
                <?php if ($has_pembimbing): ?>
                if (document.getElementById('dosen_pembimbing')) document.getElementById('dosen_pembimbing').value = data.dosen_pembimbing || '';
                <?php endif; ?>
                <?php if ($has_hadiah): ?>
                if (document.getElementById('hadiah')) document.getElementById('hadiah').value = data.hadiah || '';
                <?php endif; ?>

                updateDeskripsiCount();
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
['judul', 'mahasiswa', 'lomba', 'tahun', 'deskripsi', 'program_studi_id'
<?php if ($has_poin): ?>, 'poin'<?php endif; ?>
<?php if ($has_pembimbing): ?>, 'dosen_pembimbing'<?php endif; ?>
<?php if ($has_hadiah): ?>, 'hadiah'<?php endif; ?>
].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input', () => {
        <?php if ($has_poin): ?>
        if (id === 'poin') updatePointsVisual();
        <?php endif; ?>
        updatePreview();
        triggerAutosave();
        updateProgress();
    });
    el.addEventListener('change', () => { updatePreview(); triggerAutosave(); updateProgress(); });
});

// ===== FORM SUBMIT (FIXED - NO DISABLE!) =====
document.getElementById('prestasiForm').addEventListener('submit', function(e) {
    const judul = document.getElementById('judul').value.trim();
    const mahasiswa = document.getElementById('mahasiswa').value.trim();

    if (!judul) {
        e.preventDefault();
        document.getElementById('judul').classList.add('error');
        showToast('Validasi Error', 'Judul Prestasi wajib diisi!', 'error');
        return;
    }

    if (!mahasiswa) {
        e.preventDefault();
        document.getElementById('mahasiswa').classList.add('error');
        showToast('Validasi Error', 'Nama Mahasiswa wajib diisi!', 'error');
        return;
    }

    if (!document.querySelector('input[name="juara"]:checked')) {
        e.preventDefault();
        showToast('Validasi Error', 'Pilih perolehan juara!', 'error');
        return;
    }

    if (!document.querySelector('input[name="tingkat"]:checked')) {
        e.preventDefault();
        showToast('Validasi Error', 'Pilih tingkat prestasi!', 'error');
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
    const judul = document.getElementById('judul').value || 'Prestasi';
    const mahasiswa = document.getElementById('mahasiswa').value || '-';
    const juara = document.querySelector('input[name="juara"]:checked')?.value || 'Juara 1';
    const tingkat = document.querySelector('input[name="tingkat"]:checked')?.value || 'Nasional';
    const trophyIcon = trophyIcons[juara] || '🏆';
    const tingkatIcon = tingkatIcons[tingkat] || '🏆';

    let text = `🏆 ${judul}\n${trophyIcon} ${juara} - ${tingkatIcon} ${tingkat}\n👤 ${mahasiswa}\n\nPrestasi FKIP UNIMOF`;

    if (navigator.share) {
        navigator.share({ title: judul, text: text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        showToast('Disalin', 'Info prestasi disalin ke clipboard', 'success');
    }
}

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('prestasiForm').dispatchEvent(new Event('submit'));
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.querySelector('.template-section')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        showToast('Template', 'Pilih template di atas', 'info');
    }
    if (e.key === 'Escape') {
        if (confirm('Batalkan perubahan dan kembali?')) {
            window.location.href = 'prestasi.php';
        }
    }
});

// ===== INITIAL =====
updatePreview();
updateDeskripsiCount();
updateProgress();
<?php if ($has_poin): ?>
updatePointsVisual();
<?php endif; ?>

console.log('%c🏆 Form Prestasi FKIP UNIMOF - Super Extreme', 'color: #f59e0b; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: Ctrl+S (Simpan), Ctrl+K (Template), ESC (Batal)', 'color: #64748b;');
console.log('%cFitur: 8 Templates, Trophy/Tingkat Selector, Points System, Smart Builder, Live Preview', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>