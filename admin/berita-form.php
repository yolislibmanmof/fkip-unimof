<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$edit = null;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM berita WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch();
    if (!$edit) { header('Location: berita.php'); exit; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('error', 'Token keamanan tidak valid.');
        header('Location: berita-form.php' . ($id ? "?id=$id" : '')); exit;
    }

    $judul    = trim($_POST['judul'] ?? '');
    $kategori = $_POST['kategori'] ?? 'Umum';
    $status   = $_POST['status'] ?? 'Draft';
    $penulis  = trim($_POST['penulis'] ?? 'Humas FKIP');
    $excerpt  = trim($_POST['excerpt'] ?? '');
    $konten   = clean_html($_POST['konten'] ?? '');
    $tags     = trim($_POST['tags'] ?? '');
    $featured = isset($_POST['is_featured']) ? 1 : 0;
    
    $kat_ok = ['Akademik','Pengumuman','Prestasi','Kegiatan','Riset','Umum'];
    $st_ok  = ['Draft','Published','Archived'];
    
    if (!in_array($kategori, $kat_ok, true)) $kategori = 'Umum';
    if (!in_array($status, $st_ok, true))   $status = 'Draft';

    if ($judul === '' || $konten === '') {
        flash_message('error', 'Judul dan konten wajib diisi.');
        header('Location: berita-form.php' . ($id ? "?id=$id" : '')); exit;
    }

    // Custom slug
    $custom_slug = trim($_POST['custom_slug'] ?? '');
    $base = $custom_slug !== '' ? generate_slug($custom_slug) : generate_slug($judul);
    $slug = $base; $n = 2;
    
    while (true) {
        $chk = $pdo->prepare("SELECT id FROM berita WHERE slug = ? AND id != ?");
        $chk->execute([$slug, $id]);
        if (!$chk->fetchColumn()) break;
        $slug = $base . '-' . $n++;
    }

    // Upload gambar
    $gambar = $edit['gambar'] ?? null;
    if (!empty($_FILES['gambar']['name'])) {
        $up = upload_image($_FILES['gambar']);
        if (!$up['ok']) {
            flash_message('error', $up['error']);
            header('Location: berita-form.php' . ($id ? "?id=$id" : '')); exit;
        }
        if ($up['name']) {
            delete_upload($gambar);
            $gambar = $up['name'];
        }
    }

    try {
        if ($edit) {
            $pdo->prepare("UPDATE berita SET judul=?, slug=?, konten=?, excerpt=?, gambar=?, kategori=?, penulis=?, status=?, is_featured=? WHERE id=?")
                ->execute([$judul, $slug, $konten, $excerpt, $gambar, $kategori, $penulis, $status, $featured, $id]);
            flash_message('success', '✅ Berita berhasil diperbarui.');
        } else {
            $pub = $status === 'Published' ? date('Y-m-d H:i:s') : null;
            $pdo->prepare("INSERT INTO berita (judul, slug, konten, excerpt, gambar, kategori, penulis, status, is_featured, published_at) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$judul, $slug, $konten, $excerpt, $gambar, $kategori, $penulis, $status, $featured, $pub]);
            flash_message('success', '✅ Berita berhasil ditambahkan.');
        }
    } catch (Exception $e) {
        flash_message('error', 'Gagal menyimpan: ' . $e->getMessage());
    }
    header('Location: berita.php');
    exit;
}

$csrf = generate_csrf_token();
$active_menu = 'berita';
$page_heading = $edit ? 'Edit Berita' : 'Tambah Berita';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Kelola Berita', 'berita.php'], [$page_heading, null]];
require __DIR__ . '/includes/header.php';
?>

<style>
/* ===== EXTREME MULTIMATE EDITOR STYLES ===== */
.editor-wrap { max-width: 1400px; margin: 0 auto; }

.editor-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border);
    flex-wrap: wrap; gap: 1rem;
}
.editor-header-left { display: flex; align-items: center; gap: 1rem; }
.editor-header-left h2 { margin: 0; font-size: 1.5rem; font-weight: 800; color: var(--text-primary); }
.back-btn {
    color: var(--text-secondary); text-decoration: none; font-size: 0.9rem; font-weight: 600;
    padding: 0.5rem 1rem; background: var(--bg-secondary); border-radius: var(--radius-md);
    border: 1px solid var(--border); transition: all 0.2s; display: flex; align-items: center; gap: 0.5rem;
}
.back-btn:hover { background: var(--bg-tertiary); color: var(--primary); transform: translateX(-2px); }

