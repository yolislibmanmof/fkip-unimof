<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$duplicate_from = (int)($_GET['duplicate'] ?? 0);
$edit = null;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM riset WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$edit) {
        flash_message('error', '❌ Riset tidak ditemukan.');
        header('Location: riset.php');
        exit;
    }
} elseif ($duplicate_from > 0) {
    $stmt = $pdo->prepare("SELECT * FROM riset WHERE id = ?");
    $stmt->execute([$duplicate_from]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($source) {
        $edit = $source;
        $edit['id'] = null;
        $edit['judul'] = $source['judul'] . ' (Copy)';
        $edit['file_pdf'] = null;
        flash_message('info', '📋 Menduplikasi riset: ' . htmlspecialchars($source['judul']));
    }
}

// Ambil data untuk dropdown
$dosen_list = $pdo->query("SELECT id, nama, nidn FROM dosen WHERE status='Aktif' ORDER BY nama")->fetchAll();
$prodi_list = $pdo->query("SELECT id, nama FROM program_studi WHERE status='Aktif' ORDER BY nama")->fetchAll();

// Cek kolom optional di DB
$has_dana = false;
$has_file = false;
$has_keywords = false;
try { $pdo->query("SELECT dana FROM riset LIMIT 1"); $has_dana = true; } catch (Exception $e) {}
try { $pdo->query("SELECT file_pdf FROM riset LIMIT 1"); $has_file = true; } catch (Exception $e) {}
try { $pdo->query("SELECT keywords FROM riset LIMIT 1"); $has_keywords = true; } catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $judul = trim($_POST['judul'] ?? '');
    $ketua_id = (int)($_POST['ketua_id'] ?? 0);
    $anggota = trim($_POST['anggota'] ?? '');
    $jenis = $_POST['jenis'] ?? 'Publikasi';
    $tahun = (int)($_POST['tahun'] ?? date('Y'));
    $kategori = $_POST['kategori'] ?? 'Lainnya';
    $program_studi_id = (int)($_POST['program_studi_id'] ?? 0);
    $jurnal = trim($_POST['jurnal'] ?? '');
    $doi = trim($_POST['doi'] ?? '');
    $abstrak = trim($_POST['abstrak'] ?? '');
    $status = $_POST['status'] ?? 'Draft';
    $dana = trim($_POST['dana'] ?? '');
    $keywords = trim($_POST['keywords'] ?? '');
    $file_pdf = $edit['file_pdf'] ?? null;

    // Validasi
    if ($judul === '') {
        flash_message('error', '❌ Judul riset wajib diisi.');
        header('Location: riset-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }
    if ($ketua_id <= 0) {
        flash_message('error', '❌ Ketua peneliti wajib dipilih.');
        header('Location: riset-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }
    if ($tahun < 1900 || $tahun > 2100) {
        flash_message('error', '❌ Tahun tidak valid.');
        header('Location: riset-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }

    // Validasi DOI format
    if ($doi !== '') {
        // Remove https://doi.org/ prefix if user pasted full URL
        $doi = preg_replace('#^https?://doi\.org/#i', '', $doi);
        // Validate DOI format (10.XXXX/...)
        if (!preg_match('/^10\.\d{4,9}\/[^\s]+$/', $doi)) {
            flash_message('error', '❌ Format DOI tidak valid. Contoh: 10.1234/example.v1i1');
            header('Location: riset-form.php' . ($id ? "?id=$id" : ''));
            exit;
        }
    }

    // Validasi jenis & kategori
    $valid_jenis = ['Publikasi', 'Hibah', 'Pengabdian', 'Patent', 'Buku'];
    if (!in_array($jenis, $valid_jenis)) $jenis = 'Publikasi';
    $valid_kategori = ['Pendidikan', 'Teknologi Pendidikan', 'Pengabdian', 'Sains', 'Sosial Humaniora', 'Lainnya'];
    if (!in_array($kategori, $valid_kategori)) $kategori = 'Lainnya';

    // Upload PDF (optional)
    if ($has_file && !empty($_FILES['file_pdf']['name'])) {
        $allowed = ['application/pdf'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['file_pdf']['tmp_name']);
        if (!in_array($mime, $allowed)) {
            flash_message('error', '❌ Hanya file PDF yang diizinkan.');
            header('Location: riset-form.php' . ($id ? "?id=$id" : ''));
            exit;
        }
        if ($_FILES['file_pdf']['size'] > 10 * 1024 * 1024) {
            flash_message('error', '❌ Ukuran PDF maksimal 10MB.');
            header('Location: riset-form.php' . ($id ? "?id=$id" : ''));
            exit;
        }
        $new_name = 'riset_' . time() . '_' . bin2hex(random_bytes(8)) . '.pdf';
        $dir = APP_DIR . '/uploads/riset';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (move_uploaded_file($_FILES['file_pdf']['tmp_name'], $dir . '/' . $new_name)) {
            if ($file_pdf && file_exists($dir . '/' . $file_pdf)) unlink($dir . '/' . $file_pdf);
            $file_pdf = $new_name;
        }
    }

    try {
        if ($edit && $id > 0) {
            $sql = "UPDATE riset SET judul=?, ketua_id=?, anggota=?, jenis=?, tahun=?, kategori=?, program_studi_id=?, jurnal=?, doi=?, abstrak=?, status=?";
            $params = [$judul, $ketua_id, $anggota, $jenis, $tahun, $kategori, $program_studi_id, $jurnal, $doi, $abstrak, $status];
            if ($has_dana) { $sql .= ", dana=?"; $params[] = $dana; }
            if ($has_file) { $sql .= ", file_pdf=?"; $params[] = $file_pdf; }
            if ($has_keywords) { $sql .= ", keywords=?"; $params[] = $keywords; }
            $sql .= " WHERE id=?";
            $params[] = $id;
            $pdo->prepare($sql)->execute($params);
            flash_message('success', '✅ Data riset berhasil diperbarui.');
        } else {
            $sql = "INSERT INTO riset (judul, ketua_id, anggota, jenis, tahun, kategori, program_studi_id, jurnal, doi, abstrak, status";
            $values = "VALUES (?,?,?,?,?,?,?,?,?,?,?";
            $params = [$judul, $ketua_id, $anggota, $jenis, $tahun, $kategori, $program_studi_id, $jurnal, $doi, $abstrak, $status];
            if ($has_dana) { $sql .= ", dana"; $values .= ",?"; $params[] = $dana; }
            if ($has_file) { $sql .= ", file_pdf"; $values .= ",?"; $params[] = $file_pdf; }
            if ($has_keywords) { $sql .= ", keywords"; $values .= ",?"; $params[] = $keywords; }
            $sql .= ") " . $values . ")";
            $pdo->prepare($sql)->execute($params);
            $id = (int)$pdo->lastInsertId();
            flash_message('success', '✅ Riset baru berhasil ditambahkan.');
        }
    } catch (PDOException $e) {
        flash_message('error', '❌ Gagal menyimpan: ' . $e->getMessage());
        header('Location: riset-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }
    header('Location: riset.php');
    exit;
}

$csrf = generate_csrf_token();
$active_menu = 'riset';
$page_heading = $edit ? 'Edit Riset' : 'Tambah Riset';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Riset', 'riset.php'], [$page_heading, null]];

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
.progress-bar-wrap { flex: 1; height: 8px; background: var(--bg-tertiary); border-radius: 999px; overflow: hidden; }
.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #ef4444 0%, #f59e0b 50%, #06b6d4 100%);
    transition: width 0.4s ease;
    border-radius: 999px;
}
.progress-stats { display: flex; align-items: center; gap: 0.75rem; font-size: 0.85rem; font-weight: 600; }
.progress-percent { color: #06b6d4; font-weight: 800; font-size: 1.1rem; font-variant-numeric: tabular-nums; }
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
    background: linear-gradient(90deg, #0891b2, #06b6d4, #22d3ee);
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
    background: linear-gradient(135deg, #06b6d4, #0891b2);
    color: white;
    border-color: #06b6d4;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(6,182,212,0.25);
}

.form-section {
    background: var(--bg-secondary);
    padding: 1.5rem;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
    margin-bottom: 1.5rem;
    transition: border-color 0.2s;
}
.form-section:focus-within { border-color: #06b6d4; }
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
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
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
    background: rgba(6,182,212,0.1);
    color: #06b6d4;
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
.form-textarea { resize: vertical; min-height: 120px; line-height: 1.6; }
.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: #06b6d4;
    box-shadow: 0 0 0 4px rgba(6,182,212,0.1);
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

/* ===== DOI INPUT ===== */
.doi-input-wrap { position: relative; }
.doi-prefix {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 0.85rem;
    font-weight: 600;
    pointer-events: none;
}
.doi-input-wrap input { padding-left: 7.5rem; font-family: monospace; }
.doi-status {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.82rem;
    pointer-events: none;
}
.doi-status.valid { color: #10b981; }
.doi-status.invalid { color: #ef4444; }
.doi-status.checking { color: #f59e0b; }

/* ===== CITATION GENERATOR ===== */
.citation-generator {
    margin-top: 0.75rem;
    padding: 0.85rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
}
.citation-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.citation-label {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.citation-style-select {
    padding: 0.3rem 0.65rem;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 0.75rem;
    background: var(--bg-secondary);
    cursor: pointer;
    font-family: inherit;
    color: var(--text-primary);
}
.citation-output {
    padding: 0.75rem;
    background: linear-gradient(135deg, #cffafe, #a5f3fc);
    border: 1px solid #67e8f9;
    border-radius: var(--radius-md);
    font-size: 0.82rem;
    line-height: 1.6;
    color: var(--text-primary);
    font-style: italic;
    min-height: 50px;
    word-break: break-word;
    position: relative;
}
[data-theme="dark"] .citation-output {
    background: linear-gradient(135deg, #164e6322, #0891b233);
    border-color: #06b6d4;
}
.citation-copy-btn {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    padding: 0.25rem 0.65rem;
    background: white;
    border: 1px solid #67e8f9;
    border-radius: 6px;
    font-size: 0.7rem;
    cursor: pointer;
    font-weight: 700;
    color: #0891b2;
    transition: all 0.2s;
    font-family: inherit;
}
[data-theme="dark"] .citation-copy-btn {
    background: #164e63;
    border-color: #06b6d4;
    color: #67e8f9;
}
.citation-copy-btn:hover {
    background: #06b6d4;
    color: white;
    transform: translateY(-1px);
}

/* ===== SMART ABSTRAK BUILDER ===== */
.abstrak-builder {
    margin-top: 0.75rem;
    padding: 0.85rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
}
.abstrak-builder-header {
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
.abstrak-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.abstrak-chip {
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
.abstrak-chip:hover {
    background: #cffafe;
    border-color: #67e8f9;
    color: #0891b2;
    transform: translateY(-1px);
}

/* ===== ANGGOTA TAGS INPUT ===== */
.anggota-tags-container {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    padding: 0.65rem;
    background: var(--bg-primary);
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    min-height: 52px;
    cursor: text;
    transition: all 0.3s;
}
.anggota-tags-container:focus-within {
    border-color: #06b6d4;
    box-shadow: 0 0 0 4px rgba(6,182,212,0.1);
}
.anggota-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.3rem 0.65rem;
    background: linear-gradient(135deg, #cffafe, #a5f3fc);
    color: #0891b2;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 600;
    border: 1px solid #67e8f9;
}
[data-theme="dark"] .anggota-tag {
    background: rgba(6,182,212,0.2);
    color: #67e8f9;
    border-color: #06b6d4;
}
.anggota-tag button {
    background: none;
    border: none;
    cursor: pointer;
    color: inherit;
    font-size: 0.85rem;
    padding: 0;
    margin-left: 0.1rem;
    opacity: 0.6;
    transition: opacity 0.2s;
}
.anggota-tag button:hover { opacity: 1; }
.anggota-tag-input {
    flex: 1;
    min-width: 150px;
    border: none;
    outline: none;
    background: transparent;
    font-family: inherit;
    font-size: 0.9rem;
    color: var(--text-primary);
    padding: 0.15rem 0;
}

/* ===== KEYWORDS TAGS ===== */
.keywords-tags-container {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    padding: 0.65rem;
    background: var(--bg-primary);
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    min-height: 52px;
    cursor: text;
    transition: all 0.3s;
}
.keywords-tags-container:focus-within {
    border-color: #06b6d4;
    box-shadow: 0 0 0 4px rgba(6,182,212,0.1);
}
.keyword-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.3rem 0.65rem;
    background: #e0e7ff;
    color: #4338ca;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    border: 1px solid #c7d2fe;
}
[data-theme="dark"] .keyword-tag {
    background: rgba(67,56,202,0.2);
    color: #c7d2fe;
    border-color: #4338ca;
}
.keyword-tag::before { content: '#'; opacity: 0.6; }
.keyword-tag button {
    background: none;
    border: none;
    cursor: pointer;
    color: inherit;
    font-size: 0.85rem;
    padding: 0;
    margin-left: 0.1rem;
    opacity: 0.6;
    transition: opacity 0.2s;
}
.keyword-tag button:hover { opacity: 1; }
.keywords-tag-input {
    flex: 1;
    min-width: 120px;
    border: none;
    outline: none;
    background: transparent;
    font-family: inherit;
    font-size: 0.85rem;
    color: var(--text-primary);
    padding: 0.15rem 0;
}

/* ===== JENIS SELECTOR (Visual Cards) ===== */
.jenis-selector {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 0.5rem;
    margin-top: 0.5rem;
}
.jenis-option {
    padding: 0.75rem 0.5rem;
    background: var(--bg-primary);
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.78rem;
    font-weight: 700;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}
.jenis-option:hover { border-color: #06b6d4; transform: translateY(-2px); }
.jenis-option.selected {
    background: linear-gradient(135deg, #cffafe, #a5f3fc);
    border-color: #06b6d4;
    color: #0891b2;
}
.jenis-option .jenis-icon { font-size: 1.5rem; line-height: 1; }
.jenis-option input[type="radio"] { display: none; }

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
    border-color: #06b6d4;
    background: rgba(6,182,212,0.03);
}
.upload-zone.dragover { border-style: solid; box-shadow: 0 0 0 4px rgba(6,182,212,0.1); }
.upload-zone-icon { font-size: 3rem; margin-bottom: 0.75rem; animation: float 3s ease-in-out infinite; }
@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
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
@keyframes slideInUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.pdf-preview.show { display: flex; }
.pdf-preview-icon {
    width: 60px; height: 60px;
    border-radius: 10px;
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    flex-shrink: 0;
    border: 1px solid #fca5a5;
}
.pdf-preview-info { flex: 1; min-width: 0; }
.pdf-preview-info h4 {
    font-size: 0.88rem;
    font-weight: 700;
    margin-bottom: 0.15rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.pdf-preview-info small { font-size: 0.75rem; color: var(--text-muted); display: block; }
.pdf-preview-remove {
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
.pdf-preview-remove:hover { background: #dc2626; color: white; transform: rotate(90deg); }

.current-pdf-box {
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

/* ===== SUBMIT BUTTON ===== */
.submit-btn-extreme {
    width: 100%;
    padding: 1.1rem;
    background: linear-gradient(135deg, #06b6d4, #0891b2);
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
    box-shadow: 0 10px 25px rgba(6,182,212,0.35);
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
    border-color: #06b6d4;
    color: #06b6d4;
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
    background: #06b6d4;
    color: white;
    border-color: #06b6d4;
    transform: translateY(-2px);
}

/* Research Paper Preview */
.paper-preview {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    margin-bottom: 1rem;
    position: relative;
}
.paper-preview::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: var(--card-accent, #06b6d4);
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
}

.preview-badges {
    display: flex;
    gap: 0.4rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}
.preview-title {
    font-family: 'Georgia', serif;
    font-size: 1.2rem;
    font-weight: 800;
    margin-bottom: 0.75rem;
    line-height: 1.4;
    letter-spacing: -0.01em;
}
.preview-authors {
    font-size: 0.88rem;
    color: var(--primary);
    font-weight: 600;
    margin-bottom: 0.25rem;
}
.preview-affiliation {
    font-size: 0.78rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
    font-style: italic;
}
.preview-meta {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    margin-bottom: 1rem;
    padding: 0.75rem;
    background: var(--bg-primary);
    border-radius: var(--radius-md);
}
.preview-meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.78rem;
    color: var(--text-secondary);
}
.preview-meta-item .meta-icon {
    width: 22px;
    height: 22px;
    background: var(--bg-secondary);
    border-radius: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
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

.preview-abstrak-label {
    font-size: 0.72rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
    margin-bottom: 0.35rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.preview-abstrak {
    background: var(--bg-primary);
    padding: 0.85rem;
    border-radius: var(--radius-md);
    font-size: 0.82rem;
    line-height: 1.6;
    color: var(--text-secondary);
    min-height: 80px;
    border-left: 3px solid #06b6d4;
    font-style: italic;
    max-height: 200px;
    overflow-y: auto;
}
.preview-abstrak::before {
    content: '"';
    font-size: 1.5rem;
    color: #06b6d4;
    line-height: 0.5;
    display: block;
    margin-bottom: 0.5rem;
    opacity: 0.5;
}
.preview-empty { color: var(--text-muted); font-style: italic; }

/* Keywords preview */
.preview-keywords {
    margin-top: 0.75rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
}
.preview-keyword {
    font-size: 0.7rem;
    padding: 0.2rem 0.55rem;
    background: #e0e7ff;
    color: #4338ca;
    border-radius: 999px;
    font-weight: 600;
    border: 1px solid #c7d2fe;
}
[data-theme="dark"] .preview-keyword {
    background: rgba(67,56,202,0.2);
    color: #c7d2fe;
    border-color: #4338ca;
}
.preview-keyword::before { content: '#'; opacity: 0.6; }

/* Citation styles preview */
.citation-preview-section {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}
.citation-style-label {
    font-size: 0.72rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
    margin-bottom: 0.35rem;
    margin-top: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.citation-style-box {
    background: var(--bg-secondary);
    padding: 0.65rem 0.75rem;
    border-radius: var(--radius-md);
    font-size: 0.75rem;
    line-height: 1.5;
    color: var(--text-secondary);
    font-style: italic;
    border-left: 2px solid #06b6d4;
    word-break: break-word;
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
    .jenis-selector { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 640px) {
    .form-card-extreme, .preview-card-extreme { padding: 1.5rem; }
    .template-buttons { flex-direction: column; }
    .shortcuts-legend { font-size: 0.7rem; }
    .secondary-actions { flex-direction: column; }
    .jenis-selector { grid-template-columns: repeat(2, 1fr); }
}
</style>

<!-- Progress Indicator -->
<div class="progress-indicator" data-aos="fade-down">
    <span style="font-size: 1.25rem;">🔬</span>
    <div class="progress-bar-wrap">
        <div class="progress-bar-fill" id="progressBar" style="width: 0%"></div>
    </div>
    <div class="progress-stats">
        <span class="progress-percent" id="progressPercent">0%</span>
        <span class="progress-label">Kelengkapan Riset</span>
    </div>
</div>

<div class="form-layout-extreme">
    <!-- LEFT: FORM -->
    <div class="form-card-extreme" data-aos="fade-right">
        <div class="form-header-extreme">
            <h2><?= $edit ? '✏️ Edit' : '➕ Tambah' ?> Riset</h2>
            <p><?= $edit ? 'Perbarui detail publikasi atau kegiatan riset.' : 'Isi detail riset atau publikasi ilmiah.' ?></p>
        </div>

        <!-- Quick Templates -->
        <div class="template-section" data-aos="fade-up">
            <div class="template-label">⚡ Template Cepat (Klik untuk auto-fill)</div>
            <div class="template-buttons">
                <button type="button" class="template-btn" onclick="applyTemplate('jurnal_sinta')">📄 Jurnal SINTA</button>
                <button type="button" class="template-btn" onclick="applyTemplate('jurnal_scopus')">🌐 Jurnal Scopus</button>
                <button type="button" class="template-btn" onclick="applyTemplate('hibah_dikti')">💰 Hibah DIKTI</button>
                <button type="button" class="template-btn" onclick="applyTemplate('hibah_internal')">🏫 Hibah Internal</button>
                <button type="button" class="template-btn" onclick="applyTemplate('pengabdian')">🤝 Pengabdian</button>
                <button type="button" class="template-btn" onclick="applyTemplate('prosiding')">📚 Prosiding</button>
                <button type="button" class="template-btn" onclick="applyTemplate('prosiding_inter')">🌍 Prosiding Intl</button>
                <button type="button" class="template-btn" onclick="applyTemplate('buku')">📖 Buku ISBN</button>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="risetForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

            <!-- Section 1: Informasi Utama -->
            <div class="form-section">
                <div class="form-section-title">📄 Informasi Utama</div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">
                        <span class="label-icon">🔬</span>
                        Judul Riset <span class="required">*</span>
                    </label>
                    <input type="text" id="judul" name="judul" class="form-input" required
                           placeholder="Contoh: Implementasi AI dalam Pembelajaran Matematika di Era Digital"
                           value="<?= sanitize($edit['judul'] ?? '') ?>"
                           maxlength="300">
                    <div class="form-hint" style="display: flex; justify-content: space-between;">
                        <span>Judul harus jelas, spesifik, dan mencerminkan isi</span>
                        <span><span id="judulCounter"><?= strlen($edit['judul'] ?? '') ?></span>/300</span>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">👤</span>
                            Ketua Peneliti <span class="required">*</span>
                        </label>
                        <select id="ketua_id" name="ketua_id" class="form-select" required>
                            <option value="">-- Pilih Dosen --</option>
                            <?php foreach ($dosen_list as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= ($edit['ketua_id'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                                <?= sanitize($d['nama']) ?> <?= $d['nidn'] ? '(' . $d['nidn'] . ')' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
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
                                <?= sanitize($p['nama']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Anggota Peneliti (Tags) -->
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">👥</span>
                        Anggota Peneliti
                    </label>
                    <div class="anggota-tags-container" id="anggotaTagsContainer" onclick="document.getElementById('anggotaTagInput').focus()">
                        <input type="text" class="anggota-tag-input" id="anggotaTagInput"
                               placeholder="Ketik nama & tekan Enter (contoh: Dr. Ahmad, M.Pd.)">
                    </div>
                    <input type="hidden" id="anggota" name="anggota" value="<?= sanitize($edit['anggota'] ?? '') ?>">
                    <div class="form-hint">Pisahkan dengan Enter atau koma. Maksimal 10 anggota.</div>
                </div>
            </div>

            <!-- Section 2: Klasifikasi Riset -->
            <div class="form-section">
                <div class="form-section-title">📊 Klasifikasi Riset</div>

                <!-- Jenis Selector -->
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">
                        <span class="label-icon">📋</span>
                        Jenis Riset <span class="required">*</span>
                    </label>
                    <div class="jenis-selector" id="jenisSelector">
                        <?php
                        $jenis_list = [
                            'Publikasi' => '📄',
                            'Hibah' => '💰',
                            'Pengabdian' => '🤝',
                            'Patent' => '📜',
                            'Buku' => '📖'
                        ];
                        foreach ($jenis_list as $j => $icon):
                            $selected = ($edit['jenis'] ?? 'Publikasi') === $j;
                        ?>
                        <label class="jenis-option <?= $selected ? 'selected' : '' ?>" data-value="<?= $j ?>">
                            <input type="radio" name="jenis" value="<?= $j ?>" <?= $selected ? 'checked' : '' ?>>
                            <span class="jenis-icon"><?= $icon ?></span>
                            <span><?= $j ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📚</span>
                            Kategori
                        </label>
                        <select id="kategori" name="kategori" class="form-select">
                            <?php foreach(['Pendidikan', 'Teknologi Pendidikan', 'Pengabdian', 'Sains', 'Sosial Humaniora', 'Lainnya'] as $k): ?>
                            <option value="<?= $k ?>" <?= ($edit['kategori'] ?? '') === $k ? 'selected' : '' ?>><?= $k ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📅</span>
                            Tahun <span class="required">*</span>
                        </label>
                        <input type="number" id="tahun" name="tahun" class="form-input" required
                               min="1900" max="2100"
                               value="<?= $edit['tahun'] ?? date('Y') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📊</span>
                            Status
                        </label>
                        <select id="status" name="status" class="form-select">
                            <option value="Draft" <?= ($edit['status'] ?? 'Draft') === 'Draft' ? 'selected' : '' ?>>📝 Draft</option>
                            <option value="Published" <?= ($edit['status'] ?? '') === 'Published' ? 'selected' : '' ?>>✅ Published</option>
                        </select>
                    </div>
                    <?php if ($has_dana): ?>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">💰</span>
                            Dana / Anggaran
                        </label>
                        <input type="text" id="dana" name="dana" class="form-input"
                               placeholder="Contoh: Rp 50.000.000"
                               value="<?= sanitize($edit['dana'] ?? '') ?>">
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Section 3: Detail Publikasi -->
            <div class="form-section">
                <div class="form-section-title">📖 Detail Publikasi</div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">
                        <span class="label-icon">📰</span>
                        Nama Jurnal / Prosiding / Penerbit
                    </label>
                    <input type="text" id="jurnal" name="jurnal" class="form-input"
                           placeholder="Contoh: Jurnal Pendidikan Matematika (JPM)"
                           value="<?= sanitize($edit['jurnal'] ?? '') ?>">
                    <div class="form-hint">Nama tempat riset dipublikasikan</div>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">🔗</span>
                        DOI (Digital Object Identifier)
                    </label>
                    <div class="doi-input-wrap">
                        <span class="doi-prefix">https://doi.org/</span>
                        <input type="text" id="doi" name="doi" class="form-input"
                               placeholder="10.xxxx/xxxxx"
                               value="<?= sanitize($edit['doi'] ?? '') ?>">
                        <span class="doi-status" id="doiStatus"></span>
                    </div>
                    <div class="form-hint">Contoh: 10.1234/jpm.v1i1.12345 (auto-terhubung ke doi.org)</div>
                </div>
                <?php if ($has_keywords): ?>
                <div class="form-group" style="margin-top: 1rem;">
                    <label class="form-label">
                        <span class="label-icon">🏷️</span>
                        Keywords (Kata Kunci)
                    </label>
                    <div class="keywords-tags-container" id="keywordsTagsContainer" onclick="document.getElementById('keywordTagInput').focus()">
                        <input type="text" class="keywords-tag-input" id="keywordTagInput"
                               placeholder="Ketik keyword & tekan Enter">
                    </div>
                    <input type="hidden" id="keywords" name="keywords" value="<?= sanitize($edit['keywords'] ?? '') ?>">
                    <div class="form-hint">3-6 keyword untuk SEO akademik. Pisahkan dengan Enter atau koma.</div>
                </div>
                <?php endif; ?>

                <!-- Citation Generator -->
                <div class="citation-generator">
                    <div class="citation-header">
                        <span class="citation-label">📜 Citation Generator</span>
                        <select class="citation-style-select" id="citationStyle" onchange="updateCitation()">
                            <option value="apa">APA 7th</option>
                            <option value="ieee">IEEE</option>
                            <option value="chicago">Chicago</option>
                            <option value="harvard">Harvard</option>
                            <option value="mla">MLA</option>
                        </select>
                    </div>
                    <div class="citation-output" id="citationOutput">
                        Citation akan otomatis tergenerate...
                        <button type="button" class="citation-copy-btn" onclick="copyCitation()">📋 Copy</button>
                    </div>
                </div>
            </div>

            <!-- Section 4: Abstrak -->
            <div class="form-section">
                <div class="form-section-title">📝 Abstrak</div>
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">📝</span>
                        Abstrak Lengkap
                    </label>
                    <textarea id="abstrak" name="abstrak" class="form-textarea" rows="6"
                              placeholder="Tuliskan abstrak riset Anda (150-300 kata ideal)..."
                              maxlength="3000"><?= sanitize($edit['abstrak'] ?? '') ?></textarea>
                    <div class="form-hint" style="display: flex; justify-content: space-between;">
                        <span>Abstrak akan tampil di halaman publik & database</span>
                        <span>
                            <span id="abstrakWords">0</span> kata •
                            <span id="abstrakCounter"><?= strlen($edit['abstrak'] ?? '') ?></span>/3000 karakter
                        </span>
                    </div>
                </div>

                <!-- Smart Abstrak Builder -->
                <div class="abstrak-builder">
                    <div class="abstrak-builder-header">💡 Klik untuk menambahkan struktur abstrak:</div>
                    <div class="abstrak-chips">
                        <span class="abstrak-chip" onclick="addAbstrakStructure('background')">📖 Latar Belakang</span>
                        <span class="abstrak-chip" onclick="addAbstrakStructure('objective')">🎯 Tujuan</span>
                        <span class="abstrak-chip" onclick="addAbstrakStructure('method')">🔬 Metode</span>
                        <span class="abstrak-chip" onclick="addAbstrakStructure('result')">📊 Hasil</span>
                        <span class="abstrak-chip" onclick="addAbstrakStructure('conclusion')">✅ Kesimpulan</span>
                        <span class="abstrak-chip" onclick="addAbstrakStructure('implication')">💡 Implikasi</span>
                    </div>
                </div>
            </div>

            <!-- Section 5: File PDF (Optional) -->
            <?php if ($has_file): ?>
            <div class="form-section">
                <div class="form-section-title">📄 File Dokumen</div>
                <div class="upload-zone" id="uploadPdf">
                    <input type="file" id="file_pdf" name="file_pdf" accept="application/pdf">
                    <div class="upload-zone-icon">📤</div>
                    <div class="upload-zone-text">Upload PDF Riset</div>
                    <div class="upload-zone-sub">atau drag & drop file di sini</div>
                    <div class="upload-zone-info">
                        <span>📄 PDF only</span>
                        <span>📦 Max 10MB</span>
                        <span>🔒 Disimpan aman</span>
                    </div>
                </div>
                <div class="pdf-preview" id="pdfPreview">
                    <div class="pdf-preview-icon">📄</div>
                    <div class="pdf-preview-info">
                        <h4 id="pdfName">-</h4>
                        <small id="pdfSize">-</small>
                    </div>
                    <button type="button" class="pdf-preview-remove" onclick="removePdf()" title="Hapus PDF">✕</button>
                </div>
                <?php if (!empty($edit['file_pdf'])): ?>
                <div class="current-pdf-box">
                    <span style="font-size: 2rem;">📄</span>
                    <div style="flex: 1; min-width: 0;">
                        <strong>File saat ini:</strong>
                        <div style="font-size: 0.78rem; color: var(--text-muted); font-family: monospace; word-break: break-all; margin-top: 0.15rem;">
                            <?= sanitize($edit['file_pdf']) ?>
                        </div>
                        <small style="color: var(--text-muted);">Upload PDF baru untuk mengganti</small>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Submit Button -->
            <button type="submit" class="submit-btn-extreme" id="submitBtn">
                <span class="btn-text"><?= $edit ? '💾 Perbarui Data' : '✨ Simpan Riset' ?></span>
                <span class="spinner"></span>
            </button>

            <!-- Secondary Actions -->
            <div class="secondary-actions">
                <?php if ($edit && $id > 0): ?>
                <a href="?duplicate=<?= $id ?>" class="secondary-btn" onclick="return confirm('Duplikasi data ini?')">
                    📋 Duplikasi
                </a>
                <?php endif; ?>
                <a href="riset.php" class="secondary-btn">← Kembali</a>
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
                <p style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem;">Tampilan paper akademik</p>
            </div>
            <div class="preview-actions">
                <button class="preview-action-btn" onclick="togglePreviewMode()" title="Toggle mode">🔄</button>
                <button class="preview-action-btn" onclick="sharePreview()" title="Share">🔗</button>
            </div>
        </div>

        <!-- Paper Card Preview -->
        <div class="paper-preview" id="previewCard">
            <div class="preview-badges" id="previewBadges">
                <span class="badge-extreme badge-publikasi">📄 PUBLIKASI</span>
                <span class="badge-extreme badge-pendidikan">🎓 PENDIDIKAN</span>
                <span class="badge-extreme badge-draft">📝 DRAFT</span>
            </div>
            <h2 class="preview-title" id="previewTitle">Judul riset akan muncul di sini...</h2>
            <div class="preview-authors" id="previewAuthors">👤 Author names will appear here...</div>
            <div class="preview-affiliation" id="previewAffiliation">Program Studi, FKIP UNIMOF</div>

            <div class="preview-meta">
                <div class="preview-meta-item">
                    <span class="meta-icon">📅</span>
                    <strong id="previewYear">-</strong>
                </div>
                <div class="preview-meta-item" id="previewJurnalItem" style="display: none;">
                    <span class="meta-icon">📖</span>
                    <strong id="previewJurnal">-</strong>
                </div>
                <div class="preview-meta-item" id="previewDoiItem" style="display: none;">
                    <span class="meta-icon">🔗</span>
                    <strong id="previewDoi">-</strong>
                </div>
            </div>

            <div class="preview-abstrak-label">📝 Abstrak</div>
            <div class="preview-abstrak" id="previewAbstrak">
                <span class="preview-empty">Abstrak akan muncul di sini...</span>
            </div>

            <div class="preview-keywords" id="previewKeywords"></div>
        </div>

        <!-- Citation Preview -->
        <div class="citation-preview-section">
            <div class="citation-style-label">📜 APA 7th</div>
            <div class="citation-style-box" id="previewCitationApa">Citation akan muncul di sini...</div>

            <div class="citation-style-label">📜 IEEE</div>
            <div class="citation-style-box" id="previewCitationIeee">Citation akan muncul di sini...</div>
        </div>

        <!-- QR Code -->
        <div class="share-preview">
            <div class="share-preview-label">🔗 QR Riset</div>
            <div class="share-preview-qr">
                <img id="qrCode" src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=FKIP-UNIMOF-Riset" alt="QR">
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
const jenisConfig = {
    'Publikasi': { icon: '📄', class: 'badge-publikasi', color: '#1e40af' },
    'Hibah': { icon: '💰', class: 'badge-hibah', color: '#92400e' },
    'Pengabdian': { icon: '🤝', class: 'badge-pengabdian', color: '#166534' },
    'Patent': { icon: '📜', class: 'badge-info', color: '#4338ca' },
    'Buku': { icon: '📖', class: 'badge-info', color: '#0891b2' }
};

const katConfig = {
    'Pendidikan': { class: 'badge-pendidikan', color: '#3730a3' },
    'Teknologi Pendidikan': { class: 'badge-teknologi-pendidikan', color: '#7e22ce' },
    'Pengabdian': { class: 'badge-pengabdian', color: '#166534' },
    'Sains': { class: 'badge-info', color: '#1e40af' },
    'Sosial Humaniora': { class: 'badge-info', color: '#be185d' },
    'Lainnya': { class: 'badge-lainnya', color: '#4b5563' }
};

// ===== TEMPLATES =====
const templates = {
    jurnal_sinta: {
        judul: 'Pengaruh Metode Pembelajaran X terhadap Hasil Belajar Siswa pada Mata Pelajaran Y',
        jenis: 'Publikasi',
        kategori: 'Pendidikan',
        jurnal: 'Jurnal Pendidikan Dasar Indonesia (SINTA 3)',
        doi: '10.1234/jpdi.v1i1.12345',
        abstrak: 'Penelitian ini bertujuan untuk mengetahui pengaruh metode pembelajaran X terhadap hasil belajar siswa pada mata pelajaran Y. Metode yang digunakan adalah eksperimen kuasi dengan desain pretest-posttest control group design. Sampel penelitian terdiri dari 60 siswa kelas VIII yang dibagi menjadi kelas eksperimen dan kelas kontrol. Instrumen penelitian menggunakan tes hasil belajar yang telah diuji validitas dan reliabilitasnya. Data dianalisis menggunakan uji-t dan uji gain score. Hasil penelitian menunjukkan bahwa terdapat pengaruh signifikan metode pembelajaran X terhadap hasil belajar siswa (p < 0.05). Peningkatan hasil belajar kelas eksperimen lebih tinggi dibandingkan kelas kontrol. Temuan ini mengindikasikan bahwa metode pembelajaran X efektif untuk meningkatkan pemahaman siswa pada mata pelajaran Y.',
        keywords: 'metode pembelajaran, hasil belajar, eksperimen, pendidikan dasar',
        status: 'Published'
    },
    jurnal_scopus: {
        judul: 'The Impact of Digital Technology Integration on Student Engagement in Mathematics Learning',
        jenis: 'Publikasi',
        kategori: 'Teknologi Pendidikan',
        jurnal: 'International Journal of Educational Technology (Scopus Q2)',
        doi: '10.1016/j.ijedutech.2026.100234',
        abstrak: 'This study investigates the impact of digital technology integration on student engagement in mathematics learning. A mixed-methods approach was employed, combining quantitative survey data from 250 secondary school students with qualitative interviews from 15 teachers. The Technology Acceptance Model (TAM) served as the theoretical framework. Quantitative data were analyzed using structural equation modeling (SEM), while qualitative data underwent thematic analysis. Results indicate that digital technology significantly enhances student engagement (β = 0.67, p < 0.001), with perceived usefulness being the strongest predictor. Teacher professional development emerged as a critical moderating factor. These findings contribute to the growing body of literature on educational technology and provide practical implications for mathematics educators.',
        keywords: 'digital technology, student engagement, mathematics education, TAM, mixed-methods',
        status: 'Published'
    },
    hibah_dikti: {
        judul: 'Pengembangan Model Pembelajaran Berbasis Kearifan Lokal untuk Meningkatkan Literasi Digital Siswa NTT',
        jenis: 'Hibah',
        kategori: 'Pendidikan',
        jurnal: 'Hibah Penelitian Dasar DIKTI 2026',
        abstrak: 'Penelitian ini mengembangkan model pembelajaran berbasis kearifan lokal untuk meningkatkan literasi digital siswa di Nusa Tenggara Timur. Menggunakan pendekatan Research and Development (R&D) dengan model 4D (Define, Design, Develop, Disseminate), penelitian ini menghasilkan produk berupa modul pembelajaran terintegrasi. Subjek penelitian melibatkan 5 sekolah menengah di NTT dengan total 150 siswa. Instrumen pengumpulan data meliputi observasi, wawancara, angket, dan tes literasi digital. Data dianalisis menggunakan teknik kualitatif dan kuantitatif. Hasil penelitian menunjukkan bahwa model yang dikembangkan valid, praktis, dan efektif meningkatkan literasi digital siswa sebesar 35%. Model ini dapat diadopsi oleh sekolah-sekolah di daerah dengan konteks budaya serupa.',
        keywords: 'kearifan lokal, literasi digital, R&D, pembelajaran, NTT',
        status: 'Published',
        dana: 'Rp 75.000.000'
    },
    hibah_internal: {
        judul: 'Analisis Kebutuhan Pelatihan Guru dalam Implementasi Kurikulum Merdeka di Sekolah Mitra',
        jenis: 'Hibah',
        kategori: 'Pendidikan',
        jurnal: 'Hibah Internal UNIMOF 2026',
        abstrak: 'Penelitian ini menganalisis kebutuhan pelatihan guru dalam implementasi Kurikulum Merdeka di sekolah mitra FKIP UNIMOF. Menggunakan metode deskriptif kualitatif dengan pendekatan survei, penelitian melibatkan 30 guru dari 5 sekolah mitra. Instrumen pengumpulan data meliputi kuesioner kebutuhan pelatihan, wawancara mendalam, dan Focus Group Discussion (FGD). Data dianalisis menggunakan teknik analisis isi dan statistik deskriptif. Hasil penelitian mengidentifikasi lima bidang kebutuhan utama: (1) asesmen diagnostik, (2) pembelajaran berdiferensiasi, (3) projek penguatan profil pelajar Pancasila, (4) pemanfaatan teknologi, dan (5) penyusunan modul ajar. Temuan ini menjadi dasar pengembangan program pelatihan guru oleh FKIP UNIMOF.',
        keywords: 'Kurikulum Merdeka, pelatihan guru, kebutuhan pelatihan, asesmen',
        status: 'Published',
        dana: 'Rp 25.000.000'
    },
    pengabdian: {
        judul: 'Pelatihan Pembuatan Media Pembelajaran Interaktif bagi Guru SD di Kabupaten Sikka',
        jenis: 'Pengabdian',
        kategori: 'Pengabdian',
        jurnal: 'Program Pengabdian kepada Masyarakat FKIP UNIMOF',
        abstrak: 'Kegiatan pengabdian ini bertujuan untuk melatih guru-guru SD di Kabupaten Sikka dalam pembuatan media pembelajaran interaktif menggunakan Canva dan PowerPoint. Metode pelaksanaan meliputi: (1) sosialisasi program, (2) pelatihan teori dan praktik, (3) pendampingan, dan (4) evaluasi. Kegiatan diikuti oleh 25 guru dari 10 SD di Kecamatan Kewapante. Hasil kegiatan menunjukkan bahwa 92% peserta mampu membuat media pembelajaran interaktif secara mandiri. Produk yang dihasilkan berupa 25 media pembelajaran untuk berbagai mata pelajaran. Evaluasi menunjukkan peningkatan kompetensi guru dalam pemanfaatan teknologi untuk pembelajaran. Kegiatan ini diharapkan dapat meningkatkan kualitas pembelajaran di SD mitra.',
        keywords: 'pengabdian masyarakat, media pembelajaran, Canva, PowerPoint, guru SD',
        status: 'Published'
    },
    prosiding: {
        judul: 'Strategi Peningkatan Motivasi Belajar Mahasiswa melalui Gamifikasi dalam Pembelajaran Online',
        jenis: 'Publikasi',
        kategori: 'Teknologi Pendidikan',
        jurnal: 'Prosiding Seminar Nasional Pendidikan 2026',
        abstrak: 'Makalah ini membahas strategi peningkatan motivasi belajar mahasiswa melalui gamifikasi dalam pembelajaran online. Menggunakan studi literatur dan analisis studi kasus dari 3 perguruan tinggi, penelitian ini mengidentifikasi elemen gamifikasi yang efektif: (1) poin dan badge, (2) leaderboard, (3) level progression, dan (4) reward system. Hasil analisis menunjukkan bahwa gamifikasi dapat meningkatkan motivasi belajar mahasiswa hingga 40%, terutama pada aspek engagement dan persistence. Namun, implementasi gamifikasi harus mempertimbangkan karakteristik mahasiswa dan konteks pembelajaran. Makalah ini merekomendasikan framework gamifikasi adaptif yang dapat disesuaikan dengan berbagai mata kuliah.',
        keywords: 'gamifikasi, motivasi belajar, pembelajaran online, engagement',
        status: 'Published'
    },
    prosiding_inter: {
        judul: 'Culturally Responsive Teaching in Multicultural Classrooms: A Case Study from Eastern Indonesia',
        jenis: 'Publikasi',
        kategori: 'Pendidikan',
        jurnal: 'Proceedings of International Conference on Education 2026',
        doi: '10.2991/ice-26.2026.145',
        abstrak: 'This paper explores culturally responsive teaching practices in multicultural classrooms in Eastern Indonesia. Using a qualitative case study approach, data were collected from 8 classrooms across 4 schools in Flores through observations, teacher interviews, and student focus groups. Thematic analysis revealed three key themes: (1) integration of local wisdom into curriculum, (2) multilingual teaching strategies, and (3) inclusive classroom management. Findings demonstrate that culturally responsive teaching significantly improves student participation and academic achievement. The study proposes a framework for implementing culturally responsive pedagogy in diverse educational settings, contributing to global discourse on inclusive education.',
        keywords: 'culturally responsive teaching, multicultural education, Eastern Indonesia, inclusive pedagogy',
        status: 'Published'
    },
    buku: {
        judul: 'Teori dan Praktik Pembelajaran Matematika Berbasis Problem-Based Learning',
        jenis: 'Buku',
        kategori: 'Pendidikan',
        jurnal: 'Penerbit UNIMOF Press (ISBN: 978-602-XXXX-XX-X)',
        abstrak: 'Buku ini membahas teori dan praktik pembelajaran matematika berbasis Problem-Based Learning (PBL) untuk jenjang pendidikan dasar dan menengah. Terdiri dari 10 bab yang mencakup: (1) landasan teori PBL, (2) karakteristik pembelajaran matematika, (3) integrasi PBL dalam matematika, (4) desain masalah matematika autentik, (5) peran guru dalam PBL, (6) asesmen dalam PBL matematika, (7) studi kasus implementasi, (8) tantangan dan solusi, (9) teknologi pendukung PBL, dan (10) tren masa depan. Buku ini dilengkapi dengan contoh RPP, LKPD, dan instrumen asesmen yang siap digunakan oleh guru matematika. Ditujukan untuk guru, mahasiswa pendidikan matematika, dan peneliti pendidikan.',
        keywords: 'problem-based learning, matematika, pembelajaran, PBL, RPP',
        status: 'Published'
    }
};

function applyTemplate(key) {
    const t = templates[key];
    if (!t) return;

    document.getElementById('judul').value = t.judul;
    document.getElementById('judulCounter').textContent = t.judul.length;
    document.getElementById('jurnal').value = t.jurnal || '';
    document.getElementById('doi').value = t.doi || '';
    document.getElementById('abstrak').value = t.abstrak || '';
    document.getElementById('kategori').value = t.kategori;
    document.getElementById('status').value = t.status || 'Published';
    document.getElementById('tahun').value = new Date().getFullYear();

    // Set jenis
    document.querySelectorAll('#jenisSelector .jenis-option').forEach(el => {
        el.classList.toggle('selected', el.dataset.value === t.jenis);
        el.querySelector('input').checked = (el.dataset.value === t.jenis);
    });

    // Set keywords
    <?php if ($has_keywords): ?>
    if (t.keywords) {
        keywords = t.keywords.split(',').map(k => k.trim()).filter(k => k);
        renderKeywords();
    }
    <?php endif; ?>

    // Set dana if exists
    <?php if ($has_dana): ?>
    if (document.getElementById('dana')) document.getElementById('dana').value = t.dana || '';
    <?php endif; ?>

    updateAbstrakCount();
    updateCitation();
    updatePreview();
    updateProgress();
    triggerAutosave();

    showToast('Template Diterapkan', `Template "${key.replace(/_/g, ' ')}" berhasil diisi`, 'success');
}

// ===== ANGGOTA TAGS =====
const anggotaTagInput = document.getElementById('anggotaTagInput');
const anggotaTagsContainer = document.getElementById('anggotaTagsContainer');
const anggotaHidden = document.getElementById('anggota');
let anggotaTags = [];

<?php if ($edit && !empty($edit['anggota'])): ?>
const initialAnggota = <?= json_encode($edit['anggota']) ?>;
if (initialAnggota) {
    anggotaTags = initialAnggota.split(',').map(t => t.trim()).filter(t => t);
    renderAnggota();
}
<?php endif; ?>

function renderAnggota() {
    // Clear existing tags (but keep input)
    anggotaTagsContainer.querySelectorAll('.anggota-tag').forEach(el => el.remove());
    anggotaTags.forEach((tag, i) => {
        const pill = document.createElement('span');
        pill.className = 'anggota-tag';
        pill.innerHTML = `${escapeHtml(tag)} <button type="button" onclick="removeAnggota(${i})">✕</button>`;
        anggotaTagsContainer.insertBefore(pill, anggotaTagInput);
    });
    anggotaHidden.value = anggotaTags.join(', ');
    updatePreview();
    updateProgress();
}

function addAnggota(val) {
    val = val.trim();
    if (val && !anggotaTags.includes(val) && anggotaTags.length < 10) {
        anggotaTags.push(val);
        renderAnggota();
        triggerAutosave();
    }
}

window.removeAnggota = function(i) {
    anggotaTags.splice(i, 1);
    renderAnggota();
    triggerAutosave();
};

anggotaTagInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        addAnggota(this.value.replace(/,/g, ''));
        this.value = '';
    } else if (e.key === 'Backspace' && !this.value && anggotaTags.length > 0) {
        anggotaTags.pop();
        renderAnggota();
    }
});
anggotaTagInput.addEventListener('blur', function() {
    if (this.value.trim()) {
        addAnggota(this.value);
        this.value = '';
    }
});

// ===== KEYWORDS TAGS =====
<?php if ($has_keywords): ?>
const keywordTagInput = document.getElementById('keywordTagInput');
const keywordsTagsContainer = document.getElementById('keywordsTagsContainer');
const keywordsHidden = document.getElementById('keywords');
let keywords = [];

<?php if ($edit && !empty($edit['keywords'])): ?>
const initialKeywords = <?= json_encode($edit['keywords']) ?>;
if (initialKeywords) {
    keywords = initialKeywords.split(',').map(k => k.trim()).filter(k => k);
    renderKeywords();
}
<?php endif; ?>

function renderKeywords() {
    keywordsTagsContainer.querySelectorAll('.keyword-tag').forEach(el => el.remove());
    keywords.forEach((tag, i) => {
        const pill = document.createElement('span');
        pill.className = 'keyword-tag';
        pill.innerHTML = `${escapeHtml(tag)} <button type="button" onclick="removeKeyword(${i})">✕</button>`;
        keywordsTagsContainer.insertBefore(pill, keywordTagInput);
    });
    keywordsHidden.value = keywords.join(', ');
    updatePreview();
    updateProgress();
}

function addKeyword(val) {
    val = val.trim().toLowerCase();
    if (val && !keywords.includes(val) && keywords.length < 10) {
        keywords.push(val);
        renderKeywords();
        triggerAutosave();
    }
}

window.removeKeyword = function(i) {
    keywords.splice(i, 1);
    renderKeywords();
    triggerAutosave();
};

keywordTagInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        addKeyword(this.value.replace(/,/g, ''));
        this.value = '';
    } else if (e.key === 'Backspace' && !this.value && keywords.length > 0) {
        keywords.pop();
        renderKeywords();
    }
});
keywordTagInput.addEventListener('blur', function() {
    if (this.value.trim()) {
        addKeyword(this.value);
        this.value = '';
    }
});
<?php endif; ?>

// ===== JENIS SELECTOR =====
document.querySelectorAll('#jenisSelector .jenis-option').forEach(opt => {
    opt.addEventListener('click', () => {
        document.querySelectorAll('#jenisSelector .jenis-option').forEach(o => o.classList.remove('selected'));
        opt.classList.add('selected');
        opt.querySelector('input').checked = true;
        updateCitation();
        updatePreview();
        triggerAutosave();
        updateProgress();
    });
});

// ===== DOI VALIDATOR =====
document.getElementById('doi').addEventListener('input', function() {
    const doiStatus = document.getElementById('doiStatus');
    const val = this.value.trim();
    
    if (!val) {
        doiStatus.textContent = '';
        doiStatus.className = 'doi-status';
    } else {
        // Remove prefix if user pasted full URL
        let clean = val.replace(/^https?:\/\/doi\.org\//i, '');
        if (clean !== val) this.value = clean;
        
        if (/^10\.\d{4,9}\/[^\s]+$/.test(clean)) {
            doiStatus.textContent = '✅';
            doiStatus.className = 'doi-status valid';
        } else {
            doiStatus.textContent = '⚠️';
            doiStatus.className = 'doi-status invalid';
        }
    }
    updateCitation();
    updatePreview();
    triggerAutosave();
});

// ===== CITATION GENERATOR =====
function updateCitation() {
    const ketuaSelect = document.getElementById('ketua_id');
    const ketuaName = ketuaSelect.options[ketuaSelect.selectedIndex]?.text.split(' (')[0] || 'Author';
    const anggotaStr = anggotaTags.length > 0 ? anggotaTags.join(', ') : '';
    const judul = document.getElementById('judul').value || 'Untitled';
    const tahun = document.getElementById('tahun').value || new Date().getFullYear();
    const jurnal = document.getElementById('jurnal').value;
    const doi = document.getElementById('doi').value;
    const style = document.getElementById('citationStyle').value;

    // Build authors list
    let authors = ketuaName;
    if (anggotaStr) authors += ', ' + anggotaStr;

    let citation = '';
    if (style === 'apa') {
        citation = `${authors} (${tahun}). ${judul}.${jurnal ? ' <em>' + jurnal + '</em>.' : ''}${doi ? ' https://doi.org/' + doi : ''}`;
    } else if (style === 'ieee') {
        const initials = ketuaName.split(' ').map(n => n[0]).join('.');
        citation = `${initials}. ${ketuaName.split(' ').slice(1).join(' ')}, "${judul},"${jurnal ? ' <em>' + jurnal + '</em>,' : ''} ${tahun}.${doi ? ' doi: ' + doi + '.' : ''}`;
    } else if (style === 'chicago') {
        citation = `${authors}. "${judul}."${jurnal ? ' <em>' + jurnal + '</em>' : ''} (${tahun}).${doi ? ' https://doi.org/' + doi + '.' : ''}`;
    } else if (style === 'harvard') {
        citation = `${authors} (${tahun}) '${judul}',${jurnal ? ' <em>' + jurnal + '</em>.' : ''}${doi ? ' doi: ' + doi + '.' : ''}`;
    } else if (style === 'mla') {
        citation = `${authors}. "${judul}."${jurnal ? ' <em>' + jurnal + '</em>,' : ''} ${tahun}.${doi ? ' doi: ' + doi + '.' : ''}`;
    }

    document.getElementById('citationOutput').innerHTML = citation + '<button type="button" class="citation-copy-btn" onclick="copyCitation()">📋 Copy</button>';
    
    // Update preview citations (APA & IEEE)
    const apaCitation = `${authors} (${tahun}). ${judul}.${jurnal ? ' <em>' + jurnal + '</em>.' : ''}${doi ? ' https://doi.org/' + doi : ''}`;
    const initials = ketuaName.split(' ').map(n => n[0]).join('.');
    const ieeeCitation = `${initials}. ${ketuaName.split(' ').slice(1).join(' ')}, "${judul},"${jurnal ? ' <em>' + jurnal + '</em>,' : ''} ${tahun}.${doi ? ' doi: ' + doi + '.' : ''}`;
    
    document.getElementById('previewCitationApa').innerHTML = apaCitation;
    document.getElementById('previewCitationIeee').innerHTML = ieeeCitation;
}

window.copyCitation = function() {
    const text = document.getElementById('citationOutput').innerText.replace('📋 Copy', '').trim();
    navigator.clipboard.writeText(text).then(() => {
        showToast('Tersalin', 'Citation berhasil disalin ke clipboard', 'success');
    });
};

// ===== SMART ABSTRAK BUILDER =====
const abstrakStructures = {
    background: 'Latar Belakang: [Tuliskan konteks dan urgensi masalah yang diteliti. Mengapa penelitian ini penting dilakukan?]',
    objective: 'Tujuan: [Jelaskan tujuan spesifik penelitian ini. Apa yang ingin dicapai?]',
    method: 'Metode: [Deskripsikan metode penelitian: pendekatan, desain, sampel, instrumen, dan teknik analisis data]',
    result: 'Hasil: [Paparkan temuan utama penelitian secara ringkas dan jelas]',
    conclusion: 'Kesimpulan: [Tuliskan kesimpulan dari hasil penelitian. Apa implikasi teoritis dan praktisnya?]',
    implication: 'Implikasi: [Jelaskan rekomendasi atau implikasi hasil penelitian bagi praktik pendidikan dan penelitian selanjutnya]'
};

function addAbstrakStructure(key) {
    const textarea = document.getElementById('abstrak');
    const current = textarea.value.trim();
    const phrase = abstrakStructures[key];
    textarea.value = current ? current + '\n\n' + phrase : phrase;
    updateAbstrakCount();
    updatePreview();
    triggerAutosave();
    updateProgress();
    textarea.focus();
    showToast('Struktur Ditambahkan', key.charAt(0).toUpperCase() + key.slice(1), 'info');
}

// ===== PDF UPLOAD =====
<?php if ($has_file): ?>
const uploadPdf = document.getElementById('uploadPdf');
const pdfInput = document.getElementById('file_pdf');
const pdfPreview = document.getElementById('pdfPreview');

['dragenter', 'dragover'].forEach(ev => {
    uploadPdf.addEventListener(ev, e => { e.preventDefault(); uploadPdf.classList.add('dragover'); });
});
['dragleave', 'drop'].forEach(ev => {
    uploadPdf.addEventListener(ev, e => { e.preventDefault(); uploadPdf.classList.remove('dragover'); });
});
uploadPdf.addEventListener('drop', e => {
    if (e.dataTransfer.files.length > 0) handlePdf(e.dataTransfer.files[0]);
});
pdfInput.addEventListener('change', e => {
    if (e.target.files.length > 0) handlePdf(e.target.files[0]);
});

function handlePdf(file) {
    if (file.type !== 'application/pdf') {
        showToast('Error', 'Hanya file PDF yang diizinkan!', 'error');
        return;
    }
    if (file.size > 10 * 1024 * 1024) {
        showToast('Error', 'Ukuran file maksimal 10MB!', 'error');
        return;
    }

    const dt = new DataTransfer();
    dt.items.add(file);
    pdfInput.files = dt.files;

    document.getElementById('pdfName').textContent = file.name;
    document.getElementById('pdfSize').textContent = formatFileSize(file.size);

    pdfPreview.classList.add('show');
    uploadPdf.style.display = 'none';
    updateProgress();
    triggerAutosave();
}

function removePdf() {
    pdfInput.value = '';
    pdfPreview.classList.remove('show');
    uploadPdf.style.display = 'block';
    updateProgress();
}
<?php endif; ?>

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// ===== LIVE PREVIEW =====
function updatePreview() {
    const judul = document.getElementById('judul').value || 'Judul riset akan muncul di sini...';
    const ketuaSelect = document.getElementById('ketua_id');
    const ketuaName = ketuaSelect.options[ketuaSelect.selectedIndex]?.text.split(' (')[0] || 'Unknown';
    const tahun = document.getElementById('tahun').value || '-';
    const jenis = document.querySelector('input[name="jenis"]:checked')?.value || 'Publikasi';
    const kategori = document.getElementById('kategori').value || 'Lainnya';
    const jurnal = document.getElementById('jurnal').value;
    const doi = document.getElementById('doi').value;
    const status = document.getElementById('status').value || 'Draft';
    const abstrak = document.getElementById('abstrak').value;
    const prodiSelect = document.getElementById('program_studi_id');
    const prodiText = prodiSelect.options[prodiSelect.selectedIndex]?.text || 'FKIP UNIMOF';

    // Set card accent
    const jenisConf = jenisConfig[jenis];
    const katConf = katConfig[kategori] || katConfig['Lainnya'];
    document.getElementById('previewCard').style.setProperty('--card-accent', jenisConf?.color || '#06b6d4');

    // Badges
    const jenisClass = jenisConf?.class || 'badge-publikasi';
    const katClass = katConf?.class || 'badge-lainnya';
    const statusClass = status === 'Published' ? 'badge-published' : 'badge-draft';
    
    document.getElementById('previewBadges').innerHTML = `
        <span class="badge-extreme ${jenisClass}">${jenisConf?.icon || '📄'} ${jenis.toUpperCase()}</span>
        <span class="badge-extreme ${katClass}">${kategori}</span>
        <span class="badge-extreme ${statusClass}">${status === 'Published' ? '✅' : '📝'} ${status.toUpperCase()}</span>
    `;

    document.getElementById('previewTitle').textContent = judul;

    // Authors
    let authors = ketuaName;
    if (anggotaTags.length > 0) authors += ', ' + anggotaTags.slice(0, 3).join(', ');
    if (anggotaTags.length > 3) authors += ` (+${anggotaTags.length - 3} lainnya)`;
    document.getElementById('previewAuthors').textContent = '👤 ' + authors;
    document.getElementById('previewAffiliation').textContent = (prodiText !== '-- Pilih Prodi --' ? prodiText : 'FKIP UNIMOF') + ', Universitas Nusa Nipa';

    document.getElementById('previewYear').textContent = tahun;

    // Jurnal
    const jurnalItem = document.getElementById('previewJurnalItem');
    if (jurnal) {
        jurnalItem.style.display = 'flex';
        document.getElementById('previewJurnal').textContent = jurnal;
    } else {
        jurnalItem.style.display = 'none';
    }

    // DOI
    const doiItem = document.getElementById('previewDoiItem');
    if (doi) {
        doiItem.style.display = 'flex';
        document.getElementById('previewDoi').innerHTML = `<a href="https://doi.org/${escapeHtml(doi)}" target="_blank" style="color: #0891b2; text-decoration: none;">${escapeHtml(doi)}</a>`;
    } else {
        doiItem.style.display = 'none';
    }

    // Abstrak
    if (abstrak) {
        document.getElementById('previewAbstrak').innerHTML = abstrak.replace(/\n/g, '<br>');
        document.getElementById('previewAbstrak').style.color = 'var(--text-secondary)';
    } else {
        document.getElementById('previewAbstrak').innerHTML = '<span class="preview-empty">Abstrak akan muncul di sini...</span>';
    }

    // Keywords
    const kwPreview = document.getElementById('previewKeywords');
    <?php if ($has_keywords): ?>
    if (typeof keywords !== 'undefined' && keywords.length > 0) {
        kwPreview.innerHTML = keywords.map(k => `<span class="preview-keyword">${escapeHtml(k)}</span>`).join('');
        kwPreview.style.display = 'flex';
    } else {
        kwPreview.style.display = 'none';
    }
    <?php else: ?>
    kwPreview.style.display = 'none';
    <?php endif; ?>

    // QR Code
    const qrData = `RISET:${judul}|${ketuaName}|${tahun}`;
    document.getElementById('qrCode').src = `https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=${encodeURIComponent(qrData)}`;
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ===== CHARACTER & WORD COUNTER =====
function updateAbstrakCount() {
    const textarea = document.getElementById('abstrak');
    const counter = document.getElementById('abstrakCounter');
    const wordsEl = document.getElementById('abstrakWords');
    const len = textarea.value.length;
    const words = textarea.value.trim().split(/\s+/).filter(w => w).length;
    counter.textContent = len;
    wordsEl.textContent = words;
}

document.getElementById('judul').addEventListener('input', function() {
    document.getElementById('judulCounter').textContent = this.value.length;
    updateCitation();
    updatePreview();
    updateProgress();
    triggerAutosave();
});

document.getElementById('abstrak').addEventListener('input', function() {
    updateAbstrakCount();
    updatePreview();
    updateProgress();
    triggerAutosave();
});

document.getElementById('jurnal').addEventListener('input', function() {
    updateCitation();
    updatePreview();
    triggerAutosave();
});

// ===== PROGRESS INDICATOR =====
function updateProgress() {
    const fields = [
        { el: 'judul', weight: 20 },
        { el: 'ketua_id', weight: 10 },
        { check: () => anggotaTags.length > 0, weight: 5 },
        { check: () => document.querySelector('input[name="jenis"]:checked'), weight: 10 },
        { el: 'tahun', weight: 5 },
        { el: 'abstrak', weight: 20 },
        { el: 'jurnal', weight: 10 },
        { el: 'doi', weight: 10 },
        <?php if ($has_keywords): ?>
        { check: () => keywords.length > 0, weight: 5 },
        <?php endif; ?>
        <?php if ($has_file): ?>
        { check: () => pdfInput.files[0] || <?= $edit && $edit['file_pdf'] ? 'true' : 'false' ?>, weight: 5 },
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
const storageKey = 'fkip_riset_draft_<?= $id ?: "new" ?>';
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
            ketua_id: document.getElementById('ketua_id').value,
            anggota: anggotaTags.join(','),
            jenis: document.querySelector('input[name="jenis"]:checked')?.value || 'Publikasi',
            tahun: document.getElementById('tahun').value,
            kategori: document.getElementById('kategori').value,
            program_studi_id: document.getElementById('program_studi_id').value,
            jurnal: document.getElementById('jurnal').value,
            doi: document.getElementById('doi').value,
            abstrak: document.getElementById('abstrak').value,
            status: document.getElementById('status').value,
            <?php if ($has_dana): ?>
            dana: document.getElementById('dana')?.value || '',
            <?php endif; ?>
            <?php if ($has_keywords): ?>
            keywords: keywords.join(','),
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
                if (data.ketua_id) document.getElementById('ketua_id').value = data.ketua_id;
                if (data.anggota) {
                    anggotaTags = data.anggota.split(',').filter(t => t);
                    renderAnggota();
                }
                document.getElementById('tahun').value = data.tahun || new Date().getFullYear();
                document.getElementById('kategori').value = data.kategori || 'Pendidikan';
                if (data.program_studi_id) document.getElementById('program_studi_id').value = data.program_studi_id;
                document.getElementById('jurnal').value = data.jurnal || '';
                document.getElementById('doi').value = data.doi || '';
                document.getElementById('abstrak').value = data.abstrak || '';
                document.getElementById('status').value = data.status || 'Draft';
                
                // Set jenis
                document.querySelectorAll('#jenisSelector .jenis-option').forEach(el => {
                    el.classList.toggle('selected', el.dataset.value === (data.jenis || 'Publikasi'));
                    el.querySelector('input').checked = (el.dataset.value === (data.jenis || 'Publikasi'));
                });

                <?php if ($has_dana): ?>
                if (document.getElementById('dana')) document.getElementById('dana').value = data.dana || '';
                <?php endif; ?>
                <?php if ($has_keywords): ?>
                if (data.keywords) {
                    keywords = data.keywords.split(',').filter(k => k);
                    renderKeywords();
                }
                <?php endif; ?>

                updateAbstrakCount();
                updateCitation();
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
['ketua_id', 'tahun', 'kategori', 'program_studi_id', 'status'
<?php if ($has_dana): ?>, 'dana'<?php endif; ?>
].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input', () => { updateCitation(); updatePreview(); triggerAutosave(); updateProgress(); });
    el.addEventListener('change', () => { updateCitation(); updatePreview(); triggerAutosave(); updateProgress(); });
});

// ===== FORM SUBMIT (FIXED - NO DISABLE!) =====
document.getElementById('risetForm').addEventListener('submit', function(e) {
    const judul = document.getElementById('judul').value.trim();
    const ketua = document.getElementById('ketua_id').value;
    const doi = document.getElementById('doi').value.trim();

    if (!judul) {
        e.preventDefault();
        document.getElementById('judul').classList.add('error');
        showToast('Validasi Error', 'Judul riset wajib diisi!', 'error');
        return;
    }

    if (!ketua) {
        e.preventDefault();
        document.getElementById('ketua_id').classList.add('error');
        showToast('Validasi Error', 'Ketua peneliti wajib dipilih!', 'error');
        return;
    }

    if (doi && !/^10\.\d{4,9}\/[^\s]+$/.test(doi)) {
        e.preventDefault();
        document.getElementById('doi').classList.add('error');
        showToast('Validasi Error', 'Format DOI tidak valid!', 'error');
        return;
    }

    const btn = document.getElementById('submitBtn');
    btn.classList.add('loading');
    // ⚠️ JANGAN disable tombol!

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
    const judul = document.getElementById('judul').value || 'Riset';
    const ketuaSelect = document.getElementById('ketua_id');
    const ketua = ketuaSelect.options[ketuaSelect.selectedIndex]?.text.split(' (')[0] || '-';
    const tahun = document.getElementById('tahun').value || '-';
    const doi = document.getElementById('doi').value;

    let text = `🔬 ${judul}\n👤 ${ketua} (${tahun})\n\nRiset FKIP UNIMOF`;
    if (doi) text += `\n🔗 https://doi.org/${doi}`;

    if (navigator.share) {
        navigator.share({ title: judul, text: text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        showToast('Disalin', 'Info riset disalin ke clipboard', 'success');
    }
}

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('risetForm').dispatchEvent(new Event('submit'));
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.querySelector('.template-section')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        showToast('Template', 'Pilih template di atas', 'info');
    }
    if (e.key === 'Escape') {
        if (confirm('Batalkan perubahan dan kembali?')) {
            window.location.href = 'riset.php';
        }
    }
});

// ===== INITIAL =====
updateCitation();
updateAbstrakCount();
updatePreview();
updateProgress();

console.log('%c🔬 Form Riset FKIP UNIMOF - Super Extreme', 'color: #06b6d4; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: Ctrl+S (Simpan), Ctrl+K (Template), ESC (Batal)', 'color: #64748b;');
console.log('%cFitur: 8 Templates, Citation Generator, DOI Validator, Anggota/Keywords Tags, PDF Upload, Live Preview', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>