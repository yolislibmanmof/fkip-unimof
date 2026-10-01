<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== HELPER LOKAL (konsisten 1.4 & 1.5a) =====
if (!function_exists('vid_yt_id')) {
    function vid_yt_id($url) {
        $url = (string)$url;
        if (preg_match('~(?:v=|youtu\.be/|shorts/|embed/)([A-Za-z0-9_-]{11})~', $url, $m)) return $m[1];
        return null;
    }
}
if (!function_exists('vid_detect_type')) {
    function vid_detect_type($url) {
        $url = trim((string)$url);
        if ($url === '') return 'other';
        if (preg_match('~youtube\.com|youtu\.be~i', $url)) return 'youtube';
        if (preg_match('~vimeo\.com~i', $url)) return 'vimeo';
        if (preg_match('~\.(mp4|webm|ogg)(\?.*)?$~i', $url)) return 'local';
        return 'other';
    }
}
if (!function_exists('vid_embed_src')) {
    function vid_embed_src($url, $type) {
        $url = trim((string)$url);
        if ($url === '') return '';
        if ($type === 'youtube' || preg_match('~youtube\.com|youtu\.be~i', $url)) {
            $id = vid_yt_id($url);
            return $id ? "https://www.youtube.com/embed/$id?rel=0&modestbranding=1" : '';
        }
        if ($type === 'vimeo' || preg_match('~vimeo\.com~i', $url)) {
            if (preg_match('~(?:vimeo\.com/|player\.vimeo\.com/video/)(\d+)~', $url, $m))
                return "https://player.vimeo.com/video/{$m[1]}";
        }
        if ($type === 'local' || preg_match('~\.(mp4|webm|ogg)(\?.*)?$~i', $url)) return $url;
        return '';
    }
}
if (!function_exists('vid_del_img')) {
    function vid_del_img($f) {
        if ($f) { $p = (defined('APP_DIR') ? APP_DIR : dirname(__DIR__, 2)) . '/uploads/video/' . basename($f); if (is_file($p)) @unlink($p); }
    }
}
if (!function_exists('vid_parse_dur')) {
    function vid_parse_dur($str) {
        $str = trim((string)$str);
        if ($str === '') return 0;
        if (ctype_digit($str)) return (int)$str;
        $parts = array_reverse(explode(':', $str));
        $sec = 0; $mult = 1;
        foreach ($parts as $p) { $p = (int)trim($p); $sec += $p * $mult; $mult *= 60; }
        return max(0, $sec);
    }
}
if (!function_exists('vid_fmt_dur')) {
    function vid_fmt_dur($sec) {
        $sec = (int)$sec; if ($sec <= 0) return '';
        $h = intdiv($sec, 3600); $m = intdiv($sec % 3600, 60); $s = $sec % 60;
        return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
    }
}
if (!function_exists('vid_dir')) {
    function vid_dir() { return (defined('APP_DIR') ? APP_DIR : dirname(__DIR__, 2)) . '/uploads/video'; }
}

// =====================================================
// ENDPOINT: AMBIL THUMBNAIL YOUTUBE (AJAX JSON) — sebelum output
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'fetch_thumb') {
    header('Content-Type: application/json; charset=utf-8');
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { echo json_encode(['ok' => false, 'msg' => 'Token tidak valid.']); exit; }
    $url = trim($_POST['url'] ?? '');
    $vid = vid_yt_id($url);
    if (!$vid) { echo json_encode(['ok' => false, 'msg' => 'Bukan URL YouTube yang valid.']); exit; }

    $dir = vid_dir();
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    // Kandidat: maxresdefault (kualitas tertinggi) -> hqdefault (selalu ada utk video publik)
    $candidates = [
        "https://i.ytimg.com/vi/$vid/maxresdefault.jpg",
        "https://img.youtube.com/vi/$vid/maxresdefault.jpg",
        "https://img.youtube.com/vi/$vid/hqdefault.jpg",
        "https://img.youtube.com/vi/$vid/mqdefault.jpg",
    ];
    $downloaded = null;
    foreach ($candidates as $src) {
        $tmp = $dir . '/.tmp_' . bin2hex(random_bytes(6));
        $data = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($src);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_USERAGENT => 'Mozilla/5.0', CURLOPT_FAILONERROR => true]);
            $data = curl_exec($ch);
            $err = curl_error($ch); curl_close($ch);
            if ($data === false) $data = null;
        }
        if ($data === false || $data === null) {
            if (ini_get('allow_url_fopen')) { $ctx = stream_context_create(['http' => ['timeout' => 12, 'user_agent' => 'Mozilla/5.0', 'ignore_errors' => true]]); $data = @file_get_contents($src, false, $ctx); }
        }
        if ($data === false || $data === null || strlen($data) < 2000) continue; // skip placeholder/404
        if (@file_put_contents($tmp, $data) === false) continue;
        $info = @getimagesize($tmp);
        if ($info === false || $info[0] < 120) { @unlink($tmp); continue; } // bukan gambar valid / terlalu kecil
        $ext = image_type_to_extension($info[2], false); // jpeg, png, ...
        $ext = ($ext === 'jpeg') ? 'jpg' : $ext;
        $name = 'yt_' . $vid . '_' . time() . '.' . $ext;
        if (@rename($tmp, $dir . '/' . $name)) { $downloaded = $name; break; }
        @unlink($tmp);
    }
    if ($downloaded) echo json_encode(['ok' => true, 'name' => $downloaded, 'url' => asset('uploads/video/' . $downloaded)]);
    else echo json_encode(['ok' => false, 'msg' => 'Gagal mengambil thumbnail (video mungkin privat / tanpa thumbnail).']);
    exit;
}

// =====================================================
// KONTEKS: EDIT / DUPLICATE
// =====================================================
$id = (int)($_GET['id'] ?? 0);
$duplicate_from = (int)($_GET['duplicate'] ?? 0);
$edit = null;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM video WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch();
    if (!$edit) { header('Location: video.php?tab=videos'); exit; }
} elseif ($duplicate_from > 0) {
    $stmt = $pdo->prepare("SELECT * FROM video WHERE id = ?");
    $stmt->execute([$duplicate_from]);
    $source = $stmt->fetch();
    if ($source) {
        $edit = $source;
        $edit['id'] = null;
        $edit['judul'] = $source['judul'] . ' (Copy)';
        $edit['slug'] = null;
        $edit['status'] = 'Draft';
        $edit['views'] = 0;
        flash_message('info', '📋 Menduplikasi video: ' . htmlspecialchars($source['judul']));
    }
}

// ===== REFERENSI: KATEGORI & PRODI =====
$categories = []; $prodi_list = [];
try { $categories = $pdo->query("SELECT id, nama, icon FROM video_kategori WHERE status='Aktif' ORDER BY urutan ASC, nama ASC")->fetchAll(); } catch (Exception $e) {}
try { $prodi_list = $pdo->query("SELECT id, nama, singkatan FROM program_studi WHERE status='Aktif' ORDER BY urutan ASC, nama ASC")->fetchAll(); } catch (Exception $e) {}

$VTYPES = ['youtube', 'vimeo', 'local', 'other'];