.editor-header-right { display: flex; align-items: center; gap: 0.75rem; }
.autosave-indicator {
    display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; font-weight: 600;
    color: var(--text-muted); background: var(--bg-secondary); padding: 0.5rem 1rem;
    border-radius: 999px; border: 1px solid var(--border); transition: all 0.3s;
}
.autosave-indicator.saving { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
.autosave-indicator.saved { background: #dcfce7; color: #166534; border-color: #86efac; }

.editor-grid { display: grid; grid-template-columns: 1fr 360px; gap: 2rem; align-items: start; }
.editor-main, .editor-sidebar { display: flex; flex-direction: column; gap: 1.5rem; }

.editor-card {
    background: var(--bg-primary); border-radius: var(--radius-xl); padding: 1.5rem;
    box-shadow: var(--shadow-sm); border: 1px solid var(--border); transition: all 0.3s;
}
.editor-card:hover { box-shadow: var(--shadow-md); }

.card-title {
    font-size: 0.95rem; font-weight: 700; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;
    padding-bottom: 0.75rem; border-bottom: 2px solid var(--bg-tertiary); color: var(--text-primary);
}
.field-label {
    display: flex; justify-content: space-between; align-items: center;
    font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem;
}
.field-group { margin-bottom: 1.25rem; }
.field-group:last-child { margin-bottom: 0; }

.styled-input, .styled-select {
    width: 100%; padding: 0.75rem 1rem; border: 2px solid var(--border); border-radius: var(--radius-md);
    font-size: 0.9rem; font-family: inherit; background: var(--bg-secondary); color: var(--text-primary);
    transition: all 0.2s;
}
.styled-input:focus, .styled-select:focus {
    outline: none; border-color: var(--primary); background: var(--bg-primary);
    box-shadow: 0 0 0 4px rgba(10,104,71,0.1);
}

/* Title Card Premium */
.title-card {
    padding: 2rem !important;
    background: linear-gradient(135deg, var(--bg-primary) 0%, rgba(10,104,71,0.03) 100%);
    border: 2px solid var(--border);
}
.judul-input {
    width: 100%; border: none; font-size: 2rem; font-weight: 800; color: var(--text-primary);
    background: transparent; outline: none; font-family: inherit; padding: 0; margin-bottom: 1rem;
    line-height: 1.2;
}
.judul-input::placeholder { color: var(--text-muted); font-weight: 600; }
.slug-preview {
    display: flex; align-items: center; gap: 0.25rem; font-size: 0.85rem; color: var(--text-muted);
    background: var(--bg-tertiary); padding: 0.5rem 1rem; border-radius: var(--radius-md);
    margin-bottom: 0.75rem; font-family: ui-monospace, monospace; overflow: hidden;
}
.slug-prefix { color: var(--text-muted); flex-shrink: 0; }
.slug-text { color: var(--primary); font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.custom-slug-input {
    width: 100%; padding: 0.6rem 1rem; border: 2px solid var(--primary); border-radius: var(--radius-md);
    font-family: ui-monospace, monospace; font-size: 0.85rem; margin-bottom: 0.75rem; background: var(--bg-primary);
}
.slug-edit-btn {
    background: transparent; border: 1px solid var(--border); padding: 0.4rem 0.85rem; border-radius: var(--radius-md);
    font-size: 0.8rem; cursor: pointer; color: var(--text-secondary); transition: all 0.2s; font-family: inherit; font-weight: 600;
}
.slug-edit-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }

/* Excerpt */
.excerpt-textarea {
    width: 100%; padding: 1rem; border: 2px solid var(--border); border-radius: var(--radius-md);
    font-size: 0.95rem; font-family: inherit; background: var(--bg-secondary); color: var(--text-primary);
    resize: vertical; transition: all 0.2s; line-height: 1.6;
}
.excerpt-textarea:focus {
    outline: none; border-color: var(--primary); background: var(--bg-primary);
    box-shadow: 0 0 0 4px rgba(10,104,71,0.1);
}
.char-count { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; font-variant-numeric: tabular-nums; }
.char-count.warn { color: #f59e0b; }
.char-count.danger { color: #ef4444; }

/* Content & Toolbar Modern */
.content-stats { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; }
.rich-toolbar {
    display: flex; flex-wrap: wrap; gap: 0.35rem; padding: 0.75rem;
    background: var(--bg-secondary); border: 2px solid var(--border); border-bottom: none;
    border-radius: var(--radius-md) var(--radius-md) 0 0; align-items: center;
}
.tb-btn {
    background: var(--bg-primary); border: 1px solid var(--border); padding: 0.4rem 0.75rem;
    border-radius: 6px; font-size: 0.85rem; cursor: pointer; transition: all 0.15s;
    color: var(--text-secondary); font-family: inherit; min-width: 36px; height: 36px;
    display: flex; align-items: center; justify-content: center; gap: 0.25rem; font-weight: 600;
}
.tb-btn:hover { background: var(--primary); color: white; border-color: var(--primary); transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.tb-btn b, .tb-btn i, .tb-btn u { font-size: 0.9rem; }
.tb-divider { width: 1px; height: 24px; background: var(--border); margin: 0 0.25rem; }

.konten-textarea {
    width: 100%; padding: 1.5rem; border: 2px solid var(--border); border-top: 1px solid var(--bg-tertiary);
    border-radius: 0 0 var(--radius-md) var(--radius-md); font-size: 1rem; font-family: ui-monospace, monospace;
    background: var(--bg-primary); color: var(--text-primary); resize: vertical; min-height: 450px;
    line-height: 1.8; transition: border-color 0.2s;
}
.konten-textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }

/* Tags Modern */
.tags-input-wrap {
    border: 2px solid var(--border); border-radius: var(--radius-md); padding: 0.5rem;
    background: var(--bg-secondary); transition: all 0.2s; min-height: 52px;
    display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;
}
.tags-input-wrap:focus-within { border-color: var(--primary); background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.tag-pill {
    background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white;
    padding: 0.35rem 0.75rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600;
    display: inline-flex; align-items: center; gap: 0.4rem; animation: tagPop 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
@keyframes tagPop { from { transform: scale(0); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.tag-pill button {
    background: rgba(255,255,255,0.2); border: none; color: white; width: 18px; height: 18px;
    border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;
    font-size: 0.7rem; transition: all 0.2s; padding: 0;
}
.tag-pill button:hover { background: rgba(255,255,255,0.4); }
.tag-input-field { border: none; outline: none; flex: 1; min-width: 120px; padding: 0.4rem; background: transparent; font-family: inherit; font-size: 0.9rem; color: var(--text-primary); }

/* Status Radios Modern */
.status-radios { display: flex; flex-direction: column; gap: 0.5rem; }
.status-radio {
    display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem;
    background: var(--bg-secondary); border: 2px solid transparent; border-radius: var(--radius-md);
    cursor: pointer; transition: all 0.2s; font-size: 0.9rem; font-weight: 500; color: var(--text-secondary);
}
.status-radio:hover { background: var(--bg-tertiary); }
.status-radio input { display: none; }
.status-radio:has(input:checked).status-draft { background: #fef3c7; border-color: #f59e0b; color: #92400e; }
.status-radio:has(input:checked).status-published { background: #dcfce7; border-color: #10b981; color: #166534; }
.status-radio:has(input:checked).status-archived { background: var(--bg-tertiary); border-color: var(--border); color: var(--text-primary); }
.sr-dot {
    width: 16px; height: 16px; border-radius: 50%; border: 2px solid currentColor; position: relative; flex-shrink: 0;
}
.status-radio:has(input:checked) .sr-dot::after {
    content: ''; position: absolute; inset: 3px; background: currentColor; border-radius: 50%;
}

/* Checkbox Custom */
.checkbox-label {
    display: flex; align-items: center; gap: 0.75rem; cursor: pointer; font-size: 0.9rem;
    color: var(--text-primary); padding: 0.75rem; border-radius: var(--radius-md); transition: background 0.2s; font-weight: 500;
}
.checkbox-label:hover { background: var(--bg-secondary); }
.checkbox-label input { display: none; }
.checkbox-custom {
    width: 20px; height: 20px; border: 2px solid var(--border); border-radius: 6px;
    flex-shrink: 0; position: relative; transition: all 0.2s; background: var(--bg-primary);
}
.checkbox-label input:checked + .checkbox-custom { background: var(--primary); border-color: var(--primary); }
.checkbox-label input:checked + .checkbox-custom::after {
    content: '✓'; position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
    color: white; font-size: 0.85rem; font-weight: 700;
}

/* Meta Info */
.meta-info { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); display: flex; flex-direction: column; gap: 0.5rem; }
.meta-row { display: flex; justify-content: space-between; font-size: 0.85rem; }
.meta-row span { color: var(--text-muted); }
.meta-row strong { color: var(--text-primary); font-weight: 600; }

/* Upload Zone Premium */
.upload-zone {
    position: relative; border: 2px dashed var(--border); border-radius: var(--radius-lg);
    transition: all 0.3s; overflow: hidden; background: var(--bg-secondary);
}
.upload-zone:hover, .upload-zone.dragover { border-color: var(--primary); background: rgba(10,104,71,0.03); }
.upload-file-input { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 2; }
.upload-placeholder { padding: 2.5rem 1.5rem; text-align: center; pointer-events: none; }
.upload-icon { font-size: 3rem; margin-bottom: 0.75rem; animation: uploadFloat 3s ease-in-out infinite; }
@keyframes uploadFloat { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
.upload-text { font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem; font-size: 1rem; }
.upload-sub { font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.5rem; }
.upload-hint { font-size: 0.75rem; color: var(--text-muted); background: var(--bg-tertiary); display: inline-block; padding: 0.25rem 0.75rem; border-radius: 999px; }

.upload-preview { position: relative; }
.upload-preview img { width: 100%; max-height: 250px; object-fit: cover; display: block; border-radius: var(--radius-md) var(--radius-md) 0 0; }
.upload-remove {
    position: absolute; top: 0.75rem; right: 0.75rem; background: rgba(239, 68, 68, 0.9); color: white;
    border: none; padding: 0.5rem 0.85rem; border-radius: var(--radius-md); font-size: 0.8rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s; backdrop-filter: blur(4px); display: flex; align-items: center; gap: 0.4rem;
}
.upload-remove:hover { background: #dc2626; transform: scale(1.05); }

.current-image { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); }
.current-image img { width: 100%; border-radius: var(--radius-md); margin: 0.75rem 0; border: 1px solid var(--border); }
.current-image small { font-size: 0.8rem; color: var(--text-muted); display: block; }

/* Tips Card */
.tips-card { background: linear-gradient(135deg, #fef3c7, #fde68a); border-color: #fcd34d; }
.tips-card .card-title { color: #92400e; border-bottom-color: rgba(146, 64, 14, 0.2); }
.tips-list { list-style: none; font-size: 0.85rem; color: #92400e; line-height: 1.8; margin: 0; padding: 0; }
.tips-list li { padding-left: 0.25rem; display: flex; align-items: flex-start; gap: 0.5rem; }

/* Preview Modal Premium */
.preview-modal-box {
    background: var(--bg-primary); border-radius: var(--radius-xl); max-width: 800px; width: 95%;
    max-height: 90vh; overflow: hidden; animation: zoomIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    box-shadow: 0 30px 80px rgba(0,0,0,0.4); display: flex; flex-direction: column;
}
@keyframes zoomIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.preview-header {
    display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem;
    background: var(--bg-secondary); border-bottom: 1px solid var(--border); flex-shrink: 0;
}
.preview-header span { font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem; }
.preview-close {
    background: var(--bg-tertiary); border: none; color: var(--text-secondary); width: 36px; height: 36px;
    border-radius: 50%; cursor: pointer; font-size: 1.1rem; transition: all 0.2s; display: flex; align-items: center; justify-content: center;
}
.preview-close:hover { background: #fee2e2; color: #dc2626; transform: rotate(90deg); }
.preview-body { max-height: calc(90vh - 70px); overflow-y: auto; padding: 3rem; }
.preview-article { font-family: Georgia, 'Times New Roman', serif; max-width: 680px; margin: 0 auto; }
.preview-badge {
    display: inline-block; padding: 0.35rem 1rem; background: var(--primary); color: white;
    border-radius: 999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 1.5rem;
    font-family: var(--font-primary); letter-spacing: 0.05em;
}
.preview-title { font-size: 2.5rem; line-height: 1.2; margin-bottom: 1.5rem; color: var(--text-primary); font-weight: 800; letter-spacing: -0.02em; }
.preview-meta {
    color: var(--text-muted); font-size: 0.9rem; margin-bottom: 2.5rem; padding-bottom: 1.5rem;
    border-bottom: 2px solid var(--bg-tertiary); font-family: var(--font-primary); display: flex; align-items: center; gap: 0.5rem;
}
.preview-content { font-size: 1.1rem; line-height: 1.9; color: var(--text-secondary); }
.preview-content p { margin-bottom: 1.5rem; }
.preview-content h2, .preview-content h3 { margin: 2rem 0 1rem; color: var(--text-primary); font-family: var(--font-primary); font-weight: 700; }
.preview-content blockquote {
    border-left: 4px solid var(--primary); padding: 1rem 1.5rem; margin: 1.5rem 0;
    background: var(--bg-secondary); font-style: italic; border-radius: 0 var(--radius-md) var(--radius-md) 0;
    color: var(--text-primary);
}
.preview-content ul, .preview-content ol { margin-bottom: 1.5rem; padding-left: 1.5rem; }
.preview-content li { margin-bottom: 0.5rem; }

/* Modal overlay */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(8px);
    display: none; align-items: center; justify-content: center; z-index: 9999; padding: 2rem;
    animation: fadeIn 0.3s;
}
.modal-overlay.open { display: flex; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

/* Responsive */
@media (max-width: 968px) {
    .editor-grid { grid-template-columns: 1fr; }
    .editor-header { flex-direction: column; align-items: flex-start; }
    .editor-header-right { width: 100%; justify-content: flex-end; }
    .judul-input { font-size: 1.5rem; }
    .preview-body { padding: 1.5rem; }
    .preview-title { font-size: 1.75rem; }
}
</style>

<div class="editor-wrap">
    <!-- ===== EDITOR HEADER ===== -->
    <div class="editor-header">
        <div class="editor-header-left">
            <a href="berita.php" class="back-btn">← Kembali</a>
            <h2><?= $edit ? '✏️ Edit Berita' : '➕ Berita Baru' ?></h2>
        </div>
        <div class="editor-header-right">
            <span class="autosave-indicator" id="autosaveStatus">
                <span class="as-icon">💾</span>
                <span class="as-text">Tersimpan di draft lokal</span>
            </span>
            <button type="button" class="btn-sm gray" onclick="openPreview()">👁️ Preview</button>
            <button type="submit" form="editorForm" class="btn-sm primary">💾 <?= $edit ? 'Update' : 'Simpan' ?></button>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data" id="editorForm" class="editor-form">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

        <div class="editor-grid">
            <!-- ===== MAIN COLUMN ===== -->
            <div class="editor-main">
                <!-- Judul -->
                <div class="editor-card title-card">
                    <input type="text" name="judul" id="judulInput" class="judul-input" 
                        placeholder="Tulis judul berita yang menarik..." required maxlength="255"
                        value="<?= sanitize($edit['judul'] ?? '') ?>">
                    <div class="slug-preview">
                        <span class="slug-prefix"><?= base_url('berita-detail.php?slug=') ?></span>
                        <span class="slug-text" id="slugPreview"><?= sanitize($edit['slug'] ?? 'slug-akan-dibuat-otomatis') ?></span>
                    </div>
                    <input type="text" name="custom_slug" id="customSlug" class="custom-slug-input" 
                        placeholder="Atau ketik slug kustom di sini (opsional)" style="display:none">
                    <button type="button" class="slug-edit-btn" onclick="toggleCustomSlug()">✏️ Edit slug</button>
                </div>

                <!-- Excerpt -->
                <div class="editor-card">
                    <label class="field-label">
                        <span>📝 Ringkasan (Excerpt)</span>
                        <span class="char-count"><span id="excerptCount"><?= mb_strlen($edit['excerpt'] ?? '') ?></span>/500</span>
                    </label>
                    <textarea name="excerpt" id="excerptInput" class="excerpt-textarea" maxlength="500" rows="3"
                        placeholder="Ringkasan singkat yang menarik pembaca..."><?= sanitize($edit['excerpt'] ?? '') ?></textarea>
                </div>

                <!-- Konten dengan Rich Toolbar -->
                <div class="editor-card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 1.5rem 1.5rem 0.5rem;">
                        <label class="field-label">
                            <span>📄 Konten Berita *</span>
                            <span class="content-stats">
                                <span id="wordCount">0</span> kata • <span id="readTime">0</span> menit baca
                            </span>
                        </label>
                    </div>
                    <div class="rich-toolbar">
                        <button type="button" class="tb-btn" onclick="execCmd('bold')" title="Bold (Ctrl+B)"><b>B</b></button>
                        <button type="button" class="tb-btn" onclick="execCmd('italic')" title="Italic (Ctrl+I)"><i>I</i></button>
                        <button type="button" class="tb-btn" onclick="execCmd('underline')" title="Underline (Ctrl+U)"><u>U</u></button>
                        <div class="tb-divider"></div>
                        <button type="button" class="tb-btn" onclick="execCmd('formatBlock','<h2>')" title="Heading 2">H2</button>
                        <button type="button" class="tb-btn" onclick="execCmd('formatBlock','<h3>')" title="Heading 3">H3</button>
                        <button type="button" class="tb-btn" onclick="execCmd('formatBlock','<p>')" title="Paragraph">P</button>
                        <div class="tb-divider"></div>
                        <button type="button" class="tb-btn" onclick="execCmd('insertUnorderedList')" title="Bullet List">• List</button>
                        <button type="button" class="tb-btn" onclick="execCmd('insertOrderedList')" title="Numbered List">1. List</button>
                        <button type="button" class="tb-btn" onclick="execCmd('formatBlock','<blockquote>')" title="Quote">❝ Quote</button>
                        <div class="tb-divider"></div>
                        <button type="button" class="tb-btn" onclick="insertLink()" title="Link">🔗</button>
                        <button type="button" class="tb-btn" onclick="insertHR()" title="Horizontal Line">―</button>
                        <button type="button" class="tb-btn" onclick="execCmd('removeFormat')" title="Clear Format">🧹</button>
                    </div>
                    <textarea name="konten" id="kontenInput" class="konten-textarea" required 
                        placeholder="Tulis konten berita di sini...&#10;&#10;Tips:&#10;• Gunakan toolbar di atas untuk formatting&#10;• HTML sederhana diizinkan: p, strong, em, ul, li, h2-h4, blockquote, a"><?= sanitize($edit['konten'] ?? '') ?></textarea>
                </div>

                <!-- Tags -->
                <div class="editor-card">
                    <label class="field-label">
                        <span>🏷️ Tags</span>
                        <small style="color:var(--text-muted); margin-left:0.5rem; font-weight:500;">Pisahkan dengan koma atau tekan Enter</small>
                    </label>
                    <div class="tags-input-wrap">
                        <div class="tags-container" id="tagsContainer"></div>
                        <input type="text" id="tagInput" class="tag-input-field" placeholder="Ketik tag...">
                    </div>
                    <input type="hidden" name="tags" id="tagsHidden" value="<?= sanitize($edit['tags'] ?? '') ?>">
                </div>
            </div>

            <!-- ===== SIDEBAR ===== -->
            <aside class="editor-sidebar">
                <!-- Status -->
                <div class="editor-card">
                    <h3 class="card-title">📊 Status & Publikasi</h3>
                    <div class="field-group">
                        <label class="field-label">Status</label>
                        <div class="status-radios">
                            <label class="status-radio status-draft">
                                <input type="radio" name="status" value="Draft" <?= ($edit['status'] ?? 'Draft') === 'Draft' ? 'checked' : '' ?>>
                                <span class="sr-dot"></span><span>📝 Draft</span>
                            </label>
                            <label class="status-radio status-published">
                                <input type="radio" name="status" value="Published" <?= ($edit['status'] ?? '') === 'Published' ? 'checked' : '' ?>>
                                <span class="sr-dot"></span><span>✅ Published</span>
                            </label>
                            <label class="status-radio status-archived">
                                <input type="radio" name="status" value="Archived" <?= ($edit['status'] ?? '') === 'Archived' ? 'checked' : '' ?>>
                                <span class="sr-dot"></span><span>🗄️ Archived</span>
                            </label>
                        </div>
                    </div>
                    <div class="field-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_featured" <?= !empty($edit['is_featured']) ? 'checked' : '' ?>>
                            <span class="checkbox-custom"></span>
                            <span>⭐ Jadikan Berita Utama (Featured)</span>
                        </label>
                    </div>
                    <?php if ($edit): ?>
                    <div class="meta-info">
                        <div class="meta-row"><span>Dibuat:</span><strong><?= date('d M Y H:i', strtotime($edit['created_at'])) ?></strong></div>
                        <div class="meta-row"><span>Views:</span><strong><?= number_format($edit['views']) ?></strong></div>
                        <?php if ($edit['published_at']): ?>
                        <div class="meta-row"><span>Published:</span><strong><?= date('d M Y H:i', strtotime($edit['published_at'])) ?></strong></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Kategori & Penulis -->
                <div class="editor-card">
                    <h3 class="card-title">📁 Kategori & Penulis</h3>
                    <div class="field-group">
                        <label class="field-label">Kategori</label>
                        <select name="kategori" class="styled-select">
                            <?php foreach (['Akademik','Pengumuman','Prestasi','Kegiatan','Riset','Umum'] as $k): ?>
                            <option value="<?= $k ?>" <?= ($edit['kategori'] ?? 'Umum') === $k ? 'selected' : '' ?>><?= $k ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-group">
                        <label class="field-label">Penulis</label>
                        <input type="text" name="penulis" class="styled-input" maxlength="100" 
                            value="<?= sanitize($edit['penulis'] ?? $_SESSION['admin_name'] ?? 'Humas FKIP') ?>">
                    </div>
                </div>

                <!-- Gambar -->
                <div class="editor-card">
                    <h3 class="card-title">🖼️ Gambar Utama</h3>
                    <div class="upload-zone" id="uploadZone">
                        <input type="file" name="gambar" id="gambarInput" accept="image/jpeg,image/png,image/webp,image/gif" class="upload-file-input">
                        <div class="upload-placeholder" id="uploadPlaceholder">
                            <div class="upload-icon">📤</div>
                            <p class="upload-text">Drag & drop gambar di sini</p>
                            <p class="upload-sub">atau klik untuk memilih file</p>
                            <p class="upload-hint">JPG, PNG, WEBP, GIF (maks 2MB)</p>
                        </div>
                        <div class="upload-preview" id="uploadPreview" style="display:none">
                            <img id="previewImg" src="" alt="">
                            <button type="button" class="upload-remove" onclick="removePreview()">✕ Hapus</button>
                        </div>
                    </div>
                    <?php if (!empty($edit['gambar'])): ?>
                        <div class="current-image">
                            <label class="field-label">Gambar saat ini:</label>
                            <img src="<?= asset('uploads/' . basename($edit['gambar'])) ?>" alt="">
                            <small>Upload gambar baru untuk mengganti</small>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- SEO Tips -->
                <div class="editor-card tips-card">
                    <h3 class="card-title">💡 Tips Menulis</h3>
                    <ul class="tips-list">
                        <li>✓ Gunakan judul yang menarik & jelas</li>
                        <li>✓ Ringkasan singkat 1-2 kalimat</li>
                        <li>✓ Minimal 300 kata untuk SEO</li>
                        <li>✓ Tambahkan gambar berkualitas</li>
                        <li>✓ Gunakan heading untuk struktur</li>
                    </ul>
                </div>
            </aside>
        </div>
    </form>
</div>

<!-- ===== PREVIEW MODAL ===== -->
<div class="modal-overlay" id="previewModal" onclick="if(event.target===this)closePreview()">
    <div class="preview-modal-box">
        <div class="preview-header">
            <span>👁️ Preview Tampilan</span>
            <button class="preview-close" onclick="closePreview()">✕</button>
        </div>
        <div class="preview-body">
            <article class="preview-article">
                <div class="preview-badge" id="previewBadge">Kategori</div>
                <h1 class="preview-title" id="previewTitle">Judul berita akan muncul di sini...</h1>
                <div class="preview-meta">
                    <span>Oleh <strong id="previewAuthor">Penulis</strong></span> • 
                    <span><?= date('d F Y') ?></span> • 
                    <span id="previewReadTime">0 menit baca</span>
                </div>
                <div class="preview-content" id="previewContent"></div>
            </article>
        </div>
    </div>
</div>

<script>
// ===== Auto-generate slug from judul =====
const judulInput = document.getElementById('judulInput');
const slugPreview = document.getElementById('slugPreview');
const customSlug = document.getElementById('customSlug');

function slugify(text) {
    return text.toString().toLowerCase()
        .replace(/\s+/g, '-')
        .replace(/[^\w\-]+/g, '')
        .replace(/\-\-+/g, '-')
        .replace(/^-+/, '')
        .replace(/-+$/, '');
}

judulInput.addEventListener('input', function() {
    if (!customSlug.style.display || customSlug.style.display === 'none') {
        const slug = slugify(this.value) || 'slug-akan-dibuat-otomatis';
        slugPreview.textContent = slug;
    }
    autoSave();
});

function toggleCustomSlug() {
    const isShown = customSlug.style.display === 'block';
    customSlug.style.display = isShown ? 'none' : 'block';
    if (!isShown) customSlug.focus();
}
customSlug.addEventListener('input', function() {
    slugPreview.textContent = slugify(this.value) || slugify(judulInput.value);
});

// ===== Word counter & read time =====
const kontenInput = document.getElementById('kontenInput');
const wordCountEl = document.getElementById('wordCount');
const readTimeEl = document.getElementById('readTime');

function updateWordCount() {
    const text = kontenInput.value.trim();
    const words = text ? text.split(/\s+/).length : 0;
    wordCountEl.textContent = words;
    readTimeEl.textContent = Math.max(1, Math.ceil(words / 200));
    autoSave();
}
kontenInput.addEventListener('input', updateWordCount);
updateWordCount();

// ===== Excerpt char counter =====
const excerptInput = document.getElementById('excerptInput');
const excerptCount = document.getElementById('excerptCount');
excerptInput.addEventListener('input', function() {
    const len = this.value.length;
    excerptCount.textContent = len;
    const parent = excerptCount.parentElement;
    parent.classList.toggle('warn', len > 400 && len <= 500);
    parent.classList.toggle('danger', len > 500);
});

// ===== Rich Text Commands =====
function execCmd(cmd, val = null) {
    const ta = kontenInput;
    const start = ta.selectionStart;
    const end = ta.selectionEnd;
    const text = ta.value;
    const selected = text.substring(start, end);
    
    const wrappers = {
        'bold': ['<strong>', '</strong>'],
        'italic': ['<em>', '</em>'],
        'underline': ['<u>', '</u>'],
        'formatBlock': {
            '<h2>': ['<h2>', '</h2>'],
            '<h3>': ['<h3>', '</h3>'],
            '<p>': ['<p>', '</p>'],
            '<blockquote>': ['<blockquote>', '</blockquote>']
        },
        'insertUnorderedList': ['<ul>\n<li>', '</li>\n</ul>'],
        'insertOrderedList': ['<ol>\n<li>', '</li>\n</ol>']
    };
    
    let before = '', after = '';
    if (cmd === 'formatBlock') {
        [before, after] = wrappers[cmd][val];
    } else if (wrappers[cmd]) {
        [before, after] = wrappers[cmd];
    }
    
    if (cmd === 'removeFormat') {
        const cleaned = selected.replace(/<[^>]+>/g, '');
        ta.value = text.substring(0, start) + cleaned + text.substring(end);
    } else {
        ta.value = text.substring(0, start) + before + selected + after + text.substring(end);
        ta.selectionStart = start + before.length;
        ta.selectionEnd = start + before.length + selected.length;
    }
    ta.focus();
    updateWordCount();
}

function insertLink() {
    const url = prompt('Masukkan URL:', 'https://');
    if (url) {
        const ta = kontenInput;
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        const text = ta.value;
        const selected = text.substring(start, end) || url;
        const tag = `<a href="${url}" target="_blank">${selected}</a>`;
        ta.value = text.substring(0, start) + tag + text.substring(end);
        ta.focus();
        updateWordCount();
    }
}

function insertHR() {
    const ta = kontenInput;
    const pos = ta.selectionStart;
    ta.value = ta.value.substring(0, pos) + '\n<hr>\n' + ta.value.substring(pos);
    ta.focus();
}

// ===== Tags input =====
const tagInput = document.getElementById('tagInput');
const tagsContainer = document.getElementById('tagsContainer');
const tagsHidden = document.getElementById('tagsHidden');
let tags = [];

// Load existing tags
const initialTags = tagsHidden.value;
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
}

function addTag(val) {
    val = val.trim();
    if (val && !tags.includes(val) && tags.length < 10) {
        tags.push(val);
        renderTags();
    }
}

window.removeTag = function(i) {
    tags.splice(i, 1);
    renderTags();
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

// ===== Upload drag & drop =====
const uploadZone = document.getElementById('uploadZone');
const gambarInput = document.getElementById('gambarInput');
const uploadPlaceholder = document.getElementById('uploadPlaceholder');
const uploadPreview = document.getElementById('uploadPreview');
const previewImg = document.getElementById('previewImg');

['dragenter','dragover'].forEach(ev => {
    uploadZone.addEventListener(ev, e => { e.preventDefault(); uploadZone.classList.add('dragover'); });
});
['dragleave','drop'].forEach(ev => {
    uploadZone.addEventListener(ev, e => { e.preventDefault(); uploadZone.classList.remove('dragover'); });
});
uploadZone.addEventListener('drop', e => {
    const files = e.dataTransfer.files;
    if (files.length > 0) handleFile(files[0]);
});
gambarInput.addEventListener('change', e => {
    if (e.target.files.length > 0) handleFile(e.target.files[0]);
});

function handleFile(file) {
    if (!file.type.startsWith('image/')) {
        alert('File harus berupa gambar!');
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        alert('Ukuran maksimal 2MB!');
        return;
    }
    const reader = new FileReader();
    reader.onload = e => {
        previewImg.src = e.target.result;
        uploadPlaceholder.style.display = 'none';
        uploadPreview.style.display = 'block';
    };
    reader.readAsDataURL(file);
}

window.removePreview = function() {
    gambarInput.value = '';
    previewImg.src = '';
    uploadPlaceholder.style.display = 'block';
    uploadPreview.style.display = 'none';
};

// ===== Auto-save to localStorage =====
const storageKey = 'fkip_draft_' + (<?= $id ?> || 'new');
let autoSaveTimer;

function autoSave() {
    clearTimeout(autoSaveTimer);
    const indicator = document.getElementById('autosaveStatus');
    indicator.classList.add('saving');
    indicator.querySelector('.as-text').textContent = 'Menyimpan...';
    
    autoSaveTimer = setTimeout(() => {
        const data = {
            judul: judulInput.value,
            excerpt: excerptInput.value,
            konten: kontenInput.value,
            tags: tags.join(','),
            saved_at: new Date().toISOString()
        };
        try {
            localStorage.setItem(storageKey, JSON.stringify(data));
            indicator.classList.remove('saving');
            indicator.classList.add('saved');
            indicator.querySelector('.as-text').textContent = 'Tersimpan ' + new Date().toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'});
        } catch(e) {}
    }, 800);
}

// Load autosaved data if new post
(function(){
    <?php if (!$edit): ?>
    try {
        const saved = localStorage.getItem(storageKey);
        if (saved) {
            const data = JSON.parse(saved);
            if (confirm('Ada draft tersimpan dari ' + new Date(data.saved_at).toLocaleString('id-ID') + '. Muat draft tersebut?')) {
                judulInput.value = data.judul || '';
                excerptInput.value = data.excerpt || '';
                kontenInput.value = data.konten || '';
                if (data.tags) { tags = data.tags.split(',').filter(t => t); renderTags(); }
                judulInput.dispatchEvent(new Event('input'));
                updateWordCount();
            } else {
                localStorage.removeItem(storageKey);
            }
        }
    } catch(e) {}
    <?php endif; ?>
})();

// Clear autosave after successful submit
document.getElementById('editorForm').addEventListener('submit', function() {
    try { localStorage.removeItem(storageKey); } catch(e) {}
});

// ===== Preview modal =====
function openPreview() {
    document.getElementById('previewTitle').textContent = judulInput.value || 'Judul berita akan muncul di sini...';
    
    let rawContent = kontenInput.value;
    let safeContent = rawContent.replace(/\n/g, '<br>'); 
    
    document.getElementById('previewContent').innerHTML = safeContent || '<p style="color:var(--text-muted)"><em>Belum ada konten...</em></p>';
    
    document.getElementById('previewBadge').textContent = document.querySelector('[name="kategori"]').value;
    document.getElementById('previewAuthor').textContent = document.querySelector('[name="penulis"]').value || 'Humas FKIP';
    document.getElementById('previewReadTime').textContent = readTimeEl.textContent + ' menit baca';
    document.getElementById('previewModal').classList.add('open');
}

function closePreview() {
    document.getElementById('previewModal').classList.remove('open');
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closePreview();
});

// ===== Keyboard shortcuts in editor =====
kontenInput.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'b') { e.preventDefault(); execCmd('bold'); }
    if ((e.ctrlKey || e.metaKey) && e.key === 'i') { e.preventDefault(); execCmd('italic'); }
    if ((e.ctrlKey || e.metaKey) && e.key === 'u') { e.preventDefault(); execCmd('underline'); }
    if (e.key === 'Tab') {
        e.preventDefault();
        const start = this.selectionStart;
        this.value = this.value.substring(0, start) + '    ' + this.value.substring(this.selectionEnd);
        this.selectionStart = this.selectionEnd = start + 4;
    }
});

console.log('%c✏️ Editor Berita FKIP UNIMOF', 'color:#0a6847;font-size:16px;font-weight:bold');
console.log('%cShortcuts: Ctrl+B (bold), Ctrl+I (italic), Ctrl+U (underline)', 'color:#64748b');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>