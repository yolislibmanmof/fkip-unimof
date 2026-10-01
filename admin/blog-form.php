<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$duplicate_from = (int)($_GET['duplicate'] ?? 0);
$edit = null;

// ===== HELPER LOKAL (folder blog) =====
if (!function_exists('blog_del_img')) {
    function blog_del_img($f) {
        if ($f) { $p = (defined('APP_DIR') ? APP_DIR : dirname(__DIR__, 2)) . '/uploads/blog/' . basename($f); if (is_file($p)) @unlink($p); }
    }
}

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM blog_artikel WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch();
    if (!$edit) { header('Location: blog.php'); exit; }
} elseif ($duplicate_from > 0) {
    $stmt = $pdo->prepare("SELECT * FROM blog_artikel WHERE id = ?");
    $stmt->execute([$duplicate_from]);
    $source = $stmt->fetch();
    if ($source) {
        $edit = $source;
        $edit['id'] = null;
        $edit['judul'] = $source['judul'] . ' (Copy)';
        $edit['slug'] = null;
        $edit['status'] = 'Draft';
        $edit['views'] = 0;
        $edit['likes'] = 0;
        flash_message('info', '📋 Menduplikasi artikel: ' . htmlspecialchars($source['judul']));
    }
}

// ===== REFERENSI: DOSEN & PRODI =====
$dosen_list = []; $prodi_list = [];
try { $dosen_list = $pdo->query("SELECT id, nama, gelar_depan, gelar_belakang FROM dosen WHERE status='Aktif' ORDER BY nama")->fetchAll(); } catch (Exception $e) {}
try { $prodi_list = $pdo->query("SELECT id, nama, singkatan FROM program_studi WHERE status='Aktif' ORDER BY urutan ASC, nama ASC")->fetchAll(); } catch (Exception $e) {}

// ===== POPULAR TAGS (suggestions) =====
$popular_tags = [];
try {
    $rows = $pdo->query("SELECT tags FROM blog_artikel WHERE tags IS NOT NULL AND tags != '' ORDER BY views DESC LIMIT 50")->fetchAll(PDO::FETCH_COLUMN);
    $all = [];
    foreach ($rows as $t) foreach (explode(',', $t) as $tag) { $tag = trim($tag); if ($tag) $all[$tag] = ($all[$tag] ?? 0) + 1; }
    arsort($all);
    $popular_tags = array_slice(array_keys($all), 0, 15);
} catch (Exception $e) { $popular_tags = []; }

// ===== KATEGORI & STATUS BLOG =====
$KATEGORI = ['AI & Teknologi','Tips Riset','Pendidikan','Pengabdian','Opini'];
$STATUS   = ['Draft','Published','Archived'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('error', '❌ Token keamanan tidak valid.');
        header('Location: blog-form.php' . ($id ? "?id=$id" : '')); exit;
    }

    $judul    = trim($_POST['judul'] ?? '');
    $kategori = $_POST['kategori'] ?? 'Pendidikan';
    $status   = $_POST['status'] ?? 'Draft';
    $excerpt  = trim($_POST['excerpt'] ?? '');
    $konten   = clean_html($_POST['konten'] ?? '');
    $tags     = trim($_POST['tags'] ?? '');
    $featured = isset($_POST['is_featured']) ? 1 : 0;
    $dosen_id = (int)($_POST['dosen_id'] ?? 0) ?: null;
    $prodi_id = (int)($_POST['program_studi_id'] ?? 0) ?: null;
    $reading_time = (int)($_POST['reading_time'] ?? 0);

    if (!in_array($kategori, $KATEGORI, true)) $kategori = 'Pendidikan';
    if (!in_array($status, $STATUS, true))     $status = 'Draft';
    if ($reading_time <= 0 || $reading_time > 120) {
        $reading_time = max(1, (int)ceil(str_word_count(strip_tags($konten)) / 200));
    }

    if ($judul === '' || $konten === '') {
        flash_message('error', '❌ Judul dan konten wajib diisi.');
        header('Location: blog-form.php' . ($id ? "?id=$id" : '')); exit;
    }

    // Custom slug
    $custom_slug = trim($_POST['custom_slug'] ?? '');
    $base = $custom_slug !== '' ? generate_slug($custom_slug) : generate_slug($judul);
    $slug = $base; $n = 2;
    while (true) {
        $chk = $pdo->prepare("SELECT id FROM blog_artikel WHERE slug = ? AND id != ?");
        $chk->execute([$slug, $id]);
        if (!$chk->fetchColumn()) break;
        $slug = $base . '-' . $n++;
    }

    // Upload gambar ke uploads/blog/
    $gambar = $edit['gambar'] ?? null;
    if (!empty($_FILES['gambar']['name'])) {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['gambar']['tmp_name']);
        if (!isset($allowed[$mime])) {
            flash_message('error', '❌ Hanya file gambar (JPG, PNG, WEBP, GIF) yang diizinkan.');
            header('Location: blog-form.php' . ($id ? "?id=$id" : '')); exit;
        }
        if ($_FILES['gambar']['size'] > 5 * 1024 * 1024) {
            flash_message('error', '❌ Ukuran gambar maksimal 5MB.');
            header('Location: blog-form.php' . ($id ? "?id=$id" : '')); exit;
        }
        $ext = $allowed[$mime];
        $new_name = 'blog_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dir = (defined('APP_DIR') ? APP_DIR : dirname(__DIR__, 2)) . '/uploads/blog';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (move_uploaded_file($_FILES['gambar']['tmp_name'], $dir . '/' . $new_name)) {
            if ($gambar) blog_del_img($gambar);
            $gambar = $new_name;
        } else {
            flash_message('error', '❌ Gagal menyimpan gambar.');
            header('Location: blog-form.php' . ($id ? "?id=$id" : '')); exit;
        }
    }

    try {
        if ($edit && $id > 0) {
            $pub = $status === 'Published' ? ($edit['published_at'] ?: date('Y-m-d H:i:s')) : ($edit['published_at'] ?? null);
            $pdo->prepare("UPDATE blog_artikel SET dosen_id=?, program_studi_id=?, judul=?, slug=?, excerpt=?, konten=?, gambar=?, kategori=?, status=?, is_featured=?, tags=?, reading_time=?, published_at=? WHERE id=?")
                ->execute([$dosen_id, $prodi_id, $judul, $slug, $excerpt, $konten, $gambar, $kategori, $status, $featured, $tags, $reading_time, $pub, $id]);
            flash_message('success', '✅ Artikel berhasil diperbarui.');
        } else {
            $pub = $status === 'Published' ? date('Y-m-d H:i:s') : null;
            $pdo->prepare("INSERT INTO blog_artikel (dosen_id, program_studi_id, judul, slug, excerpt, konten, gambar, kategori, status, is_featured, tags, reading_time, published_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$dosen_id, $prodi_id, $judul, $slug, $excerpt, $konten, $gambar, $kategori, $status, $featured, $tags, $reading_time, $pub]);
            flash_message('success', '✅ Artikel berhasil ditambahkan.');
        }
    } catch (Exception $e) {
        flash_message('error', '❌ Gagal menyimpan: ' . $e->getMessage());
        header('Location: blog-form.php' . ($id ? "?id=$id" : '')); exit;
    }
    header('Location: blog.php?tab=articles');
    exit;
}

$csrf = generate_csrf_token();
$active_menu = 'blog';
$page_heading = $edit ? 'Edit Artikel' : 'Tambah Artikel';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Ide & Wawasan', 'blog.php'], [$page_heading, null]];
require __DIR__ . '/includes/header.php';
?>