// =====================================================
// PROSES SAVE (POST)
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('error', '❌ Token keamanan tidak valid.');
        header('Location: video-form.php' . ($id ? "?id=$id" : '')); exit;
    }

    $judul      = trim($_POST['judul'] ?? '');
    $kategori_id = (int)($_POST['kategori_id'] ?? 0) ?: null;
    $prodi_id   = (int)($_POST['program_studi_id'] ?? 0) ?: null;
    $video_url  = trim($_POST['video_url'] ?? '');
    $video_type = in_array($_POST['video_type'] ?? '', $VTYPES, true) ? $_POST['video_type'] : vid_detect_type($video_url);
    $deskripsi  = trim($_POST['deskripsi'] ?? '');
    $tags       = trim($_POST['tags'] ?? '');
    $status     = in_array($_POST['status'] ?? '', ['Draft','Published','Archived'], true) ? $_POST['status'] : 'Draft';
    $featured   = isset($_POST['is_featured']) ? 1 : 0;
    $podcast    = isset($_POST['is_podcast']) ? 1 : 0;

    // Durasi: sinkron teks <-> detik
    $dur_text = trim($_POST['duration'] ?? '');
    $dur_sec  = (int)($_POST['duration_seconds'] ?? 0);
    if ($dur_sec <= 0 && $dur_text !== '') $dur_sec = vid_parse_dur($dur_text);
    if ($dur_text === '' && $dur_sec > 0)  $dur_text = vid_fmt_dur($dur_sec);
    if ($dur_sec > 86400) $dur_sec = 0; // guard absurd

    if ($judul === '') { flash_message('error', '❌ Judul wajib diisi.'); header('Location: video-form.php' . ($id ? "?id=$id" : '')); exit; }
    if ($video_url === '') { flash_message('error', '❌ URL/sumber video wajib diisi.'); header('Location: video-form.php' . ($id ? "?id=$id" : '')); exit; }

    // Slug unik
    $custom_slug = trim($_POST['custom_slug'] ?? '');
    $base = generate_slug($custom_slug !== '' ? $custom_slug : $judul);
    $slug = $base; $n = 2;
    while (true) {
        $chk = $pdo->prepare("SELECT id FROM video WHERE slug = ? AND id != ?");
        $chk->execute([$slug, $id]);
        if (!$chk->fetchColumn()) break;
        $slug = $base . '-' . $n++;
    }

    // Thumbnail: upload manual > existing_thumb (auto-fetch) > pertahankan lama; remove_thumb => null
    $gambar = $edit['thumbnail'] ?? null;
    if (!empty($_POST['remove_thumb'])) {
        if ($gambar) vid_del_img($gambar);
        $gambar = null;
    } elseif (!empty($_POST['existing_thumb'])) {
        $new_t = basename(trim($_POST['existing_thumb']));
        if ($new_t !== '') { if ($gambar && $gambar !== $new_t) vid_del_img($gambar); $gambar = $new_t; }
    } elseif (!empty($_FILES['gambar']['name'])) {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['gambar']['tmp_name']);
        if (!isset($allowed[$mime])) { flash_message('error', '❌ Hanya file gambar (JPG, PNG, WEBP, GIF) yang diizinkan.'); header('Location: video-form.php' . ($id ? "?id=$id" : '')); exit; }
        if ($_FILES['gambar']['size'] > 5 * 1024 * 1024) { flash_message('error', '❌ Ukuran gambar maksimal 5MB.'); header('Location: video-form.php' . ($id ? "?id=$id" : '')); exit; }
        $ext = $allowed[$mime];
        $new_name = 'vid_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dir = vid_dir(); if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (move_uploaded_file($_FILES['gambar']['tmp_name'], $dir . '/' . $new_name)) {
            if ($gambar) vid_del_img($gambar);
            $gambar = $new_name;
        } else { flash_message('error', '❌ Gagal menyimpan gambar.'); header('Location: video-form.php' . ($id ? "?id=$id" : '')); exit; }
    }

    try {
        if ($edit && $id > 0) {
            $pub = $status === 'Published' ? ($edit['published_at'] ?: date('Y-m-d H:i:s')) : ($edit['published_at'] ?? null);
            $pdo->prepare("UPDATE video SET kategori_id=?, program_studi_id=?, judul=?, slug=?, deskripsi=?, thumbnail=?, video_url=?, video_type=?, duration=?, duration_seconds=?, is_podcast=?, is_featured=?, tags=?, status=?, published_at=? WHERE id=?")
                ->execute([$kategori_id, $prodi_id, $judul, $slug, $deskripsi, $gambar, $video_url, $video_type, $dur_text, $dur_sec, $podcast, $featured, $tags, $status, $pub, $id]);
            flash_message('success', '✅ Video berhasil diperbarui.');
        } else {
            $pub = $status === 'Published' ? date('Y-m-d H:i:s') : null;
            $pdo->prepare("INSERT INTO video (kategori_id, program_studi_id, judul, slug, deskripsi, thumbnail, video_url, video_type, duration, duration_seconds, is_podcast, is_featured, tags, status, published_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$kategori_id, $prodi_id, $judul, $slug, $deskripsi, $gambar, $video_url, $video_type, $dur_text, $dur_sec, $podcast, $featured, $tags, $status, $pub]);
            flash_message('success', '✅ Video berhasil ditambahkan.');
        }
    } catch (Exception $e) {
        flash_message('error', '❌ Gagal menyimpan: ' . $e->getMessage());
        header('Location: video-form.php' . ($id ? "?id=$id" : '')); exit;
    }
    header('Location: video.php?tab=videos');
    exit;
}

// ===== DATA AWAL UNTUK JS =====
$initial = [
    'id' => $edit ? (int)$edit['id'] : 0,
    'judul' => $edit['judul'] ?? '',
    'slug' => $edit['slug'] ?? '',
    'kategori_id' => (int)($edit['kategori_id'] ?? 0),
    'program_studi_id' => (int)($edit['program_studi_id'] ?? 0),
    'video_url' => $edit['video_url'] ?? '',
    'video_type' => $edit['video_type'] ?? 'youtube',
    'deskripsi' => $edit['deskripsi'] ?? '',
    'tags' => $edit['tags'] ?? '',
    'status' => $edit['status'] ?? 'Draft',
    'is_featured' => !empty($edit['is_featured']),
    'is_podcast' => !empty($edit['is_podcast']),
    'duration' => $edit['duration'] ?? '',
    'duration_seconds' => (int)($edit['duration_seconds'] ?? 0),
    'thumbnail' => $edit['thumbnail'] ?? '',
    'thumb_url' => !empty($edit['thumbnail']) ? asset('uploads/video/' . basename($edit['thumbnail'])) : '',
    'views' => (int)($edit['views'] ?? 0),
    'created_at' => $edit['created_at'] ?? '',
    'published_at' => $edit['published_at'] ?? '',
];

$csrf = generate_csrf_token();
$active_menu = 'video';
$page_heading = $edit ? 'Edit Video' : 'Tambah Video';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Kelola Video', 'video.php?tab=videos'], [$page_heading, null]];
require __DIR__ . '/includes/header.php';
?>

