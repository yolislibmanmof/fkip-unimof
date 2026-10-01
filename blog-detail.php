<?php
// blog-detail.php - Detail Artikel Ide & Wawasan - EXTREME MULTIMATE VERSION
require_once __DIR__ . '/includes/config.php';

// =====================================================
// HELPER LOKAL
// =====================================================
if (!function_exists('blog_slugify')) {
    function blog_slugify($text) {
        $text = trim(strtolower(strip_tags($text)));
        $text = preg_replace('/[^a-z0-9]+/u', '-', $text);
        $text = trim($text, '-');
        return $text !== '' ? $text : 'bagian';
    }
}
if (!function_exists('blog_kses')) {
    function blog_kses($html) {
        $allowed = '<p><br><b><strong><i><em><u><s><a><ul><ol><li><h2><h3><h4><h5><h6>'
                 . '<blockquote><code><pre><img><iframe><table><thead><tbody><tr><td><th><hr><span><div>';
        $out = strip_tags((string)$html, $allowed);
        $out = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $out);
        $out = preg_replace('/(href|src)\s*=\s*(["\']?)\s*javascript:[^"\'>\s]*/i', '$1=$2#', $out);
        $out = preg_replace_callback('/<iframe([^>]*)>/i', function ($m) {
            if (preg_match('~src\s*=\s*["\']?https?://(www\.)?(youtube\.com|youtu\.be|player\.vimeo\.com)~i', $m[1])) return $m[0];
            return '';
        }, $out);
        return $out;
    }
}
if (!function_exists('blog_build_toc')) {
    function blog_build_toc(&$html) {
        $toc = []; $seen = [];
        $html = preg_replace_callback('/<(h[23])([^>]*)>(.*?)<\/\1>/is', function ($m) use (&$toc, &$seen) {
            $tag = $m[1]; $attrs = $m[2]; $inner = $m[3];
            $text = trim(strip_tags($inner));
            if ($text === '') return $m[0];
            $id = 'sec-' . blog_slugify($text); $base = $id; $i = 2;
            while (isset($seen[$id])) { $id = $base . '-' . $i; $i++; }
            $seen[$id] = true;
            $toc[] = ['id' => $id, 'level' => $tag, 'text' => $text];
            return "<$tag id=\"$id\"$attrs>$inner</$tag>";
        }, $html);
        return $toc;
    }
}
if (!function_exists('blog_reading_time')) {
    function blog_reading_time($row) {
        $rt = (int)($row['reading_time'] ?? 0);
        if ($rt > 0) return $rt;
        $words = str_word_count(strip_tags((string)($row['konten'] ?? '')));
        return max(1, (int)ceil($words / 200));
    }
}
if (!function_exists('blog_col')) {
    function blog_col($pdo, $table, $col) {
        static $cache = [];
        $key = "$table.$col";
        if (isset($cache[$key])) return $cache[$key];
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
            $cache[$key] = in_array($col, $cols, true);
        } catch (Exception $e) { $cache[$key] = false; }
        return $cache[$key];
    }
}

// =====================================================
// KONTEKS & SLUG
// =====================================================
$slug = trim($_GET['slug'] ?? '');
$flash = $_GET['c'] ?? '';

// =====================================================
// ENDPOINT: SUKA (AJAX JSON) — diproses sebelum output apa pun
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'blog_like') {
    header('Content-Type: application/json; charset=utf-8');
    $s = trim($_POST['slug'] ?? '');
    $liked_session = 'blog_liked_' . md5($s);
    try {
        $stmt = $pdo->prepare("SELECT id, likes FROM blog_artikel WHERE slug = ? AND status = 'Published' LIMIT 1");
        $stmt->execute([$s]);
        $row = $stmt->fetch();
        if (!$row) { echo json_encode(['ok' => false, 'msg' => 'Artikel tidak ditemukan']); exit; }
        if (!empty($_SESSION[$liked_session])) {
            echo json_encode(['ok' => true, 'likes' => (int)$row['likes'], 'already' => true]); exit;
        }
        $pdo->prepare("UPDATE blog_artikel SET likes = likes + 1 WHERE id = ?")->execute([$row['id']]);
        $_SESSION[$liked_session] = true;
        echo json_encode(['ok' => true, 'likes' => (int)$row['likes'] + 1]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'msg' => 'Terjadi kesalahan']);
    }
    exit;
}

