<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$duplicate_from = (int)($_GET['duplicate'] ?? 0);
$edit = null;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM berita WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch();
    if (!$edit) { header('Location: berita.php'); exit; }
} elseif ($duplicate_from > 0) {
    $stmt = $pdo->prepare("SELECT * FROM berita WHERE id = ?");
    $stmt->execute([$duplicate_from]);
    $source = $stmt->fetch();
    if ($source) {
        $edit = $source;
        $edit['id'] = null;
        $edit['judul'] = $source['judul'] . ' (Copy)';
        $edit['slug'] = null;
        $edit['status'] = 'Draft';
        $edit['views'] = 0;
        flash_message('info', '📋 Menduplikasi berita: ' . htmlspecialchars($source['judul']));
    }
}

// Fetch popular tags untuk suggestions
$popular_tags = [];
try {
    $popular_tags = $pdo->query("SELECT tags FROM berita WHERE tags IS NOT NULL AND tags != '' ORDER BY views DESC LIMIT 50")->fetchAll(PDO::FETCH_COLUMN);
    $all_tags = [];
    foreach ($popular_tags as $t) {
        foreach (explode(',', $t) as $tag) {
            $tag = trim($tag);
            if ($tag) $all_tags[$tag] = ($all_tags[$tag] ?? 0) + 1;
        }
    }
    arsort($all_tags);
    $popular_tags = array_slice(array_keys($all_tags), 0, 15);
} catch (Exception $e) { $popular_tags = []; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('error', '❌ Token keamanan tidak valid.');
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
        flash_message('error', '❌ Judul dan konten wajib diisi.');
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
        if (function_exists('upload_image')) {
            $up = upload_image($_FILES['gambar']);
            if (!$up['ok']) {
                flash_message('error', $up['error']);
                header('Location: berita-form.php' . ($id ? "?id=$id" : '')); exit;
            }
            if ($up['name']) {
                if ($gambar && function_exists('delete_upload')) delete_upload($gambar);
                $gambar = $up['name'];
            }
        } else {
            // Fallback manual upload
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['gambar']['tmp_name']);
            if (!in_array($mime, $allowed)) {
                flash_message('error', '❌ Hanya file gambar (JPG, PNG, WEBP, GIF) yang diizinkan.');
                header('Location: berita-form.php' . ($id ? "?id=$id" : '')); exit;
            }
            if ($_FILES['gambar']['size'] > 5 * 1024 * 1024) {
                flash_message('error', '❌ Ukuran gambar maksimal 5MB.');
                header('Location: berita-form.php' . ($id ? "?id=$id" : '')); exit;
            }
            $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime];
            $new_name = 'berita_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $dir = APP_DIR . '/uploads';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            if (move_uploaded_file($_FILES['gambar']['tmp_name'], $dir . '/' . $new_name)) {
                if ($gambar && function_exists('delete_upload')) delete_upload($gambar);
                $gambar = $new_name;
            }
        }
    }

    try {
        if ($edit && $id > 0) {
            $pdo->prepare("UPDATE berita SET judul=?, slug=?, konten=?, excerpt=?, gambar=?, kategori=?, penulis=?, status=?, is_featured=?, tags=? WHERE id=?")
                ->execute([$judul, $slug, $konten, $excerpt, $gambar, $kategori, $penulis, $status, $featured, $tags, $id]);
            flash_message('success', '✅ Berita berhasil diperbarui.');
        } else {
            $pub = $status === 'Published' ? date('Y-m-d H:i:s') : null;
            $pdo->prepare("INSERT INTO berita (judul, slug, konten, excerpt, gambar, kategori, penulis, status, is_featured, published_at, tags) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$judul, $slug, $konten, $excerpt, $gambar, $kategori, $penulis, $status, $featured, $pub, $tags]);
            flash_message('success', '✅ Berita berhasil ditambahkan.');
        }
    } catch (Exception $e) {
        flash_message('error', '❌ Gagal menyimpan: ' . $e->getMessage());
        header('Location: berita-form.php' . ($id ? "?id=$id" : '')); exit;
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
/* ===== EXTREME ULTIMATE EDITOR STYLES ===== */
.editor-wrap { max-width: 1500px; margin: 0 auto; }

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
.progress-label { color: var(--text-muted); }

.editor-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 2px solid var(--border);
    flex-wrap: wrap; gap: 1rem;
}
.editor-header-left { display: flex; align-items: center; gap: 1rem; }
.editor-header-left h2 {
    margin: 0; font-size: 1.5rem; font-weight: 800; color: var(--text-primary);
    font-family: 'Georgia', serif; letter-spacing: -0.01em;
}
.back-btn {
    color: var(--text-secondary); text-decoration: none; font-size: 0.88rem; font-weight: 600;
    padding: 0.5rem 1rem; background: var(--bg-secondary); border-radius: var(--radius-md);
    border: 1px solid var(--border); transition: all 0.2s; display: flex; align-items: center; gap: 0.5rem;
}
.back-btn:hover { background: var(--bg-tertiary); color: var(--primary); transform: translateX(-2px); }

.editor-header-right { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.autosave-indicator {
    display: flex; align-items: center; gap: 0.5rem; font-size: 0.78rem; font-weight: 600;
    color: var(--text-muted); background: var(--bg-secondary); padding: 0.5rem 0.85rem;
    border-radius: 999px; border: 1px solid var(--border); transition: all 0.3s;
}
.autosave-indicator.saving { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
.autosave-indicator.saved { background: #dcfce7; color: #166534; border-color: #86efac; }

.btn-sm {
    padding: 0.5rem 1rem; border-radius: var(--radius-md); font-weight: 600; font-size: 0.85rem;
    cursor: pointer; transition: all 0.2s; border: 1px solid var(--border); font-family: inherit;
    display: inline-flex; align-items: center; gap: 0.4rem;
}
.btn-sm.primary {
    background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; border-color: #dc2626;
    box-shadow: 0 4px 12px rgba(220,38,38,0.25);
}
.btn-sm.primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(220,38,38,0.4); }
.btn-sm.gray { background: var(--bg-secondary); color: var(--text-primary); }
.btn-sm.gray:hover { background: var(--bg-tertiary); border-color: var(--primary); color: var(--primary); }

/* ===== QUICK TEMPLATES ===== */
.template-section {
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
}
.template-label {
    font-size: 0.78rem;
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
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    color: white;
    border-color: #dc2626;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(220,38,38,0.25);
}

.editor-grid { display: grid; grid-template-columns: 1fr 380px; gap: 2rem; align-items: start; }
.editor-main, .editor-sidebar { display: flex; flex-direction: column; gap: 1.5rem; }

.editor-card {
    background: var(--bg-primary); border-radius: var(--radius-xl); padding: 1.5rem;
    box-shadow: var(--shadow-sm); border: 1px solid var(--border); transition: all 0.3s;
}
.editor-card:hover { box-shadow: var(--shadow-md); }
.editor-card:focus-within { border-color: var(--primary); }

.card-title {
    font-size: 0.95rem; font-weight: 700; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;
    padding-bottom: 0.75rem; border-bottom: 2px solid var(--bg-tertiary); color: var(--text-primary);
    font-family: 'Georgia', serif;
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
    background: linear-gradient(135deg, var(--bg-primary) 0%, rgba(220,38,38,0.03) 100%);
    border: 2px solid var(--border);
    position: relative;
    overflow: hidden;
}
.title-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, #dc2626, #ef4444, #f87171);
}
.judul-input {
    width: 100%; border: none; font-size: 2rem; font-weight: 800; color: var(--text-primary);
    background: transparent; outline: none; font-family: 'Georgia', serif; padding: 0; margin-bottom: 0.75rem;
    line-height: 1.2; letter-spacing: -0.02em;
}
.judul-input::placeholder { color: var(--text-muted); font-weight: 600; }
.judul-counter {
    font-size: 0.72rem;
    color: var(--text-muted);
    font-weight: 600;
    margin-bottom: 0.75rem;
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}
.judul-counter.warn { color: #f59e0b; }
.judul-counter.danger { color: #ef4444; }

.slug-preview {
    display: flex; align-items: center; gap: 0.25rem; font-size: 0.82rem; color: var(--text-muted);
    background: var(--bg-tertiary); padding: 0.5rem 1rem; border-radius: var(--radius-md);
    margin-bottom: 0.75rem; font-family: ui-monospace, monospace; overflow: hidden;
}
.slug-prefix { color: var(--text-muted); flex-shrink: 0; }
.slug-text { color: var(--primary); font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.custom-slug-input {
    width: 100%; padding: 0.6rem 1rem; border: 2px solid var(--primary); border-radius: var(--radius-md);
    font-family: ui-monospace, monospace; font-size: 0.82rem; margin-bottom: 0.75rem; background: var(--bg-primary);
}
.slug-edit-btn {
    background: transparent; border: 1px solid var(--border); padding: 0.4rem 0.85rem; border-radius: var(--radius-md);
    font-size: 0.78rem; cursor: pointer; color: var(--text-secondary); transition: all 0.2s; font-family: inherit; font-weight: 600;
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
.char-count { font-size: 0.72rem; color: var(--text-muted); font-weight: 600; font-variant-numeric: tabular-nums; }
.char-count.warn { color: #f59e0b; }
.char-count.danger { color: #ef4444; }

/* Smart Suggestions */
.smart-suggestions {
    margin-top: 0.5rem;
    padding: 0.65rem 0.85rem;
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border: 1px solid #fcd34d;
    border-radius: var(--radius-md);
    font-size: 0.78rem;
    display: none;
}
.smart-suggestions.show { display: block; }
.suggestion-header {
    font-weight: 700;
    margin-bottom: 0.35rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    color: #92400e;
}
.suggestion-item {
    padding: 0.3rem 0;
    cursor: pointer;
    transition: all 0.15s;
    font-size: 0.78rem;
    color: #92400e;
}
.suggestion-item:hover { color: #78350f; transform: translateX(4px); }

/* Content & Toolbar Modern */
.content-stats { font-size: 0.72rem; color: var(--text-muted); font-weight: 600; display: flex; gap: 0.75rem; }
.content-stats span { display: inline-flex; align-items: center; gap: 0.25rem; }
.rich-toolbar {
    display: flex; flex-wrap: wrap; gap: 0.35rem; padding: 0.75rem;
    background: var(--bg-secondary); border: 2px solid var(--border); border-bottom: none;
    border-radius: var(--radius-md) var(--radius-md) 0 0; align-items: center;
}
.tb-btn {
    background: var(--bg-primary); border: 1px solid var(--border); padding: 0.4rem 0.75rem;
    border-radius: 6px; font-size: 0.82rem; cursor: pointer; transition: all 0.15s;
    color: var(--text-secondary); font-family: inherit; min-width: 36px; height: 36px;
    display: flex; align-items: center; justify-content: center; gap: 0.25rem; font-weight: 600;
}
.tb-btn:hover { background: var(--primary); color: white; border-color: var(--primary); transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.tb-btn b, .tb-btn i, .tb-btn u { font-size: 0.88rem; }
.tb-divider { width: 1px; height: 24px; background: var(--border); margin: 0 0.25rem; }

.konten-textarea {
    width: 100%; padding: 1.5rem; border: 2px solid var(--border); border-top: 1px solid var(--bg-tertiary);
    border-radius: 0 0 var(--radius-md) var(--radius-md); font-size: 0.95rem; font-family: ui-monospace, monospace;
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
    background: linear-gradient(135deg, #dc2626, #b91c1c); color: white;
    padding: 0.35rem 0.75rem; border-radius: 999px; font-size: 0.78rem; font-weight: 600;
    display: inline-flex; align-items: center; gap: 0.4rem; animation: tagPop 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
@keyframes tagPop { from { transform: scale(0); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.tag-pill button {
    background: rgba(255,255,255,0.2); border: none; color: white; width: 18px; height: 18px;
    border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;
    font-size: 0.7rem; transition: all 0.2s; padding: 0;
}
.tag-pill button:hover { background: rgba(255,255,255,0.4); }
.tag-input-field { border: none; outline: none; flex: 1; min-width: 120px; padding: 0.4rem; background: transparent; font-family: inherit; font-size: 0.88rem; color: var(--text-primary); }

/* Tag suggestions */
.tag-suggestions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-top: 0.5rem;
}
.tag-suggestion {
    padding: 0.25rem 0.65rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    color: var(--text-secondary);
}
.tag-suggestion:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* Status Radios Modern */
.status-radios { display: flex; flex-direction: column; gap: 0.5rem; }
.status-radio {
    display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem;
    background: var(--bg-secondary); border: 2px solid transparent; border-radius: var(--radius-md);
    cursor: pointer; transition: all 0.2s; font-size: 0.88rem; font-weight: 500; color: var(--text-secondary);
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
    display: flex; align-items: center; gap: 0.75rem; cursor: pointer; font-size: 0.88rem;
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
.meta-row { display: flex; justify-content: space-between; font-size: 0.82rem; }
.meta-row span { color: var(--text-muted); }
.meta-row strong { color: var(--text-primary); font-weight: 600; }

/* Upload Zone Premium */
.upload-zone {
    position: relative; border: 2px dashed var(--border); border-radius: var(--radius-lg);
    transition: all 0.3s; overflow: hidden; background: var(--bg-secondary);
}
.upload-zone:hover, .upload-zone.dragover { border-color: var(--primary); background: rgba(10,104,71,0.03); }
.upload-zone.dragover { border-style: solid; box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.upload-file-input { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 2; }
.upload-placeholder { padding: 2.5rem 1.5rem; text-align: center; pointer-events: none; }
.upload-icon { font-size: 3rem; margin-bottom: 0.75rem; animation: uploadFloat 3s ease-in-out infinite; }
@keyframes uploadFloat { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
.upload-text { font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem; font-size: 1rem; }
.upload-sub { font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.5rem; }
.upload-hint {
    font-size: 0.72rem; color: var(--text-muted); background: var(--bg-tertiary);
    display: inline-flex; align-items: center; gap: 0.3rem;
    padding: 0.25rem 0.75rem; border-radius: 999px; margin: 0.15rem;
}
.upload-hints { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.35rem; }

.upload-preview { position: relative; }
.upload-preview img { width: 100%; max-height: 250px; object-fit: cover; display: block; border-radius: var(--radius-md) var(--radius-md) 0 0; }
.upload-remove {
    position: absolute; top: 0.75rem; right: 0.75rem; background: rgba(239, 68, 68, 0.9); color: white;
    border: none; padding: 0.5rem 0.85rem; border-radius: var(--radius-md); font-size: 0.78rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s; backdrop-filter: blur(4px); display: flex; align-items: center; gap: 0.4rem;
}
.upload-remove:hover { background: #dc2626; transform: scale(1.05); }
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

.current-image { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); }
.current-image img { width: 100%; border-radius: var(--radius-md); margin: 0.75rem 0; border: 1px solid var(--border); }
.current-image small { font-size: 0.78rem; color: var(--text-muted); display: block; }

/* SEO Score Card */
.seo-card {
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    border-color: #93c5fd;
}
.seo-card .card-title { color: #1e40af; border-bottom-color: rgba(30, 64, 175, 0.2); }
.seo-score-box {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: white;
    border-radius: var(--radius-md);
    border: 1px solid #93c5fd;
    margin-bottom: 1rem;
}
.seo-score-circle {
    width: 70px;
    height: 70px;
    position: relative;
    flex-shrink: 0;
}
.seo-score-circle svg { transform: rotate(-90deg); width: 100%; height: 100%; }
.seo-score-circle .ring-bg { fill: none; stroke: rgba(59,130,246,0.2); stroke-width: 8; }
.seo-score-circle .ring-fill { fill: none; stroke: #3b82f6; stroke-width: 8; stroke-linecap: round; transition: stroke-dasharray 0.8s ease; }
.seo-score-value {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 900;
    color: #1e40af;
    font-family: 'Georgia', serif;
}
.seo-score-info { flex: 1; }
.seo-score-label { font-weight: 700; color: #1e40af; margin-bottom: 0.2rem; font-size: 0.95rem; }
.seo-score-text { font-size: 0.75rem; color: #1e40af; opacity: 0.85; }
.seo-checklist {
    list-style: none;
    padding: 0;
    margin: 0;
    font-size: 0.78rem;
}
.seo-checklist li {
    padding: 0.4rem 0;
    border-bottom: 1px dashed rgba(30, 64, 175, 0.2);
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.seo-checklist li:last-child { border-bottom: none; }

/* Tips Card */
.tips-card { background: linear-gradient(135deg, #fef3c7, #fde68a); border-color: #fcd34d; }
.tips-card .card-title { color: #92400e; border-bottom-color: rgba(146, 64, 14, 0.2); }
.tips-list { list-style: none; font-size: 0.82rem; color: #92400e; line-height: 1.8; margin: 0; padding: 0; }
.tips-list li { padding-left: 0.25rem; display: flex; align-items: flex-start; gap: 0.5rem; }

/* Secondary Actions */
.secondary-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 1.5rem;
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

/* Shortcuts Legend */
.shortcuts-legend {
    text-align: center;
    margin-top: 1rem;
    font-size: 0.75rem;
    color: var(--text-muted);
    display: flex;
    gap: 0.75rem;
    justify-content: center;
    flex-wrap: wrap;
}
.shortcuts-legend kbd {
    background: var(--bg-secondary);
    padding: 0.15rem 0.4rem;
    border-radius: 4px;
    font-family: monospace;
    font-size: 0.7rem;
    border: 1px solid var(--border);
    margin: 0 0.15rem;
}

/* Preview Modal Premium */
.preview-modal-box {
    background: var(--bg-primary); border-radius: var(--radius-xl); max-width: 900px; width: 95%;
    max-height: 90vh; overflow: hidden; animation: zoomIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    box-shadow: 0 30px 80px rgba(0,0,0,0.4); display: flex; flex-direction: column;
}
@keyframes zoomIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.preview-header {
    display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem;
    background: linear-gradient(135deg, #1e293b, #334155); color: white; flex-shrink: 0;
    position: relative;
}
.preview-header::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, #dc2626, #ef4444, #f87171);
}
.preview-header span { font-weight: 700; display: flex; align-items: center; gap: 0.5rem; position: relative; z-index: 1; }
.preview-close {
    background: rgba(255,255,255,0.2); border: none; color: white; width: 36px; height: 36px;
    border-radius: 50%; cursor: pointer; font-size: 1.1rem; transition: all 0.2s; display: flex; align-items: center; justify-content: center;
    position: relative; z-index: 1;
}
.preview-close:hover { background: rgba(255,255,255,0.3); transform: rotate(90deg); }

.preview-tabs {
    display: flex;
    gap: 0.25rem;
    padding: 0.5rem 1.5rem;
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border);
}
.preview-tab {
    padding: 0.5rem 1rem;
    background: transparent;
    border: none;
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
    color: var(--text-muted);
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.preview-tab.active { background: white; color: var(--primary); box-shadow: var(--shadow-sm); }
.preview-tab:hover:not(.active) { background: var(--bg-tertiary); color: var(--text-primary); }

.preview-body { max-height: calc(90vh - 130px); overflow-y: auto; padding: 2.5rem; }
.preview-article { font-family: Georgia, 'Times New Roman', serif; max-width: 680px; margin: 0 auto; }
.preview-badge {
    display: inline-block; padding: 0.35rem 1rem; background: linear-gradient(135deg, #dc2626, #b91c1c); color: white;
    border-radius: 999px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; margin-bottom: 1.5rem;
    font-family: var(--font-primary); letter-spacing: 0.05em;
}
.preview-title { font-size: 2.25rem; line-height: 1.2; margin-bottom: 1.5rem; color: var(--text-primary); font-weight: 800; letter-spacing: -0.02em; }
.preview-meta {
    color: var(--text-muted); font-size: 0.88rem; margin-bottom: 2rem; padding-bottom: 1.5rem;
    border-bottom: 2px solid var(--bg-tertiary); font-family: var(--font-primary); display: flex; align-items: center; gap: 0.5rem;
}
.preview-image {
    width: 100%;
    height: 300px;
    object-fit: cover;
    border-radius: var(--radius-lg);
    margin-bottom: 2rem;
    background: var(--bg-tertiary);
}
.preview-excerpt {
    font-size: 1.15rem;
    line-height: 1.7;
    color: var(--text-secondary);
    font-style: italic;
    padding: 1rem 1.5rem;
    background: var(--bg-secondary);
    border-left: 4px solid var(--primary);
    border-radius: 0 var(--radius-md) var(--radius-md) 0;
    margin-bottom: 2rem;
}
.preview-content { font-size: 1.05rem; line-height: 1.9; color: var(--text-secondary); }
.preview-content p { margin-bottom: 1.5rem; }
.preview-content h2, .preview-content h3 { margin: 2rem 0 1rem; color: var(--text-primary); font-family: var(--font-primary); font-weight: 700; }
.preview-content blockquote {
    border-left: 4px solid var(--primary); padding: 1rem 1.5rem; margin: 1.5rem 0;
    background: var(--bg-secondary); font-style: italic; border-radius: 0 var(--radius-md) var(--radius-md) 0;
    color: var(--text-primary);
}
.preview-content ul, .preview-content ol { margin-bottom: 1.5rem; padding-left: 1.5rem; }
.preview-content li { margin-bottom: 0.5rem; }

/* Social Preview */
.social-preview-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}
.social-preview-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}
.social-preview-header {
    padding: 0.5rem 0.75rem;
    background: var(--bg-secondary);
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.social-preview-body {
    padding: 0;
}
.og-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
    margin: 0.75rem;
}
.og-image {
    width: 100%;
    height: 120px;
    background: var(--bg-tertiary);
    object-fit: cover;
}
.og-content { padding: 0.75rem; }
.og-domain { font-size: 0.7rem; color: #65676b; text-transform: uppercase; margin-bottom: 0.25rem; }
.og-title { font-size: 0.9rem; font-weight: 700; color: #050505; margin-bottom: 0.25rem; line-height: 1.3; }
.og-description { font-size: 0.78rem; color: #65676b; line-height: 1.4; }

/* Mobile preview */
.mobile-preview-frame {
    width: 375px;
    max-width: 100%;
    margin: 0 auto;
    background: white;
    border-radius: 30px;
    padding: 1rem;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    border: 8px solid #1a1a1a;
}
.mobile-notch {
    width: 120px;
    height: 25px;
    background: #1a1a1a;
    border-radius: 0 0 15px 15px;
    margin: -1rem auto 1rem;
}
.mobile-article {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    padding: 1rem;
}
.mobile-article h1 { font-size: 1.5rem; margin-bottom: 1rem; }
.mobile-article p { font-size: 0.95rem; line-height: 1.6; }

/* Modal overlay */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(8px);
    display: none; align-items: center; justify-content: center; z-index: 9999; padding: 1.5rem;
    animation: fadeIn 0.3s;
}
.modal-overlay.open { display: flex; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

/* Toast helper */
.toast-helper {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    z-index: 10000;
}

/* Responsive */
@media (max-width: 1200px) {
    .editor-grid { grid-template-columns: 1fr 340px; }
}
@media (max-width: 968px) {
    .editor-grid { grid-template-columns: 1fr; }
    .editor-header { flex-direction: column; align-items: flex-start; }
    .editor-header-right { width: 100%; justify-content: flex-start; }
    .judul-input { font-size: 1.5rem; }
    .preview-body { padding: 1.5rem; }
    .preview-title { font-size: 1.75rem; }
    .social-preview-grid { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
    .editor-header-right { flex-direction: column; }
    .btn-sm { width: 100%; justify-content: center; }
    .template-buttons { flex-direction: column; }
    .secondary-actions { flex-direction: column; }
    .shortcuts-legend { font-size: 0.68rem; }
}

/* Print styles */
@media print {
    .editor-header, .template-section, .seo-card, .tips-card, .secondary-actions, .shortcuts-legend, .modal-overlay, .autosave-indicator, .progress-indicator { display: none !important; }
    .editor-grid { grid-template-columns: 1fr; }
    .editor-card { box-shadow: none; border: 1px solid #ddd; break-inside: avoid; }
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
        <span class="progress-label">Kelengkapan Artikel</span>
    </div>
</div>

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
                <span class="as-text">Draft lokal aktif</span>
            </span>
            <button type="button" class="btn-sm gray" onclick="openPreview()">👁️ Preview</button>
            <button type="submit" form="editorForm" class="btn-sm primary" id="submitBtn">
                💾 <?= $edit ? 'Update' : 'Simpan' ?>
            </button>
        </div>
    </div>

    <!-- Quick Templates -->
    <div class="template-section" data-aos="fade-up">
        <div class="template-label">⚡ Template Cepat (Klik untuk auto-fill)</div>
        <div class="template-buttons">
            <button type="button" class="template-btn" onclick="applyTemplate('pengumuman')">📢 Pengumuman</button>
            <button type="button" class="template-btn" onclick="applyTemplate('prestasi')">🏆 Prestasi Mahasiswa</button>
            <button type="button" class="template-btn" onclick="applyTemplate('kegiatan')">🎉 Kegiatan Kampus</button>
            <button type="button" class="template-btn" onclick="applyTemplate('akademik')">🎓 Info Akademik</button>
            <button type="button" class="template-btn" onclick="applyTemplate('riset')">🔬 Penelitian</button>
            <button type="button" class="template-btn" onclick="applyTemplate('wisuda')">🎓 Wisuda</button>
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
                        placeholder="Tulis judul berita yang menarik..." required maxlength="150"
                        value="<?= sanitize($edit['judul'] ?? '') ?>">
                    <div class="judul-counter" id="judulCounter">
                        <span><span id="judulCount"><?= mb_strlen($edit['judul'] ?? '') ?></span>/150 karakter</span>
                    </div>
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
                        placeholder="Ringkasan singkat yang menarik pembaca... (akan muncul di list berita dan social media)"><?= sanitize($edit['excerpt'] ?? '') ?></textarea>
                    <div class="smart-suggestions" id="excerptSuggestions"></div>
                </div>

                <!-- Konten dengan Rich Toolbar -->
                <div class="editor-card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 1.5rem 1.5rem 0.5rem;">
                        <label class="field-label">
                            <span>📄 Konten Berita *</span>
                            <div class="content-stats">
                                <span>📝 <span id="wordCount">0</span> kata</span>
                                <span>⏱️ <span id="readTime">0</span> menit baca</span>
                                <span>📊 <span id="charCount">0</span> karakter</span>
                            </div>
                        </label>
                    </div>
                    <div class="rich-toolbar">
                        <button type="button" class="tb-btn" onclick="execCmd('bold')" title="Bold (Ctrl+B)"><b>B</b></button>
                        <button type="button" class="tb-btn" onclick="execCmd('italic')" title="Italic (Ctrl+I)"><i>I</i></button>
                        <button type="button" class="tb-btn" onclick="execCmd('underline')" title="Underline (Ctrl+U)"><u>U</u></button>
                        <button type="button" class="tb-btn" onclick="execCmd('strikeThrough')" title="Strikethrough"><s>S</s></button>
                        <div class="tb-divider"></div>
                        <button type="button" class="tb-btn" onclick="execCmd('formatBlock','<h2>')" title="Heading 2">H2</button>
                        <button type="button" class="tb-btn" onclick="execCmd('formatBlock','<h3>')" title="Heading 3">H3</button>
                        <button type="button" class="tb-btn" onclick="execCmd('formatBlock','<h4>')" title="Heading 4">H4</button>
                        <button type="button" class="tb-btn" onclick="execCmd('formatBlock','<p>')" title="Paragraph">P</button>
                        <div class="tb-divider"></div>
                        <button type="button" class="tb-btn" onclick="execCmd('insertUnorderedList')" title="Bullet List">• List</button>
                        <button type="button" class="tb-btn" onclick="execCmd('insertOrderedList')" title="Numbered List">1. List</button>
                        <button type="button" class="tb-btn" onclick="execCmd('formatBlock','<blockquote>')" title="Quote">❝ Quote</button>
                        <div class="tb-divider"></div>
                        <button type="button" class="tb-btn" onclick="insertLink()" title="Link">🔗</button>
                        <button type="button" class="tb-btn" onclick="insertHR()" title="Horizontal Line">―</button>
                        <button type="button" class="tb-btn" onclick="insertCodeBlock()" title="Code Block">{ }</button>
                        <button type="button" class="tb-btn" onclick="execCmd('removeFormat')" title="Clear Format">🧹</button>
                    </div>
                    <textarea name="konten" id="kontenInput" class="konten-textarea" required
                        placeholder="Tulis konten berita di sini...&#10;&#10;Tips:&#10;• Gunakan toolbar di atas untuk formatting&#10;• HTML sederhana diizinkan: p, strong, em, ul, li, h2-h4, blockquote, a&#10;• Minimal 300 kata untuk SEO optimal"><?= sanitize($edit['konten'] ?? '') ?></textarea>
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
                    <?php if (!empty($popular_tags)): ?>
                    <div style="margin-top: 0.75rem;">
                        <div style="font-size: 0.72rem; color: var(--text-muted); font-weight: 600; margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.05em;">🔥 Tag Populer:</div>
                        <div class="tag-suggestions">
                            <?php foreach (array_slice($popular_tags, 0, 10) as $tag): ?>
                                <span class="tag-suggestion" onclick="addTag('<?= sanitize($tag) ?>')"><?= sanitize($tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Secondary Actions -->
                <div class="secondary-actions">
                    <?php if ($edit && $id > 0): ?>
                    <a href="?duplicate=<?= $id ?>" class="secondary-btn" onclick="return confirm('Duplikasi berita ini sebagai draft baru?')">
                        📋 Duplikasi
                    </a>
                    <?php endif; ?>
                    <a href="berita.php" class="secondary-btn">← Kembali</a>
                    <button type="reset" class="secondary-btn" onclick="return confirm('Reset semua field?')">🔄 Reset</button>
                </div>

                <!-- Shortcuts Legend -->
                <div class="shortcuts-legend">
                    <span>💡 Shortcuts Editor:</span>
                    <span><kbd>Ctrl</kbd>+<kbd>B</kbd> Bold</span>
                    <span><kbd>Ctrl</kbd>+<kbd>I</kbd> Italic</span>
                    <span><kbd>Ctrl</kbd>+<kbd>U</kbd> Underline</span>
                    <span><kbd>Tab</kbd> Indent</span>
                    <span><kbd>Ctrl</kbd>+<kbd>S</kbd> Save</span>
                    <span><kbd>Ctrl</kbd>+<kbd>P</kbd> Preview</span>
                    <span><kbd>Esc</kbd> Close Preview</span>
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
                        <select name="kategori" class="styled-select" id="kategoriSelect">
                            <?php foreach (['Akademik','Pengumuman','Prestasi','Kegiatan','Riset','Umum'] as $k): ?>
                            <option value="<?= $k ?>" <?= ($edit['kategori'] ?? 'Umum') === $k ? 'selected' : '' ?>>
                                <?= $k === 'Akademik' ? '🎓' : ($k === 'Prestasi' ? '🏆' : ($k === 'Pengumuman' ? '📢' : ($k === 'Kegiatan' ? '🎉' : ($k === 'Riset' ? '🔬' : '📌')))) ?>
                                <?= $k ?>
                            </option>
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
                            <div class="upload-hints">
                                <span class="upload-hint">🖼️ JPG/PNG/WEBP/GIF</span>
                                <span class="upload-hint">📦 Max 5MB</span>
                                <span class="upload-hint">📐 16:9 ideal</span>
                            </div>
                        </div>
                        <div class="upload-preview" id="uploadPreview" style="display:none">
                            <img id="previewImg" src="" alt="">
                            <button type="button" class="upload-remove" onclick="removePreview()">✕ Hapus</button>
                        </div>
                    </div>
                    <div class="aspect-warning" id="aspectWarning">
                        ⚠️ <span>Gambar sebaiknya rasio 16:9 untuk tampilan terbaik</span>
                    </div>
                    <div id="imageDim" style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.35rem; display: none;"></div>
                    <?php if (!empty($edit['gambar'])): ?>
                        <div class="current-image">
                            <label class="field-label">Gambar saat ini:</label>
                            <img src="<?= asset('uploads/' . basename($edit['gambar'])) ?>" alt="">
                            <small>Upload gambar baru untuk mengganti</small>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- SEO Score Card -->
                <div class="editor-card seo-card">
                    <h3 class="card-title">🔍 SEO Score</h3>
                    <div class="seo-score-box">
                        <div class="seo-score-circle">
                            <svg viewBox="0 0 36 36">
                                <circle cx="18" cy="18" r="15.915" class="ring-bg"/>
                                <circle cx="18" cy="18" r="15.915" class="ring-fill" id="seoRing" style="stroke-dasharray: 0, 100"/>
                            </svg>
                            <div class="seo-score-value" id="seoScoreValue">0</div>
                        </div>
                        <div class="seo-score-info">
                            <div class="seo-score-label" id="seoScoreLabel">Analisis SEO</div>
                            <div class="seo-score-text" id="seoScoreText">Isi form untuk melihat skor</div>
                        </div>
                    </div>
                    <ul class="seo-checklist" id="seoChecklist">
                        <li>⏳ Menunggu data...</li>
                    </ul>
                </div>

                <!-- Tips Card -->
                <div class="editor-card tips-card">
                    <h3 class="card-title">💡 Tips Menulis Berita</h3>
                    <ul class="tips-list">
                        <li>✓ Judul 50-70 karakter (optimal SEO)</li>
                        <li>✓ Excerpt 150-160 karakter</li>
                        <li>✓ Minimal 300 kata untuk konten</li>
                        <li>✓ Gunakan heading H2, H3 untuk struktur</li>
                        <li>✓ Tambahkan gambar berkualitas (16:9)</li>
                        <li>✓ Tulis excerpt yang menarik pembaca</li>
                        <li>✓ Gunakan 3-5 tags yang relevan</li>
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
            <span>👁️ Preview Tampilan Berita</span>
            <button class="preview-close" onclick="closePreview()">✕</button>
        </div>
        <div class="preview-tabs">
            <button class="preview-tab active" onclick="switchPreviewTab('article', this)">📰 Artikel</button>
            <button class="preview-tab" onclick="switchPreviewTab('social', this)">🔗 Social Media</button>
            <button class="preview-tab" onclick="switchPreviewTab('mobile', this)">📱 Mobile</button>
        </div>
        <div class="preview-body">
            <!-- Article Preview -->
            <div id="tab-article">
                <article class="preview-article">
                    <div class="preview-badge" id="previewBadge">Kategori</div>
                    <h1 class="preview-title" id="previewTitle">Judul berita akan muncul di sini...</h1>
                    <div class="preview-meta">
                        <span>✍️ Oleh <strong id="previewAuthor">Penulis</strong></span> •
                        <span>📅 <?= date('d F Y') ?></span> •
                        <span>⏱️ <span id="previewReadTime">0</span> menit baca</span>
                    </div>
                    <img class="preview-image" id="previewImage" src="" alt="" style="display:none">
                    <div class="preview-excerpt" id="previewExcerpt" style="display:none"></div>
                    <div class="preview-content" id="previewContent"></div>
                </article>
            </div>

            <!-- Social Preview -->
            <div id="tab-social" style="display:none">
                <div class="social-preview-grid">
                    <!-- Facebook/OG -->
                    <div class="social-preview-card">
                        <div class="social-preview-header">📘 Facebook / LinkedIn</div>
                        <div class="social-preview-body">
                            <div class="og-card">
                                <img class="og-image" id="ogImage" src="" alt="" style="display:none">
                                <div class="og-content">
                                    <div class="og-domain">fkip-unimof.ac.id</div>
                                    <div class="og-title" id="ogTitle">Judul Berita</div>
                                    <div class="og-description" id="ogDescription">Deskripsi berita...</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Twitter -->
                    <div class="social-preview-card">
                        <div class="social-preview-header">🐦 Twitter Card</div>
                        <div class="social-preview-body">
                            <div class="og-card">
                                <img class="og-image" id="twitterImage" src="" alt="" style="display:none">
                                <div class="og-content">
                                    <div class="og-title" id="twitterTitle">Judul Berita</div>
                                    <div class="og-description" id="twitterDescription">Deskripsi berita...</div>
                                    <div class="og-domain">fkip-unimof.ac.id</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Meta Tags Preview -->
                <div style="margin-top: 1.5rem; padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border);">
                    <div style="font-size: 0.78rem; font-weight: 700; margin-bottom: 0.75rem; color: var(--text-primary);">🏷️ Meta Tags yang Akan Digenerate:</div>
                    <pre style="font-size: 0.72rem; color: var(--text-muted); background: var(--bg-primary); padding: 0.75rem; border-radius: 6px; overflow-x: auto; margin: 0; border: 1px solid var(--border);"><code id="metaTagsPreview">&lt;!-- Meta tags akan muncul di sini --&gt;</code></pre>
                </div>
            </div>

            <!-- Mobile Preview -->
            <div id="tab-mobile" style="display:none">
                <div class="mobile-preview-frame">
                    <div class="mobile-notch"></div>
                    <div class="mobile-article">
                        <div style="font-size: 0.7rem; color: var(--primary); font-weight: 700; text-transform: uppercase; margin-bottom: 0.5rem;" id="mobileCategory">Kategori</div>
                        <h1 id="mobileTitle" style="font-size: 1.5rem; margin-bottom: 0.75rem; line-height: 1.3;">Judul Berita</h1>
                        <div style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 1rem; display: flex; gap: 0.75rem;">
                            <span id="mobileAuthor">✍️ Penulis</span>
                            <span><?= date('d M Y') ?></span>
                        </div>
                        <img id="mobileImage" src="" style="width: 100%; border-radius: 8px; margin-bottom: 1rem; display:none;">
                        <p id="mobileContent" style="font-size: 0.95rem; line-height: 1.6; color: var(--text-secondary);">Konten berita...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Helper -->
<div class="toast-helper" id="toastHelper"></div>

<script>
// ===== DATA =====
const popularTags = <?= json_encode($popular_tags) ?>;

// ===== TEMPLATES =====
const templates = {
    pengumuman: {
        kategori: 'Pengumuman',
        judul: 'Pengumuman Penting: [Topik Pengumuman]',
        excerpt: 'FKIP UNIMOF mengumumkan [topik] kepada seluruh mahasiswa dan civitas akademika. Silakan simak informasi lengkap berikut ini.',
        konten: '<h2>Pengumuman Resmi</h2>\n<p>Dengan hormat, kami sampaikan informasi penting mengenai [topik pengumuman] kepada seluruh mahasiswa FKIP UNIMOF.</p>\n\n<h3>Detail Informasi</h3>\n<ul>\n<li>Perihal: [jelaskan]</li>\n<li>Tanggal: [tanggal]</li>\n<li>Tempat: [tempat]</li>\n</ul>\n\n<h3>Ketentuan</h3>\n<p>Mahasiswa diharapkan untuk [ketentuan yang harus dipenuhi].</p>\n\n<blockquote>Informasi lebih lanjut dapat menghubungi bagian akademik.</blockquote>\n\n<p>Demikian pengumuman ini disampaikan. Atas perhatiannya kami ucapkan terima kasih.</p>',
        tags: 'pengumuman,informasi,mahasiswa'
    },
    prestasi: {
        kategori: 'Prestasi',
        judul: 'Mahasiswa FKIP UNIMOF Raih [Prestasi] di [Event]',
        excerpt: 'Kebanggaan FKIP! Mahasiswa berhasil meraih [prestasi] dalam [event] tingkat [nasional/internasional].',
        konten: '<h2>Prestasi Membanggakan</h2>\n<p>FKIP UNIMOF kembali menorehkan prestasi gemilang melalui mahasiswanya yang berhasil meraih [prestasi] dalam [event] yang diselenggarakan pada [tanggal].</p>\n\n<h3>Tentang Prestasi</h3>\n<p>[Nama mahasiswa], mahasiswa program studi [prodi] semester [semester], berhasil [detail prestasi]. Prestasi ini diraih setelah melalui proses seleksi ketat yang diikuti oleh [jumlah] peserta dari seluruh Indonesia.</p>\n\n<h3>Proses Pencapaian</h3>\n<p>Perjalanan menuju prestasi ini tidaklah mudah. [Ceritakan proses persiapan dan perjuangan].</p>\n\n<blockquote>"[Quote dari mahasiswa tentang pengalaman dan motivasinya]" - [Nama Mahasiswa]</blockquote>\n\n<h3>Apresiasi</h3>\n<p>Dekan FKIP UNIMOF memberikan apresiasi tinggi atas pencapaian ini dan berharap dapat memotivasi mahasiswa lainnya untuk terus berprestasi.</p>',
        tags: 'prestasi,mahasiswa,kompetisi,penghargaan'
    },
    kegiatan: {
        kategori: 'Kegiatan',
        judul: 'FKIP UNIMOF Gelar [Nama Kegiatan] yang Diikuti [Jumlah] Peserta',
        excerpt: 'Kegiatan [nama] sukses dilaksanakan dengan antusiasme tinggi dari peserta. Simak liputan lengkapnya.',
        konten: '<h2>Liputan Kegiatan</h2>\n<p>FKIP UNIMOF sukses menyelenggarakan [nama kegiatan] pada [tanggal] di [tempat]. Kegiatan ini diikuti oleh [jumlah] peserta dari [asal peserta].</p>\n\n<h3>Jalannya Kegiatan</h3>\n<p>Acara dibuka oleh [pejabat yang membuka] yang dalam sambutannya menyampaikan [pesan penting].</p>\n\n<h3>Rangkaian Acara</h3>\n<ol>\n<li>Sesi 1: [materi kegiatan]</li>\n<li>Sesi 2: [materi kegiatan]</li>\n<li>Sesi 3: [materi kegiatan]</li>\n</ol>\n\n<h3>Testimoni Peserta</h3>\n<blockquote>"[Testimoni peserta tentang kegiatan]"</blockquote>\n\n<h3>Penutup</h3>\n<p>Kegiatan ditutup dengan [acara penutup] dan diharapkan dapat memberikan manfaat bagi seluruh peserta.</p>',
        tags: 'kegiatan,event,civitas,fkip'
    },
    akademik: {
        kategori: 'Akademik',
        judul: 'Informasi Akademik: [Topik]',
        excerpt: 'Informasi penting terkait [topik akademik] untuk mahasiswa FKIP UNIMOF. Simak detail lengkapnya.',
        konten: '<h2>Informasi Akademik</h2>\n<p>Disampaikan kepada seluruh mahasiswa FKIP UNIMOF mengenai [topik akademik].</p>\n\n<h3>Detail Informasi</h3>\n<ul>\n<li><strong>Perihal:</strong> [jelaskan]</li>\n<li><strong>Berlaku untuk:</strong> [semua mahasiswa/prodi tertentu/angkatan]</li>\n<li><strong>Tanggal efektif:</strong> [tanggal]</li>\n</ul>\n\n<h3>Ketentuan</h3>\n<p>Mahasiswa diharapkan untuk [ketentuan yang harus dipenuhi].</p>\n\n<h3>Kontak</h3>\n<p>Untuk informasi lebih lanjut, silakan hubungi:</p>\n<ul>\n<li>Bagian Akademik: [kontak]</li>\n<li>Email: [email]</li>\n</ul>',
        tags: 'akademik,informasi,kuliah,mahasiswa'
    },
    riset: {
        kategori: 'Riset',
        judul: 'Penelitian [Dosen/Mahasiswa] FKIP: [Judul Penelitian]',
        excerpt: 'Hasil penelitian terbaru dari [peneliti] tentang [topik]. Temuan ini memberikan kontribusi penting bagi [bidang].',
        konten: '<h2>Hasil Penelitian</h2>\n<p>[Nama peneliti], [jabatan] dari FKIP UNIMOF, telah menyelesaikan penelitian berjudul "[judul penelitian]".</p>\n\n<h3>Latar Belakang</h3>\n<p>Penelitian ini dilatarbelakangi oleh [masalah yang diteliti]. [Peneliti] melihat adanya [gap penelitian] yang perlu dikaji lebih lanjut.</p>\n\n<h3>Metodologi</h3>\n<p>Penelitian ini menggunakan metode [metode penelitian] dengan subjek [subjek penelitian] yang dilakukan selama [durasi].</p>\n\n<h3>Temuan Utama</h3>\n<ol>\n<li>Temuan pertama: [jelaskan]</li>\n<li>Temuan kedua: [jelaskan]</li>\n<li>Temuan ketiga: [jelaskan]</li>\n</ol>\n\n<h3>Implikasi</h3>\n<p>Hasil penelitian ini memberikan implikasi penting bagi [bidang terkait] dan diharapkan dapat menjadi referensi bagi penelitian selanjutnya.</p>\n\n<blockquote>"[Quote dari peneliti tentang signifikansi temuan]" - [Nama Peneliti]</blockquote>',
        tags: 'penelitian,riset,jurnal,akademik'
    },
    wisuda: {
        kategori: 'Kegiatan',
        judul: 'FKIP UNIMOF Lepas [Jumlah] Wisudawan pada Wisuda Periode [Bulan Tahun]',
        excerpt: 'Sebanyak [jumlah] wisudawan FKIP UNIMOF resmi diwisuda. [Jumlah] di antaranya meraih predikat cumlaude.',
        konten: '<h2>Wisuda Periode [Bulan Tahun]</h2>\n<p>FKIP UNIMOF menyelenggarakan wisuda periode [bulan tahun] pada [tanggal] di [tempat]. Pada wisuda kali ini, sebanyak [jumlah] mahasiswa resmi menyandang gelar sarjana.</p>\n\n<h3>Statistik Wisudawan</h3>\n<ul>\n<li><strong>Total wisudawan:</strong> [jumlah]</li>\n<li><strong>Predikat Cumlaude:</strong> [jumlah] orang</li>\n<li><strong>IPK Tertinggi:</strong> [IPK] ([nama])</li>\n<li><strong>Program Studi:</strong> [daftar prodi]</li>\n</ul>\n\n<h3>Pesan Rektor</h3>\n<blockquote>"[Pesan rektor untuk wisudawan]"</blockquote>\n\n<h3>Pesan Dekan FKIP</h3>\n<blockquote>"[Pesan dekan untuk wisudawan]"</blockquote>\n\n<h3>Wisudawan Berprestasi</h3>\n<p>Beberapa wisudawan meraih predikat istimewa:</p>\n<ol>\n<li>[Nama] - IPK [IPK] - [prestasi]</li>\n<li>[Nama] - IPK [IPK] - [prestasi]</li>\n</ol>\n\n<p>Selamat kepada seluruh wisudawan! Semoga ilmu yang didapat bermanfaat bagi masyarakat dan bangsa.</p>',
        tags: 'wisuda,kelulusan,sarjana,mahasiswa'
    }
};

function applyTemplate(key) {
    const t = templates[key];
    if (!t) return;

    if (t.judul) document.getElementById('judulInput').value = t.judul;
    if (t.excerpt) document.getElementById('excerptInput').value = t.excerpt;
    if (t.konten) document.getElementById('kontenInput').value = t.konten;
    if (t.kategori) document.getElementById('kategoriSelect').value = t.kategori;
    if (t.tags) {
        tags = t.tags.split(',').map(t => t.trim()).filter(t => t);
        renderTags();
    }

    // Update all counters
    document.getElementById('judulCount').textContent = (t.judul || '').length;
    document.getElementById('excerptCount').textContent = (t.excerpt || '').length;
    updateWordCount();
    updateProgress();
    updateSEO();
    triggerAutosave();

    showToast('Template Diterapkan', `Template "${key}" berhasil diisi`, 'success');
}

// ===== SLUG GENERATION =====
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
    document.getElementById('judulCount').textContent = this.value.length;
    const counter = document.getElementById('judulCounter');
    counter.classList.toggle('warn', this.value.length > 100 && this.value.length <= 150);
    counter.classList.toggle('danger', this.value.length > 150);
    triggerAutosave();
    updateSEO();
    updateProgress();
});

function toggleCustomSlug() {
    const isShown = customSlug.style.display === 'block';
    customSlug.style.display = isShown ? 'none' : 'block';
    if (!isShown) customSlug.focus();
}
customSlug.addEventListener('input', function() {
    slugPreview.textContent = slugify(this.value) || slugify(judulInput.value);
});

// ===== WORD COUNTER & READ TIME =====
const kontenInput = document.getElementById('kontenInput');
const wordCountEl = document.getElementById('wordCount');
const readTimeEl = document.getElementById('readTime');
const charCountEl = document.getElementById('charCount');

function updateWordCount() {
    const text = kontenInput.value.trim();
    const words = text ? text.replace(/<[^>]+>/g, '').split(/\s+/).filter(w => w).length : 0;
    const chars = text.length;
    wordCountEl.textContent = words;
    charCountEl.textContent = chars;
    readTimeEl.textContent = Math.max(1, Math.ceil(words / 200));
    triggerAutosave();
    updateSEO();
    updateProgress();
}
kontenInput.addEventListener('input', updateWordCount);
updateWordCount();

// ===== EXCERPT CHAR COUNTER =====
const excerptInput = document.getElementById('excerptInput');
const excerptCount = document.getElementById('excerptCount');
excerptInput.addEventListener('input', function() {
    const len = this.value.length;
    excerptCount.textContent = len;
    const parent = excerptCount.parentElement;
    parent.classList.toggle('warn', len > 400 && len <= 500);
    parent.classList.toggle('danger', len > 500);
    triggerAutosave();
    updateSEO();
    updateProgress();
});

// ===== SMART EXCERPT SUGGESTIONS =====
function checkExcerptSuggestions() {
    const kategori = document.getElementById('kategoriSelect').value;
    const excerpt = excerptInput.value.toLowerCase();
    const sugBox = document.getElementById('excerptSuggestions');

    const suggestions = {
        'Prestasi': ['🏆 "Raih prestasi gemilang..."', '⭐ "Membanggakan, mahasiswa..."', '🎯 "Kembali torehkan prestasi..."'],
        'Pengumuman': ['📢 "Informasi penting untuk..."', '📋 "Disampaikan kepada..."', '⚠️ "Perhatian seluruh mahasiswa..."'],
        'Kegiatan': ['🎉 "Sukses digelar..."', '✨ "Antusiasme tinggi..."', '🌟 "Kegiatan meriah..."'],
        'Akademik': ['🎓 "Informasi akademik..."', '📚 "Penting untuk mahasiswa..."', '📝 "Terkait proses..."'],
        'Riset': ['🔬 "Penelitian terbaru..."', '📊 "Hasil studi menunjukkan..."', '💡 "Temuan penting..."']
    };

    if (suggestions[kategori] && excerpt.length < 50) {
        sugBox.innerHTML = `
            <div class="suggestion-header">💡 Saran excerpt untuk ${kategori}:</div>
            ${suggestions[kategori].map(s => `<div class="suggestion-item" onclick="document.getElementById('excerptInput').value = '${s.replace(/"/g, '\\\'')}'; excerptInput.dispatchEvent(new Event('input'));">${s}</div>`).join('')}
        `;
        sugBox.classList.add('show');
    } else {
        sugBox.classList.remove('show');
    }
}

document.getElementById('kategoriSelect').addEventListener('change', checkExcerptSuggestions);
excerptInput.addEventListener('input', checkExcerptSuggestions);

// ===== RICH TEXT COMMANDS =====
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
        'strikeThrough': ['<s>', '</s>'],
        'formatBlock': {
            '<h2>': ['<h2>', '</h2>'],
            '<h3>': ['<h3>', '</h3>'],
            '<h4>': ['<h4>', '</h4>'],
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

function insertCodeBlock() {
    const ta = kontenInput;
    const start = ta.selectionStart;
    const end = ta.selectionEnd;
    const text = ta.value;
    const selected = text.substring(start, end) || 'code here';
    const tag = `<pre><code>${selected}</code></pre>`;
    ta.value = text.substring(0, start) + tag + text.substring(end);
    ta.focus();
    updateWordCount();
}

// ===== TAGS INPUT =====
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
    updateSEO();
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

// ===== UPLOAD DRAG & DROP =====
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
        showToast('Error', 'File harus berupa gambar!', 'error');
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        showToast('Error', 'Ukuran maksimal 5MB!', 'error');
        return;
    }
    const reader = new FileReader();
    reader.onload = e => {
        previewImg.src = e.target.result;
        uploadPlaceholder.style.display = 'none';
        uploadPreview.style.display = 'block';

        // Check aspect ratio
        const img = new Image();
        img.onload = () => {
            const ratio = img.width / img.height;
            const aspectWarning = document.getElementById('aspectWarning');
            const imageDim = document.getElementById('imageDim');
            imageDim.textContent = `📐 ${img.width} × ${img.height} px (${(file.size / 1024).toFixed(1)} KB)`;
            imageDim.style.display = 'block';

            if (Math.abs(ratio - 16/9) > 0.3) {
                aspectWarning.classList.add('show');
            } else {
                aspectWarning.classList.remove('show');
            }
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
    updateSEO();
}

window.removePreview = function() {
    gambarInput.value = '';
    previewImg.src = '';
    uploadPlaceholder.style.display = 'block';
    uploadPreview.style.display = 'none';
    document.getElementById('aspectWarning').classList.remove('show');
    document.getElementById('imageDim').style.display = 'none';
    updateSEO();
};

// ===== PROGRESS INDICATOR =====
function updateProgress() {
    const fields = [
        { el: 'judulInput', weight: 25 },
        { el: 'excerptInput', weight: 15 },
        { el: 'kontenInput', weight: 35 },
        { el: 'kategoriSelect', weight: 10 },
        { check: () => tags.length > 0, weight: 10 },
        { check: () => document.getElementById('previewImg').src || document.querySelector('.current-image img'), weight: 5 }
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

// ===== SEO SCORE CALCULATOR =====
function updateSEO() {
    const judul = judulInput.value.trim();
    const excerpt = excerptInput.value.trim();
    const konten = kontenInput.value.replace(/<[^>]+>/g, '').trim();
    const words = konten ? konten.split(/\s+/).filter(w => w).length : 0;
    const hasImage = document.getElementById('previewImg').src || document.querySelector('.current-image img');

    let score = 0;
    const checks = [];

    // Judul (25 points)
    if (judul.length >= 30 && judul.length <= 70) {
        score += 25;
        checks.push('✅ Judul optimal (30-70 karakter)');
    } else if (judul.length >= 20) {
        score += 15;
        checks.push('⚠️ Judul cukup baik, tapi bisa lebih optimal');
    } else if (judul.length > 0) {
        score += 5;
        checks.push('❌ Judul terlalu pendek (< 20 karakter)');
    } else {
        checks.push('❌ Judul belum diisi');
    }

    // Excerpt (20 points)
    if (excerpt.length >= 120 && excerpt.length <= 160) {
        score += 20;
        checks.push('✅ Excerpt optimal (120-160 karakter)');
    } else if (excerpt.length >= 80) {
        score += 12;
        checks.push('⚠️ Excerpt cukup baik');
    } else if (excerpt.length > 0) {
        score += 5;
        checks.push('❌ Excerpt terlalu pendek');
    } else {
        checks.push('❌ Excerpt belum diisi');
    }

    // Konten (25 points)
    if (words >= 500) {
        score += 25;
        checks.push('✅ Konten panjang (500+ kata)');
    } else if (words >= 300) {
        score += 18;
        checks.push('✅ Konten cukup panjang (300+ kata)');
    } else if (words >= 100) {
        score += 8;
        checks.push('⚠️ Konten kurang panjang');
    } else if (words > 0) {
        score += 3;
        checks.push('❌ Konten terlalu pendek');
    } else {
        checks.push('❌ Konten belum diisi');
    }

    // Heading structure (15 points)
    const hasH2 = /<h2>/i.test(kontenInput.value);
    const hasH3 = /<h3>/i.test(kontenInput.value);
    if (hasH2 && hasH3) {
        score += 15;
        checks.push('✅ Struktur heading baik (H2 + H3)');
    } else if (hasH2 || hasH3) {
        score += 8;
        checks.push('⚠️ Tambahkan heading H2 dan H3');
    } else {
        checks.push('❌ Belum ada heading (gunakan H2, H3)');
    }

    // Gambar (10 points)
    if (hasImage) {
        score += 10;
        checks.push('✅ Ada gambar');
    } else {
        checks.push('❌ Belum ada gambar');
    }

    // Tags (5 points)
    if (tags.length >= 3) {
        score += 5;
        checks.push('✅ Tags cukup (3+ tags)');
    } else if (tags.length > 0) {
        score += 2;
        checks.push('⚠️ Tambahkan lebih banyak tags');
    } else {
        checks.push('❌ Belum ada tags');
    }

    score = Math.min(100, score);

    // Update UI
    document.getElementById('seoRing').style.strokeDasharray = score + ', 100';
    document.getElementById('seoScoreValue').textContent = score;

    let label = 'Buruk', color = '#ef4444', text = 'Perlu perbaikan signifikan';
    if (score >= 80) { label = 'Excellent'; color = '#10b981'; text = 'SEO sangat baik!'; }
    else if (score >= 60) { label = 'Baik'; color = '#3b82f6'; text = 'SEO baik, bisa ditingkatkan'; }
    else if (score >= 40) { label = 'Cukup'; color = '#f59e0b'; text = 'Perlu beberapa perbaikan'; }

    document.getElementById('seoScoreLabel').textContent = label;
    document.getElementById('seoScoreLabel').style.color = color;
    document.getElementById('seoScoreText').textContent = text;
    document.getElementById('seoRing').style.stroke = color;
    document.getElementById('seoScoreValue').style.color = color;

    // Update checklist
    document.getElementById('seoChecklist').innerHTML = checks.map(c => `<li>${c}</li>`).join('');
}

// ===== AUTO-SAVE =====
const storageKey = 'fkip_draft_' + (<?= $id ?> || 'new');
let autoSaveTimer;

function triggerAutosave() {
    clearTimeout(autoSaveTimer);
    const indicator = document.getElementById('autosaveStatus');
    indicator.classList.add('saving');
    indicator.classList.remove('saved');
    indicator.querySelector('.as-text').textContent = 'Menyimpan...';

    autoSaveTimer = setTimeout(() => {
        const data = {
            judul: judulInput.value,
            excerpt: excerptInput.value,
            konten: kontenInput.value,
            kategori: document.getElementById('kategoriSelect').value,
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
                if (data.kategori) document.getElementById('kategoriSelect').value = data.kategori;
                if (data.tags) { tags = data.tags.split(',').filter(t => t); renderTags(); }
                judulInput.dispatchEvent(new Event('input'));
                excerptInput.dispatchEvent(new Event('input'));
                updateWordCount();
                updateSEO();
                updateProgress();
                showToast('Draft Dimuat', 'Data draft berhasil dimuat', 'success');
            } else {
                localStorage.removeItem(storageKey);
            }
        }
    } catch(e) {}
    <?php endif; ?>
})();

// Clear autosave after successful submit
document.getElementById('editorForm').addEventListener('submit', function(e) {
    const judul = judulInput.value.trim();
    const konten = kontenInput.value.trim();

    if (!judul) {
        e.preventDefault();
        judulInput.classList.add('error');
        showToast('Validasi Error', 'Judul wajib diisi!', 'error');
        return;
    }
    if (!konten) {
        e.preventDefault();
        kontenInput.classList.add('error');
        showToast('Validasi Error', 'Konten wajib diisi!', 'error');
        return;
    }

    const btn = document.getElementById('submitBtn');
    btn.classList.add('loading');
    btn.innerHTML = '<span class="spinner"></span>';

    setTimeout(() => {
        try { localStorage.removeItem(storageKey); } catch(e) {}
    }, 500);
});

// ===== PREVIEW MODAL =====
function openPreview() {
    const judul = judulInput.value || 'Judul berita akan muncul di sini...';
    const excerpt = excerptInput.value;
    const kategori = document.getElementById('kategoriSelect').value;
    const penulis = document.querySelector('[name="penulis"]').value || 'Humas FKIP';

    document.getElementById('previewTitle').textContent = judul;
    document.getElementById('previewBadge').textContent = kategori;
    document.getElementById('previewAuthor').textContent = penulis;
    document.getElementById('previewReadTime').textContent = readTimeEl.textContent;

    // Image
    const imgSrc = document.getElementById('previewImg').src;
    const previewImage = document.getElementById('previewImage');
    if (imgSrc && imgSrc !== window.location.href) {
        previewImage.src = imgSrc;
        previewImage.style.display = 'block';
    } else {
        previewImage.style.display = 'none';
    }

    // Excerpt
    if (excerpt) {
        document.getElementById('previewExcerpt').textContent = excerpt;
        document.getElementById('previewExcerpt').style.display = 'block';
    } else {
        document.getElementById('previewExcerpt').style.display = 'none';
    }

    // Content
    let rawContent = kontenInput.value;
    let safeContent = rawContent.replace(/\n/g, '<br>');
    document.getElementById('previewContent').innerHTML = safeContent || '<p style="color:var(--text-muted)"><em>Belum ada konten...</em></p>';

    // Social preview
    document.getElementById('ogTitle').textContent = judul;
    document.getElementById('ogDescription').textContent = excerpt || 'Baca selengkapnya di FKIP UNIMOF';
    document.getElementById('twitterTitle').textContent = judul;
    document.getElementById('twitterDescription').textContent = excerpt || 'Baca selengkapnya di FKIP UNIMOF';

    const ogImage = document.getElementById('ogImage');
    const twitterImage = document.getElementById('twitterImage');
    if (imgSrc && imgSrc !== window.location.href) {
        ogImage.src = imgSrc;
        ogImage.style.display = 'block';
        twitterImage.src = imgSrc;
        twitterImage.style.display = 'block';
    } else {
        ogImage.style.display = 'none';
        twitterImage.style.display = 'none';
    }

    // Meta tags preview
    document.getElementById('metaTagsPreview').textContent = `<meta property="og:title" content="${judul}">
<meta property="og:description" content="${excerpt || 'Baca selengkapnya...'}">
<meta property="og:image" content="${imgSrc || 'default.jpg'}">
<meta property="og:type" content="article">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="${judul}">
<meta name="twitter:description" content="${excerpt || 'Baca selengkapnya...'}">`;

    // Mobile preview
    document.getElementById('mobileTitle').textContent = judul;
    document.getElementById('mobileCategory').textContent = kategori;
    document.getElementById('mobileAuthor').textContent = '✍️ ' + penulis;
    document.getElementById('mobileContent').textContent = excerpt || kontenInput.value.replace(/<[^>]+>/g, '').substring(0, 300) + '...';

    const mobileImage = document.getElementById('mobileImage');
    if (imgSrc && imgSrc !== window.location.href) {
        mobileImage.src = imgSrc;
        mobileImage.style.display = 'block';
    } else {
        mobileImage.style.display = 'none';
    }

    document.getElementById('previewModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closePreview() {
    document.getElementById('previewModal').classList.remove('open');
    document.body.style.overflow = '';
}

function switchPreviewTab(tab, btn) {
    document.querySelectorAll('.preview-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('[id^="tab-"]').forEach(c => c.style.display = 'none');
    btn.classList.add('active');
    document.getElementById('tab-' + tab).style.display = 'block';
}

// ===== TOAST HELPER =====
function showToast(title, message, type = 'info') {
    const toast = document.createElement('div');
    toast.style.cssText = `
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 0.85rem 1.25rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-top: 0.5rem;
        animation: slideIn 0.3s;
        min-width: 280px;
    `;
    const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
    const colors = { success: '#10b981', error: '#ef4444', warning: '#f59e0b', info: '#3b82f6' };
    toast.innerHTML = `
        <span style="font-size: 1.5rem;">${icons[type]}</span>
        <div>
            <div style="font-weight: 700; color: ${colors[type]}; font-size: 0.88rem;">${title}</div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem;">${message}</div>
        </div>
    `;
    document.getElementById('toastHelper').appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        toast.style.transition = 'all 0.3s';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ===== KEYBOARD SHORTCUTS =====
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

// Global shortcuts
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closePreview();
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('editorForm').dispatchEvent(new Event('submit'));
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        openPreview();
    }
});

// ===== INIT =====
updateSEO();
updateProgress();
checkExcerptSuggestions();

console.log('%c✏️ Editor Berita FKIP UNIMOF - Super Extreme', 'color:#dc2626;font-size:16px;font-weight:bold');
console.log('%cShortcuts: Ctrl+S (Save), Ctrl+P (Preview), Ctrl+B/I/U (Format), Esc (Close)', 'color:#64748b');
console.log('%cFitur: Templates, SEO Score, Live Preview, Autosave, Smart Suggestions', 'color:#64748b');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>