<style>
/* ===== EXTREME ULTIMATE EDITOR STYLES (Blog) ===== */
.editor-wrap { max-width: 1500px; margin: 0 auto; }
.progress-indicator { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 1rem 1.5rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-sm); }
.progress-bar-wrap { flex: 1; height: 8px; background: var(--bg-tertiary); border-radius: 999px; overflow: hidden; }
.progress-bar-fill { height: 100%; background: linear-gradient(90deg, #ef4444 0%, #f59e0b 50%, #10b981 100%); transition: width 0.4s ease; border-radius: 999px; }
.progress-stats { display: flex; align-items: center; gap: 0.75rem; font-size: 0.85rem; font-weight: 600; }
.progress-percent { color: var(--primary); font-weight: 800; font-size: 1.1rem; font-variant-numeric: tabular-nums; }
.progress-label { color: var(--text-muted); }
.editor-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 2px solid var(--border); flex-wrap: wrap; gap: 1rem; }
.editor-header-left { display: flex; align-items: center; gap: 1rem; }
.editor-header-left h2 { margin: 0; font-size: 1.5rem; font-weight: 800; color: var(--text-primary); font-family: 'Georgia', serif; letter-spacing: -0.01em; }
.back-btn { color: var(--text-secondary); text-decoration: none; font-size: 0.88rem; font-weight: 600; padding: 0.5rem 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border); transition: all 0.2s; display: flex; align-items: center; gap: 0.5rem; }
.back-btn:hover { background: var(--bg-tertiary); color: var(--primary); transform: translateX(-2px); }
.editor-header-right { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.autosave-indicator { display: flex; align-items: center; gap: 0.5rem; font-size: 0.78rem; font-weight: 600; color: var(--text-muted); background: var(--bg-secondary); padding: 0.5rem 0.85rem; border-radius: 999px; border: 1px solid var(--border); transition: all 0.3s; }
.autosave-indicator.saving { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
.autosave-indicator.saved { background: #dcfce7; color: #166534; border-color: #86efac; }
.btn-sm { padding: 0.5rem 1rem; border-radius: var(--radius-md); font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all 0.2s; border: 1px solid var(--border); font-family: inherit; display: inline-flex; align-items: center; gap: 0.4rem; }
.btn-sm.primary { background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; border-color: #dc2626; box-shadow: 0 4px 12px rgba(220,38,38,0.25); }
.btn-sm.primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(220,38,38,0.4); }
.btn-sm.gray { background: var(--bg-secondary); color: var(--text-primary); }
.btn-sm.gray:hover { background: var(--bg-tertiary); border-color: var(--primary); color: var(--primary); }
.template-section { margin-bottom: 1.5rem; padding: 1rem; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: var(--radius-lg); }
.template-label { font-size: 0.78rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.65rem; display: flex; align-items: center; gap: 0.4rem; text-transform: uppercase; letter-spacing: 0.05em; }
.template-buttons { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.template-btn { padding: 0.5rem 0.85rem; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 999px; font-size: 0.78rem; font-weight: 600; cursor: pointer; transition: all 0.2s; font-family: inherit; color: var(--text-secondary); display: inline-flex; align-items: center; gap: 0.35rem; }
.template-btn:hover { background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; border-color: #dc2626; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(220,38,38,0.25); }
.editor-grid { display: grid; grid-template-columns: 1fr 380px; gap: 2rem; align-items: start; }
.editor-main, .editor-sidebar { display: flex; flex-direction: column; gap: 1.5rem; }
.editor-card { background: var(--bg-primary); border-radius: var(--radius-xl); padding: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--border); transition: all 0.3s; }
.editor-card:hover { box-shadow: var(--shadow-md); }
.editor-card:focus-within { border-color: var(--primary); }
.card-title { font-size: 0.95rem; font-weight: 700; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; padding-bottom: 0.75rem; border-bottom: 2px solid var(--bg-tertiary); color: var(--text-primary); font-family: 'Georgia', serif; }
.field-label { display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; }
.field-group { margin-bottom: 1.25rem; }
.field-group:last-child { margin-bottom: 0; }
.styled-input, .styled-select { width: 100%; padding: 0.75rem 1rem; border: 2px solid var(--border); border-radius: var(--radius-md); font-size: 0.9rem; font-family: inherit; background: var(--bg-secondary); color: var(--text-primary); transition: all 0.2s; }
.styled-input:focus, .styled-select:focus { outline: none; border-color: var(--primary); background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.title-card { padding: 2rem !important; background: linear-gradient(135deg, var(--bg-primary) 0%, rgba(220,38,38,0.03) 100%); border: 2px solid var(--border); position: relative; overflow: hidden; }
.title-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #dc2626, #ef4444, #f87171); }
.judul-input { width: 100%; border: none; font-size: 2rem; font-weight: 800; color: var(--text-primary); background: transparent; outline: none; font-family: 'Georgia', serif; padding: 0; margin-bottom: 0.75rem; line-height: 1.2; letter-spacing: -0.02em; }
.judul-input::placeholder { color: var(--text-muted); font-weight: 600; }
.judul-counter { font-size: 0.72rem; color: var(--text-muted); font-weight: 600; margin-bottom: 0.75rem; display: flex; justify-content: flex-end; gap: 0.5rem; }
.judul-counter.warn { color: #f59e0b; }
.judul-counter.danger { color: #ef4444; }
.slug-preview { display: flex; align-items: center; gap: 0.25rem; font-size: 0.82rem; color: var(--text-muted); background: var(--bg-tertiary); padding: 0.5rem 1rem; border-radius: var(--radius-md); margin-bottom: 0.75rem; font-family: ui-monospace, monospace; overflow: hidden; }
.slug-prefix { color: var(--text-muted); flex-shrink: 0; }
.slug-text { color: var(--primary); font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.custom-slug-input { width: 100%; padding: 0.6rem 1rem; border: 2px solid var(--primary); border-radius: var(--radius-md); font-family: ui-monospace, monospace; font-size: 0.82rem; margin-bottom: 0.75rem; background: var(--bg-primary); }
.slug-edit-btn { background: transparent; border: 1px solid var(--border); padding: 0.4rem 0.85rem; border-radius: var(--radius-md); font-size: 0.78rem; cursor: pointer; color: var(--text-secondary); transition: all 0.2s; font-family: inherit; font-weight: 600; }
.slug-edit-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }
.excerpt-textarea { width: 100%; padding: 1rem; border: 2px solid var(--border); border-radius: var(--radius-md); font-size: 0.95rem; font-family: inherit; background: var(--bg-secondary); color: var(--text-primary); resize: vertical; transition: all 0.2s; line-height: 1.6; }
.excerpt-textarea:focus { outline: none; border-color: var(--primary); background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.char-count { font-size: 0.72rem; color: var(--text-muted); font-weight: 600; font-variant-numeric: tabular-nums; }
.char-count.warn { color: #f59e0b; }
.char-count.danger { color: #ef4444; }
.smart-suggestions { margin-top: 0.5rem; padding: 0.65rem 0.85rem; background: linear-gradient(135deg, #fef3c7, #fde68a); border: 1px solid #fcd34d; border-radius: var(--radius-md); font-size: 0.78rem; display: none; }
.smart-suggestions.show { display: block; }
.suggestion-header { font-weight: 700; margin-bottom: 0.35rem; display: flex; align-items: center; gap: 0.3rem; color: #92400e; }
.suggestion-item { padding: 0.3rem 0; cursor: pointer; transition: all 0.15s; font-size: 0.78rem; color: #92400e; }
.suggestion-item:hover { color: #78350f; transform: translateX(4px); }
.content-stats { font-size: 0.72rem; color: var(--text-muted); font-weight: 600; display: flex; gap: 0.75rem; }
.content-stats span { display: inline-flex; align-items: center; gap: 0.25rem; }
.rich-toolbar { display: flex; flex-wrap: wrap; gap: 0.35rem; padding: 0.75rem; background: var(--bg-secondary); border: 2px solid var(--border); border-bottom: none; border-radius: var(--radius-md) var(--radius-md) 0 0; align-items: center; }
.tb-btn { background: var(--bg-primary); border: 1px solid var(--border); padding: 0.4rem 0.75rem; border-radius: 6px; font-size: 0.82rem; cursor: pointer; transition: all 0.15s; color: var(--text-secondary); font-family: inherit; min-width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; gap: 0.25rem; font-weight: 600; }
.tb-btn:hover { background: var(--primary); color: white; border-color: var(--primary); transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.tb-btn b, .tb-btn i, .tb-btn u { font-size: 0.88rem; }
.tb-divider { width: 1px; height: 24px; background: var(--border); margin: 0 0.25rem; }
.konten-textarea { width: 100%; padding: 1.5rem; border: 2px solid var(--border); border-top: 1px solid var(--bg-tertiary); border-radius: 0 0 var(--radius-md) var(--radius-md); font-size: 0.95rem; font-family: ui-monospace, monospace; background: var(--bg-primary); color: var(--text-primary); resize: vertical; min-height: 450px; line-height: 1.8; transition: border-color 0.2s; }
.konten-textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.tags-input-wrap { border: 2px solid var(--border); border-radius: var(--radius-md); padding: 0.5rem; background: var(--bg-secondary); transition: all 0.2s; min-height: 52px; display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
.tags-input-wrap:focus-within { border-color: var(--primary); background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.tag-pill { background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; padding: 0.35rem 0.75rem; border-radius: 999px; font-size: 0.78rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; animation: tagPop 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
@keyframes tagPop { from { transform: scale(0); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.tag-pill button { background: rgba(255,255,255,0.2); border: none; color: white; width: 18px; height: 18px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; transition: all 0.2s; padding: 0; }
.tag-pill button:hover { background: rgba(255,255,255,0.4); }
.tag-input-field { border: none; outline: none; flex: 1; min-width: 120px; padding: 0.4rem; background: transparent; font-family: inherit; font-size: 0.88rem; color: var(--text-primary); }
.tag-suggestions { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.5rem; }
.tag-suggestion { padding: 0.25rem 0.65rem; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 999px; font-size: 0.72rem; font-weight: 600; cursor: pointer; transition: all 0.2s; color: var(--text-secondary); }
.tag-suggestion:hover { background: var(--primary); color: white; border-color: var(--primary); }
.status-radios { display: flex; flex-direction: column; gap: 0.5rem; }
.status-radio { display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem; background: var(--bg-secondary); border: 2px solid transparent; border-radius: var(--radius-md); cursor: pointer; transition: all 0.2s; font-size: 0.88rem; font-weight: 500; color: var(--text-secondary); }
.status-radio:hover { background: var(--bg-tertiary); }
.status-radio input { display: none; }
.status-radio:has(input:checked).status-draft { background: #fef3c7; border-color: #f59e0b; color: #92400e; }
.status-radio:has(input:checked).status-published { background: #dcfce7; border-color: #10b981; color: #166534; }
.status-radio:has(input:checked).status-archived { background: var(--bg-tertiary); border-color: var(--border); color: var(--text-primary); }
.sr-dot { width: 16px; height: 16px; border-radius: 50%; border: 2px solid currentColor; position: relative; flex-shrink: 0; }
.status-radio:has(input:checked) .sr-dot::after { content: ''; position: absolute; inset: 3px; background: currentColor; border-radius: 50%; }
.checkbox-label { display: flex; align-items: center; gap: 0.75rem; cursor: pointer; font-size: 0.88rem; color: var(--text-primary); padding: 0.75rem; border-radius: var(--radius-md); transition: background 0.2s; font-weight: 500; }
.checkbox-label:hover { background: var(--bg-secondary); }
.checkbox-label input { display: none; }
.checkbox-custom { width: 20px; height: 20px; border: 2px solid var(--border); border-radius: 6px; flex-shrink: 0; position: relative; transition: all 0.2s; background: var(--bg-primary); }
.checkbox-label input:checked + .checkbox-custom { background: var(--primary); border-color: var(--primary); }
.checkbox-label input:checked + .checkbox-custom::after { content: '✓'; position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.85rem; font-weight: 700; }
.meta-info { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); display: flex; flex-direction: column; gap: 0.5rem; }
.meta-row { display: flex; justify-content: space-between; font-size: 0.82rem; }
.meta-row span { color: var(--text-muted); }
.meta-row strong { color: var(--text-primary); font-weight: 600; }
.upload-zone { position: relative; border: 2px dashed var(--border); border-radius: var(--radius-lg); transition: all 0.3s; overflow: hidden; background: var(--bg-secondary); }
.upload-zone:hover, .upload-zone.dragover { border-color: var(--primary); background: rgba(10,104,71,0.03); }
.upload-zone.dragover { border-style: solid; box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.upload-file-input { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 2; }
.upload-placeholder { padding: 2.5rem 1.5rem; text-align: center; pointer-events: none; }
.upload-icon { font-size: 3rem; margin-bottom: 0.75rem; animation: uploadFloat 3s ease-in-out infinite; }
@keyframes uploadFloat { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
.upload-text { font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem; font-size: 1rem; }
.upload-sub { font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.5rem; }
.upload-hint { font-size: 0.72rem; color: var(--text-muted); background: var(--bg-tertiary); display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.25rem 0.75rem; border-radius: 999px; margin: 0.15rem; }
.upload-hints { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.35rem; }
.upload-preview { position: relative; }
.upload-preview img { width: 100%; max-height: 250px; object-fit: cover; display: block; border-radius: var(--radius-md) var(--radius-md) 0 0; }
.upload-remove { position: absolute; top: 0.75rem; right: 0.75rem; background: rgba(239, 68, 68, 0.9); color: white; border: none; padding: 0.5rem 0.85rem; border-radius: var(--radius-md); font-size: 0.78rem; font-weight: 600; cursor: pointer; transition: all 0.2s; backdrop-filter: blur(4px); display: flex; align-items: center; gap: 0.4rem; }
.upload-remove:hover { background: #dc2626; transform: scale(1.05); }
.aspect-warning { margin-top: 0.5rem; padding: 0.5rem 0.75rem; background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; border-radius: 6px; font-size: 0.78rem; display: none; align-items: center; gap: 0.4rem; }
.aspect-warning.show { display: flex; }
.current-image { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); }
.current-image img { width: 100%; border-radius: var(--radius-md); margin: 0.75rem 0; border: 1px solid var(--border); }
.current-image small { font-size: 0.78rem; color: var(--text-muted); display: block; }
.seo-card { background: linear-gradient(135deg, #dbeafe, #bfdbfe); border-color: #93c5fd; }
.seo-card .card-title { color: #1e40af; border-bottom-color: rgba(30, 64, 175, 0.2); }
.seo-score-box { display: flex; align-items: center; gap: 1rem; padding: 1rem; background: white; border-radius: var(--radius-md); border: 1px solid #93c5fd; margin-bottom: 1rem; }
.seo-score-circle { width: 70px; height: 70px; position: relative; flex-shrink: 0; }
.seo-score-circle svg { transform: rotate(-90deg); width: 100%; height: 100%; }
.seo-score-circle .ring-bg { fill: none; stroke: rgba(59,130,246,0.2); stroke-width: 8; }
.seo-score-circle .ring-fill { fill: none; stroke: #3b82f6; stroke-width: 8; stroke-linecap: round; transition: stroke-dasharray 0.8s ease; }
.seo-score-value { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 900; color: #1e40af; font-family: 'Georgia', serif; }
.seo-score-info { flex: 1; }
.seo-score-label { font-weight: 700; color: #1e40af; margin-bottom: 0.2rem; font-size: 0.95rem; }
.seo-score-text { font-size: 0.75rem; color: #1e40af; opacity: 0.85; }
.seo-checklist { list-style: none; padding: 0; margin: 0; font-size: 0.78rem; }
.seo-checklist li { padding: 0.4rem 0; border-bottom: 1px dashed rgba(30, 64, 175, 0.2); display: flex; align-items: center; gap: 0.4rem; }
.seo-checklist li:last-child { border-bottom: none; }
.tips-card { background: linear-gradient(135deg, #fef3c7, #fde68a); border-color: #fcd34d; }
.tips-card .card-title { color: #92400e; border-bottom-color: rgba(146, 64, 14, 0.2); }
.tips-list { list-style: none; font-size: 0.82rem; color: #92400e; line-height: 1.8; margin: 0; padding: 0; }
.tips-list li { padding-left: 0.25rem; display: flex; align-items: flex-start; gap: 0.5rem; }
.secondary-actions { display: flex; gap: 0.75rem; margin-top: 1.5rem; flex-wrap: wrap; }
.secondary-btn { flex: 1; padding: 0.75rem; background: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border); border-radius: var(--radius-md); font-family: inherit; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 0.4rem; text-decoration: none; min-width: 120px; }
.secondary-btn:hover { background: var(--bg-tertiary); border-color: var(--primary); color: var(--primary); transform: translateY(-1px); }
.shortcuts-legend { text-align: center; margin-top: 1rem; font-size: 0.75rem; color: var(--text-muted); display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }
.shortcuts-legend kbd { background: var(--bg-secondary); padding: 0.15rem 0.4rem; border-radius: 4px; font-family: monospace; font-size: 0.7rem; border: 1px solid var(--border); margin: 0 0.15rem; }
.preview-modal-box { background: var(--bg-primary); border-radius: var(--radius-xl); max-width: 900px; width: 95%; max-height: 90vh; overflow: hidden; animation: zoomIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); box-shadow: 0 30px 80px rgba(0,0,0,0.4); display: flex; flex-direction: column; }
@keyframes zoomIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.preview-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; background: linear-gradient(135deg, #1e293b, #334155); color: white; flex-shrink: 0; position: relative; }
.preview-header::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #dc2626, #ef4444, #f87171); }
.preview-header span { font-weight: 700; display: flex; align-items: center; gap: 0.5rem; position: relative; z-index: 1; }
.preview-close { background: rgba(255,255,255,0.2); border: none; color: white; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; font-size: 1.1rem; transition: all 0.2s; display: flex; align-items: center; justify-content: center; position: relative; z-index: 1; }
.preview-close:hover { background: rgba(255,255,255,0.3); transform: rotate(90deg); }
.preview-tabs { display: flex; gap: 0.25rem; padding: 0.5rem 1.5rem; background: var(--bg-secondary); border-bottom: 1px solid var(--border); }
.preview-tab { padding: 0.5rem 1rem; background: transparent; border: none; border-radius: 6px; font-size: 0.78rem; font-weight: 600; cursor: pointer; transition: all 0.2s; font-family: inherit; color: var(--text-muted); display: inline-flex; align-items: center; gap: 0.3rem; }
.preview-tab.active { background: white; color: var(--primary); box-shadow: var(--shadow-sm); }
.preview-tab:hover:not(.active) { background: var(--bg-tertiary); color: var(--text-primary); }
.preview-body { max-height: calc(90vh - 130px); overflow-y: auto; padding: 2.5rem; }
.preview-article { font-family: Georgia, 'Times New Roman', serif; max-width: 680px; margin: 0 auto; }
.preview-badge { display: inline-block; padding: 0.35rem 1rem; background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; border-radius: 999px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; margin-bottom: 1.5rem; font-family: var(--font-primary); letter-spacing: 0.05em; }
.preview-title { font-size: 2.25rem; line-height: 1.2; margin-bottom: 1.5rem; color: var(--text-primary); font-weight: 800; letter-spacing: -0.02em; }
.preview-meta { color: var(--text-muted); font-size: 0.88rem; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 2px solid var(--bg-tertiary); font-family: var(--font-primary); display: flex; align-items: center; gap: 0.5rem; }
.preview-image { width: 100%; height: 300px; object-fit: cover; border-radius: var(--radius-lg); margin-bottom: 2rem; background: var(--bg-tertiary); }
.preview-excerpt { font-size: 1.15rem; line-height: 1.7; color: var(--text-secondary); font-style: italic; padding: 1rem 1.5rem; background: var(--bg-secondary); border-left: 4px solid var(--primary); border-radius: 0 var(--radius-md) var(--radius-md) 0; margin-bottom: 2rem; }
.preview-content { font-size: 1.05rem; line-height: 1.9; color: var(--text-secondary); }
.preview-content p { margin-bottom: 1.5rem; }
.preview-content h2, .preview-content h3 { margin: 2rem 0 1rem; color: var(--text-primary); font-family: var(--font-primary); font-weight: 700; }
.preview-content blockquote { border-left: 4px solid var(--primary); padding: 1rem 1.5rem; margin: 1.5rem 0; background: var(--bg-secondary); font-style: italic; border-radius: 0 var(--radius-md) var(--radius-md) 0; color: var(--text-primary); }
.preview-content ul, .preview-content ol { margin-bottom: 1.5rem; padding-left: 1.5rem; }
.preview-content li { margin-bottom: 0.5rem; }
.social-preview-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.social-preview-card { background: white; border: 1px solid var(--border); border-radius: var(--radius-md); overflow: hidden; box-shadow: var(--shadow-sm); }
.social-preview-header { padding: 0.5rem 0.75rem; background: var(--bg-secondary); font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); display: flex; align-items: center; gap: 0.35rem; }
.social-preview-body { padding: 0; }
.og-card { border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; margin: 0.75rem; }
.og-image { width: 100%; height: 120px; background: var(--bg-tertiary); object-fit: cover; }
.og-content { padding: 0.75rem; }
.og-domain { font-size: 0.7rem; color: #65676b; text-transform: uppercase; margin-bottom: 0.25rem; }
.og-title { font-size: 0.9rem; font-weight: 700; color: #050505; margin-bottom: 0.25rem; line-height: 1.3; }
.og-description { font-size: 0.78rem; color: #65676b; line-height: 1.4; }
.mobile-preview-frame { width: 375px; max-width: 100%; margin: 0 auto; background: white; border-radius: 30px; padding: 1rem; box-shadow: 0 20px 60px rgba(0,0,0,0.3); border: 8px solid #1a1a1a; }
.mobile-notch { width: 120px; height: 25px; background: #1a1a1a; border-radius: 0 0 15px 15px; margin: -1rem auto 1rem; }
.mobile-article { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; padding: 1rem; }
.mobile-article h1 { font-size: 1.5rem; margin-bottom: 1rem; }
.mobile-article p { font-size: 0.95rem; line-height: 1.6; }
.modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center; z-index: 9999; padding: 1.5rem; animation: fadeIn 0.3s; }
.modal-overlay.open { display: flex; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.toast-helper { position: fixed; bottom: 2rem; right: 2rem; z-index: 10000; }
@media (max-width: 1200px) { .editor-grid { grid-template-columns: 1fr 340px; } }
@media (max-width: 968px) { .editor-grid { grid-template-columns: 1fr; } .editor-header { flex-direction: column; align-items: flex-start; } .editor-header-right { width: 100%; justify-content: flex-start; } .judul-input { font-size: 1.5rem; } .preview-body { padding: 1.5rem; } .preview-title { font-size: 1.75rem; } .social-preview-grid { grid-template-columns: 1fr; } }
@media (max-width: 640px) { .editor-header-right { flex-direction: column; } .btn-sm { width: 100%; justify-content: center; } .template-buttons { flex-direction: column; } .secondary-actions { flex-direction: column; } .shortcuts-legend { font-size: 0.68rem; } }
@media print { .editor-header, .template-section, .seo-card, .tips-card, .secondary-actions, .shortcuts-legend, .modal-overlay, .autosave-indicator, .progress-indicator { display: none !important; } .editor-grid { grid-template-columns: 1fr; } .editor-card { box-shadow: none; border: 1px solid #ddd; break-inside: avoid; } }
</style>

<!-- Progress Indicator -->
<div class="progress-indicator" data-aos="fade-down">
    <span style="font-size: 1.25rem;">📊</span>
    <div class="progress-bar-wrap"><div class="progress-bar-fill" id="progressBar" style="width: 0%"></div></div>
    <div class="progress-stats"><span class="progress-percent" id="progressPercent">0%</span><span class="progress-label">Kelengkapan Artikel</span></div>
</div>

<div class="editor-wrap">
    <!-- ===== EDITOR HEADER ===== -->
    <div class="editor-header">
        <div class="editor-header-left">
            <a href="blog.php?tab=articles" class="back-btn">← Kembali</a>
            <h2><?= $edit ? '✏️ Edit Artikel' : '➕ Artikel Baru' ?></h2>
        </div>
        <div class="editor-header-right">
            <span class="autosave-indicator" id="autosaveStatus"><span class="as-icon">💾</span><span class="as-text">Draft lokal aktif</span></span>
            <button type="button" class="btn-sm gray" onclick="openPreview()">👁️ Preview</button>
            <button type="submit" form="editorForm" class="btn-sm primary" id="submitBtn">💾 <?= $edit ? 'Update' : 'Simpan' ?></button>
        </div>
    </div>

    <!-- Quick Templates -->
    <div class="template-section" data-aos="fade-up">
        <div class="template-label">⚡ Template Cepat (Klik untuk auto-fill)</div>
        <div class="template-buttons">
            <button type="button" class="template-btn" onclick="applyTemplate('ai')">🤖 AI & Teknologi</button>
            <button type="button" class="template-btn" onclick="applyTemplate('riset')">🔬 Tips Riset</button>
            <button type="button" class="template-btn" onclick="applyTemplate('pendidikan')">🎓 Pendidikan</button>
            <button type="button" class="template-btn" onclick="applyTemplate('pengabdian')">🤝 Pengabdian</button>
            <button type="button" class="template-btn" onclick="applyTemplate('opini')">💭 Opini</button>
            <button type="button" class="template-btn" onclick="applyTemplate('tutorial')">📚 Tutorial</button>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data" id="editorForm" class="editor-form">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
        <div class="editor-grid">
            <!-- ===== MAIN COLUMN ===== -->
            <div class="editor-main">
                <!-- Judul -->
                <div class="editor-card title-card">
                    <input type="text" name="judul" id="judulInput" class="judul-input" placeholder="Tulis judul artikel yang menarik..." required maxlength="255" value="<?= sanitize($edit['judul'] ?? '') ?>">
                    <div class="judul-counter" id="judulCounter"><span><span id="judulCount"><?= mb_strlen($edit['judul'] ?? '') ?></span>/255 karakter</span></div>
                    <div class="slug-preview">
                        <span class="slug-prefix"><?= base_url('blog-detail.php?slug=') ?></span>
                        <span class="slug-text" id="slugPreview"><?= sanitize($edit['slug'] ?? 'slug-akan-dibuat-otomatis') ?></span>
                    </div>
                    <input type="text" name="custom_slug" id="customSlug" class="custom-slug-input" placeholder="Atau ketik slug kustom di sini (opsional)" style="display:none">
                    <button type="button" class="slug-edit-btn" onclick="toggleCustomSlug()">✏️ Edit slug</button>
                </div>

                <!-- Excerpt -->
                <div class="editor-card">
                    <label class="field-label"><span>📝 Ringkasan (Excerpt)</span><span class="char-count"><span id="excerptCount"><?= mb_strlen($edit['excerpt'] ?? '') ?></span>/500</span></label>
                    <textarea name="excerpt" id="excerptInput" class="excerpt-textarea" maxlength="500" rows="3" placeholder="Ringkasan singkat yang menarik pembaca... (muncul di list blog & social media)"><?= sanitize($edit['excerpt'] ?? '') ?></textarea>
                    <div class="smart-suggestions" id="excerptSuggestions"></div>
                </div>

                <!-- Konten -->
                <div class="editor-card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 1.5rem 1.5rem 0.5rem;">
                        <label class="field-label"><span>📄 Konten Artikel *</span>
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
                    <textarea name="konten" id="kontenInput" class="konten-textarea" required placeholder="Tulis konten artikel di sini...&#10;&#10;Tips:&#10;• Gunakan toolbar di atas untuk formatting&#10;• HTML sederhana diizinkan: p, strong, em, ul, li, h2-h4, blockquote, a&#10;• Minimal 300 kata untuk SEO optimal"><?= sanitize($edit['konten'] ?? '') ?></textarea>
                </div>

                <!-- Tags -->
                <div class="editor-card">
                    <label class="field-label"><span>🏷️ Tags</span><small style="color:var(--text-muted); margin-left:0.5rem; font-weight:500;">Pisahkan dengan koma atau tekan Enter</small></label>
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
                    <a href="?duplicate=<?= $id ?>" class="secondary-btn" onclick="return confirm('Duplikasi artikel ini sebagai draft baru?')">📋 Duplikasi</a>
                    <?php endif; ?>
                    <a href="blog.php?tab=articles" class="secondary-btn">← Kembali</a>
                    <button type="reset" class="secondary-btn" onclick="return confirm('Reset semua field?')">🔄 Reset</button>
                </div>

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
                            <label class="status-radio status-draft"><input type="radio" name="status" value="Draft" <?= ($edit['status'] ?? 'Draft') === 'Draft' ? 'checked' : '' ?>><span class="sr-dot"></span><span>📝 Draft</span></label>
                            <label class="status-radio status-published"><input type="radio" name="status" value="Published" <?= ($edit['status'] ?? '') === 'Published' ? 'checked' : '' ?>><span class="sr-dot"></span><span>✅ Published</span></label>
                            <label class="status-radio status-archived"><input type="radio" name="status" value="Archived" <?= ($edit['status'] ?? '') === 'Archived' ? 'checked' : '' ?>><span class="sr-dot"></span><span>🗄️ Archived</span></label>
                        </div>
                    </div>
                    <div class="field-group">
                        <label class="checkbox-label"><input type="checkbox" name="is_featured" <?= !empty($edit['is_featured']) ? 'checked' : '' ?>><span class="checkbox-custom"></span><span>⭐ Jadikan Sorotan Editor</span></label>
                    </div>
                    <div class="field-group">
                        <label class="field-label">Waktu Baca (menit) <small style="color:var(--text-muted);font-weight:500">0 = otomatis</small></label>
                        <input type="number" name="reading_time" class="styled-input" min="0" max="120" value="<?= (int)($edit['reading_time'] ?? 0) ?>">
                    </div>
                    <?php if ($edit && !empty($edit['id'])): ?>
                    <div class="meta-info">
                        <div class="meta-row"><span>Dibuat:</span><strong><?= date('d M Y H:i', strtotime($edit['created_at'])) ?></strong></div>
                        <div class="meta-row"><span>Views:</span><strong><?= number_format($edit['views'] ?? 0) ?></strong></div>
                        <div class="meta-row"><span>Likes:</span><strong><?= number_format($edit['likes'] ?? 0) ?></strong></div>
                        <?php if (!empty($edit['published_at'])): ?><div class="meta-row"><span>Published:</span><strong><?= date('d M Y H:i', strtotime($edit['published_at'])) ?></strong></div><?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Kategori, Penulis & Afiliasi -->
                <div class="editor-card">
                    <h3 class="card-title">📁 Kategori, Penulis & Afiliasi</h3>
                    <div class="field-group">
                        <label class="field-label">Kategori</label>
                        <select name="kategori" class="styled-select" id="kategoriSelect">
                            <?php
                            $kicons = ['AI & Teknologi'=>'🤖','Tips Riset'=>'🔬','Pendidikan'=>'🎓','Pengabdian'=>'🤝','Opini'=>'💭'];
                            foreach ($KATEGORI as $k): ?>
                            <option value="<?= $k ?>" <?= ($edit['kategori'] ?? 'Pendidikan') === $k ? 'selected' : '' ?>><?= $kicons[$k] ?? '📄' ?> <?= $k ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-group">
                        <label class="field-label">Dosen / Penulis</label>
                        <select name="dosen_id" class="styled-select">
                            <option value="0">— Redaksi FKIP (tanpa penulis) —</option>
                            <?php foreach ($dosen_list as $d): $nm = trim(($d['gelar_depan'] ?? '') . ' ' . $d['nama'] . ' ' . ($d['gelar_belakang'] ?? '')); ?>
                            <option value="<?= (int)$d['id'] ?>" <?= (int)($edit['dosen_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>><?= sanitize($nm) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-group">
                        <label class="field-label">Program Studi</label>
                        <select name="program_studi_id" class="styled-select">
                            <option value="0">— Umum / Lintas prodi —</option>
                            <?php foreach ($prodi_list as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= (int)($edit['program_studi_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= sanitize($p['nama']) ?> (<?= sanitize($p['singkatan'] ?? '') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Gambar -->
                <div class="editor-card">
                    <h3 class="card-title">🖼️ Gambar Sampul</h3>
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
                    <div class="aspect-warning" id="aspectWarning">⚠️ <span>Gambar sebaiknya rasio 16:9 untuk tampilan terbaik</span></div>
                    <div id="imageDim" style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.35rem; display: none;"></div>
                    <?php if (!empty($edit['gambar'])): ?>
                        <div class="current-image">
                            <label class="field-label">Gambar saat ini:</label>
                            <img src="<?= asset('uploads/blog/' . basename($edit['gambar'])) ?>" alt="">
                            <small>Upload gambar baru untuk mengganti</small>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- SEO Score Card -->
                <div class="editor-card seo-card">
                    <h3 class="card-title">🔍 SEO Score</h3>
                    <div class="seo-score-box">
                        <div class="seo-score-circle"><svg viewBox="0 0 36 36"><circle cx="18" cy="18" r="15.915" class="ring-bg"/><circle cx="18" cy="18" r="15.915" class="ring-fill" id="seoRing" style="stroke-dasharray: 0, 100"/></svg><div class="seo-score-value" id="seoScoreValue">0</div></div>
                        <div class="seo-score-info"><div class="seo-score-label" id="seoScoreLabel">Analisis SEO</div><div class="seo-score-text" id="seoScoreText">Isi form untuk melihat skor</div></div>
                    </div>
                    <ul class="seo-checklist" id="seoChecklist"><li>⏳ Menunggu data...</li></ul>
                </div>

                <!-- Tips Card -->
                <div class="editor-card tips-card">
                    <h3 class="card-title">💡 Tips Menulis Wawasan</h3>
                    <ul class="tips-list">
                        <li>✓ Judul 50-70 karakter (optimal SEO)</li>
                        <li>✓ Excerpt 150-160 karakter</li>
                        <li>✓ Minimal 300 kata untuk konten</li>
                        <li>✓ Gunakan heading H2, H3 untuk struktur</li>
                        <li>✓ Tambahkan gambar berkualitas (16:9)</li>
                        <li>✓ Pilih dosen &amp; prodi agar terafiliasi benar</li>
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
        <div class="preview-header"><span>👁️ Preview Tampilan Artikel</span><button class="preview-close" onclick="closePreview()">✕</button></div>
        <div class="preview-tabs">
            <button class="preview-tab active" onclick="switchPreviewTab('article', this)">📄 Artikel</button>
            <button class="preview-tab" onclick="switchPreviewTab('social', this)">🔗 Social Media</button>
            <button class="preview-tab" onclick="switchPreviewTab('mobile', this)">📱 Mobile</button>
        </div>
        <div class="preview-body">
            <div id="tab-article">
                <article class="preview-article">
                    <div class="preview-badge" id="previewBadge">Kategori</div>
                    <h1 class="preview-title" id="previewTitle">Judul artikel akan muncul di sini...</h1>
                    <div class="preview-meta"><span>✍️ Oleh <strong id="previewAuthor">Penulis</strong></span> • <span>📅 <?= date('d F Y') ?></span> • <span>⏱️ <span id="previewReadTime">0</span> menit baca</span></div>
                    <img class="preview-image" id="previewImage" src="" alt="" style="display:none">
                    <div class="preview-excerpt" id="previewExcerpt" style="display:none"></div>
                    <div class="preview-content" id="previewContent"></div>
                </article>
            </div>
            <div id="tab-social" style="display:none">
                <div class="social-preview-grid">
                    <div class="social-preview-card"><div class="social-preview-header">📘 Facebook / LinkedIn</div><div class="social-preview-body"><div class="og-card"><img class="og-image" id="ogImage" src="" alt="" style="display:none"><div class="og-content"><div class="og-domain">fkip-unimof.ac.id</div><div class="og-title" id="ogTitle">Judul Artikel</div><div class="og-description" id="ogDescription">Deskripsi artikel...</div></div></div></div></div>
                    <div class="social-preview-card"><div class="social-preview-header">🐦 Twitter Card</div><div class="social-preview-body"><div class="og-card"><img class="og-image" id="twitterImage" src="" alt="" style="display:none"><div class="og-content"><div class="og-title" id="twitterTitle">Judul Artikel</div><div class="og-description" id="twitterDescription">Deskripsi artikel...</div><div class="og-domain">fkip-unimof.ac.id</div></div></div></div></div>
                </div>
                <div style="margin-top: 1.5rem; padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border);">
                    <div style="font-size: 0.78rem; font-weight: 700; margin-bottom: 0.75rem; color: var(--text-primary);">🏷️ Meta Tags yang Akan Digenerate:</div>
                    <pre style="font-size: 0.72rem; color: var(--text-muted); background: var(--bg-primary); padding: 0.75rem; border-radius: 6px; overflow-x: auto; margin: 0; border: 1px solid var(--border);"><code id="metaTagsPreview">&lt;!-- Meta tags akan muncul di sini --&gt;</code></pre>
                </div>
            </div>
            <div id="tab-mobile" style="display:none">
                <div class="mobile-preview-frame"><div class="mobile-notch"></div><div class="mobile-article">
                    <div style="font-size: 0.7rem; color: var(--primary); font-weight: 700; text-transform: uppercase; margin-bottom: 0.5rem;" id="mobileCategory">Kategori</div>
                    <h1 id="mobileTitle" style="font-size: 1.5rem; margin-bottom: 0.75rem; line-height: 1.3;">Judul Artikel</h1>
                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 1rem; display: flex; gap: 0.75rem;"><span id="mobileAuthor">✍️ Penulis</span><span><?= date('d M Y') ?></span></div>
                    <img id="mobileImage" src="" style="width: 100%; border-radius: 8px; margin-bottom: 1rem; display:none;">
                    <p id="mobileContent" style="font-size: 0.95rem; line-height: 1.6; color: var(--text-secondary);">Konten artikel...</p>
                </div></div>
            </div>
        </div>
    </div>
</div>

<div class="toast-helper" id="toastHelper"></div>

<script>
const popularTags = <?= json_encode($popular_tags) ?>;
const dosenNameMap = <?= json_encode(array_column($dosen_list, 'nama', 'id'), JSON_UNESCAPED_UNICODE) ?>;

// ===== TEMPLATES (khusus blog) =====
const templates = {
    ai: { kategori: 'AI & Teknologi', judul: 'Peran [Teknologi AI] dalam [Bidang Pendidikan]', excerpt: 'Bagaimana [teknologi AI] mengubah lanskap [bidang] di era digital, serta implikasinya bagi praktisi pendidikan.', konten: '<h2>Pendahuluan</h2>\n<p>Kecerdasan buatan (AI) telah berkembang pesat dan mulai menyentuh berbagai aspek kehidupan, termasuk [bidang yang dibahas].</p>\n\n<h3>Lanskap Terkini</h3>\n<p>[Jelaskan kondisi terkini dan data pendukung].</p>\n\n<h3>Peluang & Tantangan</h3>\n<ul>\n<li><strong>Peluang:</strong> [sebutkan]</li>\n<li><strong>Tantangan:</strong> [sebutkan]</li>\n</ul>\n\n<h3>Implikasi Praktis</h3>\n<p>[Jelaskan apa artinya bagi pembaca/praktisi].</p>\n\n<blockquote>"[Kutipan reflektif tentang masa depan teknologi ini]"</blockquote>\n\n<h3>Penutup</h3>\n<p>[Simpulan dan ajakan berpikir].</p>', tags: 'AI,teknologi,pendidikan,digital' },
    riset: { kategori: 'Tips Riset', judul: 'Panduan [Topik Riset]: Langkah Praktis untuk [Peneliti Pemula]', excerpt: 'Tips praktis menyusun [proposal/publikasi/analisis] riset yang sistematis dan layak terbit di jurnal terakreditasi.', konten: '<h2>Mengapa [Topik] Penting?</h2>\n<p>[Jelaskan urgensi topik riset].</p>\n\n<h3>Langkah 1: Menemukan Gap</h3>\n<p>[Cara identifikasi celah penelitian].</p>\n\n<h3>Langkah 2: Metodologi</h3>\n<ol>\n<li>[Tahap 1]</li>\n<li>[Tahap 2]</li>\n<li>[Tahap 3]</li>\n</ol>\n\n<h3>Langkah 3: Menulis & Publikasi</h3>\n<p>[Struktur IMRaD dan pemilihan jurnal].</p>\n\n<blockquote>"Riset yang baik dimulai dari pertanyaan yang tepat."</blockquote>\n\n<h3>Checklist</h3>\n<ul><li>✓ [Item 1]</li><li>✓ [Item 2]</li><li>✓ [Item 3]</li></ul>', tags: 'riset,publikasi,jurnal,metodologi' },
    pendidikan: { kategori: 'Pendidikan', judul: '[Tren/Isu Pendidikan]: Refleksi untuk [Guru/Pendidik] di Era [Konteks]', excerpt: 'Tinjauan kritis tentang [tren/isu] dan bagaimana [pendidik] dapat meresponsnya secara pedagogis.', konten: '<h2>Konteks Isu</h2>\n<p>[Jelaskan latar belakang isu pendidikan].</p>\n\n<h3>Analisis Pedagogis</h3>\n<p>[Kaitkan dengan teori/prinsip pembelajaran].</p>\n\n<h3>Strategi di Kelas</h3>\n<ul>\n<li>[Strategi 1]</li>\n<li>[Strategi 2]</li>\n</ul>\n\n<h3>Peran Pendidik</h3>\n<p>[Refleksi peran guru sebagai fasilitator].</p>\n\n<blockquote>"[Kutipan tokoh pendidikan]"</blockquote>\n\n<h3>Penutup</h3>\n<p>[Ajakan aksi].</p>', tags: 'pendidikan,pedagogi,guru,kelas' },
    pengabdian: { kategori: 'Pengabdian', judul: 'Pengabdian Masyarakat: [Program] untuk [Sasaran] di [Lokasi]', excerpt: 'Cerita dan dampak program pengabdian [nama program] yang memberdayakan [sasaran masyarakat].', konten: '<h2>Latar Belakang</h2>\n<p>[Masalah/kebutuhan masyarakat yang dijawab].</p>\n\n<h3>Bentuk Kegiatan</h3>\n<ol><li>[Kegiatan 1]</li><li>[Kegiatan 2]</li></ol>\n\n<h3>Dampak Nyata</h3>\n<p>[Hasil dan perubahan yang terjadi].</p>\n\n<h3>Testimoni</h3>\n<blockquote>"[Kutipan peserta/mitra]"</blockquote>\n\n<h3>Rekomendasi Keberlanjutan</h3>\n<p>[Langkah tindak lanjut].</p>', tags: 'pengabdian,masyarakat,pemberdayaan,NTT' },
    opini: { kategori: 'Opini', judul: 'Opini: [Sudut Pandang Kritis tentang Isu]', excerpt: 'Tulisan opini yang menawarkan perspektif segar dan argumentatif mengenai [isu].', konten: '<h2>Tesis</h2>\n<p>[Pernyataan posisi penulis].</p>\n\n<h3>Argumen 1</h3>\n<p>[Data/logika pendukung].</p>\n\n<h3>Argumen 2</h3>\n<p>[Perspektif alternatif/bantahan].</p>\n\n<h3>Implikasi</h3>\n<p>[Apa konsekuensinya jika posisi ini diterima].</p>\n\n<blockquote>"[Kalimat penutup yang kuat]"</blockquote>', tags: 'opini,kritika,analisis,kebijakan' },
    tutorial: { kategori: 'Tips Riset', judul: 'Tutorial: Cara [Melakukan Sesuatu] dalam [Langkah/Waktu]', excerpt: 'Panduan langkah demi langkah yang mudah diikuti untuk [tujuan tutorial].', konten: '<h2>Apa yang Akan Anda Pelajari</h2>\n<p>[Output akhir tutorial].</p>\n\n<h3>Prasyarat</h3>\n<ul><li>[Kebutuhan 1]</li><li>[Kebutuhan 2]</li></ul>\n\n<h3>Langkah-langkah</h3>\n<ol>\n<li><strong>Langkah 1:</strong> [instruksi]</li>\n<li><strong>Langkah 2:</strong> [instruksi]</li>\n<li><strong>Langkah 3:</strong> [instruksi]</li>\n</ol>\n\n<h3>Tips Tambahan</h3>\n<p>[Best practice].</p>\n\n<h3>Penutup</h3>\n<p>[Encouragement].</p>', tags: 'tutorial,panduan,how-to,praktis' }
};

function applyTemplate(key) {
    const t = templates[key]; if (!t) return;
    if (t.judul) document.getElementById('judulInput').value = t.judul;
    if (t.excerpt) document.getElementById('excerptInput').value = t.excerpt;
    if (t.konten) document.getElementById('kontenInput').value = t.konten;
    if (t.kategori) document.getElementById('kategoriSelect').value = t.kategori;
    if (t.tags) { tags = t.tags.split(',').map(x => x.trim()).filter(x => x); renderTags(); }
    document.getElementById('judulCount').textContent = (t.judul || '').length;
    document.getElementById('excerptCount').textContent = (t.excerpt || '').length;
    updateWordCount(); updateProgress(); updateSEO(); triggerAutosave();
    showToast('Template Diterapkan', `Template "${key}" berhasil diisi`, 'success');
}

// ===== SLUG =====
const judulInput = document.getElementById('judulInput');
const slugPreview = document.getElementById('slugPreview');
const customSlug = document.getElementById('customSlug');
function slugify(text) { return text.toString().toLowerCase().replace(/\s+/g, '-').replace(/[^\w\-]+/g, '').replace(/\-\-+/g, '-').replace(/^-+/, '').replace(/-+$/, ''); }
judulInput.addEventListener('input', function() {
    if (!customSlug.style.display || customSlug.style.display === 'none') slugPreview.textContent = slugify(this.value) || 'slug-akan-dibuat-otomatis';
    document.getElementById('judulCount').textContent = this.value.length;
    const c = document.getElementById('judulCounter');
    c.classList.toggle('warn', this.value.length > 180 && this.value.length <= 255);
    c.classList.toggle('danger', this.value.length > 255);
    triggerAutosave(); updateSEO(); updateProgress();
});
function toggleCustomSlug() { const s = customSlug.style.display === 'block'; customSlug.style.display = s ? 'none' : 'block'; if (!s) customSlug.focus(); }
customSlug.addEventListener('input', function() { slugPreview.textContent = slugify(this.value) || slugify(judulInput.value); });

// ===== WORD COUNTER =====
const kontenInput = document.getElementById('kontenInput');
function updateWordCount() {
    const text = kontenInput.value.trim();
    const words = text ? text.replace(/<[^>]+>/g, '').split(/\s+/).filter(w => w).length : 0;
    document.getElementById('wordCount').textContent = words;
    document.getElementById('charCount').textContent = text.length;
    document.getElementById('readTime').textContent = Math.max(1, Math.ceil(words / 200));
    triggerAutosave(); updateSEO(); updateProgress();
}
kontenInput.addEventListener('input', updateWordCount); updateWordCount();

// ===== EXCERPT =====
const excerptInput = document.getElementById('excerptInput');
const excerptCount = document.getElementById('excerptCount');
excerptInput.addEventListener('input', function() {
    const len = this.value.length; excerptCount.textContent = len;
    const p = excerptCount.parentElement;
    p.classList.toggle('warn', len > 400 && len <= 500); p.classList.toggle('danger', len > 500);
    triggerAutosave(); updateSEO(); updateProgress();
});

// ===== SMART SUGGESTIONS =====
function checkExcerptSuggestions() {
    const kategori = document.getElementById('kategoriSelect').value;
    const excerpt = excerptInput.value.toLowerCase();
    const sugBox = document.getElementById('excerptSuggestions');
    const suggestions = {
        'AI & Teknologi': ['🤖 "Di era disrupsi digital..."', '⚙️ "Kecerdasan buatan membuka..."', '🚀 "Transformasi teknologi..."'],
        'Tips Riset': ['🔬 "Menulis riset yang impactful..."', '📊 "Langkah sistematis..."', '💡 "Gap penelitian adalah..."'],
        'Pendidikan': ['🎓 "Pendidikan bukan sekadar..."', '📚 "Peran pendidik dalam..."', '✨ "Pembelajaran bermakna..."'],
        'Pengabdian': ['🤝 "Pengabdian yang memberdayakan..."', '🌱 "Dari kampus untuk masyarakat..."', '❤️ "Dampak nyata terasa..."'],
        'Opini': ['💭 "Sudah saatnya kita bertanya..."', '⚖️ "Di balik kebijakan ini..."', '🔍 "Perspektif yang sering terlewat..."']
    };
    if (suggestions[kategori] && excerpt.length < 50) {
        sugBox.innerHTML = `<div class="suggestion-header">💡 Saran excerpt untuk ${kategori}:</div>` + suggestions[kategori].map(s => `<div class="suggestion-item" onclick="document.getElementById('excerptInput').value = '${s.replace(/'/g, "\\'")}'; excerptInput.dispatchEvent(new Event('input'));">${s}</div>`).join('');
        sugBox.classList.add('show');
    } else sugBox.classList.remove('show');
}
document.getElementById('kategoriSelect').addEventListener('change', checkExcerptSuggestions);
excerptInput.addEventListener('input', checkExcerptSuggestions);

// ===== RICH TEXT =====
function execCmd(cmd, val = null) {
    const ta = kontenInput, start = ta.selectionStart, end = ta.selectionEnd, text = ta.value, selected = text.substring(start, end);
    const wrappers = { 'bold': ['<strong>', '</strong>'], 'italic': ['<em>', '</em>'], 'underline': ['<u>', '</u>'], 'strikeThrough': ['<s>', '</s>'],
        'formatBlock': { '<h2>': ['<h2>', '</h2>'], '<h3>': ['<h3>', '</h3>'], '<h4>': ['<h4>', '</h4>'], '<p>': ['<p>', '</p>'], '<blockquote>': ['<blockquote>', '</blockquote>'] },
        'insertUnorderedList': ['<ul>\n<li>', '</li>\n</ul>'], 'insertOrderedList': ['<ol>\n<li>', '</li>\n</ol>'] };
    let before = '', after = '';
    if (cmd === 'formatBlock') [before, after] = wrappers[cmd][val]; else if (wrappers[cmd]) [before, after] = wrappers[cmd];
    if (cmd === 'removeFormat') ta.value = text.substring(0, start) + selected.replace(/<[^>]+>/g, '') + text.substring(end);
    else { ta.value = text.substring(0, start) + before + selected + after + text.substring(end); ta.selectionStart = start + before.length; ta.selectionEnd = start + before.length + selected.length; }
    ta.focus(); updateWordCount();
}
function insertLink() { const url = prompt('Masukkan URL:', 'https://'); if (url) { const ta = kontenInput, s = ta.selectionStart, e = ta.selectionEnd, sel = ta.value.substring(s, e) || url; ta.value = ta.value.substring(0, s) + `<a href="${url}" target="_blank">${sel}</a>` + ta.value.substring(e); ta.focus(); updateWordCount(); } }
function insertHR() { const ta = kontenInput, p = ta.selectionStart; ta.value = ta.value.substring(0, p) + '\n<hr>\n' + ta.value.substring(p); ta.focus(); }
function insertCodeBlock() { const ta = kontenInput, s = ta.selectionStart, e = ta.selectionEnd, sel = ta.value.substring(s, e) || 'code here'; ta.value = ta.value.substring(0, s) + `<pre><code>${sel}</code></pre>` + ta.value.substring(e); ta.focus(); updateWordCount(); }

// ===== TAGS =====
const tagInput = document.getElementById('tagInput'), tagsContainer = document.getElementById('tagsContainer'), tagsHidden = document.getElementById('tagsHidden');
let tags = [];
if (tagsHidden.value) { tags = tagsHidden.value.split(',').map(t => t.trim()).filter(t => t); renderTags(); }
function renderTags() { tagsContainer.innerHTML = ''; tags.forEach((tag, i) => { const pill = document.createElement('span'); pill.className = 'tag-pill'; pill.innerHTML = `${tag} <button type="button" onclick="removeTag(${i})">✕</button>`; tagsContainer.appendChild(pill); }); tagsHidden.value = tags.join(', '); updateSEO(); updateProgress(); }
function addTag(val) { val = val.trim(); if (val && !tags.includes(val) && tags.length < 10) { tags.push(val); renderTags(); triggerAutosave(); } }
window.removeTag = function(i) { tags.splice(i, 1); renderTags(); triggerAutosave(); };
tagInput.addEventListener('keydown', function(e) { if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addTag(this.value.replace(',', '')); this.value = ''; } else if (e.key === 'Backspace' && !this.value && tags.length > 0) { tags.pop(); renderTags(); } });
tagInput.addEventListener('blur', function() { if (this.value.trim()) { addTag(this.value); this.value = ''; } });

// ===== UPLOAD =====
const uploadZone = document.getElementById('uploadZone'), gambarInput = document.getElementById('gambarInput'), uploadPlaceholder = document.getElementById('uploadPlaceholder'), uploadPreview = document.getElementById('uploadPreview'), previewImg = document.getElementById('previewImg');
['dragenter','dragover'].forEach(ev => uploadZone.addEventListener(ev, e => { e.preventDefault(); uploadZone.classList.add('dragover'); }));
['dragleave','drop'].forEach(ev => uploadZone.addEventListener(ev, e => { e.preventDefault(); uploadZone.classList.remove('dragover'); }));
uploadZone.addEventListener('drop', e => { const f = e.dataTransfer.files; if (f.length > 0) handleFile(f[0]); });
gambarInput.addEventListener('change', e => { if (e.target.files.length > 0) handleFile(e.target.files[0]); });
function handleFile(file) {
    if (!file.type.startsWith('image/')) { showToast('Error', 'File harus berupa gambar!', 'error'); return; }
    if (file.size > 5 * 1024 * 1024) { showToast('Error', 'Ukuran maksimal 5MB!', 'error'); return; }
    const reader = new FileReader();
    reader.onload = e => {
        previewImg.src = e.target.result; uploadPlaceholder.style.display = 'none'; uploadPreview.style.display = 'block';
        const img = new Image();
        img.onload = () => {
            const ratio = img.width / img.height, aw = document.getElementById('aspectWarning'), dim = document.getElementById('imageDim');
            dim.textContent = `📐 ${img.width} × ${img.height} px (${(file.size / 1024).toFixed(1)} KB)`; dim.style.display = 'block';
            aw.classList.toggle('show', Math.abs(ratio - 16/9) > 0.3);
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file); updateSEO();
}
window.removePreview = function() { gambarInput.value = ''; previewImg.src = ''; uploadPlaceholder.style.display = 'block'; uploadPreview.style.display = 'none'; document.getElementById('aspectWarning').classList.remove('show'); document.getElementById('imageDim').style.display = 'none'; updateSEO(); };

// ===== PROGRESS =====
function updateProgress() {
    const fields = [
        { el: 'judulInput', weight: 25 }, { el: 'excerptInput', weight: 15 }, { el: 'kontenInput', weight: 35 },
        { el: 'kategoriSelect', weight: 10 }, { check: () => tags.length > 0, weight: 10 },
        { check: () => document.getElementById('previewImg').src || document.querySelector('.current-image img'), weight: 5 }
    ];
    let score = 0, total = 0;
    fields.forEach(f => { total += f.weight; if (f.check) { if (f.check()) score += f.weight; } else { const el = document.getElementById(f.el); if (el && el.value && el.value.trim()) score += f.weight; } });
    const percent = Math.round((score / total) * 100);
    document.getElementById('progressBar').style.width = percent + '%';
    document.getElementById('progressPercent').textContent = percent + '%';
}

// ===== SEO =====
function updateSEO() {
    const judul = judulInput.value.trim(), excerpt = excerptInput.value.trim();
    const konten = kontenInput.value.replace(/<[^>]+>/g, '').trim();
    const words = konten ? konten.split(/\s+/).filter(w => w).length : 0;
    const hasImage = document.getElementById('previewImg').src || document.querySelector('.current-image img');
    let score = 0; const checks = [];
    if (judul.length >= 30 && judul.length <= 70) { score += 25; checks.push('✅ Judul optimal (30-70 karakter)'); }
    else if (judul.length >= 20) { score += 15; checks.push('⚠️ Judul cukup baik, bisa lebih optimal'); }
    else if (judul.length > 0) { score += 5; checks.push('❌ Judul terlalu pendek (< 20 karakter)'); }
    else checks.push('❌ Judul belum diisi');
    if (excerpt.length >= 120 && excerpt.length <= 160) { score += 20; checks.push('✅ Excerpt optimal (120-160 karakter)'); }
    else if (excerpt.length >= 80) { score += 12; checks.push('⚠️ Excerpt cukup baik'); }
    else if (excerpt.length > 0) { score += 5; checks.push('❌ Excerpt terlalu pendek'); }
    else checks.push('❌ Excerpt belum diisi');
    if (words >= 500) { score += 25; checks.push('✅ Konten panjang (500+ kata)'); }
    else if (words >= 300) { score += 18; checks.push('✅ Konten cukup panjang (300+ kata)'); }
    else if (words >= 100) { score += 8; checks.push('⚠️ Konten kurang panjang'); }
    else if (words > 0) { score += 3; checks.push('❌ Konten terlalu pendek'); }
    else checks.push('❌ Konten belum diisi');
    const hasH2 = /<h2>/i.test(kontenInput.value), hasH3 = /<h3>/i.test(kontenInput.value);
    if (hasH2 && hasH3) { score += 15; checks.push('✅ Struktur heading baik (H2 + H3)'); }
    else if (hasH2 || hasH3) { score += 8; checks.push('⚠️ Tambahkan heading H2 dan H3'); }
    else checks.push('❌ Belum ada heading (gunakan H2, H3)');
    if (hasImage) { score += 10; checks.push('✅ Ada gambar'); } else checks.push('❌ Belum ada gambar');
    if (tags.length >= 3) { score += 5; checks.push('✅ Tags cukup (3+ tags)'); }
    else if (tags.length > 0) { score += 2; checks.push('⚠️ Tambahkan lebih banyak tags'); }
    else checks.push('❌ Belum ada tags');
    score = Math.min(100, score);
    document.getElementById('seoRing').style.strokeDasharray = score + ', 100';
    document.getElementById('seoScoreValue').textContent = score;
    let label = 'Buruk', color = '#ef4444', text = 'Perlu perbaikan signifikan';
    if (score >= 80) { label = 'Excellent'; color = '#10b981'; text = 'SEO sangat baik!'; }
    else if (score >= 60) { label = 'Baik'; color = '#3b82f6'; text = 'SEO baik, bisa ditingkatkan'; }
    else if (score >= 40) { label = 'Cukup'; color = '#f59e0b'; text = 'Perlu beberapa perbaikan'; }
    document.getElementById('seoScoreLabel').textContent = label; document.getElementById('seoScoreLabel').style.color = color;
    document.getElementById('seoScoreText').textContent = text;
    document.getElementById('seoRing').style.stroke = color; document.getElementById('seoScoreValue').style.color = color;
    document.getElementById('seoChecklist').innerHTML = checks.map(c => `<li>${c}</li>`).join('');
}

// ===== AUTOSAVE =====
const storageKey = 'fkip_blog_draft_' + (<?= $id ?> || 'new');
let autoSaveTimer;
function triggerAutosave() {
    clearTimeout(autoSaveTimer);
    const ind = document.getElementById('autosaveStatus');
    ind.classList.add('saving'); ind.classList.remove('saved'); ind.querySelector('.as-text').textContent = 'Menyimpan...';
    autoSaveTimer = setTimeout(() => {
        const data = { judul: judulInput.value, excerpt: excerptInput.value, konten: kontenInput.value, kategori: document.getElementById('kategoriSelect').value, dosen_id: document.querySelector('[name=dosen_id]').value, prodi_id: document.querySelector('[name=program_studi_id]').value, tags: tags.join(','), saved_at: new Date().toISOString() };
        try { localStorage.setItem(storageKey, JSON.stringify(data)); ind.classList.remove('saving'); ind.classList.add('saved'); ind.querySelector('.as-text').textContent = 'Tersimpan ' + new Date().toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'}); } catch(e) {}
    }, 800);
}
(function(){
    <?php if (!$edit): ?>
    try {
        const saved = localStorage.getItem(storageKey);
        if (saved) {
            const data = JSON.parse(saved);
            if (confirm('Ada draft tersimpan dari ' + new Date(data.saved_at).toLocaleString('id-ID') + '. Muat draft tersebut?')) {
                judulInput.value = data.judul || ''; excerptInput.value = data.excerpt || ''; kontenInput.value = data.konten || '';
                if (data.kategori) document.getElementById('kategoriSelect').value = data.kategori;
                if (data.dosen_id) document.querySelector('[name=dosen_id]').value = data.dosen_id;
                if (data.prodi_id) document.querySelector('[name=program_studi_id]').value = data.prodi_id;
                if (data.tags) { tags = data.tags.split(',').filter(t => t); renderTags(); }
                judulInput.dispatchEvent(new Event('input')); excerptInput.dispatchEvent(new Event('input'));
                updateWordCount(); updateSEO(); updateProgress();
                showToast('Draft Dimuat', 'Data draft berhasil dimuat', 'success');
            } else localStorage.removeItem(storageKey);
        }
    } catch(e) {}
    <?php endif; ?>
})();
document.getElementById('editorForm').addEventListener('submit', function(e) {
    if (!judulInput.value.trim()) { e.preventDefault(); showToast('Validasi Error', 'Judul wajib diisi!', 'error'); return; }
    if (!kontenInput.value.trim()) { e.preventDefault(); showToast('Validasi Error', 'Konten wajib diisi!', 'error'); return; }
    setTimeout(() => { try { localStorage.removeItem(storageKey); } catch(e) {} }, 500);
});

// ===== PREVIEW =====
function openPreview() {
    const judul = judulInput.value || 'Judul artikel akan muncul di sini...';
    const excerpt = excerptInput.value;
    const kategori = document.getElementById('kategoriSelect').value;
    const dosenId = document.querySelector('[name=dosen_id]').value;
    const penulis = (dosenId && dosenId !== '0' && dosenNameMap[dosenId]) ? dosenNameMap[dosenId] : 'Redaksi FKIP';
    document.getElementById('previewTitle').textContent = judul;
    document.getElementById('previewBadge').textContent = kategori;
    document.getElementById('previewAuthor').textContent = penulis;
    document.getElementById('previewReadTime').textContent = document.getElementById('readTime').textContent;
    const imgSrc = document.getElementById('previewImg').src, previewImage = document.getElementById('previewImage');
    if (imgSrc && imgSrc !== window.location.href) { previewImage.src = imgSrc; previewImage.style.display = 'block'; } else previewImage.style.display = 'none';
    if (excerpt) { document.getElementById('previewExcerpt').textContent = excerpt; document.getElementById('previewExcerpt').style.display = 'block'; } else document.getElementById('previewExcerpt').style.display = 'none';
    document.getElementById('previewContent').innerHTML = kontenInput.value.replace(/\n/g, '<br>') || '<p style="color:var(--text-muted)"><em>Belum ada konten...</em></p>';
    document.getElementById('ogTitle').textContent = judul;
    document.getElementById('ogDescription').textContent = excerpt || 'Baca selengkapnya di FKIP UNIMOF';
    document.getElementById('twitterTitle').textContent = judul;
    document.getElementById('twitterDescription').textContent = excerpt || 'Baca selengkapnya di FKIP UNIMOF';
    const ogImage = document.getElementById('ogImage'), twitterImage = document.getElementById('twitterImage');
    if (imgSrc && imgSrc !== window.location.href) { ogImage.src = imgSrc; ogImage.style.display = 'block'; twitterImage.src = imgSrc; twitterImage.style.display = 'block'; }
    else { ogImage.style.display = 'none'; twitterImage.style.display = 'none'; }
    document.getElementById('metaTagsPreview').textContent = `<meta property="og:title" content="${judul}">\n<meta property="og:description" content="${excerpt || 'Baca selengkapnya...'}">\n<meta property="og:image" content="${imgSrc || 'default.jpg'}">\n<meta property="og:type" content="article">\n<meta name="twitter:card" content="summary_large_image">\n<meta name="twitter:title" content="${judul}">\n<meta name="twitter:description" content="${excerpt || 'Baca selengkapnya...'}">`;
    document.getElementById('mobileTitle').textContent = judul;
    document.getElementById('mobileCategory').textContent = kategori;
    document.getElementById('mobileAuthor').textContent = '✍️ ' + penulis;
    document.getElementById('mobileContent').textContent = excerpt || kontenInput.value.replace(/<[^>]+>/g, '').substring(0, 300) + '...';
    const mobileImage = document.getElementById('mobileImage');
    if (imgSrc && imgSrc !== window.location.href) { mobileImage.src = imgSrc; mobileImage.style.display = 'block'; } else mobileImage.style.display = 'none';
    document.getElementById('previewModal').classList.add('open'); document.body.style.overflow = 'hidden';
}
function closePreview() { document.getElementById('previewModal').classList.remove('open'); document.body.style.overflow = ''; }
function switchPreviewTab(tab, btn) { document.querySelectorAll('.preview-tab').forEach(t => t.classList.remove('active')); document.querySelectorAll('[id^="tab-"]').forEach(c => c.style.display = 'none'); btn.classList.add('active'); document.getElementById('tab-' + tab).style.display = 'block'; }

// ===== TOAST =====
function showToast(title, message, type = 'info') {
    const toast = document.createElement('div');
    toast.style.cssText = `background: var(--bg-primary); border: 1px solid var(--border); border-radius: 12px; padding: 0.85rem 1.25rem; box-shadow: 0 10px 30px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 0.75rem; margin-top: 0.5rem; animation: slideIn 0.3s; min-width: 280px;`;
    const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' }, colors = { success: '#10b981', error: '#ef4444', warning: '#f59e0b', info: '#3b82f6' };
    toast.innerHTML = `<span style="font-size: 1.5rem;">${icons[type]}</span><div><div style="font-weight: 700; color: ${colors[type]}; font-size: 0.88rem;">${title}</div><div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem;">${message}</div></div>`;
    document.getElementById('toastHelper').appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(100%)'; toast.style.transition = 'all 0.3s'; setTimeout(() => toast.remove(), 300); }, 3000);
}

// ===== SHORTCUTS =====
kontenInput.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'b') { e.preventDefault(); execCmd('bold'); }
    if ((e.ctrlKey || e.metaKey) && e.key === 'i') { e.preventDefault(); execCmd('italic'); }
    if ((e.ctrlKey || e.metaKey) && e.key === 'u') { e.preventDefault(); execCmd('underline'); }
    if (e.key === 'Tab') { e.preventDefault(); const s = this.selectionStart; this.value = this.value.substring(0, s) + '    ' + this.value.substring(this.selectionEnd); this.selectionStart = this.selectionEnd = s + 4; }
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closePreview();
    if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); document.getElementById('editorForm').dispatchEvent(new Event('submit')); }
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') { e.preventDefault(); openPreview(); }
});

updateSEO(); updateProgress(); checkExcerptSuggestions();
console.log('%c✏️ Editor Ide & Wawasan FKIP UNIMOF - EXTREME MULTIMATE', 'color:#dc2626;font-size:16px;font-weight:bold');
console.log('%cShortcuts: Ctrl+S (Save), Ctrl+P (Preview), Ctrl+B/I/U (Format), Esc (Close)', 'color:#64748b');
console.log('%cFitur: Templates blog, SEO Score, Live Preview, Autosave, Dosen & Prodi selector', 'color:#64748b');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>