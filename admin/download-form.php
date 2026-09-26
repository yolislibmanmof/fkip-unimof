<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$duplicate_from = (int)($_GET['duplicate'] ?? 0);
$edit = null;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM downloads WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch();
    if (!$edit) {
        flash_message('error', '❌ File tidak ditemukan.');
        header('Location: download.php');
        exit;
    }
} elseif ($duplicate_from > 0) {
    $stmt = $pdo->prepare("SELECT * FROM downloads WHERE id = ?");
    $stmt->execute([$duplicate_from]);
    $source = $stmt->fetch();
    if ($source) {
        $edit = $source;
        $edit['id'] = null;
        $edit['judul'] = $source['judul'] . ' (Copy)';
        $edit['file_name'] = null;
        $edit['file_size'] = null;
        $edit['downloads_count'] = 0;
        flash_message('info', '📋 Menduplikasi file: ' . htmlspecialchars($source['judul']));
    }
}

// Ambil kategori yang sudah ada untuk suggestions
$existing_categories = [];
try {
    $existing_categories = $pdo->query("SELECT DISTINCT kategori FROM downloads WHERE kategori IS NOT NULL AND kategori != '' ORDER BY kategori")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Kategori default + existing
$all_categories = array_unique(array_merge(['Akademik', 'PMB', 'Formulir', 'Pedoman', 'Penelitian', 'Pengabdian', 'Kemahasiswaan', 'Lainnya'], $existing_categories));
sort($all_categories);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $judul = trim($_POST['judul'] ?? '');
    $kategori = trim($_POST['kategori'] ?? 'Lainnya');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $file_name = $edit['file_name'] ?? null;
    $file_size = $edit['file_size'] ?? null;

    // Validasi
    if ($judul === '') {
        flash_message('error', '❌ Judul dokumen wajib diisi.');
        header('Location: download-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }

    if ($kategori === '') $kategori = 'Lainnya';

    // Upload file baru
    if (!empty($_FILES['file']['name'])) {
        $allowed = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            'image/jpeg',
            'image/png',
            'image/webp'
        ];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['file']['tmp_name']);

        if (!in_array($mime, $allowed)) {
            flash_message('error', '❌ Format file tidak diizinkan. Hanya PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, ZIP, RAR, 7Z, JPG, PNG, WEBP.');
            header('Location: download-form.php' . ($id ? "?id=$id" : ''));
            exit;
        }
        if ($_FILES['file']['size'] > 25 * 1024 * 1024) {
            flash_message('error', '❌ Ukuran file maksimal 25MB.');
            header('Location: download-form.php' . ($id ? "?id=$id" : ''));
            exit;
        }

        $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $safe_ext = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($ext));
        $file_name = bin2hex(random_bytes(16)) . ($safe_ext ? '.' . $safe_ext : '');
        $file_size = number_format($_FILES['file']['size'] / 1024, 1) . ' KB';
        if ($_FILES['file']['size'] > 1024 * 1024) {
            $file_size = number_format($_FILES['file']['size'] / (1024 * 1024), 2) . ' MB';
        }

        $dir = APP_DIR . '/assets/downloads';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $dir . '/' . $file_name)) {
            flash_message('error', '❌ Gagal mengupload file.');
            header('Location: download-form.php' . ($id ? "?id=$id" : ''));
            exit;
        }

        // Hapus file lama
        if ($edit && $edit['file_name']) {
            $old_path = $dir . '/' . $edit['file_name'];
            if (file_exists($old_path)) @unlink($old_path);
        }
    }

    try {
        if ($edit && $id > 0) {
            // Cek kolom tags ada
            $has_tags = false;
            try {
                $pdo->query("SELECT tags FROM downloads LIMIT 1");
                $has_tags = true;
            } catch (Exception $e) {}

            if ($has_tags) {
                $pdo->prepare("UPDATE downloads SET judul=?, kategori=?, deskripsi=?, file_name=?, file_size=?, tags=? WHERE id=?")
                    ->execute([$judul, $kategori, $deskripsi, $file_name, $file_size, $tags, $id]);
            } else {
                $pdo->prepare("UPDATE downloads SET judul=?, kategori=?, deskripsi=?, file_name=?, file_size=? WHERE id=?")
                    ->execute([$judul, $kategori, $deskripsi, $file_name, $file_size, $id]);
            }
            flash_message('success', '✅ File berhasil diperbarui.');
        } else {
            $has_tags = false;
            try {
                $pdo->query("SELECT tags FROM downloads LIMIT 1");
                $has_tags = true;
            } catch (Exception $e) {}

            if ($has_tags) {
                $pdo->prepare("INSERT INTO downloads (judul, kategori, deskripsi, file_name, file_size, tags) VALUES (?,?,?,?,?,?)")
                    ->execute([$judul, $kategori, $deskripsi, $file_name, $file_size, $tags]);
            } else {
                $pdo->prepare("INSERT INTO downloads (judul, kategori, deskripsi, file_name, file_size) VALUES (?,?,?,?,?)")
                    ->execute([$judul, $kategori, $deskripsi, $file_name, $file_size]);
            }
            flash_message('success', '✅ File berhasil diupload.');
        }
    } catch (PDOException $e) {
        flash_message('error', '❌ Gagal menyimpan: ' . $e->getMessage());
        header('Location: download-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }

    header('Location: download.php');
    exit;
}

$csrf = generate_csrf_token();
$active_menu = 'download';
$page_heading = $edit ? 'Edit File' : 'Upload File';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Download Center', 'download.php'], [$page_heading, null]];

require __DIR__ . '/includes/header.php';
?>

<style>
/* ===== FORM LAYOUT ===== */
.upload-layout {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
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
    background: linear-gradient(90deg, #ef4444 0%, #f59e0b 50%, #6366f1 100%);
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
    color: #6366f1;
    font-weight: 800;
    font-size: 1.1rem;
    font-variant-numeric: tabular-nums;
}
.progress-label { color: var(--text-muted); }

/* Form Card */
.form-card-premium {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 2rem;
    box-shadow: var(--shadow-lg);
    position: relative;
    overflow: hidden;
}
.form-card-premium::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, #6366f1, #8b5cf6, #ec4899);
}
.form-header-premium {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border);
}
.form-header-premium h2 {
    font-family: 'Georgia', serif;
    font-size: 1.75rem;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    letter-spacing: -0.01em;
}
.form-header-premium p { color: var(--text-muted); font-size: 0.9rem; }

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
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border-color: #6366f1;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(99,102,241,0.25);
}