<style>
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
.btn-sm.purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; border-color: #8b5cf6; }
.btn-sm.purple:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(139,92,246,.4); }
.source-bar { margin-bottom: 1.5rem; padding: 1rem 1.25rem; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: var(--radius-lg); display: flex; gap: 1.25rem; flex-wrap: wrap; align-items: center; }
.source-bar .sb-label { font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.source-chip { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.8rem; border-radius: 999px; background: var(--bg-primary); border: 1px solid var(--border); font-size: 0.78rem; font-weight: 600; color: var(--text-secondary); }
.source-chip b { color: var(--primary); font-family: ui-monospace, monospace; font-size: 0.72rem; }
.editor-grid { display: grid; grid-template-columns: 1fr 380px; gap: 2rem; align-items: start; }
.editor-main, .editor-sidebar { display: flex; flex-direction: column; gap: 1.5rem; }
.editor-card { background: var(--bg-primary); border-radius: var(--radius-xl); padding: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--border); transition: all 0.3s; }
.editor-card:hover { box-shadow: var(--shadow-md); }
.editor-card:focus-within { border-color: var(--primary); }
.card-title { font-size: 0.95rem; font-weight: 700; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; padding-bottom: 0.75rem; border-bottom: 2px solid var(--bg-tertiary); color: var(--text-primary); font-family: 'Georgia', serif; }
.field-label { display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; }
.field-group { margin-bottom: 1.25rem; }
.field-group:last-child { margin-bottom: 0; }
.styled-input, .styled-select, .styled-textarea { width: 100%; padding: 0.75rem 1rem; border: 2px solid var(--border); border-radius: var(--radius-md); font-size: 0.9rem; font-family: inherit; background: var(--bg-secondary); color: var(--text-primary); transition: all 0.2s; }
.styled-input:focus, .styled-select:focus, .styled-textarea:focus { outline: none; border-color: var(--primary); background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.styled-textarea { resize: vertical; min-height: 120px; line-height: 1.7; }
.title-card { padding: 2rem !important; background: linear-gradient(135deg, var(--bg-primary) 0%, rgba(220,38,38,0.03) 100%); border: 2px solid var(--border); position: relative; overflow: hidden; }
.title-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #dc2626, #ef4444, #f87171); }
.judul-input { width: 100%; border: none; font-size: 1.9rem; font-weight: 800; color: var(--text-primary); background: transparent; outline: none; font-family: 'Georgia', serif; padding: 0; margin-bottom: 0.75rem; line-height: 1.2; letter-spacing: -0.02em; }
.judul-input::placeholder { color: var(--text-muted); font-weight: 600; }
.judul-counter { font-size: 0.72rem; color: var(--text-muted); font-weight: 600; margin-bottom: 0.75rem; display: flex; justify-content: flex-end; gap: 0.5rem; }
.judul-counter.warn { color: #f59e0b; }
.judul-counter.danger { color: #ef4444; }
.slug-preview { display: flex; align-items: center; gap: 0.25rem; font-size: 0.82rem; color: var(--text-muted); background: var(--bg-tertiary); padding: 0.5rem 1rem; border-radius: var(--radius-md); margin-bottom: 0.75rem; font-family: ui-monospace, monospace; overflow: hidden; }
.slug-prefix { color: var(--text-muted); flex-shrink: 0; }
.slug-text { color: var(--primary); font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.custom-slug-input { width: 100%; padding: 0.6rem 1rem; border: 2px solid var(--primary); border-radius: var(--radius-md); font-family: ui-monospace, monospace; font-size: 0.82rem; margin-bottom: 0.75rem; background: var(--bg-primary); display: none; }
.slug-edit-btn { background: transparent; border: 1px solid var(--border); padding: 0.4rem 0.85rem; border-radius: var(--radius-md); font-size: 0.78rem; cursor: pointer; color: var(--text-secondary); transition: all 0.2s; font-family: inherit; font-weight: 600; }
.slug-edit-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }
/* URL + PREVIEW */
.url-row { display: flex; gap: 0.6rem; align-items: stretch; }
.url-row .styled-input { flex: 1; font-family: ui-monospace, monospace; font-size: 0.88rem; }
.type-badge { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0 0.9rem; border-radius: var(--radius-md); font-size: 0.78rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; color: #fff; flex-shrink: 0; }
.tb-youtube { background: #ff0000; } .tb-vimeo { background: #1ab7ea; } .tb-local { background: #8b5cf6; } .tb-other { background: #64748b; }
.url-hint { font-size: 0.74rem; color: var(--text-muted); margin-top: 0.4rem; }
.url-hint code { background: var(--bg-tertiary); padding: 0.05rem 0.4rem; border-radius: 5px; font-family: ui-monospace, monospace; color: var(--primary); }
.preview-frame { margin-top: 1rem; position: relative; width: 100%; aspect-ratio: 16/9; background: #0f172a; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border); display: none; }
.preview-frame.show { display: block; }
.preview-frame iframe, .preview-frame video { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
.preview-frame .pf-empty { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: #64748b; font-size: 0.85rem; flex-direction: column; gap: 0.5rem; }
.preview-frame .pf-empty .ic { font-size: 2.5rem; opacity: 0.5; }
/* Tags */
.tags-input-wrap { border: 2px solid var(--border); border-radius: var(--radius-md); padding: 0.5rem; background: var(--bg-secondary); transition: all 0.2s; min-height: 52px; display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
.tags-input-wrap:focus-within { border-color: var(--primary); background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.tag-pill { background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; padding: 0.35rem 0.75rem; border-radius: 999px; font-size: 0.78rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; animation: tagPop 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
@keyframes tagPop { from { transform: scale(0); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.tag-pill button { background: rgba(255,255,255,0.2); border: none; color: white; width: 18px; height: 18px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; transition: all 0.2s; padding: 0; }
.tag-pill button:hover { background: rgba(255,255,255,0.4); }
.tag-input-field { border: none; outline: none; flex: 1; min-width: 120px; padding: 0.4rem; background: transparent; font-family: inherit; font-size: 0.88rem; color: var(--text-primary); }
/* Status & checkbox */
.status-radios { display: flex; flex-direction: column; gap: 0.5rem; }
.status-radio { display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem; background: var(--bg-secondary); border: 2px solid transparent; border-radius: var(--radius-md); cursor: pointer; transition: all 0.2s; font-size: 0.88rem; font-weight: 500; color: var(--text-secondary); }
.status-radio:hover { background: var(--bg-tertiary); }
.status-radio input { display: none; }
.status-radio:has(input:checked).status-draft { background: #fef3c7; border-color: #f59e0b; color: #92400e; }
.status-radio:has(input:checked).status-published { background: #dcfce7; border-color: #10b981; color: #166534; }
.status-radio:has(input:checked).status-archived { background: var(--bg-tertiary); border-color: var(--border); color: var(--text-primary); }
.sr-dot { width: 16px; height: 16px; border-radius: 50%; border: 2px solid currentColor; position: relative; flex-shrink: 0; }
.status-radio:has(input:checked) .sr-dot::after { content: ''; position: absolute; inset: 3px; background: currentColor; border-radius: 50%; }
.toggle-row { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.75rem; border-radius: var(--radius-md); transition: background 0.2s; }
.toggle-row:hover { background: var(--bg-secondary); }
.toggle-row .tr-label { font-size: 0.88rem; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem; }
.switch { position: relative; width: 44px; height: 24px; flex-shrink: 0; }
.switch input { opacity: 0; width: 0; height: 0; }
.switch .slider { position: absolute; inset: 0; background: var(--bg-tertiary); border: 1px solid var(--border); border-radius: 999px; cursor: pointer; transition: 0.25s; }
.switch .slider::before { content: ''; position: absolute; height: 18px; width: 18px; left: 2px; top: 2px; background: white; border-radius: 50%; transition: 0.25s; box-shadow: 0 1px 3px rgba(0,0,0,0.3); }
.switch input:checked + .slider { background: linear-gradient(135deg, #8b5cf6, #ec4899); border-color: transparent; }
.switch input:checked + .slider::before { transform: translateX(20px); }
.switch.pod input:checked + .slider { background: linear-gradient(135deg, #ec4899, #be185d); }
/* Duration */
.dur-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.dur-row .styled-input { font-family: ui-monospace, monospace; text-align: center; font-weight: 700; }
/* Upload */
.upload-zone { position: relative; border: 2px dashed var(--border); border-radius: var(--radius-lg); transition: all 0.3s; overflow: hidden; background: var(--bg-secondary); }
.upload-zone:hover, .upload-zone.dragover { border-color: var(--primary); background: rgba(10,104,71,0.03); }
.upload-zone.dragover { border-style: solid; box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.upload-file-input { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 2; }
.upload-placeholder { padding: 2rem 1.5rem; text-align: center; pointer-events: none; }
.upload-icon { font-size: 2.5rem; margin-bottom: 0.5rem; animation: uploadFloat 3s ease-in-out infinite; }
@keyframes uploadFloat { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
.upload-text { font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem; font-size: 0.95rem; }
.upload-sub { font-size: 0.82rem; color: var(--text-secondary); margin-bottom: 0.5rem; }
.upload-hint { font-size: 0.72rem; color: var(--text-muted); background: var(--bg-tertiary); display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.25rem 0.75rem; border-radius: 999px; margin: 0.15rem; }
.upload-hints { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.35rem; }
.upload-preview { position: relative; }
.upload-preview img { width: 100%; max-height: 220px; object-fit: cover; display: block; border-radius: var(--radius-md) var(--radius-md) 0 0; }
.upload-remove { position: absolute; top: 0.75rem; right: 0.75rem; background: rgba(239, 68, 68, 0.9); color: white; border: none; padding: 0.5rem 0.85rem; border-radius: var(--radius-md); font-size: 0.78rem; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 0.4rem; }
.upload-remove:hover { background: #dc2626; transform: scale(1.05); }
.fetch-thumb-btn { display: flex; align-items: center; justify-content: center; gap: 0.5rem; width: 100%; margin-top: 0.75rem; padding: 0.7rem; border-radius: var(--radius-md); border: 2px solid #ff0000; background: rgba(255,0,0,0.06); color: #ff0000; font-weight: 700; font-size: 0.85rem; cursor: pointer; transition: all 0.2s; font-family: inherit; }
.fetch-thumb-btn:hover { background: #ff0000; color: white; transform: translateY(-2px); }
.fetch-thumb-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
.fetch-thumb-btn.loading { pointer-events: none; }
.fetch-thumb-btn .spin { width: 14px; height: 14px; border: 2px solid currentColor; border-top-color: transparent; border-radius: 50%; animation: fspin 0.7s linear infinite; }
@keyframes fspin { to { transform: rotate(360deg); } }
.current-image { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); }
.current-image img { width: 100%; border-radius: var(--radius-md); margin: 0.5rem 0; border: 1px solid var(--border); }
.current-image small { font-size: 0.78rem; color: var(--text-muted); display: block; }
/* SEO & tips */
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
.meta-info { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); display: flex; flex-direction: column; gap: 0.5rem; }
.meta-row { display: flex; justify-content: space-between; font-size: 0.82rem; }
.meta-row span { color: var(--text-muted); }
.meta-row strong { color: var(--text-primary); font-weight: 600; }
.secondary-actions { display: flex; gap: 0.75rem; margin-top: 1.5rem; flex-wrap: wrap; }
.secondary-btn { flex: 1; padding: 0.75rem; background: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border); border-radius: var(--radius-md); font-family: inherit; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 0.4rem; text-decoration: none; min-width: 120px; }
.secondary-btn:hover { background: var(--bg-tertiary); border-color: var(--primary); color: var(--primary); transform: translateY(-1px); }
.shortcuts-legend { text-align: center; margin-top: 1rem; font-size: 0.75rem; color: var(--text-muted); display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }
.shortcuts-legend kbd { background: var(--bg-secondary); padding: 0.15rem 0.4rem; border-radius: 4px; font-family: monospace; font-size: 0.7rem; border: 1px solid var(--border); margin: 0 0.15rem; }
/* Preview modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center; z-index: 9999; padding: 1.5rem; animation: fadeIn 0.3s; }
.modal-overlay.open { display: flex; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.preview-modal-box { background: var(--bg-primary); border-radius: var(--radius-xl); max-width: 820px; width: 100%; max-height: 90vh; overflow: hidden; animation: zoomIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); box-shadow: 0 30px 80px rgba(0,0,0,0.4); display: flex; flex-direction: column; }
@keyframes zoomIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.preview-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; background: linear-gradient(135deg, #1e293b, #334155); color: white; flex-shrink: 0; position: relative; }
.preview-header::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #dc2626, #ef4444, #f87171); }
.preview-header span { font-weight: 700; display: flex; align-items: center; gap: 0.5rem; position: relative; z-index: 1; }
.preview-close { background: rgba(255,255,255,0.2); border: none; color: white; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; font-size: 1.1rem; transition: all 0.2s; display: flex; align-items: center; justify-content: center; position: relative; z-index: 1; }
.preview-close:hover { background: rgba(255,255,255,0.3); transform: rotate(90deg); }
.preview-body { padding: 1.5rem; overflow-y: auto; }
.pv-player { position: relative; width: 100%; aspect-ratio: 16/9; background: #000; border-radius: var(--radius-md); overflow: hidden; margin-bottom: 1.25rem; }
.pv-player iframe, .pv-player video { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
.pv-title { font-family: 'Georgia', serif; font-size: 1.5rem; font-weight: 800; margin-bottom: 0.6rem; color: var(--text-primary); }
.pv-meta { display: flex; gap: 1rem; flex-wrap: wrap; font-size: 0.82rem; color: var(--text-muted); margin-bottom: 1rem; }
.pv-meta span { display: inline-flex; align-items: center; gap: 0.35rem; }
.pv-desc { font-size: 0.92rem; line-height: 1.7; color: var(--text-secondary); padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border-left: 4px solid var(--primary); white-space: pre-wrap; }
.toast-helper { position: fixed; bottom: 2rem; right: 2rem; z-index: 10000; }
@media (max-width: 1200px) { .editor-grid { grid-template-columns: 1fr 340px; } }
@media (max-width: 968px) { .editor-grid { grid-template-columns: 1fr; } .editor-header { flex-direction: column; align-items: flex-start; } .editor-header-right { width: 100%; justify-content: flex-start; } .judul-input { font-size: 1.5rem; } }
@media (max-width: 640px) { .editor-header-right { flex-direction: column; } .btn-sm { width: 100%; justify-content: center; } .secondary-actions { flex-direction: column; } .dur-row { grid-template-columns: 1fr; } .url-row { flex-direction: column; } .shortcuts-legend { font-size: 0.68rem; } }
@media print { .editor-header, .source-bar, .seo-card, .tips-card, .secondary-actions, .shortcuts-legend, .modal-overlay, .autosave-indicator, .progress-indicator { display: none !important; } .editor-grid { grid-template-columns: 1fr; } .editor-card { box-shadow: none; border: 1px solid #ddd; break-inside: avoid; } }
</style>

<!-- Progress Indicator -->
<div class="progress-indicator" data-aos="fade-down">
    <span style="font-size: 1.25rem;">📊</span>
    <div class="progress-bar-wrap"><div class="progress-bar-fill" id="progressBar" style="width: 0%"></div></div>
    <div class="progress-stats"><span class="progress-percent" id="progressPercent">0%</span><span class="progress-label">Kelengkapan Video</span></div>
</div>

<div class="editor-wrap">
    <!-- HEADER -->
    <div class="editor-header">
        <div class="editor-header-left">
            <a href="video.php?tab=videos" class="back-btn">← Kembali</a>
            <h2><?= $edit ? '✏️ Edit Video' : '➕ Video Baru' ?></h2>
        </div>
        <div class="editor-header-right">
            <span class="autosave-indicator" id="autosaveStatus"><span class="as-icon">💾</span><span class="as-text">Draft lokal aktif</span></span>
            <button type="button" class="btn-sm gray" onclick="openPreview()">👁️ Preview</button>
            <button type="submit" form="editorForm" class="btn-sm primary" id="submitBtn">💾 <?= $edit ? 'Update' : 'Simpan' ?></button>
        </div>
    </div>

    <!-- SOURCE INFO BAR -->
    <div class="source-bar" data-aos="fade-up">
        <span class="sb-label">️ Sumber Didukung:</span>
        <span class="source-chip">▶️ YouTube <b>watch / youtu.be / shorts</b></span>
        <span class="source-chip"> Vimeo <b>vimeo.com/ID</b></span>
        <span class="source-chip">📁 File Lokal <b>.mp4 / .webm / .ogg</b></span>
        <span class="source-chip">🔗 Lainnya <b>URL embed</b></span>
    </div>

    <form method="POST" enctype="multipart/form-data" id="editorForm">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
        <input type="hidden" name="existing_thumb" id="existingThumb" value="<?= sanitize($edit['thumbnail'] ?? '') ?>">
        <input type="hidden" name="remove_thumb" id="removeThumb" value="0">
        <input type="hidden" name="duration_seconds" id="durSecHidden" value="<?= (int)($edit['duration_seconds'] ?? 0) ?>">

        <div class="editor-grid">
            <!-- MAIN -->
            <div class="editor-main">
                <!-- Judul -->
                <div class="editor-card title-card">
                    <input type="text" name="judul" id="judulInput" class="judul-input" placeholder="Tulis judul video yang menarik..." required maxlength="255" value="<?= sanitize($edit['judul'] ?? '') ?>">
                    <div class="judul-counter" id="judulCounter"><span><span id="judulCount"><?= mb_strlen($edit['judul'] ?? '') ?></span>/255 karakter</span></div>
                    <div class="slug-preview">
                        <span class="slug-prefix"><?= base_url('video.php') ?>#vid-</span>
                        <span class="slug-text" id="slugPreview"><?= sanitize($edit['slug'] ?? 'slug-akan-dibuat-otomatis') ?></span>
                    </div>
                    <input type="text" name="custom_slug" id="customSlug" class="custom-slug-input" placeholder="Atau ketik slug kustom di sini (opsional)">
                    <button type="button" class="slug-edit-btn" onclick="toggleCustomSlug()">✏️ Edit slug</button>
                </div>

                <!-- URL + PREVIEW -->
                <div class="editor-card">
                    <h3 class="card-title">🔗 Sumber Video *</h3>
                    <div class="field-group">
                        <label class="field-label"><span>URL / Embed</span><span class="type-badge tb-<?= sanitize($edit['video_type'] ?? 'youtube') ?>" id="typeBadge">▶ YOUTUBE</span></label>
                        <div class="url-row">
                            <input type="url" name="video_url" id="urlInput" class="styled-input" required placeholder="https://www.youtube.com/watch?v=..." value="<?= sanitize($edit['video_url'] ?? '') ?>">
                            <select name="video_type" id="typeSelect" class="styled-select" style="max-width:150px">
                                <option value="youtube" <?= ($edit['video_type'] ?? 'youtube')==='youtube'?'selected':'' ?>>▶ YouTube</option>
                                <option value="vimeo" <?= ($edit['video_type'] ?? '')==='vimeo'?'selected':'' ?>>🎥 Vimeo</option>
                                <option value="local" <?= ($edit['video_type'] ?? '')==='local'?'selected':'' ?>>📁 Lokal</option>
                                <option value="other" <?= ($edit['video_type'] ?? '')==='other'?'selected':'' ?>>🔗 Lainnya</option>
                            </select>
                        </div>
                        <div class="url-hint">💡 Tempel URL lengkap. Tipe terdeteksi otomatis. Untuk <b>file lokal</b>, isi path relatif mis. <code>uploads/video/files/cerita.mp4</code> atau URL absolut.</div>
                    </div>
                    <div class="preview-frame" id="previewFrame">
                        <div class="pf-empty"><span class="ic">🎬</span><span>Preview akan muncul saat URL valid</span></div>
                    </div>
                </div>

                <!-- Deskripsi -->
                <div class="editor-card">
                    <label class="field-label"><span>📝 Deskripsi</span><span class="char-count"><span id="descCount"><?= mb_strlen($edit['deskripsi'] ?? '') ?></span>/1000</span></label>
                    <textarea name="deskripsi" id="descInput" class="styled-textarea" maxlength="1000" rows="5" placeholder="Ringkasan isi video: poin utama, narasumber, timestamp penting, dll. (teks polos, muncul di modal player)"><?= sanitize($edit['deskripsi'] ?? '') ?></textarea>
                </div>

                <!-- Tags -->
                <div class="editor-card">
                    <label class="field-label"><span>🏷️ Tags</span><small style="color:var(--text-muted); margin-left:0.5rem; font-weight:500;">Pisahkan dengan koma atau tekan Enter</small></label>
                    <div class="tags-input-wrap">
                        <div class="tags-container" id="tagsContainer"></div>
                        <input type="text" id="tagInput" class="tag-input-field" placeholder="Ketik tag...">
                    </div>
                    <input type="hidden" name="tags" id="tagsHidden" value="<?= sanitize($edit['tags'] ?? '') ?>">
                </div>

                <!-- Secondary -->
                <div class="secondary-actions">
                    <?php if ($edit && $id > 0): ?>
                    <a href="?duplicate=<?= $id ?>" class="secondary-btn" onclick="return confirm('Duplikasi video ini sebagai draft baru?')">📋 Duplikasi</a>
                    <?php endif; ?>
                    <a href="video.php?tab=videos" class="secondary-btn">← Kembali</a>
                    <button type="reset" class="secondary-btn" onclick="return confirm('Reset semua field?')">🔄 Reset</button>
                </div>
                <div class="shortcuts-legend">
                    <span>💡 Shortcuts:</span>
                    <span><kbd>Ctrl</kbd>+<kbd>S</kbd> Save</span>
                    <span><kbd>Ctrl</kbd>+<kbd>P</kbd> Preview</span>
                    <span><kbd>Enter</kbd> / <kbd>,</kbd> Tambah tag</span>
                    <span><kbd>Esc</kbd> Tutup preview</span>
                </div>
            </div>

            <!-- SIDEBAR -->
            <aside class="editor-sidebar">
                <!-- Status -->
                <div class="editor-card">
                    <h3 class="card-title">📊 Status & Tipe Konten</h3>
                    <div class="field-group">
                        <label class="field-label">Status</label>
                        <div class="status-radios">
                            <label class="status-radio status-draft"><input type="radio" name="status" value="Draft" <?= ($edit['status'] ?? 'Draft') === 'Draft' ? 'checked' : '' ?>><span class="sr-dot"></span><span>📝 Draft</span></label>
                            <label class="status-radio status-published"><input type="radio" name="status" value="Published" <?= ($edit['status'] ?? '') === 'Published' ? 'checked' : '' ?>><span class="sr-dot"></span><span>✅ Published</span></label>
                            <label class="status-radio status-archived"><input type="radio" name="status" value="Archived" <?= ($edit['status'] ?? '') === 'Archived' ? 'checked' : '' ?>><span class="sr-dot"></span><span>🗄️ Archived</span></label>
                        </div>
                    </div>
                    <div class="toggle-row">
                        <span class="tr-label">⭐ Sorotan Editor</span>
                        <label class="switch"><input type="checkbox" name="is_featured" <?= !empty($edit['is_featured']) ? 'checked' : '' ?>><span class="slider"></span></label>
                    </div>
                    <div class="toggle-row">
                        <span class="tr-label">🎙️ Ini Podcast (audio)</span>
                        <label class="switch pod"><input type="checkbox" name="is_podcast" <?= !empty($edit['is_podcast']) ? 'checked' : '' ?>><span class="slider"></span></label>
                    </div>
                    <?php if ($edit && !empty($edit['id'])): ?>
                    <div class="meta-info">
                        <div class="meta-row"><span>Dibuat:</span><strong><?= $edit['created_at'] ? date('d M Y H:i', strtotime($edit['created_at'])) : '—' ?></strong></div>
                        <div class="meta-row"><span>Views:</span><strong><?= number_format((int)($edit['views'] ?? 0)) ?></strong></div>
                        <?php if (!empty($edit['published_at'])): ?><div class="meta-row"><span>Published:</span><strong><?= date('d M Y H:i', strtotime($edit['published_at'])) ?></strong></div><?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Kategori & Prodi -->
                <div class="editor-card">
                    <h3 class="card-title">📁 Kategori & Afiliasi</h3>
                    <div class="field-group">
                        <label class="field-label">Kategori</label>
                        <select name="kategori_id" class="styled-select" id="kategoriSelect">
                            <option value="0">— Tanpa Kategori —</option>
                            <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= (int)($edit['kategori_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= sanitize(($c['icon'] ?? '📁') . ' ' . $c['nama']) ?></option>
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

                <!-- Durasi -->
                <div class="editor-card">
                    <h3 class="card-title">⏱️ Durasi</h3>
                    <div class="dur-row">
                        <div class="field-group" style="margin:0">
                            <label class="field-label">Format MM:SS / HH:MM:SS</label>
                            <input type="text" name="duration" id="durTextInput" class="styled-input" placeholder="12:34" value="<?= sanitize($edit['duration'] ?? '') ?>">
                        </div>
                        <div class="field-group" style="margin:0">
                            <label class="field-label">Detik (otomatis)</label>
                            <input type="number" id="durSecInput" class="styled-input" min="0" max="86400" value="<?= (int)($edit['duration_seconds'] ?? 0) ?>">
                        </div>
                    </div>
                    <div class="url-hint" style="margin-top:0.5rem">💡 Isi salah satu; keduanya tersinkron otomatis. Durasi tampil sebagai badge di kartu video.</div>
                </div>

                <!-- Thumbnail -->
                <div class="editor-card">
                    <h3 class="card-title">🖼️ Thumbnail</h3>
                    <div class="upload-zone" id="uploadZone">
                        <input type="file" name="gambar" id="gambarInput" accept="image/jpeg,image/png,image/webp,image/gif" class="upload-file-input">
                        <div class="upload-placeholder" id="uploadPlaceholder">
                            <div class="upload-icon">📤</div>
                            <p class="upload-text">Drag & drop thumbnail</p>
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
                    <button type="button" class="fetch-thumb-btn" id="fetchThumbBtn" onclick="fetchYouTubeThumb()">
                        <span class="ft-icon">🎯</span><span class="ft-text">Ambil Thumbnail Otomatis dari YouTube</span>
                    </button>
                    <?php if (!empty($edit['thumbnail'])): ?>
                    <div class="current-image" id="currentImage">
                        <label class="field-label">Thumbnail saat ini:</label>
                        <img src="<?= asset('uploads/video/' . basename($edit['thumbnail'])) ?>" alt="">
                        <small>Upload / ambil otomatis untuk mengganti</small>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- SEO -->
                <div class="editor-card seo-card">
                    <h3 class="card-title">🔍 Kesiapan Tayang</h3>
                    <div class="seo-score-box">
                        <div class="seo-score-circle"><svg viewBox="0 0 36 36"><circle cx="18" cy="18" r="15.915" class="ring-bg"/><circle cx="18" cy="18" r="15.915" class="ring-fill" id="seoRing" style="stroke-dasharray: 0, 100"/></svg><div class="seo-score-value" id="seoScoreValue">0</div></div>
                        <div class="seo-score-info"><div class="seo-score-label" id="seoScoreLabel">Analisis</div><div class="seo-score-text" id="seoScoreText">Isi form untuk melihat skor</div></div>
                    </div>
                    <ul class="seo-checklist" id="seoChecklist"><li>⏳ Menunggu data...</li></ul>
                </div>

                <!-- Tips -->
                <div class="editor-card tips-card">
                    <h3 class="card-title">💡 Tips Konten Video</h3>
                    <ul class="tips-list">
                        <li>✓ Judul jelas &amp; deskriptif (30-90 karakter)</li>
                        <li>✓ Deskripsi 80-300 karakter untuk SEO</li>
                        <li>✓ Thumbnail 16:9 berkualitas tinggi</li>
                        <li>✓ Untuk YouTube, pakai tombol <b>Ambil Otomatis</b></li>
                        <li>✓ Tandai 🎙️ bila berupa podcast/audio</li>
                        <li>✓ Pilih kategori agar mudah difilter</li>
                        <li>✓ Gunakan 2-5 tags relevan</li>
                    </ul>
                </div>
            </aside>
        </div>
    </form>
</div>

<!-- PREVIEW MODAL -->
<div class="modal-overlay" id="previewModal" onclick="if(event.target===this)closePreview()">
    <div class="preview-modal-box">
        <div class="preview-header"><span>👁️ Preview Tampilan Video</span><button class="preview-close" onclick="closePreview()">✕</button></div>
        <div class="preview-body">
            <div class="pv-player" id="pvPlayer"></div>
            <h2 class="pv-title" id="pvTitle">Judul video...</h2>
            <div class="pv-meta" id="pvMeta"></div>
            <div class="pv-desc" id="pvDesc"></div>
        </div>
    </div>
</div>

<div class="toast-helper" id="toastHelper"></div>

<script>
const INIT = <?= json_encode($initial, JSON_UNESCAPED_UNICODE) ?>;
const CSRF = <?= json_encode($csrf) ?>;
const FETCH_URL = <?= json_encode(base_url('admin/video-form.php')) ?>;

const $ = id => document.getElementById(id);
const judulInput = $('judulInput'), slugPreview = $('slugPreview'), customSlug = $('customSlug');
const urlInput = $('urlInput'), typeSelect = $('typeSelect'), typeBadge = $('typeBadge');
const descInput = $('descInput'), durTextInput = $('durTextInput'), durSecInput = $('durSecInput'), durSecHidden = $('durSecHidden');
const previewFrame = $('previewFrame'), uploadZone = $('uploadZone'), gambarInput = $('gambarInput');
const uploadPlaceholder = $('uploadPlaceholder'), uploadPreview = $('uploadPreview'), previewImg = $('previewImg');
const fetchBtn = $('fetchThumbBtn'), existingThumb = $('existingThumb'), removeThumb = $('removeThumb');
const tagInput = $('tagInput'), tagsContainer = $('tagsContainer'), tagsHidden = $('tagsHidden');

let tags = [];
if (tagsHidden.value) { tags = tagsHidden.value.split(',').map(t => t.trim()).filter(t => t); renderTags(); }

// ===== SLUG =====
function slugify(t){return t.toString().toLowerCase().replace(/\s+/g,'-').replace(/[^\w\-]+/g,'').replace(/\-\-+/g,'-').replace(/^-+/,'').replace(/-+$/,'');}
judulInput.addEventListener('input', function(){
    if (customSlug.style.display !== 'block') slugPreview.textContent = slugify(this.value) || 'slug-akan-dibuat-otomatis';
    $('judulCount').textContent = this.value.length;
    const c = $('judulCounter');
    c.classList.toggle('warn', this.value.length > 180 && this.value.length <= 255);
    c.classList.toggle('danger', this.value.length > 255);
    triggerAutosave(); updateSEO(); updateProgress();
});
function toggleCustomSlug(){ const s = customSlug.style.display === 'block'; customSlug.style.display = s ? 'none' : 'block'; if (!s) { customSlug.value = slugPreview.textContent.startsWith('slug-') ? '' : slugPreview.textContent; customSlug.focus(); } }
customSlug.addEventListener('input', function(){ slugPreview.textContent = slugify(this.value) || slugify(judulInput.value) || '...'; });

// ===== DETEKSI TIPE + PREVIEW URL =====
function detectType(url){
    url = (url||'').trim();
    if (!url) return 'other';
    if (/youtube\.com|youtu\.be/i.test(url)) return 'youtube';
    if (/vimeo\.com/i.test(url)) return 'vimeo';
    if (/\.(mp4|webm|ogg)(\?.*)?$/i.test(url)) return 'local';
    return 'other';
}
function ytId(url){ const m = (url||'').match(/(?:v=|youtu\.be\/|shorts\/|embed\/)([A-Za-z0-9_-]{11})/); return m ? m[1] : null; }
function embedSrc(url, type){
    url = (url||'').trim(); if (!url) return '';
    if (type === 'youtube' || /youtube\.com|youtu\.be/i.test(url)) { const id = ytId(url); return id ? 'https://www.youtube.com/embed/'+id+'?rel=0&modestbranding=1' : ''; }
    if (type === 'vimeo' || /vimeo\.com/i.test(url)) { const m = url.match(/(?:vimeo\.com\/|player\.vimeo\.com\/video\/)(\d+)/); return m ? 'https://player.vimeo.com/video/'+m[1] : ''; }
    if (type === 'local' || /\.(mp4|webm|ogg)(\?.*)?$/i.test(url)) return url;
    return '';
}
function updateTypeBadge(type){
    const map = { youtube:['▶ YOUTUBE','tb-youtube'], vimeo:['🎥 VIMEO','tb-vimeo'], local:['📁 LOKAL','tb-local'], other:['🔗 LAINNYA','tb-other'] };
    const [lbl, cls] = map[type] || map.other;
    typeBadge.textContent = lbl; typeBadge.className = 'type-badge ' + cls;
}
let pvTimer;
function refreshPreview(){
    clearTimeout(pvTimer);
    pvTimer = setTimeout(function(){
        const url = urlInput.value.trim(), type = typeSelect.value;
        const src = embedSrc(url, type);
        if (!src) { previewFrame.classList.remove('show'); previewFrame.innerHTML = '<div class="pf-empty"><span class="ic">🎬</span><span>Preview akan muncul saat URL valid</span></div>'; return; }
        previewFrame.classList.add('show');
        if (type === 'local') previewFrame.innerHTML = '<video src="'+src+'" controls preload="metadata"></video>';
        else previewFrame.innerHTML = '<iframe src="'+src+'" title="preview" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
    }, 600);
}
urlInput.addEventListener('input', function(){
    const auto = detectType(this.value);
    typeSelect.value = auto; updateTypeBadge(auto);
    refreshPreview(); syncFetchBtn(); triggerAutosave(); updateSEO(); updateProgress();
});
typeSelect.addEventListener('change', function(){ updateTypeBadge(this.value); refreshPreview(); syncFetchBtn(); updateSEO(); });

// ===== DURASI SINKRON =====
function parseDur(str){ str=(str||'').trim(); if(!str) return 0; if(/^\d+$/.test(str)) return parseInt(str); const p=str.split(':').reverse(); let s=0,m=1; p.forEach(x=>{ s+=(parseInt(x)||0)*m; m*=60; }); return Math.max(0,s); }
function fmtDur(sec){ sec=parseInt(sec)||0; if(sec<=0) return ''; const h=Math.floor(sec/3600), m=Math.floor((sec%3600)/60), s=sec%60; const pad=n=>String(n).padStart(2,'0'); return h>0 ? h+':'+pad(m)+':'+pad(s) : m+':'+pad(s); }
durTextInput.addEventListener('input', function(){ const sec = parseDur(this.value); durSecInput.value = sec; durSecHidden.value = sec; triggerAutosave(); updateSEO(); });
durSecInput.addEventListener('input', function(){ const sec = parseInt(this.value)||0; durSecHidden.value = sec; durTextInput.value = fmtDur(sec); triggerAutosave(); updateSEO(); });

// ===== TAGS =====
function renderTags(){ tagsContainer.innerHTML=''; tags.forEach((tag,i)=>{ const pill=document.createElement('span'); pill.className='tag-pill'; pill.innerHTML=`${tag} <button type="button" onclick="removeTag(${i})">✕</button>`; tagsContainer.appendChild(pill); }); tagsHidden.value=tags.join(', '); updateSEO(); updateProgress(); }
function addTag(v){ v=v.trim(); if(v && !tags.includes(v) && tags.length<10){ tags.push(v); renderTags(); triggerAutosave(); } }
window.removeTag = function(i){ tags.splice(i,1); renderTags(); triggerAutosave(); };
tagInput.addEventListener('keydown', function(e){ if(e.key==='Enter'||e.key===','){ e.preventDefault(); addTag(this.value.replace(',','')); this.value=''; } else if(e.key==='Backspace'&&!this.value&&tags.length>0){ tags.pop(); renderTags(); } });
tagInput.addEventListener('blur', function(){ if(this.value.trim()){ addTag(this.value); this.value=''; } });

// ===== UPLOAD =====
['dragenter','dragover'].forEach(ev=>uploadZone.addEventListener(ev,e=>{e.preventDefault();uploadZone.classList.add('dragover');}));
['dragleave','drop'].forEach(ev=>uploadZone.addEventListener(ev,e=>{e.preventDefault();uploadZone.classList.remove('dragover');}));
uploadZone.addEventListener('drop',e=>{ const f=e.dataTransfer.files; if(f.length>0) handleFile(f[0]); });
gambarInput.addEventListener('change',e=>{ if(e.target.files.length>0) handleFile(e.target.files[0]); });
function handleFile(file){
    if(!file.type.startsWith('image/')){ showToast('File harus berupa gambar!','error'); return; }
    if(file.size>5*1024*1024){ showToast('Ukuran maksimal 5MB!','error'); return; }
    const r=new FileReader();
    r.onload=e=>{ previewImg.src=e.target.result; uploadPlaceholder.style.display='none'; uploadPreview.style.display='block'; removeThumb.value='0'; existingThumb.value=''; /* upload manual membatalkan existing */ updateSEO(); };
    r.readAsDataURL(file);
}
window.removePreview = function(){
    gambarInput.value=''; previewImg.src=''; uploadPlaceholder.style.display='block'; uploadPreview.style.display='none';
    existingThumb.value=''; removeThumb.value='1'; /* tandai hapus saat save */
    const ci=$('currentImage'); if(ci) ci.style.display='none';
    updateSEO(); updateProgress(); showToast('Thumbnail ditandai untuk dihapus','info');
};

// ===== FETCH THUMBNAIL YOUTUBE =====
function syncFetchBtn(){
    const isYt = typeSelect.value === 'youtube' && ytId(urlInput.value);
    fetchBtn.style.display = isYt ? 'flex' : 'none';
}
window.fetchYouTubeThumb = function(){
    const url = urlInput.value.trim(); const id = ytId(url);
    if (!id) { showToast('Bukan URL YouTube yang valid','error'); return; }
    fetchBtn.classList.add('loading'); fetchBtn.disabled = true;
    fetchBtn.querySelector('.ft-text').textContent = 'Mengambil...';
    const fd = new FormData(); fd.append('action','fetch_thumb'); fd.append('csrf_token',CSRF); fd.append('url',url);
    fetch(FETCH_URL, {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}})
    .then(r=>r.json()).then(d=>{
        fetchBtn.classList.remove('loading'); fetchBtn.disabled=false;
        fetchBtn.querySelector('.ft-text').textContent='Ambil Thumbnail Otomatis dari YouTube';
        if(d.ok){
            existingThumb.value = d.name; removeThumb.value='0'; gambarInput.value='';
            previewImg.src = d.url; uploadPlaceholder.style.display='none'; uploadPreview.style.display='block';
            const ci=$('currentImage'); if(ci) ci.style.display='none';
            showToast('Thumbnail berhasil diambil ✓','success'); updateSEO(); updateProgress(); triggerAutosave();
        } else showToast(d.msg||'Gagal mengambil thumbnail','error');
    }).catch(()=>{ fetchBtn.classList.remove('loading'); fetchBtn.disabled=false; fetchBtn.querySelector('.ft-text').textContent='Ambil Thumbnail Otomatis dari YouTube'; showToast('Koneksi gagal','error'); });
};

// ===== PROGRESS =====
function updateProgress(){
    const fields=[
        {el:judulInput, weight:20},{el:urlInput, weight:25},{el:descInput, weight:15},
        {check:()=>($('kategoriSelect').value>0), weight:10},
        {check:()=>previewImg.src||existingThumb.value||$('currentImage'), weight:15},
        {check:()=>tags.length>0, weight:10},{check:()=>durSecHidden.value>0, weight:5}
    ];
    let score=0,total=0;
    fields.forEach(f=>{ total+=f.weight; if(f.check){ if(f.check()) score+=f.weight; } else { if(f.el && f.el.value && f.el.value.trim()) score+=f.weight; } });
    const pct=Math.round((score/total)*100);
    $('progressBar').style.width=pct+'%'; $('progressPercent').textContent=pct+'%';
}

// ===== SEO / KESIAPAN =====
function updateSEO(){
    const judul=judulInput.value.trim(), desc=descInput.value.trim(), url=urlInput.value.trim();
    const type=typeSelect.value, hasThumb=previewImg.src||existingThumb.value||!!$('currentImage');
    const hasEmbed=!!embedSrc(url,type); const kat=$('kategoriSelect').value>0;
    let score=0; const checks=[];
    if(judul.length>=30&&judul.length<=90){score+=25;checks.push('✅ Judul optimal (30-90 karakter)');}
    else if(judul.length>=15){score+=15;checks.push('⚠️ Judul cukup, bisa lebih deskriptif');}
    else if(judul.length>0){score+=5;checks.push('❌ Judul terlalu pendek');}
    else checks.push('❌ Judul belum diisi');
    if(desc.length>=80&&desc.length<=300){score+=20;checks.push('✅ Deskripsi optimal (80-300 karakter)');}
    else if(desc.length>=40){score+=12;checks.push('⚠️ Deskripsi cukup');}
    else if(desc.length>0){score+=5;checks.push('❌ Deskripsi terlalu pendek');}
    else checks.push('❌ Deskripsi belum diisi');
    if(hasThumb){score+=15;checks.push('✅ Thumbnail tersedia');}else checks.push('❌ Belum ada thumbnail');
    if(hasEmbed){score+=20;checks.push('✅ Sumber valid & dapat diputar');}
    else if(url){score+=8;checks.push('⚠️ URL belum dikenali sebagai sumber putar');}
    else checks.push('❌ URL sumber belum diisi');
    if(kat){score+=10;checks.push('✅ Kategori terpilih');}else checks.push('⚠️ Pilih kategori (opsional tapi disarankan)');
    if(tags.length>=2){score+=10;checks.push('✅ Tags cukup (2+)');}
    else if(tags.length>0){score+=4;checks.push('⚠️ Tambahkan tags');}
    else checks.push('❌ Belum ada tags');
    score=Math.min(100,score);
    $('seoRing').style.strokeDasharray=score+',100'; $('seoScoreValue').textContent=score;
    let label='Perlu kerja',color='#ef4444',text='Lengkapi field penting';
    if(score>=80){label='Siap Tayang';color='#10b981';text='Video siap dipublikasikan!';}
    else if(score>=60){label='Hampir Siap';color='#3b82f6';text='Beberapa penyempurnaan lagi';}
    else if(score>=40){label='Cukup';color='#f59e0b';text='Perlu beberapa perbaikan';}
    $('seoScoreLabel').textContent=label; $('seoScoreLabel').style.color=color;
    $('seoScoreText').textContent=text; $('seoRing').style.stroke=color; $('seoScoreValue').style.color=color;
    $('seoChecklist').innerHTML=checks.map(c=>`<li>${c}</li>`).join('');
}

// ===== AUTOSAVE =====
const storageKey='fkip_video_draft_'+(INIT.id||'new');
let asTimer;
function triggerAutosave(){
    clearTimeout(asTimer);
    const ind=$('autosaveStatus'); ind.classList.add('saving'); ind.classList.remove('saved'); ind.querySelector('.as-text').textContent='Menyimpan...';
    asTimer=setTimeout(()=>{
        const data={judul:judulInput.value,url:urlInput.value,type:typeSelect.value,desc:descInput.value,kategori:$('kategoriSelect').value,prodi:document.querySelector('[name=program_studi_id]').value,tags:tags.join(','),dur:durTextInput.value,durSec:durSecHidden.value,featured:document.querySelector('[name=is_featured]').checked,podcast:document.querySelector('[name=is_podcast]').checked,saved_at:new Date().toISOString()};
        try{ localStorage.setItem(storageKey,JSON.stringify(data)); ind.classList.remove('saving'); ind.classList.add('saved'); ind.querySelector('.as-text').textContent='Tersimpan '+new Date().toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'}); }catch(e){}
    },800);
}
(function(){
    if(INIT.id) return; // hanya untuk video baru
    try{
        const saved=localStorage.getItem(storageKey);
        if(saved){ const d=JSON.parse(saved);
            if(confirm('Ada draft tersimpan dari '+new Date(d.saved_at).toLocaleString('id-ID')+'. Muat draft tersebut?')){
                judulInput.value=d.judul||''; urlInput.value=d.url||''; if(d.type)typeSelect.value=d.type; descInput.value=d.desc||'';
                if(d.kategori)$('kategoriSelect').value=d.kategori; if(d.prodi)document.querySelector('[name=program_studi_id]').value=d.prodi;
                if(d.tags){tags=d.tags.split(',').filter(t=>t);renderTags();}
                if(d.dur)durTextInput.value=d.dur; if(d.durSec){durSecInput.value=d.durSec;durSecHidden.value=d.durSec;}
                if(d.featured!==undefined)document.querySelector('[name=is_featured]').checked=d.featured;
                if(d.podcast!==undefined)document.querySelector('[name=is_podcast]').checked=d.podcast;
                judulInput.dispatchEvent(new Event('input')); urlInput.dispatchEvent(new Event('input')); descInput.dispatchEvent(new Event('input'));
                updateTypeBadge(typeSelect.value); refreshPreview(); syncFetchBtn();
                showToast('Draft dimuat','success');
            } else localStorage.removeItem(storageKey);
        }
    }catch(e){}
})();
$('editorForm').addEventListener('submit',function(e){
    if(!judulInput.value.trim()){e.preventDefault();showToast('Judul wajib diisi!','error');return;}
    if(!urlInput.value.trim()){e.preventDefault();showToast('URL sumber video wajib diisi!','error');return;}
    durSecHidden.value=parseInt(durSecInput.value)||parseDur(durTextInput.value);
    setTimeout(()=>{try{localStorage.removeItem(storageKey);}catch(e){}},500);
});

// ===== PREVIEW MODAL =====
window.openPreview=function(){
    const url=urlInput.value.trim(), type=typeSelect.value, src=embedSrc(url,type);
    const pl=$('pvPlayer');
    if(!src) pl.innerHTML='<div class="pf-empty" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#64748b;flex-direction:column;gap:.5rem"><span style="font-size:2.5rem">🎬</span><span>URL belum valid untuk preview</span></div>';
    else if(type==='local') pl.innerHTML='<video src="'+src+'" controls autoplay playsinline></video>';
    else pl.innerHTML='<iframe src="'+src+'&autoplay=1" title="preview" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>';
    $('pvTitle').textContent=judulInput.value||'Judul video...';
    const katOpt=$('kategoriSelect').selectedOptions[0]; const katTxt=katOpt?katOpt.textContent.trim():'Tanpa Kategori';
    const pod=document.querySelector('[name=is_podcast]').checked;
    const meta=['📁 '+katTxt, (pod?'🎙️ Podcast':'🎬 Video'), '⏱️ '+(fmtDur(durSecHidden.value)||'—'), '🔗 '+type.toUpperCase()];
    $('pvMeta').innerHTML=meta.map(m=>'<span>'+m+'</span>').join('');
    $('pvDesc').textContent=descInput.value||'(belum ada deskripsi)';
    $('previewModal').classList.add('open'); document.body.style.overflow='hidden';
};
window.closePreview=function(){ $('previewModal').classList.remove('open'); $('pvPlayer').innerHTML=''; document.body.style.overflow=''; };

// ===== TOAST =====
function showToast(msg,type='info'){
    const t=document.createElement('div');
    t.style.cssText='background:var(--bg-primary);border:1px solid var(--border);border-radius:12px;padding:0.85rem 1.25rem;box-shadow:0 10px 30px rgba(0,0,0,0.15);display:flex;align-items:center;gap:0.75rem;margin-top:0.5rem;animation:fadeIn 0.3s;min-width:260px;';
    const icons={success:'✅',error:'❌',warning:'⚠️',info:'ℹ️'},colors={success:'#10b981',error:'#ef4444',warning:'#f59e0b',info:'#3b82f6'};
    t.innerHTML=`<span style="font-size:1.4rem">${icons[type]||icons.info}</span><div style="font-weight:600;color:${colors[type]||colors.info};font-size:0.88rem">${msg}</div>`;
    $('toastHelper').appendChild(t);
    setTimeout(()=>{t.style.opacity='0';t.style.transform='translateX(100%)';t.style.transition='all 0.3s';setTimeout(()=>t.remove(),300);},3000);
}

// ===== SHORTCUTS =====
document.addEventListener('keydown',e=>{
    if(e.key==='Escape') closePreview();
    if((e.ctrlKey||e.metaKey)&&e.key==='s'){e.preventDefault();$('editorForm').dispatchEvent(new Event('submit',{cancelable:true}));}
    if((e.ctrlKey||e.metaKey)&&e.key==='p'){e.preventDefault();openPreview();}
});

// ===== INIT =====
updateTypeBadge(typeSelect.value); refreshPreview(); syncFetchBtn();
if(INIT.thumb_url){ previewImg.src=INIT.thumb_url; uploadPlaceholder.style.display='none'; uploadPreview.style.display='block'; }
descInput.addEventListener('input',function(){ $('descCount').textContent=this.value.length; triggerAutosave(); updateSEO(); updateProgress(); });
updateSEO(); updateProgress();
console.log('%c🎬 Editor Video & Podcast FKIP UNIMOF - EXTREME MULTIMATE','color:#dc2626;font-size:16px;font-weight:bold');
console.log('%cShortcuts: Ctrl+S (Save), Ctrl+P (Preview), Esc (Close)','color:#64748b');
console.log('%cFitur: Auto-fetch thumbnail YouTube, preview embed live, deteksi tipe, sinkron durasi, autosave','color:#64748b');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>