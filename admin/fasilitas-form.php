<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$duplicate_from = (int)($_GET['duplicate'] ?? 0);
$edit = null;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM fasilitas WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$edit) {
        flash_message('error', '❌ Fasilitas tidak ditemukan.');
        header('Location: fasilitas.php');
        exit;
    }
} elseif ($duplicate_from > 0) {
    $stmt = $pdo->prepare("SELECT * FROM fasilitas WHERE id = ?");
    $stmt->execute([$duplicate_from]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($source) {
        $edit = $source;
        $edit['id'] = null;
        $edit['nama'] = $source['nama'] . ' (Copy)';
        $edit['gambar'] = null;
        flash_message('info', '📋 Menduplikasi fasilitas: ' . htmlspecialchars($source['nama']));
    }
}

// Cek kolom optional di DB
$has_lokasi = false;
$has_tags = false;
$has_jam = false;
try {
    $pdo->query("SELECT lokasi FROM fasilitas LIMIT 1");
    $has_lokasi = true;
} catch (Exception $e) {}
try {
    $pdo->query("SELECT tags FROM fasilitas LIMIT 1");
    $has_tags = true;
} catch (Exception $e) {}
try {
    $pdo->query("SELECT jam_operasional FROM fasilitas LIMIT 1");
    $has_jam = true;
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $nama = trim($_POST['nama'] ?? '');
    $kategori = $_POST['kategori'] ?? 'Lainnya';
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $kapasitas = (int)($_POST['kapasitas'] ?? 0);
    $status = $_POST['status'] ?? 'Aktif';
    $lokasi = trim($_POST['lokasi'] ?? '');
    $gedung = trim($_POST['gedung'] ?? '');
    $lantai = trim($_POST['lantai'] ?? '');
    $jam_operasional = trim($_POST['jam_operasional'] ?? '');
    $kontak = trim($_POST['kontak'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $gambar = $edit['gambar'] ?? null;

    // Validasi
    if ($nama === '') {
        flash_message('error', '❌ Nama fasilitas wajib diisi.');
        header('Location: fasilitas-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }
    if ($kapasitas < 0) $kapasitas = 0;

    // Validasi kategori
    $valid_kategori = ['Laboratorium', 'Ruang Kelas', 'Perpustakaan', 'Fasilitas Umum', 'Lainnya'];
    if (!in_array($kategori, $valid_kategori)) $kategori = 'Lainnya';

    // Upload gambar
    if (!empty($_FILES['gambar']['name'])) {
        if (function_exists('upload_image')) {
            $up = upload_image($_FILES['gambar'], 'fasilitas', 5 * 1024 * 1024);
            if (!$up['ok']) {
                flash_message('error', $up['error']);
                header('Location: fasilitas-form.php' . ($id ? "?id=$id" : ''));
                exit;
            }
            if ($up['name']) {
                if ($gambar && function_exists('delete_upload')) delete_upload($gambar, 'fasilitas');
                $gambar = $up['name'];
            }
        } else {
            // Fallback manual upload
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['gambar']['tmp_name']);
            if (!in_array($mime, $allowed)) {
                flash_message('error', '❌ Hanya file gambar (JPG, PNG, WEBP) yang diizinkan.');
                header('Location: fasilitas-form.php' . ($id ? "?id=$id" : ''));
                exit;
            }
            if ($_FILES['gambar']['size'] > 5 * 1024 * 1024) {
                flash_message('error', '❌ Ukuran foto maksimal 5MB.');
                header('Location: fasilitas-form.php' . ($id ? "?id=$id" : ''));
                exit;
            }
            $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
            $new_name = 'fasilitas_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $dir = APP_DIR . '/uploads/fasilitas';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            if (move_uploaded_file($_FILES['gambar']['tmp_name'], $dir . '/' . $new_name)) {
                if ($gambar && function_exists('delete_upload')) delete_upload($gambar, 'fasilitas');
                $gambar = $new_name;
            }
        }
    }

    try {
        if ($edit && $id > 0) {
            if ($has_lokasi && $has_tags && $has_jam) {
                $pdo->prepare("UPDATE fasilitas SET nama=?, kategori=?, deskripsi=?, gambar=?, kapasitas=?, status=?, lokasi=?, gedung=?, lantai=?, jam_operasional=?, kontak=?, tags=? WHERE id=?")
                    ->execute([$nama, $kategori, $deskripsi, $gambar, $kapasitas, $status, $lokasi, $gedung, $lantai, $jam_operasional, $kontak, $tags, $id]);
            } elseif ($has_lokasi) {
                $pdo->prepare("UPDATE fasilitas SET nama=?, kategori=?, deskripsi=?, gambar=?, kapasitas=?, status=?, lokasi=?, gedung=?, lantai=?, kontak=? WHERE id=?")
                    ->execute([$nama, $kategori, $deskripsi, $gambar, $kapasitas, $status, $lokasi, $gedung, $lantai, $kontak, $id]);
            } else {
                $pdo->prepare("UPDATE fasilitas SET nama=?, kategori=?, deskripsi=?, gambar=?, kapasitas=?, status=? WHERE id=?")
                    ->execute([$nama, $kategori, $deskripsi, $gambar, $kapasitas, $status, $id]);
            }
            flash_message('success', '✅ Data fasilitas berhasil diperbarui.');
        } else {
            if ($has_lokasi && $has_tags && $has_jam) {
                $pdo->prepare("INSERT INTO fasilitas (nama, kategori, deskripsi, gambar, kapasitas, status, lokasi, gedung, lantai, jam_operasional, kontak, tags) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$nama, $kategori, $deskripsi, $gambar, $kapasitas, $status, $lokasi, $gedung, $lantai, $jam_operasional, $kontak, $tags]);
            } elseif ($has_lokasi) {
                $pdo->prepare("INSERT INTO fasilitas (nama, kategori, deskripsi, gambar, kapasitas, status, lokasi, gedung, lantai, kontak) VALUES (?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$nama, $kategori, $deskripsi, $gambar, $kapasitas, $status, $lokasi, $gedung, $lantai, $kontak]);
            } else {
                $pdo->prepare("INSERT INTO fasilitas (nama, kategori, deskripsi, gambar, kapasitas, status) VALUES (?,?,?,?,?,?)")
                    ->execute([$nama, $kategori, $deskripsi, $gambar, $kapasitas, $status]);
            }
            flash_message('success', '✅ Fasilitas baru berhasil ditambahkan.');
        }
    } catch (PDOException $e) {
        flash_message('error', '❌ Gagal menyimpan: ' . $e->getMessage());
        header('Location: fasilitas-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }
    header('Location: fasilitas.php');
    exit;
}

$csrf = generate_csrf_token();
$active_menu = 'fasilitas';
$page_heading = $edit ? 'Edit Fasilitas' : 'Tambah Fasilitas';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Fasilitas', 'fasilitas.php'], [$page_heading, null]];

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
    background: linear-gradient(90deg, #8b5cf6, #6366f1, #4f46e5);
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
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: white;
    border-color: #8b5cf6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(139,92,246,0.25);
}

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
.form-textarea { resize: vertical; min-height: 120px; }
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
.form-hint {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.35rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
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
    border-color: #8b5cf6;
    background: rgba(139,92,246,0.03);
}
.upload-zone.dragover {
    border-style: solid;
    box-shadow: 0 0 0 4px rgba(139,92,246,0.1);
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
    background: #ede9fe;
    border-color: #c4b5fd;
    color: #6d28d9;
    transform: translateY(-1px);
}

/* ===== TAGS INPUT ===== */
.tags-input-wrap {
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    padding: 0.5rem;
    background: var(--bg-primary);
    transition: all 0.2s;
    min-height: 52px;
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
}
.tags-input-wrap:focus-within {
    border-color: #8b5cf6;
    box-shadow: 0 0 0 4px rgba(139,92,246,0.1);
}
.tag-pill {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
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
.submit-btn-extreme {
    width: 100%;
    padding: 1.1rem;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
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
    background: #8b5cf6;
    color: white;
    border-color: #8b5cf6;
    transform: translateY(-2px);
}

/* Facility Card Preview */
.facility-card-preview {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-bottom: 1rem;
    position: relative;
}
.facility-card-preview::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: var(--card-accent, #8b5cf6);
    z-index: 2;
}

.preview-image-box {
    width: 100%;
    height: 180px;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 5rem;
    overflow: hidden;
    position: relative;
}
.preview-image-box img { width: 100%; height: 100%; object-fit: cover; }
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
    color: #4f46e5;
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
    border-left: 3px solid #8b5cf6;
    font-style: italic;
}
.preview-desc::before {
    content: '"';
    font-size: 1.5rem;
    color: #8b5cf6;
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

/* Facility Stats */
.preview-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
    margin-top: 1rem;
    padding: 1rem;
    background: linear-gradient(135deg, #ede9fe, #ddd6fe);
    border: 1px solid #c4b5fd;
    border-radius: var(--radius-md);
}
.preview-stat-item {
    text-align: center;
    padding: 0.5rem;
    background: white;
    border-radius: 8px;
    border: 1px solid #c4b5fd;
}
.preview-stat-value {
    font-size: 1.1rem;
    font-weight: 800;
    color: #6d28d9;
    line-height: 1;
    margin-bottom: 0.2rem;
    font-family: 'Georgia', serif;
}
.preview-stat-label {
    font-size: 0.65rem;
    color: #6d28d9;
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
        <span class="progress-label">Kelengkapan Fasilitas</span>
    </div>
</div>

<div class="form-layout-extreme">
    <!-- LEFT: FORM -->
    <div class="form-card-extreme" data-aos="fade-right">
        <div class="form-header-extreme">
            <h2><?= $edit ? '✏️ Edit' : '➕ Tambah' ?> Fasilitas</h2>
            <p><?= $edit ? 'Perbarui detail fasilitas di bawah ini.' : 'Isi detail fasilitas untuk ditampilkan di website publik.' ?></p>
        </div>

        <!-- Quick Templates -->
        <div class="template-section" data-aos="fade-up">
            <div class="template-label">⚡ Template Cepat (Klik untuk auto-fill)</div>
            <div class="template-buttons">
                <button type="button" class="template-btn" onclick="applyTemplate('lab_komputer')">💻 Lab Komputer</button>
                <button type="button" class="template-btn" onclick="applyTemplate('lab_bahasa')">🎧 Lab Bahasa</button>
                <button type="button" class="template-btn" onclick="applyTemplate('lab_micro')">🔬 Lab Micro Teaching</button>
                <button type="button" class="template-btn" onclick="applyTemplate('ruang_kelas')">🏫 Ruang Kelas Standar</button>
                <button type="button" class="template-btn" onclick="applyTemplate('perpustakaan')">📚 Perpustakaan</button>
                <button type="button" class="template-btn" onclick="applyTemplate('aula')">🏛️ Aula Serbaguna</button>
                <button type="button" class="template-btn" onclick="applyTemplate('mushola')">🕌 Mushola</button>
                <button type="button" class="template-btn" onclick="applyTemplate('kantin')">🍽️ Kantin</button>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="fasilitasForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

            <!-- Section 1: Informasi Dasar -->
            <div class="form-section">
                <div class="form-section-title">🏢 Informasi Dasar</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🏢</span>
                            Nama Fasilitas <span class="required">*</span>
                        </label>
                        <input type="text" id="nama" name="nama" class="form-input" required
                               placeholder="Contoh: Laboratorium Komputer 1"
                               value="<?= sanitize($edit['nama'] ?? '') ?>"
                               maxlength="150">
                        <div class="form-hint" style="display: flex; justify-content: space-between;">
                            <span>Nama harus jelas dan unik</span>
                            <span><span id="namaCounter"><?= strlen($edit['nama'] ?? '') ?></span>/150</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📂</span>
                            Kategori <span class="required">*</span>
                        </label>
                        <select id="kategori" name="kategori" class="form-select" required>
                            <?php
                            $cat_icons = ['Laboratorium' => '🧪', 'Ruang Kelas' => '🏫', 'Perpustakaan' => '📚', 'Fasilitas Umum' => '🏢', 'Lainnya' => '📌'];
                            foreach(['Laboratorium', 'Ruang Kelas', 'Perpustakaan', 'Fasilitas Umum', 'Lainnya'] as $k): ?>
                            <option value="<?= $k ?>" <?= ($edit['kategori'] ?? 'Lainnya') === $k ? 'selected' : '' ?>>
                                <?= $cat_icons[$k] ?> <?= $k ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">👥</span>
                            Kapasitas (Orang)
                        </label>
                        <input type="number" id="kapasitas" name="kapasitas" class="form-input" min="0" max="10000"
                               placeholder="Contoh: 30"
                               value="<?= $edit['kapasitas'] ?? 0 ?>">
                        <div class="form-hint" id="kapasitasHint">👥 Kapasitas maksimal orang yang dapat ditampung</div>
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

            <!-- Section 2: Lokasi & Gedung (NEW!) -->
            <?php if ($has_lokasi): ?>
            <div class="form-section">
                <div class="form-section-title">📍 Lokasi & Akses</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📍</span>
                            Lokasi / Alamat
                        </label>
                        <input type="text" id="lokasi" name="lokasi" class="form-input"
                               placeholder="Contoh: Jl. Pendidikan No. 1"
                               value="<?= sanitize($edit['lokasi'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🏛️</span>
                            Gedung
                        </label>
                        <input type="text" id="gedung" name="gedung" class="form-input"
                               placeholder="Contoh: Gedung A"
                               value="<?= sanitize($edit['gedung'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🏢</span>
                            Lantai
                        </label>
                        <input type="text" id="lantai" name="lantai" class="form-input"
                               placeholder="Contoh: Lantai 2"
                               value="<?= sanitize($edit['lantai'] ?? '') ?>">
                    </div>
                    <?php if ($has_jam): ?>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🕐</span>
                            Jam Operasional
                        </label>
                        <input type="text" id="jam_operasional" name="jam_operasional" class="form-input"
                               placeholder="Contoh: Senin-Jumat, 08:00-16:00"
                               value="<?= sanitize($edit['jam_operasional'] ?? '') ?>">
                    </div>
                    <?php else: ?>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📞</span>
                            Kontak
                        </label>
                        <input type="text" id="kontak" name="kontak" class="form-input"
                               placeholder="Contoh: 081234567890"
                               value="<?= sanitize($edit['kontak'] ?? '') ?>">
                    </div>
                    <?php endif; ?>
                </div>
                <?php if ($has_jam): ?>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📞</span>
                            Kontak
                        </label>
                        <input type="text" id="kontak" name="kontak" class="form-input"
                               placeholder="Contoh: 081234567890 / admin@fkip.ac.id"
                               value="<?= sanitize($edit['kontak'] ?? '') ?>">
                    </div>
                    <div class="form-group"></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Section 3: Upload Foto -->
            <div class="form-section">
                <div class="form-section-title">📷 Foto Fasilitas</div>
                <div class="upload-zone" id="uploadZone">
                    <input type="file" id="gambar" name="gambar" accept="image/*">
                    <div class="upload-zone-icon">📤</div>
                    <div class="upload-zone-text">Drag & drop foto di sini</div>
                    <div class="upload-zone-sub">atau klik untuk memilih file</div>
                    <div class="upload-zone-info">
                        <span>🖼️ JPG/PNG/WEBP</span>
                        <span>📦 Max 5MB</span>
                        <span>📐 16:9 ideal</span>
                    </div>
                </div>
                <div class="image-preview" id="imagePreview">
                    <div class="image-preview-img" id="imagePreviewImg"><span style="font-size: 2rem;">🏢</span></div>
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
                <?php if (!empty($edit['gambar'])): ?>
                    <div class="current-image-box">
                        <img src="<?= asset('uploads/fasilitas/' . basename($edit['gambar'])) ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                        <div style="flex: 1; min-width: 0;">
                            <strong>Foto saat ini:</strong>
                            <div style="font-size: 0.78rem; color: var(--text-muted); font-family: monospace; word-break: break-all; margin-top: 0.15rem;">
                                <?= sanitize($edit['gambar']) ?>
                            </div>
                            <small style="color: var(--text-muted);">Upload foto baru untuk mengganti</small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Section 4: Deskripsi -->
            <div class="form-section">
                <div class="form-section-title">📝 Deskripsi Lengkap</div>
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">📝</span>
                        Deskripsi Fasilitas
                    </label>
                    <textarea id="deskripsi" name="deskripsi" class="form-textarea" rows="5"
                              placeholder="Jelaskan fasilitas, peralatan, dan kegunaan..."
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
                        <span class="deskripsi-chip" onclick="addDescPhrase('Fasilitas ini dilengkapi dengan peralatan modern dan terkini untuk mendukung kegiatan akademik.')">+ Peralatan modern</span>
                        <span class="deskripsi-chip" onclick="addDescPhrase('Ruang ber-AC dengan kapasitas yang nyaman untuk kegiatan belajar mengajar.')">+ Ruang ber-AC</span>
                        <span class="deskripsi-chip" onclick="addDescPhrase('Dilengkapi dengan WiFi berkecepatan tinggi dan proyektor multimedia.')">+ WiFi & Proyektor</span>
                        <span class="deskripsi-chip" onclick="addDescPhrase('Terbuka untuk seluruh civitas akademika FKIP UNIMOF pada jam operasional.')">+ Akses civitas</span>
                        <span class="deskripsi-chip" onclick="addDescPhrase('Pemesanan dapat dilakukan melalui bagian administrasi fakultas.')">+ Cara pesan</span>
                        <span class="deskripsi-chip" onclick="addDescPhrase('Fasilitas rutin dilakukan perawatan dan pemeliharaan untuk menjaga kualitas.')">+ Perawatan rutin</span>
                    </div>
                </div>
            </div>

            <!-- Section 5: Tags -->
            <?php if ($has_tags): ?>
            <div class="form-section">
                <div class="form-section-title">🏷️ Tags</div>
                <div class="form-group">
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
            </div>
            <?php endif; ?>

            <!-- Submit Button -->
            <button type="submit" class="submit-btn-extreme" id="submitBtn">
                <span class="btn-text"><?= $edit ? '💾 Perbarui Data' : '✨ Simpan Fasilitas' ?></span>
                <span class="spinner"></span>
            </button>

            <!-- Secondary Actions -->
            <div class="secondary-actions">
                <?php if ($edit && $id > 0): ?>
                <a href="?duplicate=<?= $id ?>" class="secondary-btn" onclick="return confirm('Duplikasi data ini?')">
                    📋 Duplikasi
                </a>
                <?php endif; ?>
                <a href="fasilitas.php" class="secondary-btn">← Kembali</a>
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
                <p style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem;">Tampilan kartu fasilitas</p>
            </div>
            <div class="preview-actions">
                <button class="preview-action-btn" onclick="togglePreviewMode()" title="Toggle mode">🔄</button>
                <button class="preview-action-btn" onclick="sharePreview()" title="Share">🔗</button>
                <?php if ($edit && $id > 0): ?>
                <a href="fasilitas.php" class="preview-action-btn" target="_blank" title="Lihat di list">📋</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Facility Card Preview -->
        <div class="facility-card-preview" id="previewCard">
            <div class="preview-image-box" id="previewImageBox">
                <span id="previewIcon">🏢</span>
                <span class="preview-badge" id="previewBadge">🏢 LAINNYA</span>
                <span class="preview-status-badge aktif" id="previewStatusBadge">✅ Aktif</span>
            </div>
            <div class="preview-body">
                <h2 class="preview-title" id="previewTitle">Nama fasilitas akan muncul di sini...</h2>

                <div class="preview-meta">
                    <div class="preview-meta-item">
                        <span class="meta-icon">👥</span>
                        <strong id="previewKapasitas">0 orang</strong>
                    </div>
                    <div class="preview-meta-item" id="previewLokasiItem" style="display: none;">
                        <span class="meta-icon">📍</span>
                        <strong id="previewLokasi">-</strong>
                    </div>
                    <div class="preview-meta-item" id="previewGedungItem" style="display: none;">
                        <span class="meta-icon">🏛️</span>
                        <strong id="previewGedung">-</strong>
                    </div>
                    <div class="preview-meta-item" id="previewJamItem" style="display: none;">
                        <span class="meta-icon">🕐</span>
                        <strong id="previewJam">-</strong>
                    </div>
                </div>

                <div class="preview-desc" id="previewDesc">
                    <span class="preview-empty">Deskripsi fasilitas akan muncul di sini...</span>
                </div>

                <!-- Tags Preview -->
                <div class="preview-tags" id="previewTags" style="display: none;"></div>

                <!-- Stats Preview -->
                <?php if ($edit): ?>
                <div class="preview-stats" id="previewStats">
                    <div class="preview-stat-item">
                        <div class="preview-stat-value" id="previewDaysOld">0</div>
                        <div class="preview-stat-label">Hari</div>
                    </div>
                    <div class="preview-stat-item">
                        <div class="preview-stat-value" id="previewPopularity">-</div>
                        <div class="preview-stat-label">Kapasitas</div>
                    </div>
                    <div class="preview-stat-item">
                        <div class="preview-stat-value" id="previewCategory">-</div>
                        <div class="preview-stat-label">Level</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- QR Preview -->
        <div class="share-preview">
            <div class="share-preview-label">🔗 QR Fasilitas</div>
            <div class="share-preview-qr">
                <img id="qrCode" src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=FKIP-UNIMOF-Fasilitas" alt="QR">
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
    lab_komputer: {
        nama: 'Laboratorium Komputer',
        kategori: 'Laboratorium',
        kapasitas: 40,
        deskripsi: 'Laboratorium komputer modern yang dilengkapi dengan perangkat terkini untuk mendukung kegiatan praktikum dan penelitian di bidang teknologi informasi dan pendidikan.\n\nDilengkapi dengan:\n• 40 unit PC spesifikasi tinggi\n• Koneksi internet fiber optic\n• AC dan sistem keamanan CCTV\n• Proyektor dan sound system',
        tags: 'lab,komputer,praktikum,IT'
    },
    lab_bahasa: {
        nama: 'Laboratorium Bahasa',
        kategori: 'Laboratorium',
        kapasitas: 30,
        deskripsi: 'Laboratorium bahasa dengan peralatan audio-visual modern untuk pembelajaran bahasa asing dan linguistik terapan.\n\nFasilitas:\n• 30 station dengan headset berkualitas\n• Software pembelajaran bahasa\n• Audio system surround\n• Video conference system',
        tags: 'lab,bahasa,audio,linguistik'
    },
    lab_micro: {
        nama: 'Laboratorium Micro Teaching',
        kategori: 'Laboratorium',
        kapasitas: 25,
        deskripsi: 'Laboratorium micro teaching untuk praktik mengajar bagi calon guru. Dilengkapi dengan sistem recording untuk evaluasi performa mengajar.\n\nFitur:\n• Recording system multi-angle\n• Smart board interaktif\n• Observasi room dengan one-way mirror\n• Playback dan analisis video',
        tags: 'micro teaching,praktik,guru,pendidikan'
    },
    ruang_kelas: {
        nama: 'Ruang Kelas Standar',
        kategori: 'Ruang Kelas',
        kapasitas: 35,
        deskripsi: 'Ruang kelas standar dengan fasilitas lengkap untuk kegiatan belajar mengajar yang nyaman dan kondusif.\n\nFasilitas:\n• AC dan pencahayaan optimal\n• Proyektor dan layar\n• Papan tulis whiteboard\n• Meja-kursi ergonomis\n• WiFi kencang',
        tags: 'kelas,kuliah,belajar,ruang'
    },
    perpustakaan: {
        nama: 'Perpustakaan FKIP',
        kategori: 'Perpustakaan',
        kapasitas: 100,
        deskripsi: 'Perpustakaan fakultas dengan koleksi buku dan jurnal lengkap untuk mendukung kegiatan akademik dan penelitian.\n\nKoleksi:\n• Buku teks dan referensi\n• Jurnal nasional dan internasional\n• E-book dan database online\n• Ruang baca ber-AC\n• Area diskusi kelompok',
        tags: 'perpustakaan,buku,jurnal,referensi'
    },
    aula: {
        nama: 'Aula Serbaguna',
        kategori: 'Fasilitas Umum',
        kapasitas: 500,
        deskripsi: 'Aula serbaguna untuk kegiatan besar seperti wisuda, seminar nasional, dan acara fakultas lainnya.\n\nFasilitas:\n• Kapasitas 500 orang\n• Sound system profesional\n• AC central\n• Stage permanen\n• Lighting system\n• Ruang VIP',
        tags: 'aula,seminar,wisuda,event'
    },
    mushola: {
        nama: 'Mushola FKIP',
        kategori: 'Fasilitas Umum',
        kapasitas: 50,
        deskripsi: 'Mushola yang nyaman untuk ibadah sholat berjamaah bagi civitas akademika FKIP UNIMOF.\n\nFasilitas:\n• Area sholat laki-laki dan perempuan\n• Tempat wudhu terpisah\n• AC dan karpet berkualitas\n• Pengeras suara\n• Rak Al-Quran',
        tags: 'mushola,ibadah,sholat,rohani'
    },
    kantin: {
        nama: 'Kantin FKIP',
        kategori: 'Fasilitas Umum',
        kapasitas: 80,
        deskripsi: 'Kantin fakultas dengan beragam pilihan makanan dan minuman yang bersih, sehat, dan terjangkau.\n\nFasilitas:\n• Area makan ber-AC\n• Berbagai tenant makanan\n• Kebersihan terjamin\n• WiFi gratis\n• Harga mahasiswa',
        tags: 'kantin,makan,minum,istirahat'
    }
};

function applyTemplate(key) {
    const t = templates[key];
    if (!t) return;

    document.getElementById('nama').value = t.nama;
    document.getElementById('namaCounter').textContent = t.nama.length;
    document.getElementById('kategori').value = t.kategori;
    document.getElementById('kapasitas').value = t.kapasitas;
    document.getElementById('deskripsi').value = t.deskripsi;
    updateDeskripsiCount();

    <?php if ($has_tags): ?>
    if (t.tags) {
        tags = t.tags.split(',').map(t => t.trim()).filter(t => t);
        renderTags();
    }
    <?php endif; ?>

    updatePreview();
    updateProgress();
    triggerAutosave();

    showToast('Template Diterapkan', `Template "${key.replace(/_/g, ' ')}" berhasil diisi`, 'success');
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

// ===== DRAG & DROP IMAGE =====
const uploadZone = document.getElementById('uploadZone');
const imageInput = document.getElementById('gambar');
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
imageInput.addEventListener('change', e => {
    if (e.target.files.length > 0) handleImage(e.target.files[0]);
});

function handleImage(file) {
    if (!file.type.startsWith('image/')) {
        showToast('Error', 'Hanya file gambar yang diizinkan!', 'error');
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        showToast('Error', 'Ukuran file maksimal 5MB!', 'error');
        return;
    }

    const dt = new DataTransfer();
    dt.items.add(file);
    imageInput.files = dt.files;

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

        // Update preview card image
        document.getElementById('previewImageBox').innerHTML = `
            <img src="${e.target.result}" alt="">
            <span class="preview-badge" id="previewBadge">🏢 LAINNYA</span>
            <span class="preview-status-badge aktif" id="previewStatusBadge">✅ Aktif</span>
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
    imageInput.value = '';
    imagePreview.classList.remove('show');
    document.getElementById('aspectWarning').classList.remove('show');
    uploadZone.style.display = 'block';

    // Reset preview box
    const icon = getIconForCategory(document.getElementById('kategori').value);
    document.getElementById('previewImageBox').innerHTML = `
        <span id="previewIcon">${icon}</span>
        <span class="preview-badge" id="previewBadge">🏢 LAINNYA</span>
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
function getIconForCategory(kat) {
    const icons = {
        'Laboratorium': '🧪',
        'Ruang Kelas': '🏫',
        'Perpustakaan': '📚',
        'Fasilitas Umum': '🏢',
        'Lainnya': '📌'
    };
    return icons[kat] || '🏢';
}

const catAccentMap = {
    'Laboratorium': '#1e40af',
    'Ruang Kelas': '#92400e',
    'Perpustakaan': '#7e22ce',
    'Fasilitas Umum': '#be185d',
    'Lainnya': '#4b5563'
};

function updatePreview() {
    const nama = document.getElementById('nama').value || 'Nama fasilitas akan muncul di sini...';
    const kategori = document.getElementById('kategori').value || 'Lainnya';
    const kapasitas = document.getElementById('kapasitas').value || 0;
    const deskripsi = document.getElementById('deskripsi').value;
    const status = document.getElementById('status').value || 'Aktif';

    <?php if ($has_lokasi): ?>
    const lokasi = document.getElementById('lokasi')?.value || '';
    const gedung = document.getElementById('gedung')?.value || '';
    <?php endif; ?>
    <?php if ($has_jam): ?>
    const jam = document.getElementById('jam_operasional')?.value || '';
    <?php endif; ?>

    const icon = getIconForCategory(kategori);
    const accent = catAccentMap[kategori] || '#4b5563';

    // Set card accent
    document.getElementById('previewCard').style.setProperty('--card-accent', accent);

    document.getElementById('previewTitle').textContent = nama;

    const badge = document.getElementById('previewBadge');
    if (badge) badge.textContent = `${icon} ${kategori.toUpperCase()}`;

    const iconEl = document.getElementById('previewIcon');
    if (iconEl && !document.getElementById('previewImageBox').querySelector('img')) {
        iconEl.textContent = icon;
    }

    document.getElementById('previewKapasitas').textContent = `${kapasitas} orang`;

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

    <?php if ($has_lokasi): ?>
    // Lokasi
    const lokasiItem = document.getElementById('previewLokasiItem');
    if (lokasi) {
        lokasiItem.style.display = 'flex';
        document.getElementById('previewLokasi').textContent = lokasi;
    } else {
        lokasiItem.style.display = 'none';
    }

    // Gedung + Lantai
    const gedungItem = document.getElementById('previewGedungItem');
    const gedungLantai = [gedung, document.getElementById('lantai')?.value].filter(x => x).join(' • ');
    if (gedungLantai) {
        gedungItem.style.display = 'flex';
        document.getElementById('previewGedung').textContent = gedungLantai;
    } else {
        gedungItem.style.display = 'none';
    }
    <?php endif; ?>

    <?php if ($has_jam): ?>
    const jamItem = document.getElementById('previewJamItem');
    if (jam) {
        jamItem.style.display = 'flex';
        document.getElementById('previewJam').textContent = jam;
    } else {
        jamItem.style.display = 'none';
    }
    <?php endif; ?>

    // Deskripsi
    if (deskripsi) {
        document.getElementById('previewDesc').innerHTML = deskripsi.replace(/\n/g, '<br>');
        document.getElementById('previewDesc').style.color = 'var(--text-secondary)';
    } else {
        document.getElementById('previewDesc').innerHTML = '<span class="preview-empty">Deskripsi fasilitas akan muncul di sini...</span>';
    }

    <?php if ($has_tags): ?>
    // Tags preview
    const tagsContainer = document.getElementById('previewTags');
    if (tags && tags.length > 0) {
        tagsContainer.innerHTML = tags.map(t => `<span class="preview-tag">#${t}</span>`).join('');
        tagsContainer.style.display = 'flex';
    } else {
        tagsContainer.style.display = 'none';
    }
    <?php endif; ?>

    <?php if ($edit): ?>
    // Stats preview
    const daysOld = Math.floor((new Date() - new Date('<?= $edit['created_at'] ?? 'now' ?>')) / (1000 * 60 * 60 * 24));
    document.getElementById('previewDaysOld').textContent = daysOld;
    document.getElementById('previewPopularity').textContent = kapasitas || '-';
    const level = kapasitas >= 200 ? '🏛️ Besar' : (kapasitas >= 50 ? '🏢 Sedang' : '📍 Kecil');
    document.getElementById('previewCategory').textContent = level;
    <?php endif; ?>

    // QR Code
    const qrData = `FASILITAS:${nama}|${kategori}|Kapasitas:${kapasitas}`;
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

<?php if ($has_tags): ?>
// ===== TAGS INPUT =====
const tagInput = document.getElementById('tagInput');
const tagsContainer = document.getElementById('tagsContainer');
const tagsHidden = document.getElementById('tagsHidden');
let tags = [];

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
<?php endif; ?>

// ===== PROGRESS INDICATOR =====
function updateProgress() {
    const fields = [
        { el: 'nama', weight: 25 },
        { el: 'kategori', weight: 15 },
        { el: 'kapasitas', weight: 10, check: () => parseInt(document.getElementById('kapasitas').value) > 0 },
        { el: 'deskripsi', weight: 20 },
        { check: () => imageInput.files[0] || <?= $edit && $edit['gambar'] ? 'true' : 'false' ?>, weight: 20 },
        <?php if ($has_lokasi): ?>
        { el: 'lokasi', weight: 5 },
        { el: 'gedung', weight: 3 },
        <?php endif; ?>
        <?php if ($has_tags): ?>
        { check: () => tags.length > 0, weight: 2 },
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
const storageKey = 'fkip_fasilitas_draft_<?= $id ?: "new" ?>';
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
            kategori: document.getElementById('kategori').value,
            kapasitas: document.getElementById('kapasitas').value,
            deskripsi: document.getElementById('deskripsi').value,
            status: document.getElementById('status').value,
            <?php if ($has_lokasi): ?>
            lokasi: document.getElementById('lokasi')?.value || '',
            gedung: document.getElementById('gedung')?.value || '',
            lantai: document.getElementById('lantai')?.value || '',
            <?php endif; ?>
            <?php if ($has_jam): ?>
            jam_operasional: document.getElementById('jam_operasional')?.value || '',
            <?php endif; ?>
            kontak: document.getElementById('kontak')?.value || '',
            <?php if ($has_tags): ?>
            tags: tags.join(','),
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
                document.getElementById('kategori').value = data.kategori || 'Lainnya';
                document.getElementById('kapasitas').value = data.kapasitas || 0;
                document.getElementById('deskripsi').value = data.deskripsi || '';
                document.getElementById('status').value = data.status || 'Aktif';

                <?php if ($has_lokasi): ?>
                if (document.getElementById('lokasi')) document.getElementById('lokasi').value = data.lokasi || '';
                if (document.getElementById('gedung')) document.getElementById('gedung').value = data.gedung || '';
                if (document.getElementById('lantai')) document.getElementById('lantai').value = data.lantai || '';
                <?php endif; ?>
                <?php if ($has_jam): ?>
                if (document.getElementById('jam_operasional')) document.getElementById('jam_operasional').value = data.jam_operasional || '';
                <?php endif; ?>
                if (document.getElementById('kontak')) document.getElementById('kontak').value = data.kontak || '';

                <?php if ($has_tags): ?>
                if (data.tags) {
                    tags = data.tags.split(',').filter(t => t);
                    renderTags();
                }
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
['nama', 'kategori', 'kapasitas', 'deskripsi', 'status'<?php if ($has_lokasi): ?>, 'lokasi', 'gedung', 'lantai'<?php endif; ?><?php if ($has_jam): ?>, 'jam_operasional'<?php endif; ?>, 'kontak'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input', () => { updatePreview(); triggerAutosave(); updateProgress(); });
    el.addEventListener('change', () => { updatePreview(); triggerAutosave(); updateProgress(); });
});

// Kapasitas visual feedback
document.getElementById('kapasitas').addEventListener('input', function() {
    const hint = document.getElementById('kapasitasHint');
    const val = parseInt(this.value) || 0;
    if (val === 0) {
        hint.textContent = '👥 Kapasitas maksimal orang yang dapat ditampung';
        hint.style.color = 'var(--text-muted)';
    } else if (val < 20) {
        hint.textContent = '📍 Kapasitas kecil (ruang privat)';
        hint.style.color = '#f59e0b';
    } else if (val < 100) {
        hint.textContent = '🏢 Kapasitas sedang (ruang kelas)';
        hint.style.color = '#3b82f6';
    } else {
        hint.textContent = '🏛️ Kapasitas besar (aula/hall)';
        hint.style.color = '#10b981';
    }
});

// ===== FORM SUBMIT (FIXED - NO DISABLE!) =====
document.getElementById('fasilitasForm').addEventListener('submit', function(e) {
    const nama = document.getElementById('nama').value.trim();
    const kategori = document.getElementById('kategori').value;

    if (!nama) {
        e.preventDefault();
        document.getElementById('nama').classList.add('error');
        showToast('Validasi Error', 'Nama Fasilitas wajib diisi!', 'error');
        return;
    }

    if (!kategori) {
        e.preventDefault();
        document.getElementById('kategori').classList.add('error');
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
    const card = document.querySelector('.preview-card-extreme');
    card.style.transform = card.style.transform === 'scale(0.95)' ? 'scale(1)' : 'scale(0.95)';
    card.style.transition = 'transform 0.3s';
}

// ===== SHARE PREVIEW =====
function sharePreview() {
    const nama = document.getElementById('nama').value || 'Fasilitas';
    const kategori = document.getElementById('kategori').value || 'Lainnya';
    const kapasitas = document.getElementById('kapasitas').value || 0;
    <?php if ($has_lokasi): ?>
    const lokasi = document.getElementById('lokasi')?.value || '';
    <?php endif; ?>

    let text = `🏢 ${nama}\n📂 ${kategori}\n👥 Kapasitas: ${kapasitas} orang`;
    <?php if ($has_lokasi): ?>
    if (lokasi) text += `\n📍 ${lokasi}`;
    <?php endif; ?>
    text += `\n\nFasilitas FKIP UNIMOF`;

    if (navigator.share) {
        navigator.share({ title: nama, text: text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        showToast('Disalin', 'Info fasilitas disalin ke clipboard', 'success');
    }
}

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('fasilitasForm').dispatchEvent(new Event('submit'));
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.querySelector('.template-section')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        showToast('Template', 'Pilih template di atas', 'info');
    }
    if (e.key === 'Escape') {
        if (confirm('Batalkan perubahan dan kembali?')) {
            window.location.href = 'fasilitas.php';
        }
    }
});

// ===== INITIAL =====
updatePreview();
updateDeskripsiCount();
updateProgress();

console.log('%c🏢 Form Fasilitas FKIP UNIMOF - Super Extreme', 'color: #8b5cf6; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: Ctrl+S (Simpan), Ctrl+K (Template), ESC (Batal)', 'color: #64748b;');
console.log('%cFitur: 8 Templates, Smart Deskripsi Builder, Tags, Lokasi, QR Code, Live Preview', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>