// =====================================================
// PROSES: KOMENTAR BARU (PRG pattern + anti-spam)
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'blog_comment') {
    $hp = trim($_POST['website_url'] ?? '');          // honeypot
    $c_nama  = trim($_POST['nama'] ?? '');
    $c_email = trim($_POST['email'] ?? '');
    $c_isi   = trim($_POST['komentar'] ?? '');
    $c_parent = (int)($_POST['parent_id'] ?? 0);
    $target = base_url('blog-detail.php?slug=' . urlencode($slug));

    // Honeypot terisi -> anggap bot, redirect senyap
    if ($hp !== '') { header('Location: ' . $target . '#comments'); exit; }
    // Rate limit 20 detik antar komentar
    $last = $_SESSION['blog_last_comment'] ?? 0;
    if (time() - $last < 20) { header('Location: ' . $target . '?c=slow#c'); exit; }

    if ($c_nama === '' || mb_strlen($c_isi) < 3) {
        header('Location: ' . $target . '?c=invalid#c'); exit;
    }
    if ($c_email !== '' && !filter_var($c_email, FILTER_VALIDATE_EMAIL)) {
        header('Location: ' . $target . '?c=email#c'); exit;
    }
    try {
        $ins = $pdo->prepare("INSERT INTO blog_komentar (artikel_id, parent_id, nama, email, komentar, status)
                              SELECT id, ?, ?, ?, ?, 'Pending' FROM blog_artikel WHERE slug = ? AND status='Published' LIMIT 1");
        $ins->execute([$c_parent > 0 ? $c_parent : null, $c_nama, $c_email ?: null, $c_isi]);
        if ($ins->rowCount() > 0) {
            $_SESSION['blog_last_comment'] = time();
            header('Location: ' . $target . '?c=ok#c'); exit;
        }
    } catch (Exception $e) {}
    header('Location: ' . $target . '?c=error#c'); exit;
}

// =====================================================
// AMBIL ARTIKEL
// =====================================================
$artikel = false;
if ($slug !== '') {
    try {
        $stmt = $pdo->prepare("SELECT b.*, d.nama AS dosen_nama, d.gelar_depan, d.gelar_belakang,
                                      d.foto AS dosen_foto, d.jabatan_fungsional, d.bidang_keahlian,
                                      d.email AS dosen_email, p.nama AS prodi_nama, p.singkatan AS prodi_singkatan
                               FROM blog_artikel b
                               LEFT JOIN dosen d ON b.dosen_id = d.id
                               LEFT JOIN program_studi p ON b.program_studi_id = p.id
                               WHERE b.slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $artikel = $stmt->fetch();
    } catch (Exception $e) { $artikel = false; }
}

// =====================================================
// 404 — artikel tidak ditemukan / draft
// =====================================================
if (!$artikel || $artikel['status'] !== 'Published') {
    $page_title = 'Artikel Tidak Ditemukan';
    require_once __DIR__ . '/includes/header.php';
    ?>
    <section class="section" style="padding:8rem 0 5rem; text-align:center;">
        <div class="container">
            <div style="font-size:5rem; margin-bottom:1rem;">🔍</div>
            <h1 style="font-family:var(--font-display); font-size:2rem; margin-bottom:.75rem;">Artikel Tidak Ditemukan</h1>
            <p style="color:var(--text-muted); max-width:520px; margin:0 auto 2rem;">Tautan yang Anda tuju mungkin telah dipindahkan, dihapus, atau belum dipublikasikan.</p>
            <a href="<?= base_url('blog.php') ?>" class="btn btn-primary">← Kembali ke Ide &amp; Wawasan</a>
        </div>
    </section>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// =====================================================
// INCREMENT VIEW (anti-refresh: per sesi per artikel)
// =====================================================
$view_key = 'blog_viewed_' . $artikel['id'];
if (empty($_SESSION[$view_key])) {
    try { $pdo->prepare("UPDATE blog_artikel SET views = views + 1 WHERE id = ?")->execute([$artikel['id']]); } catch (Exception $e) {}
    $_SESSION[$view_key] = true;
    $artikel['views'] = (int)$artikel['views'] + 1;
}

// =====================================================
// META DINAMIS
// =====================================================
$page_title = $artikel['judul'];
$page_description = excerpt($artikel['excerpt'] ?: $artikel['konten'], 160);
$author_full = trim(($artikel['gelar_depan'] ?? '') . ' ' . ($artikel['dosen_nama'] ?? 'Redaksi FKIP UNIMOF') . ' ' . ($artikel['gelar_belakang'] ?? ''));
$published = !empty($artikel['published_at']) ? $artikel['published_at'] : $artikel['created_at'];
$read_time = blog_reading_time($artikel);
$liked = !empty($_SESSION['blog_liked_' . md5($slug)]);

// Konten + TOC
$konten_html = blog_kses($artikel['konten'] ?? '');
$toc = blog_build_toc($konten_html);

// =====================================================
// ARTIKEL TERKAIT (kategori sama)
// =====================================================
$related = [];
try {
    $stmt = $pdo->prepare("SELECT judul, slug, gambar, kategori, published_at, views
                           FROM blog_artikel
                           WHERE status='Published' AND kategori = ? AND id != ?
                           ORDER BY published_at DESC LIMIT 3");
    $stmt->execute([$artikel['kategori'], $artikel['id']]);
    $related = $stmt->fetchAll();
} catch (Exception $e) {}
if (count($related) < 3) {
    try {
        $have = array_merge([$artikel['id']], array_column($related, 'id'));
        $in = implode(',', array_map('intval', $have));
        $stmt2 = $pdo->query("SELECT judul, slug, gambar, kategori, published_at, views
                              FROM blog_artikel
                              WHERE status='Published' AND id NOT IN ($in)
                              ORDER BY views DESC LIMIT " . (3 - count($related)));
        $related = array_merge($related, $stmt2->fetchAll());
    } catch (Exception $e) {}
}

// =====================================================
// SIDEBAR: KATEGORI + TERPOPULER
// =====================================================
$kategor_counts = [];
try {
    $kategor_counts = $pdo->query("SELECT kategori, COUNT(*) c FROM blog_artikel WHERE status='Published' GROUP BY kategori ORDER BY c DESC")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {}
$popular = [];
try {
    $popular = $pdo->query("SELECT judul, slug, views, likes, published_at FROM blog_artikel WHERE status='Published' ORDER BY views DESC LIMIT 5")->fetchAll();
} catch (Exception $e) {}

// =====================================================
// KOMENTAR (hanya Approved) — bangun pohon threaded
// =====================================================
$comments_flat = [];
try {
    $sel_c = "id, parent_id, nama, komentar, created_at";
    $stmt = $pdo->query("SELECT $sel_c FROM blog_komentar WHERE artikel_id = " . (int)$artikel['id'] . " AND status='Approved' ORDER BY created_at ASC");
    $comments_flat = $stmt->fetchAll();
} catch (Exception $e) { $comments_flat = []; }
$total_comments = count($comments_flat);

// Group: root + children
$comment_tree = []; $by_id = [];
foreach ($comments_flat as $c) { $by_id[$c['id']] = $c; $c['children'] = []; $comment_tree[$c['id']] = &$c; }
unset($c);
$roots = [];
foreach ($comment_tree as $id => &$node) {
    $pid = $node['parent_id'];
    if ($pid && isset($comment_tree[$pid])) { $comment_tree[$pid]['children'][] = &$node; }
    else { $roots[] = &$node; }
}
unset($node);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Schema.org Article -->
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Article",
    "headline": <?= json_encode($artikel['judul']) ?>,
    "description": <?= json_encode($page_description) ?>,
    "image": <?= json_encode(!empty($artikel['gambar']) ? asset('uploads/blog/' . basename($artikel['gambar'])) : asset('images/og-default.jpg')) ?>,
    "author": { "@type": "Person", "name": <?= json_encode($author_full) ?> },
    "publisher": { "@type": "Organization", "name": "FKIP UNIMOF", "logo": { "@type": "ImageObject", "url": <?= json_encode(asset('images/logo.png')) ?> } },
    "datePublished": <?= json_encode(date('c', strtotime($published))) ?>,
    "dateModified": <?= json_encode(date('c', strtotime($artikel['updated_at'] ?: $published))) ?>,
    "wordCount": <?= (int)str_word_count(strip_tags($artikel['konten'] ?? '')) ?>,
    "articleSection": <?= json_encode($artikel['kategori']) ?>,
    "mainEntityOfPage": { "@type": "WebPage", "@id": <?= json_encode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on'?'https':'http').'://'.($_SERVER['HTTP_HOST']??'').$_SERVER['REQUEST_URI']) ?> }
}
</script>

<style>
/* ===== READING PROGRESS ===== */
.read-progress { position: fixed; top: 0; left: 0; height: 3px; width: 0%; z-index: 1200;
    background: linear-gradient(90deg, var(--primary), var(--secondary, #f59e0b)); transition: width .1s linear; }

/* ===== ARTICLE HERO ===== */
.art-hero { background: linear-gradient(135deg, #0a6847 0%, #084d35 45%, #16213e 100%); color: #fff;
    padding: 8.5rem 0 3rem; position: relative; overflow: hidden; }
.art-hero::before { content:''; position:absolute; inset:0;
    background: radial-gradient(circle at 15% 30%, rgba(245,166,35,.2), transparent 50%),
                radial-gradient(circle at 85% 70%, rgba(59,130,246,.16), transparent 50%); }
.art-hero .container { position: relative; z-index: 2; }
.art-hero .breadcrumb a, .art-hero .breadcrumb span { color: rgba(255,255,255,.8); }
.art-hero .breadcrumb a:hover { color: #fff; }
.art-cat-badge { display:inline-flex; align-items:center; gap:.4rem; padding:.35rem .9rem; border-radius:999px;
    background: rgba(245,158,11,.9); color:#1a1a1a; font-size:.7rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; margin-bottom:1rem; }
.art-title { font-family: var(--font-display); font-size: clamp(1.9rem, 4.5vw, 3.25rem); font-weight: 900; line-height: 1.12; margin-bottom: 1.25rem; letter-spacing:-.02em; }
.art-meta { display:flex; flex-wrap:wrap; gap:1.25rem; align-items:center; font-size:.88rem; opacity:.92; }
.art-meta .m-item { display:inline-flex; align-items:center; gap:.4rem; }
.art-avatar { width:38px; height:38px; border-radius:50%; background:rgba(255,255,255,.2); display:flex; align-items:center; justify-content:center; font-weight:800; overflow:hidden; flex-shrink:0; }
.art-avatar img { width:100%; height:100%; object-fit:cover; }

/* ===== LAYOUT ===== */
.art-layout { display:grid; grid-template-columns: minmax(0,1fr) 320px; gap:3rem; padding:3rem 0 5rem; align-items:start; }
.art-main { min-width:0; }

/* ===== TOC ===== */
.art-toc { background: var(--bg-secondary); border:1px solid var(--border); border-left:4px solid var(--primary);
    border-radius: var(--radius-lg,12px); padding:1.5rem 1.75rem; margin-bottom:2.5rem; }
.art-toc h4 { font-size:.78rem; text-transform:uppercase; letter-spacing:.1em; color:var(--text-muted); margin-bottom:1rem; display:flex; align-items:center; gap:.5rem; }
.art-toc ol { list-style:none; padding:0; margin:0; counter-reset: toc; }
.art-toc li { counter-increment: toc; margin-bottom:.55rem; }
.art-toc li.lv3 { padding-left:1.25rem; }
.art-toc a { display:flex; gap:.6rem; color:var(--text-secondary); font-size:.9rem; font-weight:600; text-decoration:none; transition:color .2s, transform .2s; }
.art-toc a::before { content: counter(toc, decimal-leading-zero); color:var(--primary); font-weight:800; font-family:var(--font-mono,monospace); font-size:.8rem; }
.art-toc li.lv3 a::before { content: "—"; }
.art-toc a:hover, .art-toc a.active { color:var(--primary); transform:translateX(3px); }

/* ===== BODY KONTEN ===== */
.art-body { font-size:1.06rem; line-height:1.9; color:var(--text-secondary); }
.art-body > *:first-child { margin-top:0; }
.art-body p { margin-bottom:1.4rem; }
.art-body h2 { font-family:var(--font-display); font-size:1.7rem; font-weight:800; color:var(--text-primary); margin:2.5rem 0 1rem; scroll-margin-top:120px; }
.art-body h3 { font-size:1.3rem; font-weight:700; color:var(--text-primary); margin:2rem 0 .75rem; scroll-margin-top:120px; }
.art-body a { color:var(--primary); text-decoration:underline; text-underline-offset:3px; }
.art-body ul, .art-body ol { margin:0 0 1.4rem 1.5rem; }
.art-body ul { list-style:disc; } .art-body ol { list-style:decimal; }
.art-body li { margin-bottom:.5rem; }
.art-body blockquote { border-left:4px solid var(--primary); background:var(--bg-secondary); padding:1.25rem 1.5rem; margin:1.75rem 0; border-radius:0 var(--radius-md,8px) var(--radius-md,8px) 0; font-style:italic; color:var(--text-primary); }
.art-body img { border-radius:var(--radius-lg,12px); margin:1.5rem 0; box-shadow:var(--shadow-md); }
.art-body iframe { width:100%; aspect-ratio:16/9; border:0; border-radius:var(--radius-lg,12px); margin:1.5rem 0; }
.art-body code { background:var(--bg-tertiary); padding:.15rem .45rem; border-radius:6px; font-family:var(--font-mono,monospace); font-size:.88em; color:var(--primary); }
.art-body pre { background:#0f172a; color:#e2e8f0; padding:1.25rem; border-radius:var(--radius-lg,12px); overflow-x:auto; margin:1.5rem 0; }
.art-body pre code { background:none; color:inherit; padding:0; }
.art-body table { width:100%; border-collapse:collapse; margin:1.5rem 0; font-size:.95rem; }
.art-body th, .art-body td { border:1px solid var(--border); padding:.7rem .9rem; text-align:left; }
.art-body th { background:var(--bg-secondary); font-weight:700; }
.art-body hr { border:0; border-top:1px solid var(--border); margin:2rem 0; }

/* ===== TAGS ===== */
.art-tags { display:flex; flex-wrap:wrap; gap:.5rem; margin:2.5rem 0; padding-top:1.75rem; border-top:1px solid var(--border); }
.art-tag { padding:.35rem .85rem; border-radius:999px; background:var(--bg-secondary); border:1px solid var(--border); color:var(--text-secondary); font-size:.8rem; font-weight:600; text-decoration:none; transition:all .2s; }
.art-tag:hover { border-color:var(--primary); color:var(--primary); }

/* ===== ACTION BAR (suka + share) ===== */
.art-actions { display:flex; flex-wrap:wrap; gap:1rem; align-items:center; justify-content:space-between; padding:1.5rem; background:var(--bg-secondary); border:1px solid var(--border); border-radius:var(--radius-xl,16px); margin-bottom:2.5rem; }
.like-btn { display:inline-flex; align-items:center; gap:.6rem; padding:.7rem 1.4rem; border-radius:999px; border:2px solid var(--border); background:var(--bg-primary); color:var(--text-secondary); font-weight:700; font-size:.92rem; cursor:pointer; transition:all .25s; font-family:inherit; }
.like-btn:hover { border-color:#ef4444; color:#ef4444; transform:translateY(-2px); }
.like-btn.liked { background:#fee2e2; border-color:#ef4444; color:#ef4444; }
.like-btn .heart { font-size:1.15rem; transition:transform .3s cubic-bezier(.68,-.55,.265,1.55); }
.like-btn.pop .heart { transform:scale(1.4); }
.share-row { display:flex; gap:.5rem; flex-wrap:wrap; }
.share-btn { width:42px; height:42px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.05rem; text-decoration:none; transition:all .25s; border:none; cursor:pointer; }
.share-btn:hover { transform:translateY(-3px) scale(1.08); box-shadow:0 6px 16px rgba(0,0,0,.2); }
.sb-wa{background:#25D366;} .sb-x{background:#0f172a;} .sb-fb{background:#1877F2;} .sb-li{background:#0a66c2;} .sb-copy{background:var(--primary);}

/* ===== AUTHOR BOX ===== */
.author-box { display:flex; gap:1.5rem; padding:2rem; background:var(--bg-primary); border:1px solid var(--border); border-radius:var(--radius-xl,16px); margin-bottom:3rem; box-shadow:var(--shadow-sm); }
.author-box .ab-photo { width:96px; height:96px; border-radius:20px; background:linear-gradient(135deg,var(--primary),var(--primary-light,#16a34a)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:2.25rem; font-weight:900; font-family:var(--font-display); flex-shrink:0; overflow:hidden; }
.author-box .ab-photo img { width:100%; height:100%; object-fit:cover; }
.author-box .ab-role { color:var(--primary); font-weight:700; font-size:.82rem; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.35rem; }
.author-box h4 { font-family:var(--font-display); font-size:1.3rem; margin-bottom:.5rem; }
.author-box p { color:var(--text-secondary); font-size:.92rem; line-height:1.7; margin-bottom:.85rem; }
.author-box .ab-links { display:flex; gap:.6rem; flex-wrap:wrap; }

/* ===== COMMENTS ===== */
.comments { margin-top:1rem; }
.comments-head { display:flex; align-items:center; gap:.75rem; margin-bottom:1.75rem; }
.comments-head h3 { font-family:var(--font-display); font-size:1.5rem; margin:0; }
.comments-count { background:var(--primary); color:#fff; font-size:.8rem; font-weight:800; padding:.15rem .65rem; border-radius:999px; }
.comment-form { background:var(--bg-secondary); border:1px solid var(--border); border-radius:var(--radius-xl,16px); padding:1.75rem; margin-bottom:2.5rem; }
.comment-form .reply-note { display:none; align-items:center; justify-content:space-between; background:rgba(10,104,71,.08); border:1px solid rgba(10,104,71,.2); border-radius:var(--radius-md,8px); padding:.6rem .9rem; margin-bottom:1rem; font-size:.85rem; color:var(--primary); font-weight:600; }
.comment-form.replying .reply-note { display:flex; }
.cf-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem; }
.cf-field label { display:block; font-size:.82rem; font-weight:700; margin-bottom:.35rem; color:var(--text-primary); }
.cf-field input, .cf-field textarea { width:100%; padding:.7rem .9rem; border:2px solid var(--border); border-radius:var(--radius-md,8px); background:var(--bg-primary); color:var(--text-primary); font-family:inherit; font-size:.92rem; transition:border-color .2s; }
.cf-field input:focus, .cf-field textarea:focus { outline:none; border-color:var(--primary); }
.cf-field textarea { min-height:110px; resize:vertical; }
.hp-field { position:absolute; left:-9999px; opacity:0; height:0; width:0; overflow:hidden; }
.cf-submit { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; margin-top:1rem; }
.cf-submit small { color:var(--text-muted); font-size:.78rem; }

.comment-list { display:flex; flex-direction:column; gap:1.25rem; }
.comment { display:flex; gap:1rem; }
.comment .c-avatar { width:46px; height:46px; border-radius:50%; background:linear-gradient(135deg,var(--accent-light,#3b82f6),#1d4ed8); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; flex-shrink:0; }
.comment .c-body { flex:1; background:var(--bg-primary); border:1px solid var(--border); border-radius:var(--radius-lg,12px); padding:1.15rem 1.35rem; }
.comment .c-top { display:flex; align-items:center; gap:.6rem; flex-wrap:wrap; margin-bottom:.5rem; }
.comment .c-name { font-weight:700; color:var(--text-primary); }
.comment .c-date { font-size:.75rem; color:var(--text-muted); }
.comment .c-text { color:var(--text-secondary); font-size:.94rem; line-height:1.7; }
.comment .c-reply-btn { background:none; border:none; color:var(--primary); font-weight:700; font-size:.8rem; cursor:pointer; padding:.25rem 0; margin-top:.5rem; font-family:inherit; }
.comment .c-reply-btn:hover { text-decoration:underline; }
.comment-children { margin-left:2.5rem; margin-top:1.25rem; display:flex; flex-direction:column; gap:1.25rem; border-left:2px solid var(--border); padding-left:1.25rem; }
.no-comments { text-align:center; padding:2.5rem; background:var(--bg-secondary); border:2px dashed var(--border); border-radius:var(--radius-xl,16px); color:var(--text-muted); }

/* ===== SIDEBAR ===== */
.art-sidebar { position:sticky; top:110px; display:flex; flex-direction:column; gap:1.5rem; }
.side-card { background:var(--bg-primary); border:1px solid var(--border); border-radius:var(--radius-xl,16px); padding:1.5rem; }
.side-card h4 { font-family:var(--font-display); font-size:1.1rem; margin-bottom:1.15rem; padding-bottom:.75rem; border-bottom:2px solid var(--bg-tertiary); display:flex; align-items:center; gap:.5rem; }
.side-cat { display:flex; flex-direction:column; gap:.4rem; }
.side-cat a { display:flex; justify-content:space-between; align-items:center; padding:.55rem .75rem; border-radius:var(--radius-md,8px); color:var(--text-secondary); font-size:.88rem; font-weight:600; text-decoration:none; transition:all .2s; }
.side-cat a:hover { background:var(--bg-secondary); color:var(--primary); }
.side-cat a.active { background:rgba(10,104,71,.1); color:var(--primary); }
.side-cat .sc-cnt { background:var(--bg-tertiary); color:var(--text-muted); font-size:.72rem; font-weight:800; padding:.05rem .5rem; border-radius:999px; }
.side-pop { display:flex; flex-direction:column; gap:1rem; }
.side-pop a { display:flex; gap:.85rem; text-decoration:none; align-items:flex-start; }
.side-pop .sp-rank { font-family:var(--font-display); font-size:1.5rem; font-weight:900; color:var(--bg-tertiary); line-height:1; flex-shrink:0; width:28px; }
.side-pop a:hover .sp-rank { color:var(--primary); }
.side-pop .sp-title { font-size:.9rem; font-weight:700; color:var(--text-primary); line-height:1.4; margin-bottom:.25rem; transition:color .2s; }
.side-pop a:hover .sp-title { color:var(--primary); }
.side-pop .sp-meta { font-size:.72rem; color:var(--text-muted); }
.side-cta { background:linear-gradient(135deg,var(--primary),#064e34); color:#fff; text-align:center; }
.side-cta h4 { color:#fff; border-bottom-color:rgba(255,255,255,.2); }
.side-cta p { font-size:.88rem; opacity:.92; margin-bottom:1.15rem; line-height:1.6; }

/* ===== RELATED ===== */
.related { margin-top:3rem; padding-top:2.5rem; border-top:1px solid var(--border); }
.related h3 { font-family:var(--font-display); font-size:1.5rem; margin-bottom:1.5rem; }
.related-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:1.5rem; }
.rel-card { background:var(--bg-primary); border:1px solid var(--border); border-radius:var(--radius-lg,12px); overflow:hidden; text-decoration:none; color:inherit; transition:all .3s; display:flex; flex-direction:column; }
.rel-card:hover { transform:translateY(-5px); box-shadow:var(--shadow-lg); border-color:var(--primary); }
.rel-img { height:140px; background-size:cover; background-position:center; }
.rel-body { padding:1.15rem; flex:1; display:flex; flex-direction:column; }
.rel-cat { font-size:.68rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:var(--primary); margin-bottom:.4rem; }
.rel-title { font-size:1rem; font-weight:700; line-height:1.4; margin-bottom:.6rem; }
.rel-meta { font-size:.75rem; color:var(--text-muted); margin-top:auto; }

/* ===== TOAST ===== */
.art-toast { position:fixed; bottom:2rem; left:50%; transform:translateX(-50%) translateY(150%); background:var(--bg-primary); border:1px solid var(--border); border-left:4px solid var(--primary); border-radius:999px; padding:.85rem 1.5rem; box-shadow:var(--shadow-xl); z-index:9999; font-size:.9rem; font-weight:600; display:flex; align-items:center; gap:.6rem; transition:transform .4s cubic-bezier(.4,0,.2,1); max-width:90%; }
.art-toast.show { transform:translateX(-50%) translateY(0); }

@media (max-width: 1024px) {
    .art-layout { grid-template-columns:1fr; }
    .art-sidebar { position:static; }
}
@media (max-width: 640px) {
    .cf-grid { grid-template-columns:1fr; }
    .author-box { flex-direction:column; text-align:center; align-items:center; }
    .author-box .ab-links { justify-content:center; }
    .art-actions { flex-direction:column; align-items:stretch; }
    .comment-children { margin-left:1rem; padding-left:.85rem; }
}
</style>

<div class="read-progress" id="readProgress"></div>

<!-- ===== ARTICLE HERO ===== -->
<section class="art-hero">
    <div class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= base_url() ?>">Beranda</a><span>›</span>
            <a href="<?= base_url('blog.php') ?>">Ide &amp; Wawasan</a><span>›</span>
            <a href="<?= base_url('blog.php?kategori=' . urlencode($artikel['kategori'])) ?>"><?= sanitize($artikel['kategori']) ?></a><span>›</span>
            <span style="color:#fff;"><?= excerpt($artikel['judul'], 40) ?></span>
        </nav>
        <span class="art-cat-badge">⭐ <?= sanitize($artikel['kategori']) ?></span>
        <h1 class="art-title"><?= sanitize($artikel['judul']) ?></h1>
        <div class="art-meta">
            <span class="m-item">
                <span class="art-avatar">
                    <?php if (!empty($artikel['dosen_foto'])): ?><img src="<?= asset('dosen/' . basename($artikel['dosen_foto'])) ?>" alt=""><?php else: ?><?= strtoupper(substr(trim($artikel['dosen_nama'] ?? 'R'),0,1)) ?><?php endif; ?>
                </span>
                <strong><?= sanitize($author_full) ?></strong>
            </span>
            <span class="m-item">📅 <?= format_tanggal_singkat($published) ?></span>
            <span class="m-item">📖 <?= $read_time ?> menit baca</span>
            <span class="m-item">👁 <?= number_format((int)$artikel['views']) ?> dibaca</span>
            <?php if (!empty($artikel['prodi_singkatan'])): ?><span class="m-item">🎓 <?= sanitize($artikel['prodi_singkatan']) ?></span><?php endif; ?>
        </div>
    </div>
</section>

<!-- ===== LAYOUT ===== -->
<div class="container">
    <div class="art-layout">
        <!-- MAIN -->
        <article class="art-main">
            <?php if (count($toc) >= 2): ?>
            <nav class="art-toc" id="artToc" aria-label="Daftar isi">
                <h4>📑 Dalam Artikel Ini</h4>
                <ol>
                    <?php foreach ($toc as $t): ?>
                    <li class="<?= $t['level'] === 'h3' ? 'lv3' : 'lv2' ?>"><a href="#<?= $t['id'] ?>" data-target="<?= $t['id'] ?>"><?= sanitize($t['text']) ?></a></li>
                    <?php endforeach; ?>
                </ol>
            </nav>
            <?php endif; ?>

            <div class="art-body" id="artBody"><?= $konten_html ?></div>

            <?php if (!empty($artikel['tags'])): ?>
            <div class="art-tags">
                <?php foreach (array_filter(array_map('trim', explode(',', $artikel['tags']))) as $tg): ?>
                <a href="<?= base_url('blog.php?q=' . urlencode($tg)) ?>" class="art-tag">#<?= sanitize($tg) ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ACTION BAR -->
            <div class="art-actions">
                <button class="like-btn <?= $liked ? 'liked' : '' ?>" id="likeBtn" data-slug="<?= sanitize($slug) ?>" data-liked="<?= $liked ? '1' : '0' ?>">
                    <span class="heart"><?= $liked ? '❤️' : '🤍' ?></span>
                    <span id="likeCount"><?= number_format((int)$artikel['likes']) ?></span> Sukai
                </button>
                <div class="share-row">
                    <a class="share-btn sb-wa" target="_blank" rel="noopener" title="WhatsApp"
                       href="https://wa.me/?text=<?= rawurlencode($artikel['judul'] . ' — ' . (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']) ?>">🟢</a>
                    <a class="share-btn sb-x" target="_blank" rel="noopener" title="X / Twitter"
                       href="https://twitter.com/intent/tweet?text=<?= rawurlencode($artikel['judul']) ?>&url=<?= rawurlencode((isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']) ?>">𝕏</a>
                    <a class="share-btn sb-fb" target="_blank" rel="noopener" title="Facebook"
                       href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode((isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']) ?>">f</a>
                    <a class="share-btn sb-li" target="_blank" rel="noopener" title="LinkedIn"
                       href="https://www.linkedin.com/sharing/share-offsite/?url=<?= rawurlencode((isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']) ?>">in</a>
                    <button class="share-btn sb-copy" id="copyLink" title="Salin tautan">🔗</button>
                </div>
            </div>

            <!-- AUTHOR BOX -->
            <div class="author-box">
                <div class="ab-photo">
                    <?php if (!empty($artikel['dosen_foto'])): ?><img src="<?= asset('dosen/' . basename($artikel['dosen_foto'])) ?>" alt="<?= sanitize($author_full) ?>"><?php else: ?><?= strtoupper(substr(trim($artikel['dosen_nama'] ?? 'R'),0,1)) ?><?php endif; ?>
                </div>
                <div>
                    <div class="ab-role">Penulis · <?= sanitize($artikel['jabatan_fungsional'] ?? 'Dosen FKIP UNIMOF') ?></div>
                    <h4><?= sanitize($author_full) ?></h4>
                    <?php if (!empty($artikel['bidang_keahlian'])): ?>
                    <p><strong>Bidang keahlian:</strong> <?= sanitize($artikel['bidang_keahlian']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($artikel['prodi_nama'])): ?>
                    <p style="margin-bottom:.85rem;">🎓 <?= sanitize($artikel['prodi_nama']) ?></p>
                    <?php endif; ?>
                    <div class="ab-links">
                        <?php if (!empty($artikel['dosen_email'])): ?>
                        <a href="mailto:<?= sanitize($artikel['dosen_email']) ?>" class="btn btn-sm btn-secondary">✉️ Email Penulis</a>
                        <?php endif; ?>
                        <a href="<?= base_url('blog.php') ?>" class="btn btn-sm btn-outline">📚 Artikel Lainnya</a>
                    </div>
                </div>
            </div>

            <!-- KOMENTAR -->
            <section class="comments" id="comments">
                <div class="comments-head">
                    <h3>💬 Diskusi</h3>
                    <span class="comments-count"><?= $total_comments ?></span>
                </div>

                <?php if ($flash === 'ok'): ?>
                <div class="alert alert-success" style="margin-bottom:1.5rem;"><span class="alert-icon">✅</span><div>Komentar Anda telah dikirim dan <strong>sedang menunggu moderasi admin</strong> sebelum ditampilkan.</div></div>
                <?php elseif ($flash === 'slow'): ?>
                <div class="alert alert-warning" style="margin-bottom:1.5rem;"><span class="alert-icon">⏳</span><div>Terlalu cepat. Mohon tunggu beberapa detik sebelum mengirim komentar lagi.</div></div>
                <?php elseif ($flash === 'invalid'): ?>
                <div class="alert alert-error" style="margin-bottom:1.5rem;"><span class="alert-icon">⚠️</span><div>Nama dan isi komentar wajib diisi (minimal 3 karakter).</div></div>
                <?php elseif ($flash === 'email'): ?>
                <div class="alert alert-error" style="margin-bottom:1.5rem;"><span class="alert-icon">⚠️</span><div>Format email tidak valid.</div></div>
                <?php elseif ($flash === 'error'): ?>
                <div class="alert alert-error" style="margin-bottom:1.5rem;"><span class="alert-icon">❌</span><div>Gagal mengirim komentar. Silakan coba lagi.</div></div>
                <?php endif; ?>

                <!-- FORM -->
                <form class="comment-form" id="commentForm" method="post" action="<?= base_url('blog-detail.php?slug=' . urlencode($slug) . '#c') ?>">
                    <input type="hidden" name="action" value="blog_comment">
                    <input type="hidden" name="slug" value="<?= sanitize($slug) ?>">
                    <input type="hidden" name="parent_id" id="parentId" value="0">
                    <div class="reply-note" id="replyNote">
                        <span>↩️ Membalas <strong id="replyToName"></strong></span>
                        <button type="button" class="c-reply-btn" onclick="cancelReply()" style="color:var(--danger);">Batal ✕</button>
                    </div>
                    <div class="cf-grid">
                        <div class="cf-field"><label for="cNama">Nama <span style="color:var(--danger)">*</span></label><input type="text" id="cNama" name="nama" required maxlength="100" placeholder="Nama Anda"></div>
                        <div class="cf-field"><label for="cEmail">Email <small style="color:var(--text-muted); font-weight:400">(tidak ditampilkan)</small></label><input type="email" id="cEmail" name="email" maxlength="100" placeholder="nama@email.com"></div>
                    </div>
                    <div class="cf-field" style="margin-bottom:0;"><label for="cIsi">Komentar <span style="color:var(--danger)">*</span></label><textarea id="cIsi" name="komentar" required minlength="3" placeholder="Bagikan pandangan Anda..."></textarea></div>
                    <input type="text" name="website_url" class="hp-field" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <div class="cf-submit">
                        <small>🔒 Komentar dimoderasi terlebih dahulu untuk menjaga kualitas diskusi.</small>
                        <button type="submit" class="btn btn-primary">Kirim Komentar →</button>
                    </div>
                </form>

                <!-- LIST -->
                <?php if (empty($roots)): ?>
                <div class="no-comments">
                    <div style="font-size:2.5rem; margin-bottom:.5rem;">🗨️</div>
                    <p>Belum ada diskusi. Jadilah yang pertama berkomentar!</p>
                </div>
                <?php else: ?>
                <div class="comment-list">
                    <?php
                    function render_comment($c, $depth = 0) {
                        $initial = strtoupper(substr(trim($c['nama']), 0, 1));
                        $tgl = format_tanggal_singkat($c['created_at']);
                        ?>
                        <div class="comment">
                            <div class="c-avatar"><?= $initial ?></div>
                            <div class="c-body">
                                <div class="c-top">
                                    <span class="c-name"><?= sanitize($c['nama']) ?></span>
                                    <span class="c-date">· <?= $tgl ?></span>
                                </div>
                                <div class="c-text"><?= nl2br(sanitize($c['komentar'])) ?></div>
                                <?php if ($depth < 2): ?>
                                <button class="c-reply-btn" onclick="setReply(<?= (int)$c['id'] ?>, '<?= addslashes(sanitize($c['nama'])) ?>')">↩️ Balas</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($c['children'])): ?>
                        <div class="comment-children">
                            <?php foreach ($c['children'] as $ch) render_comment($ch, $depth + 1); ?>
                        </div>
                        <?php endif; ?>
                        <?php
                    }
                    foreach ($roots as $r) render_comment($r);
                    ?>
                </div>
                <?php endif; ?>
            </section>

            <!-- RELATED -->
            <?php if (!empty($related)): ?>
            <section class="related">
                <h3>📖 Artikel Terkait</h3>
                <div class="related-grid">
                    <?php foreach ($related as $rl):
                        $rimg = !empty($rl['gambar']) ? asset('uploads/blog/' . basename($rl['gambar'])) : '';
                        $grads = ['linear-gradient(135deg,#0a6847,#16a34a)','linear-gradient(135deg,#16213e,#3b82f6)','linear-gradient(135deg,#f59e0b,#ec4899)'];
                    ?>
                    <a href="<?= base_url('blog-detail.php?slug=' . urlencode($rl['slug'])) ?>" class="rel-card">
                        <div class="rel-img" style="<?= $rimg ? "background-image:url('".$rimg."')" : "background:".$grads[array_rand($grads)] ?>"></div>
                        <div class="rel-body">
                            <div class="rel-cat"><?= sanitize($rl['kategori']) ?></div>
                            <div class="rel-title"><?= sanitize($rl['judul']) ?></div>
                            <div class="rel-meta">📅 <?= format_tanggal_singkat($rl['published_at']) ?> · 👁 <?= number_format((int)$rl['views']) ?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
        </article>

        <!-- SIDEBAR -->
        <aside class="art-sidebar">
            <div class="side-card">
                <h4>🗂️ Kategori</h4>
                <div class="side-cat">
                    <a href="<?= base_url('blog.php') ?>" class="<?= $artikel['kategori']==='' ? 'active':'' ?>">Semua Kategori <span class="sc-cnt"><?= array_sum($kategor_counts) ?></span></a>
                    <?php foreach ($kategor_counts as $k => $cnt): ?>
                    <a href="<?= base_url('blog.php?kategori=' . urlencode($k)) ?>" class="<?= $k === $artikel['kategori'] ? 'active' : '' ?>"><?= sanitize($k) ?> <span class="sc-cnt"><?= (int)$cnt ?></span></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (!empty($popular)): ?>
            <div class="side-card">
                <h4>🔥 Terpopuler</h4>
                <div class="side-pop">
                    <?php foreach ($popular as $i => $pp): ?>
                    <a href="<?= base_url('blog-detail.php?slug=' . urlencode($pp['slug'])) ?>">
                        <span class="sp-rank"><?= $i + 1 ?></span>
                        <span>
                            <span class="sp-title"><?= sanitize($pp['judul']) ?></span>
                            <span class="sp-meta">👁 <?= number_format((int)$pp['views']) ?> · ❤️ <?= number_format((int)$pp['likes']) ?></span>
                        </span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="side-card side-cta">
                <h4>📬 Tetap Terhubung</h4>
                <p>Dapatkan artikel &amp; wawasan terbaru langsung ke email Anda.</p>
                <a href="<?= base_url('kontak.php') ?>" class="btn btn-lg" style="background:#fff; color:var(--primary); font-weight:800; border:none; width:100%;">Gabung Newsletter</a>
            </div>
        </aside>
    </div>
</div>

<!-- TOAST -->
<div class="art-toast" id="artToast"><span id="artToastIcon">✓</span><span id="artToastMsg"></span></div>

<script>
(function(){
    'use strict';
    var toast = document.getElementById('artToast');
    function showToast(msg, icon){
        document.getElementById('artToastMsg').textContent = msg;
        document.getElementById('artToastIcon').textContent = icon || '✓';
        toast.classList.add('show');
        clearTimeout(showToast._t);
        showToast._t = setTimeout(function(){ toast.classList.remove('show'); }, 3000);
    }

    /* ===== READING PROGRESS ===== */
    var bar = document.getElementById('readProgress');
    var body = document.getElementById('artBody');
    function onScroll(){
        if(!bar||!body) return;
        var rect = body.getBoundingClientRect();
        var total = body.offsetHeight - window.innerHeight;
        var scrolled = Math.min(Math.max(-rect.top, 0), Math.max(total,1));
        var pct = total > 0 ? (scrolled/total)*100 : 0;
        bar.style.width = pct + '%';
        highlightToc();
    }
    window.addEventListener('scroll', onScroll, {passive:true});
    onScroll();

    /* ===== TOC ACTIVE + SMOOTH ===== */
    var tocLinks = Array.prototype.slice.call(document.querySelectorAll('.art-toc a'));
    function highlightToc(){
        if(!tocLinks.length) return;
        var pos = window.pageYOffset + 140; var active = null;
        tocLinks.forEach(function(a){
            var sec = document.getElementById(a.dataset.target);
            if(sec && sec.offsetTop <= pos) active = a;
        });
        tocLinks.forEach(function(a){ a.classList.toggle('active', a===active); });
    }
    tocLinks.forEach(function(a){
        a.addEventListener('click', function(e){
            e.preventDefault();
            var sec = document.getElementById(a.dataset.target);
            if(sec) window.scrollTo({top: sec.offsetTop - 110, behavior:'smooth'});
        });
    });

    /* ===== COPY LINK ===== */
    var copyBtn = document.getElementById('copyLink');
    if(copyBtn) copyBtn.addEventListener('click', function(){
        var url = location.href;
        if(navigator.clipboard){ navigator.clipboard.writeText(url).then(function(){ showToast('Tautan disalin ke clipboard', '📋'); }); }
        else { showToast('Tautan: ' + url, '🔗'); }
    });

    /* ===== LIKE (AJAX) ===== */
    var likeBtn = document.getElementById('likeBtn');
    if(likeBtn) likeBtn.addEventListener('click', function(){
        if(likeBtn.dataset.liked === '1'){ showToast('Anda sudah menyukai artikel ini', '❤️'); return; }
        var fd = new FormData(); fd.append('action','blog_like'); fd.append('slug', likeBtn.dataset.slug);
        likeBtn.disabled = true;
        fetch(location.pathname + '?slug=' + encodeURIComponent(likeBtn.dataset.slug), {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(function(r){ return r.json(); })
        .then(function(d){
            likeBtn.disabled = false;
            if(d.ok){
                likeBtn.classList.add('liked','pop');
                likeBtn.dataset.liked = '1';
                likeBtn.querySelector('.heart').textContent = '❤️';
                document.getElementById('likeCount').textContent = Number(d.likes).toLocaleString('id-ID');
                setTimeout(function(){ likeBtn.classList.remove('pop'); }, 400);
                showToast('Terima kasih telah menyukai!', '❤️');
            } else { showToast(d.msg || 'Gagal menyukai artikel', '⚠️'); }
        })
        .catch(function(){ likeBtn.disabled = false; showToast('Koneksi gagal', '⚠️'); });
    });

    /* ===== REPLY ===== */
    window.setReply = function(id, name){
        document.getElementById('parentId').value = id;
        document.getElementById('replyToName').textContent = name;
        var form = document.getElementById('commentForm');
        form.classList.add('replying');
        form.scrollIntoView({behavior:'smooth', block:'center'});
        setTimeout(function(){ document.getElementById('cIsi').focus(); }, 400);
    };
    window.cancelReply = function(){
        document.getElementById('parentId').value = 0;
        document.getElementById('commentForm').classList.remove('replying');
    };

    console.log('%c📖 Blog Detail - EXTREME MULTIMATE', 'color:#0a6847;font-size:13px;font-weight:bold');
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>