/* ===== DRAG & DROP ZONE ===== */
.drop-zone {
    border: 2px dashed var(--border);
    border-radius: var(--radius-lg);
    padding: 2.5rem 1.5rem;
    text-align: center;
    background: var(--bg-secondary);
    transition: all 0.3s;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}
.drop-zone:hover, .drop-zone.dragover {
    border-color: #6366f1;
    background: rgba(99,102,241,0.03);
}
.drop-zone.dragover {
    border-style: solid;
    transform: scale(1.02);
    box-shadow: 0 0 0 4px rgba(99,102,241,0.1);
}
.drop-zone-icon {
    font-size: 3.5rem;
    margin-bottom: 1rem;
    animation: float 3s ease-in-out infinite;
}
@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}
.drop-zone-text {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}
.drop-zone-sub {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
}
.drop-zone-hints {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.5rem;
}
.drop-zone-hint {
    font-size: 0.72rem;
    color: var(--text-muted);
    background: var(--bg-primary);
    padding: 0.3rem 0.75rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.drop-zone input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
}

/* File Preview */
.file-preview {
    display: none;
    margin-top: 1rem;
    padding: 1rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    animation: slideInUp 0.3s;
}
@keyframes slideInUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.file-preview.show { display: flex; align-items: center; gap: 1rem; }
.file-preview-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}
.file-pdf { background: linear-gradient(135deg, #fee2e2, #fecaca); color: #dc2626; }
.file-doc { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #2563eb; }
.file-xls { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #16a34a; }
.file-ppt { background: linear-gradient(135deg, #fed7aa, #fdba74); color: #c2410c; }
.file-zip { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #d97706; }
.file-rar { background: linear-gradient(135deg, #e0e7ff, #c7d2fe); color: #4338ca; }
.file-img { background: linear-gradient(135deg, #fce7f3, #fbcfe8); color: #db2777; }
.file-other { background: linear-gradient(135deg, #f3f4f6, #e5e7eb); color: #4b5563; }
.file-preview-info { flex: 1; min-width: 0; }
.file-preview-info h4 {
    font-size: 0.9rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.file-preview-info small { font-size: 0.78rem; color: var(--text-muted); display: block; }
.file-preview-dim {
    font-size: 0.7rem;
    color: var(--text-muted);
    margin-top: 0.2rem;
    font-family: monospace;
}
.file-preview-remove {
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
.file-preview-remove:hover {
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

.current-file-box {
    margin-top: 1rem;
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    font-size: 0.85rem;
    border: 1px solid var(--border);
}

/* ===== CATEGORY VISUAL SELECTOR ===== */
.cat-selector {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
    gap: 0.65rem;
    margin-bottom: 1.5rem;
}
.cat-option { position: relative; }
.cat-option input { position: absolute; opacity: 0; pointer-events: none; }
.cat-option label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.4rem;
    padding: 0.85rem 0.4rem;
    background: var(--bg-secondary);
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all 0.3s;
    text-align: center;
}
.cat-option label:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
    border-color: #6366f1;
}
.cat-option input:checked + label {
    border-color: #6366f1;
    background: rgba(99,102,241,0.05);
    box-shadow: 0 4px 12px rgba(99,102,241,0.15);
}
.cat-icon { font-size: 1.5rem; line-height: 1; }
.cat-name {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--text-primary);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

/* Custom category input */
.custom-cat-toggle {
    margin-top: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.custom-cat-btn {
    padding: 0.4rem 0.85rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    color: var(--text-secondary);
    font-family: inherit;
    transition: all 0.2s;
}
.custom-cat-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }
.custom-cat-input {
    flex: 1;
    padding: 0.5rem 0.85rem;
    border: 2px solid var(--border);
    border-radius: 6px;
    font-family: inherit;
    font-size: 0.85rem;
    display: none;
}
.custom-cat-input.show { display: block; }
.custom-cat-input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
}

/* ===== FLOATING LABEL FORM ===== */
.form-group-premium { margin-bottom: 1.5rem; position: relative; }
.form-label {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}
.form-label .label-icon {
    width: 18px; height: 18px;
    background: rgba(99,102,241,0.1);
    color: #6366f1;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
}

.input-wrapper-premium { position: relative; }
.form-input-premium {
    width: 100%;
    padding: 1rem 1rem 1rem 3rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    font-family: inherit;
    font-size: 0.95rem;
    background: var(--bg-secondary);
    transition: all 0.3s;
    color: var(--text-primary);
}
.form-input-premium:focus {
    outline: none;
    border-color: #6366f1;
    background: var(--bg-primary);
    box-shadow: 0 0 0 4px rgba(99,102,241,0.1);
}
.form-input-premium::placeholder { color: transparent; }
.input-icon-premium {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 1.1rem;
    pointer-events: none;
    transition: color 0.3s;
}
.form-input-premium:focus ~ .input-icon-premium { color: #6366f1; }
.floating-label-premium {
    position: absolute;
    left: 3rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 0.95rem;
    pointer-events: none;
    transition: all 0.25s;
    background: var(--bg-secondary);
    padding: 0 0.35rem;
}
.form-input-premium:focus ~ .floating-label-premium,
.form-input-premium:not(:placeholder-shown) ~ .floating-label-premium {
    top: 0;
    font-size: 0.75rem;
    color: #6366f1;
    font-weight: 600;
    background: var(--bg-primary);
}

.form-input-premium.error {
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

/* Textarea with counter */
.textarea-wrapper { position: relative; }
.form-textarea-premium {
    width: 100%;
    padding: 1rem;
    padding-bottom: 2.5rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    font-family: inherit;
    font-size: 0.95rem;
    background: var(--bg-secondary);
    transition: all 0.3s;
    min-height: 120px;
    resize: vertical;
    color: var(--text-primary);
}
.form-textarea-premium:focus {
    outline: none;
    border-color: #6366f1;
    background: var(--bg-primary);
    box-shadow: 0 0 0 4px rgba(99,102,241,0.1);
}
.char-counter-premium {
    position: absolute;
    bottom: 0.75rem;
    right: 1rem;
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 600;
    background: var(--bg-primary);
    padding: 0.2rem 0.5rem;
    border-radius: 999px;
    font-variant-numeric: tabular-nums;
}
.char-counter-premium.warn { color: #f59e0b; }
.char-counter-premium.danger { color: #ef4444; }

/* ===== SMART DESKRIPSI BUILDER ===== */
.deskripsi-builder {
    margin-top: 0.75rem;
    padding: 0.85rem;
    background: var(--bg-secondary);
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
.deskripsi-chip:hover {
    background: #dbeafe;
    border-color: #93c5fd;
    color: #1e40af;
    transform: translateY(-1px);
}

/* ===== TAGS INPUT ===== */
.tags-input-wrap {
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    padding: 0.5rem;
    background: var(--bg-secondary);
    transition: all 0.2s;
    min-height: 52px;
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
}
.tags-input-wrap:focus-within {
    border-color: #6366f1;
    background: var(--bg-primary);
    box-shadow: 0 0 0 4px rgba(99,102,241,0.1);
}
.tag-pill {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    animation: tagPop 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
@keyframes tagPop { from { transform: scale(0); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.tag-pill button {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    transition: all 0.2s;
    padding: 0;
}
.tag-pill button:hover { background: rgba(255,255,255,0.4); }
.tag-input-field {
    border: none;
    outline: none;
    flex: 1;
    min-width: 120px;
    padding: 0.4rem;
    background: transparent;
    font-family: inherit;
    font-size: 0.88rem;
    color: var(--text-primary);
}

/* ===== SUBMIT BUTTON ===== */
.submit-btn-premium {
    width: 100%;
    padding: 1.1rem;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
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
.submit-btn-premium:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(99,102,241,0.35);
}
.submit-btn-premium:disabled { opacity: 0.7; cursor: not-allowed; }
.submit-btn-premium .spinner {
    width: 20px;
    height: 20px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
    display: none;
}
.submit-btn-premium.loading .spinner { display: inline-block; }
.submit-btn-premium.loading .btn-text { display: none; }
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
    border-color: #6366f1;
    color: #6366f1;
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
.preview-card-premium {
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
    background: #6366f1;
    color: white;
    border-color: #6366f1;
    transform: translateY(-2px);
}

/* File Card Preview */
.file-card-preview {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    margin-bottom: 1rem;
    position: relative;
    overflow: hidden;
}
.file-card-preview::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: var(--card-accent, #6366f1);
}
.preview-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.85rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 1rem;
}
.preview-title {
    font-family: 'Georgia', serif;
    font-size: 1.35rem;
    font-weight: 800;
    margin-bottom: 1rem;
    line-height: 1.3;
    min-height: 2.5rem;
    letter-spacing: -0.01em;
}
.preview-meta {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    margin-bottom: 1.5rem;
}
.preview-meta-item {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    font-size: 0.85rem;
    color: var(--text-secondary);
}
.preview-meta-item .meta-icon {
    width: 28px;
    height: 28px;
    background: var(--bg-primary);
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
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
    font-size: 0.88rem;
    line-height: 1.6;
    color: var(--text-secondary);
    min-height: 80px;
    border-left: 3px solid #6366f1;
    font-style: italic;
}
.preview-desc::before {
    content: '"';
    font-size: 1.5rem;
    color: #6366f1;
    line-height: 0.5;
    display: block;
    margin-bottom: 0.5rem;
    opacity: 0.5;
}
.preview-empty { color: var(--text-muted); font-style: italic; }

/* Tags preview */
.preview-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px dashed var(--border);
}
.preview-tag {
    padding: 0.2rem 0.6rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--text-secondary);
}

/* Download stats preview */
.preview-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
    margin-top: 1rem;
    padding: 1rem;
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    border: 1px solid #93c5fd;
    border-radius: var(--radius-md);
}
.preview-stat-item {
    text-align: center;
    padding: 0.5rem;
    background: white;
    border-radius: 8px;
    border: 1px solid #93c5fd;
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
    .upload-layout { grid-template-columns: 1fr; }
    .preview-card-premium { position: static; order: -1; }
    .cat-selector { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 640px) {
    .form-card-premium, .preview-card-premium { padding: 1.5rem; }
    .template-buttons { flex-direction: column; }
    .shortcuts-legend { font-size: 0.7rem; }
    .cat-selector { grid-template-columns: repeat(2, 1fr); }
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
        <span class="progress-label">Kelengkapan File</span>
    </div>
</div>

<div class="upload-layout">
    <!-- ===== LEFT: FORM ===== -->
    <div class="form-card-premium" data-aos="fade-right">
        <div class="form-header-premium">
            <h2><?= $edit ? '✏️ Edit' : '📤 Upload' ?> File</h2>
            <p><?= $edit ? 'Perbarui detail dokumen di bawah ini.' : 'Upload dokumen untuk dibagikan ke pengunjung website.' ?></p>
        </div>

        <!-- Quick Templates -->
        <div class="template-section" data-aos="fade-up">
            <div class="template-label">⚡ Template Cepat (Klik untuk auto-fill)</div>
            <div class="template-buttons">
                <button type="button" class="template-btn" onclick="applyTemplate('kurikulum')">🎓 Kurikulum Prodi</button>
                <button type="button" class="template-btn" onclick="applyTemplate('silabus')">📋 Silabus</button>
                <button type="button" class="template-btn" onclick="applyTemplate('brosur')">📄 Brosur PMB</button>
                <button type="button" class="template-btn" onclick="applyTemplate('formulir')">📝 Formulir Pendaftaran</button>
                <button type="button" class="template-btn" onclick="applyTemplate('pedoman')">📖 Pedoman Skripsi</button>
                <button type="button" class="template-btn" onclick="applyTemplate('jadwal')">📅 Jadwal Kuliah</button>
                <button type="button" class="template-btn" onclick="applyTemplate('penelitian')">🔬 Laporan Penelitian</button>
                <button type="button" class="template-btn" onclick="applyTemplate('surat')">📧 Surat Resmi</button>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="uploadForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

            <!-- Drop Zone -->
            <div class="form-group-premium">
                <label class="form-label">
                    <span class="label-icon">📎</span>
                    File Dokumen <?= $edit ? '' : '*' ?>
                </label>
                <div class="drop-zone" id="dropZone">
                    <input type="file" name="file" id="fileInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.7z,.jpg,.jpeg,.png,.webp" <?= $edit ? '' : 'required' ?>>
                    <div class="drop-zone-icon">📤</div>
                    <div class="drop-zone-text">Drag & drop file di sini</div>
                    <div class="drop-zone-sub">atau klik untuk memilih file</div>
                    <div class="drop-zone-hints">
                        <span class="drop-zone-hint">📕 PDF</span>
                        <span class="drop-zone-hint">📘 DOC/DOCX</span>
                        <span class="drop-zone-hint">📗 XLS/XLSX</span>
                        <span class="drop-zone-hint">📙 PPT/PPTX</span>
                        <span class="drop-zone-hint">📦 ZIP/RAR/7Z</span>
                        <span class="drop-zone-hint">🖼️ Images</span>
                        <span class="drop-zone-hint">📦 Max: 25MB</span>
                    </div>
                </div>
                <div class="file-preview" id="filePreview">
                    <div class="file-preview-icon file-other" id="filePreviewIcon">📄</div>
                    <div class="file-preview-info">
                        <h4 id="filePreviewName">-</h4>
                        <small id="filePreviewSize">-</small>
                        <div class="file-preview-dim" id="filePreviewDim"></div>
                    </div>
                    <button type="button" class="file-preview-remove" onclick="removeFile()" title="Hapus file">✕</button>
                </div>
                <div class="aspect-warning" id="aspectWarning">
                    ⚠️ <span id="aspectWarningText">File warning</span>
                </div>
                <?php if ($edit && $edit['file_name']): ?>
                    <div class="current-file-box">
                        <strong>📄 File saat ini:</strong>
                        <div style="font-size: 0.78rem; color: var(--text-muted); font-family: monospace; margin-top: 0.25rem; word-break: break-all;">
                            <?= sanitize($edit['file_name']) ?>
                        </div>
                        <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.25rem;">
                            💾 <?= sanitize($edit['file_size']) ?> • ⬇️ <?= number_format($edit['downloads_count'] ?? 0) ?> downloads
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Judul -->
            <div class="form-group-premium">
                <div class="input-wrapper-premium">
                    <input type="text" id="judul" name="judul" class="form-input-premium" placeholder=" " required maxlength="255" value="<?= sanitize($edit['judul'] ?? '') ?>">
                    <span class="input-icon-premium">📝</span>
                    <label class="floating-label-premium" for="judul">Judul Dokumen *</label>
                </div>
                <div class="form-hint" style="display: flex; justify-content: space-between;">
                    <span>Gunakan judul yang deskriptif dan mudah dipahami</span>
                    <span><span id="judulCounter"><?= strlen($edit['judul'] ?? '') ?></span>/255 karakter</span>
                </div>
            </div>

            <!-- Kategori Visual -->
            <div class="form-group-premium">
                <label class="form-label">
                    <span class="label-icon">🏷️</span>
                    Kategori *
                </label>
                <div class="cat-selector" id="catSelector">
                    <?php
                    $cat_icons = [
                        'Akademik' => '🎓', 'PMB' => '📋', 'Formulir' => '📄',
                        'Pedoman' => '📚', 'Penelitian' => '🔬', 'Pengabdian' => '🤝',
                        'Kemahasiswaan' => '🎉', 'Lainnya' => '📌'
                    ];
                    foreach ($all_categories as $cat):
                        $icon = $cat_icons[$cat] ?? '📄';
                        $is_checked = ($edit['kategori'] ?? 'Lainnya') === $cat;
                    ?>
                    <div class="cat-option">
                        <input type="radio" name="kategori" id="kat-<?= md5($cat) ?>" value="<?= sanitize($cat) ?>" <?= $is_checked ? 'checked' : '' ?>>
                        <label for="kat-<?= md5($cat) ?>">
                            <span class="cat-icon"><?= $icon ?></span>
                            <span class="cat-name"><?= sanitize($cat) ?></span>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="custom-cat-toggle">
                    <button type="button" class="custom-cat-btn" onclick="toggleCustomCat()">+ Kategori Lain</button>
                    <input type="text" id="customCatInput" class="custom-cat-input" placeholder="Ketik kategori baru..." maxlength="50">
                </div>
            </div>

            <!-- Deskripsi -->
            <div class="form-group-premium">
                <label class="form-label">
                    <span class="label-icon">📝</span>
                    Deskripsi
                </label>
                <div class="textarea-wrapper">
                    <textarea id="deskripsi" name="deskripsi" class="form-textarea-premium" placeholder=" " maxlength="1000"><?= sanitize($edit['deskripsi'] ?? '') ?></textarea>
                    <span class="char-counter-premium" id="charCounter"><?= strlen($edit['deskripsi'] ?? '') ?>/1000</span>
                </div>

                <!-- Smart Deskripsi Builder -->
                <div class="deskripsi-builder">
                    <div class="deskripsi-builder-header">💡 Klik untuk menambahkan frase umum:</div>
                    <div class="deskripsi-chips">
                        <span class="deskripsi-chip" onclick="addDescPhrase('Dokumen resmi yang berlaku untuk tahun akademik saat ini.')">+ Dokumen resmi</span>
                        <span class="deskripsi-chip" onclick="addDescPhrase('Silakan unduh dan baca dengan seksama sebelum digunakan.')">+ Instruksi unduh</span>
                        <span class="deskripsi-chip" onclick="addDescPhrase('Untuk informasi lebih lanjut, hubungi bagian administrasi.')">+ Kontak info</span>
                        <span class="deskripsi-chip" onclick="addDescPhrase('Dokumen ini bersifat wajib bagi seluruh mahasiswa aktif.')">+ Wajib mahasiswa</span>
                        <span class="deskripsi-chip" onclick="addDescPhrase('Terakhir diperbarui pada semester ini.')">+ Update info</span>
                        <span class="deskripsi-chip" onclick="addDescPhrase('Format PDF, siap untuk dicetak.')">+ Format info</span>
                    </div>
                </div>
            </div>

            <!-- Tags -->
            <div class="form-group-premium">
                <label class="form-label">
                    <span class="label-icon">🏷️</span>
                    Tags
                    <small style="color:var(--text-muted); margin-left:0.5rem; font-weight:500;">Pisahkan dengan koma atau tekan Enter</small>
                </label>
                <div class="tags-input-wrap">
                    <div class="tags-container" id="tagsContainer"></div>
                    <input type="text" id="tagInput" class="tag-input-field" placeholder="Ketik tag...">
                </div>
                <input type="hidden" name="tags" id="tagsHidden" value="<?= sanitize($edit['tags'] ?? '') ?>">
            </div>

            <!-- Submit -->
            <button type="submit" class="submit-btn-premium" id="submitBtn">
                <span class="btn-text"><?= $edit ? '💾 Perbarui File' : '✨ Upload File' ?></span>
                <span class="spinner"></span>
            </button>

            <!-- Secondary Actions -->
            <div class="secondary-actions">
                <?php if ($edit && $id > 0): ?>
                <a href="?duplicate=<?= $id ?>" class="secondary-btn" onclick="return confirm('Duplikasi data ini?')">
                    📋 Duplikasi
                </a>
                <?php endif; ?>
                <a href="download.php" class="secondary-btn">← Kembali</a>
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
    <div class="preview-card-premium" data-aos="fade-left">
        <div class="preview-header">
            <div>
                <h3>👁️ Live Preview</h3>
                <p style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem;">Tampilan kartu download</p>
            </div>
            <div class="preview-actions">
                <button class="preview-action-btn" onclick="togglePreviewMode()" title="Toggle mode">🔄</button>
                <button class="preview-action-btn" onclick="sharePreview()" title="Share">🔗</button>
                <?php if ($edit && $id > 0): ?>
                <a href="download.php" class="preview-action-btn" target="_blank" title="Lihat di list">📋</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- File Card Preview -->
        <div class="file-card-preview" id="previewCard">
            <div class="preview-badge" id="previewBadge" style="background: #f3f4f6; color: #4b5563;">📌 LAINNYA</div>
            <h2 class="preview-title" id="previewTitle">Judul dokumen akan muncul di sini...</h2>

            <div class="preview-meta">
                <div class="preview-meta-item">
                    <span class="meta-icon">📄</span>
                    <strong id="previewFile">Belum ada file</strong>
                </div>
                <div class="preview-meta-item">
                    <span class="meta-icon">💾</span>
                    <strong id="previewSize">-</strong>
                </div>
                <div class="preview-meta-item">
                    <span class="meta-icon">⬇️</span>
                    <strong id="previewDownloads"><?= $edit ? number_format($edit['downloads_count']) : '0' ?> downloads</strong>
                </div>
            </div>

            <div class="preview-desc" id="previewDesc">
                <span class="preview-empty">Deskripsi akan muncul di sini...</span>
            </div>

            <!-- Tags Preview -->
            <div class="preview-tags" id="previewTags" style="display: none;"></div>

            <!-- Stats Preview -->
            <div class="preview-stats" id="previewStats" style="display: none;">
                <div class="preview-stat-item">
                    <div class="preview-stat-value" id="previewDaysOld">0</div>
                    <div class="preview-stat-label">Hari</div>
                </div>
                <div class="preview-stat-item">
                    <div class="preview-stat-value" id="previewPerDay">0</div>
                    <div class="preview-stat-label">Dl/Hari</div>
                </div>
                <div class="preview-stat-item">
                    <div class="preview-stat-value" id="previewPopularity">-</div>
                    <div class="preview-stat-label">Popular</div>
                </div>
            </div>
        </div>

        <!-- QR Preview -->
        <div class="share-preview">
            <div class="share-preview-label">🔗 QR Download</div>
            <div class="share-preview-qr">
                <img id="qrCode" src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=FKIP-UNIMOF-Download" alt="QR">
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
const initialTags = <?= json_encode($edit['tags'] ?? '') ?>;

// ===== TEMPLATES =====
const templates = {
    kurikulum: {
        judul: 'Kurikulum Program Studi',
        kategori: 'Akademik',
        deskripsi: 'Dokumen kurikulum resmi program studi yang berlaku untuk tahun akademik saat ini. Berisi struktur mata kuliah, SKS, dan deskripsi mata kuliah.',
        tags: 'kurikulum,akademik,prodi,mata kuliah'
    },
    silabus: {
        judul: 'Silabus Mata Kuliah',
        kategori: 'Akademik',
        deskripsi: 'Silabus lengkap mata kuliah termasuk RPS, tujuan pembelajaran, metode evaluasi, dan referensi.',
        tags: 'silabus,rps,mata kuliah,akademik'
    },
    brosur: {
        judul: 'Brosur Penerimaan Mahasiswa Baru',
        kategori: 'PMB',
        deskripsi: 'Brosur informasi lengkap pendaftaran mahasiswa baru FKIP UNIMOF termasuk program studi, biaya, dan fasilitas.',
        tags: 'pmb,brosur,pendaftaran,mahasiswa baru'
    },
    formulir: {
        judul: 'Formulir Pendaftaran',
        kategori: 'Formulir',
        deskripsi: 'Formulir pendaftaran resmi yang harus diisi oleh calon mahasiswa baru.',
        tags: 'formulir,pendaftaran,form'
    },
    pedoman: {
        judul: 'Pedoman Penulisan Skripsi',
        kategori: 'Pedoman',
        deskripsi: 'Pedoman lengkap penulisan skripsi termasuk format, sistematika, dan contoh.',
        tags: 'pedoman,skripsi,tugas akhir,penulisan'
    },
    jadwal: {
        judul: 'Jadwal Kuliah Semester Ganjil',
        kategori: 'Akademik',
        deskripsi: 'Jadwal perkuliahan semester ganjil untuk seluruh program studi.',
        tags: 'jadwal,kuliah,semester,akademik'
    },
    penelitian: {
        judul: 'Laporan Penelitian',
        kategori: 'Penelitian',
        deskripsi: 'Laporan lengkap hasil penelitian termasuk metodologi, temuan, dan kesimpulan.',
        tags: 'penelitian,riset,laporan,jurnal'
    },
    surat: {
        judul: 'Surat Resmi',
        kategori: 'Lainnya',
        deskripsi: 'Surat resmi yang dapat diunduh dan digunakan untuk keperluan administratif.',
        tags: 'surat,resmi,administrasi'
    }
};

function applyTemplate(key) {
    const t = templates[key];
    if (!t) return;

    document.getElementById('judul').value = t.judul;
    document.getElementById('judulCounter').textContent = t.judul.length;

    const radio = document.querySelector(`input[name="kategori"][value="${t.kategori}"]`);
    if (radio) {
        radio.checked = true;
    } else {
        // Kategori custom
        document.getElementById('customCatInput').value = t.kategori;
        document.getElementById('customCatInput').classList.add('show');
    }

    document.getElementById('deskripsi').value = t.deskripsi;
    updateCharCounter();

    if (t.tags) {
        tags = t.tags.split(',').map(t => t.trim()).filter(t => t);
        renderTags();
    }

    updatePreview();
    updateProgress();
    triggerAutosave();

    showToast('Template Diterapkan', `Template "${key}" berhasil diisi`, 'success');
}

// ===== CUSTOM CATEGORY =====
function toggleCustomCat() {
    const input = document.getElementById('customCatInput');
    input.classList.toggle('show');
    if (input.classList.contains('show')) {
        input.focus();
        // Uncheck all radios
        document.querySelectorAll('input[name="kategori"]').forEach(r => r.checked = false);
    }
}

document.getElementById('customCatInput').addEventListener('input', function() {
    if (this.value.trim()) {
        document.querySelectorAll('input[name="kategori"]').forEach(r => r.checked = false);
    }
    updatePreview();
    triggerAutosave();
});

// ===== DRAG & DROP =====
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const filePreview = document.getElementById('filePreview');

['dragenter', 'dragover'].forEach(ev => {
    dropZone.addEventListener(ev, e => { e.preventDefault(); dropZone.classList.add('dragover'); });
});
['dragleave', 'drop'].forEach(ev => {
    dropZone.addEventListener(ev, e => { e.preventDefault(); dropZone.classList.remove('dragover'); });
});
dropZone.addEventListener('drop', e => {
    if (e.dataTransfer.files.length > 0) handleFile(e.dataTransfer.files[0]);
});
fileInput.addEventListener('change', e => {
    if (e.target.files.length > 0) handleFile(e.target.files[0]);
});

function handleFile(file) {
    const ext = file.name.split('.').pop().toLowerCase();
    const allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', '7z', 'jpg', 'jpeg', 'png', 'webp'];

    if (!allowedExts.includes(ext)) {
        showToast('Error', 'Format file tidak diizinkan!', 'error');
        return;
    }
    if (file.size > 25 * 1024 * 1024) {
        showToast('Error', 'Ukuran file maksimal 25MB!', 'error');
        return;
    }

    // Update file input
    const dt = new DataTransfer();
    dt.items.add(file);
    fileInput.files = dt.files;

    // File icon & class
    const iconClassMap = {
        'pdf': ['📕', 'file-pdf'],
        'doc': ['📘', 'file-doc'], 'docx': ['📘', 'file-doc'],
        'xls': ['📗', 'file-xls'], 'xlsx': ['📗', 'file-xls'],
        'ppt': ['📙', 'file-ppt'], 'pptx': ['📙', 'file-ppt'],
        'zip': ['📦', 'file-zip'], 'rar': ['🗜️', 'file-rar'], '7z': ['🗜️', 'file-rar'],
        'jpg': ['🖼️', 'file-img'], 'jpeg': ['🖼️', 'file-img'],
        'png': ['🖼️', 'file-img'], 'webp': ['🖼️', 'file-img']
    };
    const [icon, cls] = iconClassMap[ext] || ['📄', 'file-other'];

    const iconEl = document.getElementById('filePreviewIcon');
    iconEl.className = 'file-preview-icon ' + cls;
    iconEl.textContent = icon;
    document.getElementById('filePreviewName').textContent = file.name;
    document.getElementById('filePreviewSize').textContent = formatFileSize(file.size);

    // Warnings
    const warning = document.getElementById('aspectWarning');
    const warningText = document.getElementById('aspectWarningText');
    const dimEl = document.getElementById('filePreviewDim');

    if (['jpg', 'jpeg', 'png', 'webp'].includes(ext)) {
        const reader = new FileReader();
        reader.onload = e => {
            const img = new Image();
            img.onload = () => {
                dimEl.textContent = `📐 ${img.width} × ${img.height} px`;
                const ratio = img.width / img.height;
                if (Math.abs(ratio - 16/9) > 0.3 && Math.abs(ratio - 4/3) > 0.3) {
                    warning.classList.add('show');
                    warningText.textContent = 'Rasio gambar tidak standar, pertimbangkan 16:9 atau 4:3';
                } else {
                    warning.classList.remove('show');
                }
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    } else if (file.size > 10 * 1024 * 1024) {
        warning.classList.add('show');
        warningText.textContent = 'File cukup besar, pastikan pengguna memiliki koneksi yang baik';
        dimEl.textContent = '';
    } else {
        warning.classList.remove('show');
        dimEl.textContent = '';
    }

    filePreview.classList.add('show');
    dropZone.style.display = 'none';

    updatePreview();
    updateProgress();
    triggerAutosave();
}

function removeFile() {
    fileInput.value = '';
    filePreview.classList.remove('show');
    document.getElementById('aspectWarning').classList.remove('show');
    dropZone.style.display = 'block';
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

// ===== SMART DESKRIPSI BUILDER =====
function addDescPhrase(phrase) {
    const textarea = document.getElementById('deskripsi');
    const current = textarea.value.trim();
    const newDesc = current ? current + ' ' + phrase : phrase;
    textarea.value = newDesc;
    updateCharCounter();
    updatePreview();
    triggerAutosave();
    updateProgress();
    textarea.focus();
    showToast('Frase Ditambahkan', phrase.substring(0, 40) + '...', 'success');
}

// ===== LIVE PREVIEW =====
const catConfig = {
    'Akademik': { icon: '🎓', color: '#10b981', bg: '#dcfce7', label: 'AKADEMIK', accent: '#10b981' },
    'PMB': { icon: '📋', color: '#3b82f6', bg: '#dbeafe', label: 'PMB', accent: '#3b82f6' },
    'Formulir': { icon: '📄', color: '#f59e0b', bg: '#fef3c7', label: 'FORMULIR', accent: '#f59e0b' },
    'Pedoman': { icon: '📚', color: '#8b5cf6', bg: '#ede9fe', label: 'PEDOMAN', accent: '#8b5cf6' },
    'Penelitian': { icon: '🔬', color: '#06b6d4', bg: '#cffafe', label: 'PENELITIAN', accent: '#06b6d4' },
    'Pengabdian': { icon: '🤝', color: '#ec4899', bg: '#fce7f3', label: 'PENGABDIAN', accent: '#ec4899' },
    'Kemahasiswaan': { icon: '🎉', color: '#f59e0b', bg: '#fef3c7', label: 'KEMAHASISWAAN', accent: '#f59e0b' },
    'Lainnya': { icon: '📌', color: '#6b7280', bg: '#f3f4f6', label: 'LAINNYA', accent: '#6b7280' }
};

function updatePreview() {
    const judul = document.getElementById('judul').value || 'Judul dokumen akan muncul di sini...';
    const checkedRadio = document.querySelector('input[name="kategori"]:checked');
    const customCat = document.getElementById('customCatInput').value.trim();
    const kategori = customCat || checkedRadio?.value || 'Lainnya';
    const deskripsi = document.getElementById('deskripsi').value;
    const fileName = fileInput.files[0]?.name || '<?= $edit ? sanitize($edit["file_name"]) : "" ?>';
    const fileSize = fileInput.files[0] ? formatFileSize(fileInput.files[0].size) : '<?= $edit ? sanitize($edit["file_size"]) : "-" ?>';

    const config = catConfig[kategori] || { icon: '📄', color: '#6b7280', bg: '#f3f4f6', label: kategori.toUpperCase(), accent: '#6b7280' };
    const badge = document.getElementById('previewBadge');
    badge.textContent = `${config.icon} ${config.label}`;
    badge.style.background = config.bg;
    badge.style.color = config.color;

    // Set accent color on card
    document.getElementById('previewCard').style.setProperty('--card-accent', config.accent);

    document.getElementById('previewTitle').textContent = judul;
    document.getElementById('previewFile').textContent = fileName || 'Belum ada file';
    document.getElementById('previewSize').textContent = fileSize;

    if (deskripsi) {
        document.getElementById('previewDesc').innerHTML = deskripsi.replace(/\n/g, '<br>');
        document.getElementById('previewDesc').style.color = 'var(--text-secondary)';
    } else {
        document.getElementById('previewDesc').innerHTML = '<span class="preview-empty">Deskripsi akan muncul di sini...</span>';
    }

    // Tags preview
    const tagsContainer = document.getElementById('previewTags');
    if (tags.length > 0) {
        tagsContainer.innerHTML = tags.map(t => `<span class="preview-tag">#${t}</span>`).join('');
        tagsContainer.style.display = 'flex';
    } else {
        tagsContainer.style.display = 'none';
    }

    // Stats preview (if editing)
    <?php if ($edit): ?>
    const statsEl = document.getElementById('previewStats');
    const daysOld = Math.floor((new Date() - new Date('<?= $edit['created_at'] ?? 'now' ?>')) / (1000 * 60 * 60 * 24));
    const downloads = <?= $edit['downloads_count'] ?? 0 ?>;
    const perDay = daysOld > 0 ? (downloads / daysOld).toFixed(2) : downloads;
    const popularity = downloads >= 100 ? '🔥' : (downloads >= 50 ? '📈' : (downloads >= 10 ? '📊' : '🆕'));

    document.getElementById('previewDaysOld').textContent = daysOld;
    document.getElementById('previewPerDay').textContent = perDay;
    document.getElementById('previewPopularity').textContent = popularity;
    statsEl.style.display = 'grid';
    <?php endif; ?>

    // QR Code
    const qrData = `DOWNLOAD:${judul}|${kategori}|${fileName}`;
    document.getElementById('qrCode').src = `https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=${encodeURIComponent(qrData)}`;
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

// ===== JUDUL COUNTER =====
document.getElementById('judul').addEventListener('input', function() {
    document.getElementById('judulCounter').textContent = this.value.length;
    updatePreview();
    updateProgress();
    triggerAutosave();
});

// ===== TAGS INPUT =====
const tagInput = document.getElementById('tagInput');
const tagsContainer = document.getElementById('tagsContainer');
const tagsHidden = document.getElementById('tagsHidden');
let tags = [];

// Load existing tags
if (initialTags) {
    tags = initialTags.split(',').map(t => t.trim()).filter(t => t);
    renderTags();
}

function renderTags() {
    tagsContainer.innerHTML = '';
    tags.forEach((tag, i) => {
        const pill = document.createElement('span');
        pill.className = 'tag-pill';
        pill.innerHTML = `${tag} <button type="button" onclick="removeTag(${i})">✕</button>`;
        tagsContainer.appendChild(pill);
    });
    tagsHidden.value = tags.join(', ');
    updatePreview();
    updateProgress();
}

function addTag(val) {
    val = val.trim();
    if (val && !tags.includes(val) && tags.length < 10) {
        tags.push(val);
        renderTags();
        triggerAutosave();
    }
}

window.removeTag = function(i) {
    tags.splice(i, 1);
    renderTags();
    triggerAutosave();
};

tagInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        addTag(this.value.replace(',', ''));
        this.value = '';
    } else if (e.key === 'Backspace' && !this.value && tags.length > 0) {
        tags.pop();
        renderTags();
    }
});
tagInput.addEventListener('blur', function() {
    if (this.value.trim()) {
        addTag(this.value);
        this.value = '';
    }
});

// ===== PROGRESS INDICATOR =====
function updateProgress() {
    const fields = [
        { check: () => fileInput.files[0] || <?= $edit ? 'true' : 'false' ?>, weight: 30 },
        { el: 'judul', weight: 25 },
        { check: () => document.querySelector('input[name="kategori"]:checked') || document.getElementById('customCatInput').value.trim(), weight: 20 },
        { el: 'deskripsi', weight: 15 },
        { check: () => tags.length > 0, weight: 10 }
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
const storageKey = 'fkip_download_draft_<?= $id ?: "new" ?>';
let autosaveTimer;

function triggerAutosave() {
    clearTimeout(autosaveTimer);
    const indicator = document.getElementById('autosaveIndicator');
    indicator.classList.add('show', 'saving');
    indicator.classList.remove('saved');
    document.getElementById('autosaveIcon').textContent = '⏳';
    document.getElementById('autosaveText').textContent = 'Menyimpan draft...';

    autosaveTimer = setTimeout(() => {
        const customCat = document.getElementById('customCatInput').value.trim();
        const checkedRadio = document.querySelector('input[name="kategori"]:checked');
        const kategori = customCat || checkedRadio?.value || '';

        const data = {
            judul: document.getElementById('judul').value,
            kategori: kategori,
            custom_kategori: customCat,
            deskripsi: document.getElementById('deskripsi').value,
            tags: tags.join(','),
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

                if (data.custom_kategori) {
                    document.getElementById('customCatInput').value = data.custom_kategori;
                    document.getElementById('customCatInput').classList.add('show');
                } else if (data.kategori) {
                    const radio = document.querySelector(`input[name="kategori"][value="${data.kategori}"]`);
                    if (radio) radio.checked = true;
                }

                document.getElementById('deskripsi').value = data.deskripsi || '';
                updateCharCounter();

                if (data.tags) {
                    tags = data.tags.split(',').filter(t => t);
                    renderTags();
                }

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
document.getElementById('deskripsi').addEventListener('input', function() {
    updateCharCounter();
    updatePreview();
    updateProgress();
    triggerAutosave();
});
document.querySelectorAll('input[name="kategori"]').forEach(r => {
    r.addEventListener('change', () => {
        document.getElementById('customCatInput').value = '';
        document.getElementById('customCatInput').classList.remove('show');
        updatePreview();
        updateProgress();
        triggerAutosave();
    });
});

// ===== FORM SUBMIT (FIXED - NO DISABLE!) =====
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    const judul = document.getElementById('judul').value.trim();
    const file = fileInput.files[0];
    const kategori = document.querySelector('input[name="kategori"]:checked')?.value || document.getElementById('customCatInput').value.trim();

    if (!judul) {
        e.preventDefault();
        document.getElementById('judul').classList.add('error');
        showToast('Validasi Error', 'Judul dokumen wajib diisi!', 'error');
        return;
    }
    if (!file && !<?= $edit ? 'true' : 'false' ?>) {
        e.preventDefault();
        showToast('Validasi Error', 'File wajib diupload!', 'error');
        return;
    }
    if (!kategori) {
        e.preventDefault();
        showToast('Validasi Error', 'Kategori wajib dipilih!', 'error');
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
    const card = document.querySelector('.preview-card-premium');
    card.style.transform = card.style.transform === 'scale(0.95)' ? 'scale(1)' : 'scale(0.95)';
    card.style.transition = 'transform 0.3s';
}

// ===== SHARE PREVIEW =====
function sharePreview() {
    const judul = document.getElementById('judul').value || 'Dokumen';
    const kategori = document.querySelector('input[name="kategori"]:checked')?.value || document.getElementById('customCatInput').value || 'Lainnya';
    const fileName = fileInput.files[0]?.name || '<?= $edit ? sanitize($edit["file_name"]) : "file" ?>';
    const fileSize = fileInput.files[0] ? formatFileSize(fileInput.files[0].size) : '<?= $edit ? sanitize($edit["file_size"]) : "-" ?>';

    const text = `📥 ${judul}\n📂 ${kategori}\n📄 ${fileName}\n💾 ${fileSize}\n\nDownload dari FKIP UNIMOF`;

    if (navigator.share) {
        navigator.share({ title: judul, text: text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        showToast('Disalin', 'Info file disalin ke clipboard', 'success');
    }
}

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('uploadForm').dispatchEvent(new Event('submit'));
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.querySelector('.template-section')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        showToast('Template', 'Pilih template di atas', 'info');
    }
    if (e.key === 'Escape') {
        if (confirm('Batalkan perubahan dan kembali?')) {
            window.location.href = 'download.php';
        }
    }
});

// ===== INITIAL =====
updatePreview();
updateCharCounter();
updateProgress();

console.log('%c📥 Upload File FKIP UNIMOF - Super Extreme', 'color: #6366f1; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: Ctrl+S (Simpan), Ctrl+K (Template), ESC (Batal)', 'color: #64748b;');
console.log('%cFitur: Templates, Smart Deskripsi Builder, Tags, Custom Kategori, Live Preview, QR Code', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>