<?php
// video.php - Video & Podcast - EXTREME MULTIMATE VERSION
require_once __DIR__ . '/includes/config.php';
$page_title = 'Video & Podcast';
$page_description = 'Kumpulan video kuliah umum, tutorial, dokumentasi kegiatan, dan podcast dari civitas akademika FKIP UNIMOF.';

// =====================================================
// HELPER LOKAL (schema-safe)
// =====================================================
if (!function_exists('video_col')) {
    function video_col($pdo, $table, $col) {
        static $cache = [];
        $key = $table . '.' . $col;
        if (isset($cache[$key])) return $cache[$key];
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
            $cache[$key] = in_array($col, $cols, true);
        } catch (Exception $e) { $cache[$key] = false; }
        return $cache[$key];
    }
}
if (!function_exists('setting_get')) {
    function setting_get($pdo, $key, $default = '') {
        try {
            if (video_col($pdo, 'pengaturan', 'nama_key')) {
                $s = $pdo->prepare("SELECT nilai FROM pengaturan WHERE nama_key = ? LIMIT 1");
                $s->execute([$key]); $v = $s->fetchColumn();
                return ($v !== false && $v !== null && $v !== '') ? $v : $default;
            }
            if (video_col($pdo, 'pengaturan', 'key')) {
                $s = $pdo->prepare("SELECT value FROM pengaturan WHERE `key` = ? LIMIT 1");
                $s->execute([$key]); $v = $s->fetchColumn();
                return ($v !== false && $v !== null && $v !== '') ? $v : $default;
            }
        } catch (Exception $e) {}
        return $default;
    }
}
if (!function_exists('video_yt_id')) {
    function video_yt_id($url) {
        $url = (string)$url;
        if (preg_match('~(?:v=|youtu\.be/|shorts/|embed/)([A-Za-z0-9_-]{11})~', $url, $m)) return $m[1];
        return null;
    }
}
if (!function_exists('video_embed')) {
    // Return ['kind'=>'iframe'|'video'|'link', 'src'=>..., 'id'=>...]
    function video_embed($url, $type) {
        $url = trim((string)$url);
        if ($url === '') return ['kind' => 'link', 'src' => '', 'id' => null];
        if ($type === 'youtube' || preg_match('~youtube\.com|youtu\.be~i', $url)) {
            $id = video_yt_id($url);
            if ($id) return ['kind' => 'iframe', 'src' => "https://www.youtube.com/embed/$id?autoplay=1&rel=0&modestbranding=1", 'id' => $id];
        }
        if ($type === 'vimeo' || preg_match('~vimeo\.com~i', $url)) {
            if (preg_match('~(?:vimeo\.com/|player\.vimeo\.com/video/)(\d+)~', $url, $m))
                return ['kind' => 'iframe', 'src' => "https://player.vimeo.com/video/{$m[1]}?autoplay=1", 'id' => $m[1]];
        }
        if ($type === 'local' || preg_match('~\.(mp4|webm|ogg)(\?.*)?$~i', $url))
            return ['kind' => 'video', 'src' => $url, 'id' => null];
        return ['kind' => 'link', 'src' => $url, 'id' => null];
    }
}
if (!function_exists('video_thumb')) {
    function video_thumb($row) {
        if (!empty($row['thumbnail'])) return asset('uploads/video/' . basename($row['thumbnail']));
        if (($row['video_type'] ?? '') === 'youtube') {
            $id = video_yt_id($row['video_url'] ?? '');
            if ($id) return "https://img.youtube.com/vi/$id/hqdefault.jpg";
        }
        return '';
    }
}
if (!function_exists('video_fmt_dur')) {
    function video_fmt_dur($sec) {
        $sec = (int)$sec;
        if ($sec <= 0) return '';
        $h = intdiv($sec, 3600); $m = intdiv($sec % 3600, 60); $s = $sec % 60;
        return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
    }
}
if (!function_exists('video_dur_label')) {
    function video_dur_label($row) {
        if (!empty($row['duration'])) return $row['duration'];
        $f = video_fmt_dur($row['duration_seconds'] ?? 0);
        return $f !== '' ? $f : '';
    }
}
if (!function_exists('video_build_query')) {
    function video_build_query(array $override = []) {
        $params = array_merge([
            'kategori' => $_GET['kategori'] ?? '',
            'tab'      => $_GET['tab'] ?? 'semua',
            'q'        => $_GET['q'] ?? '',
            'sort'     => $_GET['sort'] ?? 'terbaru',
        ], $override);
        $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
        return empty($params) ? '?' : '?' . http_build_query($params);
    }
}

// =====================================================
// ENDPOINT: INCREMENT VIEW (AJAX JSON) — sebelum output
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'video_view') {
    header('Content-Type: application/json; charset=utf-8');
    $sid = (int)($_POST['id'] ?? 0);
    $vk = 'video_viewed_' . $sid;
    try {
        $stmt = $pdo->prepare("SELECT id, views FROM video WHERE id = ? AND status='Published' LIMIT 1");
        $stmt->execute([$sid]); $row = $stmt->fetch();
        if (!$row) { echo json_encode(['ok' => false]); exit; }
        if (!empty($_SESSION[$vk])) { echo json_encode(['ok' => true, 'views' => (int)$row['views'], 'already' => true]); exit; }
        $pdo->prepare("UPDATE video SET views = views + 1 WHERE id = ?")->execute([$sid]);
        $_SESSION[$vk] = true;
        echo json_encode(['ok' => true, 'views' => (int)$row['views'] + 1]);
    } catch (Exception $e) { echo json_encode(['ok' => false]); }
    exit;
}

// =====================================================
// KONFIG FILTER
// =====================================================
$vid_tabs = ['semua' => 'Semua', 'video' => '🎬 Video', 'podcast' => '🎙️ Podcast'];
$vid_sorts = ['terbaru' => 'Terbaru', 'populer' => 'Terpopuler', 'terlama' => 'Terlama'];

$akt_tab   = array_key_exists($_GET['tab'] ?? '', $vid_tabs) ? $_GET['tab'] : 'semua';
$akt_kategori = trim($_GET['kategori'] ?? '');
$akt_q     = trim($_GET['q'] ?? '');
$akt_sort  = array_key_exists($_GET['sort'] ?? '', $vid_sorts) ? $_GET['sort'] : 'terbaru';
$akt_page  = max(1, (int)($_GET['page'] ?? 1));
$per_page  = 8;

// Daftar kategori (dari DB)
$kategori_list = [];
try {
    $kategori_list = $pdo->query("SELECT id, nama, slug, icon FROM video_kategori WHERE status='Aktif' ORDER BY urutan ASC, nama ASC")->fetchAll();
} catch (Exception $e) { $kategori_list = []; }
$kategori_slugs = array_column($kategori_list, 'slug');
if ($akt_kategori !== '' && !in_array($akt_kategori, $kategori_slugs, true)) $akt_kategori = '';

// =====================================================
// QUERY: FEATURED (Sorotan)
// =====================================================
$featured = [];
try {
    $sql_f = "SELECT v.*, vk.nama AS kat_nama, vk.icon AS kat_icon, vk.slug AS kat_slug,
                     p.nama AS prodi_nama, p.singkatan AS prodi_singkatan
              FROM video v
              LEFT JOIN video_kategori vk ON v.kategori_id = vk.id
              LEFT JOIN program_studi p ON v.program_studi_id = p.id
              WHERE v.status='Published' AND v.is_featured=1
              ORDER BY v.published_at DESC LIMIT 1";
    $featured = $pdo->query($sql_f)->fetch();
} catch (Exception $e) { $featured = false; }
$featured_id = $featured ? (int)$featured['id'] : 0;

// =====================================================
// QUERY: LISTING + FILTER + SEARCH + SORT + PAGINATION
// =====================================================
$where = ["v.status = 'Published'"];
$args  = [];
if ($featured_id) $where[] = 'v.id != ?'; $args[] = $featured_id;
if ($akt_tab === 'video')   $where[] = 'v.is_podcast = 0';
if ($akt_tab === 'podcast') $where[] = 'v.is_podcast = 1';
if ($akt_kategori !== '')   { $where[] = 'vk.slug = ?'; $args[] = $akt_kategori; }
if ($akt_q !== '') {
    $where[] = '(v.judul LIKE ? OR v.deskripsi LIKE ? OR v.tags LIKE ?)';
    $like = '%' . $akt_q . '%'; $args[] = $like; $args[] = $like; $args[] = $like;
}
$where_sql = 'WHERE ' . implode(' AND ', $where);

$order_sql = match ($akt_sort) {
    'populer' => 'v.views DESC, v.published_at DESC',
    'terlama' => 'v.published_at ASC',
    default   => 'v.published_at DESC',
};

$total_rows = 0;
try {
    $stmt_c = $pdo->prepare("SELECT COUNT(*) FROM video v LEFT JOIN video_kategori vk ON v.kategori_id=vk.id $where_sql");
    $stmt_c->execute($args); $total_rows = (int)$stmt_c->fetchColumn();
} catch (Exception $e) {}
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$akt_page = min($akt_page, $total_pages);
$offset = ($akt_page - 1) * $per_page;

$videos = [];
try {
    $stmt = $pdo->prepare("SELECT v.*, vk.nama AS kat_nama, vk.icon AS kat_icon, vk.slug AS kat_slug,
                                  p.nama AS prodi_nama, p.singkatan AS prodi_singkatan
                           FROM video v
                           LEFT JOIN video_kategori vk ON v.kategori_id = vk.id
                           LEFT JOIN program_studi p ON v.program_studi_id = p.id
                           $where_sql ORDER BY $order_sql LIMIT $per_page OFFSET $offset");
    $stmt->execute($args); $videos = $stmt->fetchAll();
} catch (Exception $e) { $videos = []; }

// Hitung per kategori (badge chip)
$kat_counts = [];
try {
    $kc = $pdo->query("SELECT vk.slug, COUNT(*) c FROM video v LEFT JOIN video_kategori vk ON v.kategori_id=vk.id WHERE v.status='Published' GROUP BY vk.slug")->fetchAll(PDO::FETCH_KEY_PAIR);
    $kat_counts = $kc;
} catch (Exception $e) {}

// Statistik hero
$stat_video = 0; $stat_podcast = 0; $stat_dur = 0;
try {
    $stat_video = (int)$pdo->query("SELECT COUNT(*) FROM video WHERE status='Published' AND is_podcast=0")->fetchColumn();
    $stat_podcast = (int)$pdo->query("SELECT COUNT(*) FROM video WHERE status='Published' AND is_podcast=1")->fetchColumn();
    $stat_dur = (int)$pdo->query("SELECT COALESCE(SUM(duration_seconds),0) FROM video WHERE status='Published'")->fetchColumn();
} catch (Exception $e) {}
$stat_dur_label = video_fmt_dur($stat_dur);
$youtube_url = setting_get($pdo, 'youtube_url', 'https://youtube.com/@fkipunimof');

require_once __DIR__ . '/includes/header.php';
?>

<!-- Schema.org ItemList + VideoObject -->
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "ItemList",
    "name": "Video & Podcast FKIP UNIMOF",
    "itemListElement": [
        <?php
        $items = [];
        if ($featured) $items[] = $featured;
        $items = array_merge($items, $videos);
        foreach ($items as $i => $it):
            $emb = video_embed($it['video_url'] ?? '', $it['video_type'] ?? 'youtube');
            $th  = video_thumb($it);
        ?>
        {
            "@type": "ListItem",
            "position": <?= $i + 1 ?>,
            "item": {
                "@type": <?= ($it['is_podcast'] ?? 0) ? '"AudioObject"' : '"VideoObject"' ?>,
                "name": <?= json_encode($it['judul']) ?>,
                "description": <?= json_encode(excerpt($it['deskripsi'] ?? '', 200)) ?>,
                <?php if ($th): ?>"thumbnailUrl": <?= json_encode($th) ?>,<?php endif; ?>
                <?php if (!empty($it['duration_seconds'])): ?>"duration": "PT<?= intdiv((int)$it['duration_seconds'],60) ?>M<?= (int)$it['duration_seconds']%60 ?>S",<?php endif; ?>
                "url": <?= json_encode(base_url('video.php') . '#vid-' . (int)$it['id']) ?>
            }
        }<?= $i < count($items) - 1 ? ',' : '' ?>
        <?php endforeach; ?>
    ]
}
</script>

<style>
/* ===== HERO ===== */
.video-hero {
    background: linear-gradient(135deg, #1e1b4b 0%, #4c1d95 45%, #831843 100%);
    color: #fff; 
    padding: 12rem 0 5rem; /* DITINGKATKAN: 12rem (~192px) agar aman dari tertutup Header + Top Bar */
    position: relative; 
    z-index: 1; /* Memastikan layering benar */
    overflow: hidden;
    margin-top: 0; /* Pastikan tidak ada margin negatif yang menarik ke atas */
}
.video-hero::before {
    content: ''; position: absolute; inset: 0;
    background:
        radial-gradient(circle at 15% 30%, rgba(168,85,247,0.28) 0%, transparent 50%),
        radial-gradient(circle at 85% 70%, rgba(236,72,153,0.22) 0%, transparent 50%);
    animation: vidAurora 18s ease-in-out infinite;
}
@keyframes vidAurora { 0%,100%{transform:translate(0,0) scale(1);} 33%{transform:translate(-25px,15px) scale(1.05);} 66%{transform:translate(20px,-25px) scale(.95);} }
.video-hero .container { position: relative; z-index: 2; }
.video-hero .breadcrumb a, .video-hero .breadcrumb span { color: rgba(255,255,255,0.8); }
.video-hero .breadcrumb a:hover { color: #fff; }
.video-hero-title { font-family: var(--font-display); font-size: clamp(2.25rem,5vw,3.5rem); font-weight: 900; line-height: 1.1; margin: 0.5rem 0 1rem; }
.video-hero-title em { font-style: italic; background: linear-gradient(135deg,#c084fc,#f472b6,#fb7185); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
.video-hero-sub { font-size: 1.1rem; opacity: .92; max-width: 680px; line-height: 1.7; }
.video-hero-stats { display: flex; gap: 2rem; flex-wrap: wrap; margin-top: 2rem; }
.video-hero-stat strong { display: block; font-family: var(--font-display); font-size: 2rem; font-weight: 900; color: #c084fc; line-height: 1; }
.video-hero-stat span { font-size: .78rem; text-transform: uppercase; letter-spacing: .08em; opacity: .8; }

/* ===== TOOLBAR ===== */
.video-toolbar {
    background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl,16px);
    padding: 1.25rem; margin: -1rem auto 2.5rem; /* DIUBAH: dari -2.5rem ke -1rem agar tidak terlalu menutupi hero */
    position: relative; z-index: 5;
    box-shadow: var(--shadow-lg,0 10px 15px -3px rgba(0,0,0,.1)); max-width: 1100px;
}
.video-search { display: flex; gap: .65rem; flex-wrap: wrap; margin-bottom: 1rem; position: relative; }
.video-search .s-ico { position: absolute; left: .95rem; top: 50%; transform: translateY(-50%); pointer-events: none; opacity: .5; }
.video-search input {
    flex: 1; min-width: 220px; padding: .75rem 1rem .75rem 2.6rem; border: 2px solid var(--border);
    border-radius: var(--radius-md,12px); background: var(--bg-secondary); color: var(--text-primary);
    font-family: inherit; font-size: .92rem; transition: all .3s;
}
.video-search input:focus { outline: none; border-color: #8b5cf6; background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(139,92,246,.12); }
.video-search button { padding: .75rem 1.4rem; border-radius: var(--radius-md,12px); border: none; background: linear-gradient(135deg,#8b5cf6,#ec4899); color: #fff; font-weight: 700; cursor: pointer; font-family: inherit; transition: all .25s; }
.video-search button:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(139,92,246,.35); }
.video-tabs { display: flex; gap: .45rem; flex-wrap: wrap; align-items: center; justify-content: space-between; }
.video-tab-group { display: flex; gap: .4rem; flex-wrap: wrap; }
.video-tab {
    display: inline-flex; align-items: center; gap: .4rem; padding: .5rem 1rem; border-radius: 999px;
    border: 1px solid var(--border); background: var(--bg-primary); color: var(--text-secondary);
    font-size: .85rem; font-weight: 700; text-decoration: none; transition: all .2s;
}
.video-tab:hover { border-color: #8b5cf6; color: #8b5cf6; transform: translateY(-2px); }
.video-tab.active { background: linear-gradient(135deg,#8b5cf6,#ec4899); color: #fff; border-color: transparent; box-shadow: 0 4px 14px rgba(139,92,246,.35); }
.video-sort { display: flex; align-items: center; gap: .5rem; }
.video-sort label { font-size: .82rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .05em; }
.video-sort select { padding: .55rem .9rem; border: 2px solid var(--border); border-radius: var(--radius-md,12px); background: var(--bg-secondary); color: var(--text-primary); font-family: inherit; font-size: .88rem; font-weight: 600; cursor: pointer; }
.video-chips { display: flex; gap: .45rem; flex-wrap: wrap; margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed var(--border); }
.video-chip {
    display: inline-flex; align-items: center; gap: .4rem; padding: .4rem .9rem; border-radius: 999px;
    border: 1px solid var(--border); background: var(--bg-primary); color: var(--text-secondary);
    font-size: .8rem; font-weight: 600; text-decoration: none; transition: all .2s;
}
.video-chip:hover { border-color: #8b5cf6; color: #8b5cf6; transform: translateY(-2px); }
.video-chip.active { background: rgba(139,92,246,.12); color: #8b5cf6; border-color: #8b5cf6; }
.video-chip .cnt { background: rgba(0,0,0,.08); padding: 0 .45rem; border-radius: 999px; font-size: .68rem; font-weight: 800; }
.video-chip.active .cnt { background: rgba(139,92,246,.2); }

/* ===== FEATURED ===== */
.video-feat {
    position: relative; border-radius: var(--radius-xl,16px); overflow: hidden; margin-bottom: 3rem;
    box-shadow: var(--shadow-xl,0 20px 25px -5px rgba(0,0,0,.15)); aspect-ratio: 16/7; min-height: 320px;
    display: flex; align-items: flex-end; cursor: pointer; transition: transform .4s;
}
.video-feat:hover { transform: translateY(-4px); }
.video-feat-bg { position: absolute; inset: 0; background-size: cover; background-position: center; transition: transform .6s; }
.video-feat:hover .video-feat-bg { transform: scale(1.05); }
.video-feat-overlay { position: absolute; inset: 0; background: linear-gradient(to top, rgba(15,5,30,.95) 0%, rgba(15,5,30,.45) 45%, rgba(139,92,246,.15) 100%); }
.video-feat-play {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
    width: 84px; height: 84px; border-radius: 50%; background: rgba(255,255,255,.18);
    backdrop-filter: blur(8px); border: 2px solid rgba(255,255,255,.5);
    display: flex; align-items: center; justify-content: center; font-size: 2rem; color: #fff;
    transition: all .3s; z-index: 3;
}
.video-feat:hover .video-feat-play { background: #ec4899; border-color: #ec4899; transform: translate(-50%,-50%) scale(1.12); }
.video-feat-content { position: relative; z-index: 2; padding: 2rem; width: 100%; color: #fff; }
.video-feat-badges { display: flex; gap: .5rem; flex-wrap: wrap; margin-bottom: .85rem; }
.video-feat-badge { display: inline-flex; align-items: center; gap: .35rem; padding: .3rem .8rem; border-radius: 999px; font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; }
.vb-feat { background: #f59e0b; color: #1a1a1a; }
.vb-cat { background: rgba(255,255,255,.2); color: #fff; backdrop-filter: blur(6px); }
.vb-type { background: rgba(139,92,246,.85); color: #fff; }
.video-feat-title { font-family: var(--font-display); font-size: clamp(1.4rem,3vw,2.25rem); font-weight: 800; line-height: 1.2; margin-bottom: .6rem; }
.video-feat-desc { font-size: .95rem; opacity: .9; line-height: 1.6; max-width: 720px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.video-feat-meta { display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1rem; font-size: .82rem; opacity: .85; }
.video-feat-meta span { display: inline-flex; align-items: center; gap: .35rem; }

/* ===== GRID ===== */
.video-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.75rem; }
.video-card {
    background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl,16px);
    overflow: hidden; display: flex; flex-direction: column; transition: all .4s cubic-bezier(.4,0,.2,1); cursor: pointer;
}
.video-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl,0 20px 25px -5px rgba(0,0,0,.15)); border-color: #8b5cf6; }
.video-thumb { position: relative; aspect-ratio: 16/9; overflow: hidden; background: var(--bg-tertiary); }
.video-thumb img, .video-thumb .ph { width: 100%; height: 100%; object-fit: cover; transition: transform .6s; }
.video-card:hover .video-thumb img, .video-card:hover .video-thumb .ph { transform: scale(1.08); }
.video-thumb .ph { display: flex; align-items: center; justify-content: center; font-size: 3rem; color: #fff; }
.video-play {
    position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
    background: rgba(15,5,30,.35); opacity: 0; transition: opacity .3s;
}
.video-card:hover .video-play { opacity: 1; }
.video-play-btn {
    width: 60px; height: 60px; border-radius: 50%; background: rgba(255,255,255,.92);
    display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: #8b5cf6;
    box-shadow: 0 8px 24px rgba(0,0,0,.3); transition: transform .25s;
}
.video-card:hover .video-play-btn { transform: scale(1.1); }
.video-dur { position: absolute; bottom: .65rem; right: .65rem; padding: .2rem .55rem; border-radius: 6px; background: rgba(0,0,0,.8); color: #fff; font-size: .72rem; font-weight: 700; font-variant-numeric: tabular-nums; }
.video-type-badge { position: absolute; top: .65rem; left: .65rem; padding: .2rem .55rem; border-radius: 6px; font-size: .66rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: #fff; backdrop-filter: blur(6px); }
.vt-youtube { background: rgba(255,0,0,.85); }
.vt-vimeo { background: rgba(26,188,156,.85); }
.vt-local { background: rgba(139,92,246,.85); }
.vt-other { background: rgba(100,116,139,.85); }
.vt-podcast { background: rgba(236,72,153,.9); }
.video-body { padding: 1.25rem 1.4rem 1.4rem; display: flex; flex-direction: column; flex: 1; }
.video-cat { font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: #8b5cf6; margin-bottom: .4rem; display: flex; align-items: center; gap: .35rem; }
.video-title { font-size: 1.05rem; font-weight: 800; line-height: 1.4; margin-bottom: .55rem; color: var(--text-primary); transition: color .25s; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.video-card:hover .video-title { color: #8b5cf6; }
.video-desc { color: var(--text-secondary); font-size: .85rem; line-height: 1.6; margin-bottom: 1rem; flex: 1; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.video-foot { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding-top: .9rem; border-top: 1px dashed var(--border); font-size: .76rem; color: var(--text-muted); }
.video-foot .vf-left { display: flex; align-items: center; gap: .7rem; }
.video-foot span { display: inline-flex; align-items: center; gap: .3rem; }

/* ===== MODAL PLAYER ===== */
.video-modal { position: fixed; inset: 0; background: rgba(8,3,18,.92); backdrop-filter: blur(10px); z-index: 9999; display: none; align-items: center; justify-content: center; padding: 1.5rem; }
.video-modal.open { display: flex; animation: vidFade .3s; }
@keyframes vidFade { from{opacity:0} to{opacity:1} }
.video-modal-box { background: var(--bg-primary); border-radius: var(--radius-xl,16px); width: 100%; max-width: 960px; max-height: 92vh; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 30px 80px rgba(0,0,0,.5); border: 1px solid var(--border); animation: vidZoom .35s cubic-bezier(.175,.885,.32,1.275); }
@keyframes vidZoom { from{transform:scale(.95);opacity:0} to{transform:none;opacity:1} }
.video-modal-head { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; background: linear-gradient(135deg,#1e1b4b,#4c1d95); color: #fff; flex-shrink: 0; }
.video-modal-head h3 { font-family: var(--font-display); font-size: 1.1rem; margin: 0; line-height: 1.3; }
.video-modal-close { background: rgba(255,255,255,.2); border: none; color: #fff; width: 38px; height: 38px; border-radius: 50%; cursor: pointer; font-size: 1.1rem; transition: all .2s; flex-shrink: 0; }
.video-modal-close:hover { background: #ec4899; transform: rotate(90deg); }
.video-player-wrap { position: relative; width: 100%; aspect-ratio: 16/9; background: #000; flex-shrink: 0; }
.video-player-wrap iframe, .video-player-wrap video { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
.video-player-loading { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: #fff; font-size: .9rem; gap: .6rem; }
.video-spinner { width: 28px; height: 28px; border: 3px solid rgba(255,255,255,.25); border-top-color: #c084fc; border-radius: 50%; animation: vidSpin .8s linear infinite; }
@keyframes vidSpin { to{transform:rotate(360deg)} }
.video-modal-foot { padding: 1.25rem 1.5rem; overflow-y: auto; }
.video-modal-desc { color: var(--text-secondary); font-size: .92rem; line-height: 1.7; margin-bottom: 1rem; }
.video-modal-meta { display: flex; gap: 1rem; flex-wrap: wrap; font-size: .82rem; color: var(--text-muted); margin-bottom: 1rem; }
.video-modal-meta span { display: inline-flex; align-items: center; gap: .35rem; }
.video-modal-actions { display: flex; gap: .6rem; flex-wrap: wrap; }
.video-modal-actions a, .video-modal-actions button { padding: .55rem 1rem; border-radius: 999px; border: 1px solid var(--border); background: var(--bg-secondary); color: var(--text-primary); font-weight: 700; font-size: .82rem; cursor: pointer; text-decoration: none; transition: all .2s; font-family: inherit; display: inline-flex; align-items: center; gap: .4rem; }
.video-modal-actions a:hover, .video-modal-actions button:hover { border-color: #8b5cf6; color: #8b5cf6; transform: translateY(-2px); }

/* ===== CTA SUBSCRIBE ===== */
.video-cta {
    background: linear-gradient(135deg,#ff0000,#c4302b); border-radius: var(--radius-xl,16px);
    padding: 2.5rem 2rem; text-align: center; color: #fff; position: relative; overflow: hidden; margin-top: 3rem;
}
.video-cta::before { content: '▶'; position: absolute; font-size: 18rem; opacity: .08; right: -2rem; top: 50%; transform: translateY(-50%); }
.video-cta .container-inner { position: relative; z-index: 2; max-width: 640px; margin: 0 auto; }
.video-cta h2 { font-family: var(--font-display); font-size: clamp(1.5rem,3vw,2.25rem); margin-bottom: .6rem; color: #fff; }
.video-cta p { opacity: .92; margin-bottom: 1.5rem; line-height: 1.7; }
.video-cta .btn-sub { display: inline-flex; align-items: center; gap: .6rem; padding: .9rem 2rem; border-radius: 999px; background: #fff; color: #c4302b; font-weight: 800; text-decoration: none; transition: all .25s; box-shadow: 0 8px 24px rgba(0,0,0,.25); }
.video-cta .btn-sub:hover { transform: translateY(-3px) scale(1.03); }

/* ===== PAGINATION ===== */
.video-pagination { display: flex; gap: .45rem; justify-content: center; align-items: center; margin-top: 3rem; flex-wrap: wrap; }
.video-pagination a, .video-pagination span { min-width: 42px; height: 42px; padding: 0 .85rem; border-radius: var(--radius-md,12px); border: 1px solid var(--border); display: inline-flex; align-items: center; justify-content: center; font-size: .9rem; font-weight: 700; color: var(--text-secondary); text-decoration: none; transition: all .2s; background: var(--bg-primary); }
.video-pagination a:hover { border-color: #8b5cf6; color: #8b5cf6; transform: translateY(-2px); }
.video-pagination .current { background: linear-gradient(135deg,#8b5cf6,#ec4899); color: #fff; border-color: transparent; }
.video-pagination .disabled { opacity: .4; pointer-events: none; }

/* ===== TOAST ===== */
.video-toast { position: fixed; bottom: 2rem; left: 50%; transform: translateX(-50%) translateY(150%); background: var(--bg-primary); border: 1px solid var(--border); border-left: 4px solid #8b5cf6; border-radius: 999px; padding: .85rem 1.5rem; box-shadow: var(--shadow-xl); z-index: 10000; font-size: .9rem; font-weight: 600; display: flex; align-items: center; gap: .6rem; transition: transform .4s cubic-bezier(.4,0,.2,1); max-width: 90%; }
.video-toast.show { transform: translateX(-50%) translateY(0); }

@media (max-width: 768px) {
    .video-hero { padding: 7rem 0 3rem; }
    .video-toolbar { margin-top: -1.5rem; }
    .video-feat { aspect-ratio: 16/10; }
    .video-feat-play { width: 64px; height: 64px; font-size: 1.5rem; }
    .video-grid { grid-template-columns: 1fr; }
    .video-tabs { flex-direction: column; align-items: stretch; }
    .video-sort { justify-content: space-between; }
}
</style>

<!-- ===== HERO ===== -->
<section class="video-hero">
    <div class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= base_url() ?>">Beranda</a><span>›</span><span>Video &amp; Podcast</span>
        </nav>
        <h1 class="video-hero-title" data-aos="fade-up">Tonton &amp; Dengarkan <em>Wawasan Kami</em></h1>
        <p class="video-hero-sub" data-aos="fade-up" data-aos-delay="100">
            Kuliah umum, tutorial, dokumentasi kegiatan, dan podcast dari civitas akademika FKIP UNIMOF — pengetahuan yang bisa Anda simak kapan saja.
        </p>
        <div class="video-hero-stats" data-aos="fade-up" data-aos-delay="200">
            <div class="video-hero-stat"><strong class="count-up" data-count="<?= $stat_video ?>">0</strong><span>Video</span></div>
            <div class="video-hero-stat"><strong class="count-up" data-count="<?= $stat_podcast ?>">0</strong><span>Podcast</span></div>
            <div class="video-hero-stat"><strong><?= $stat_dur_label !== '' ? $stat_dur_label : '0:00' ?></strong><span>Total Durasi</span></div>
            <div class="video-hero-stat"><strong><?= count($kategori_list) ?></strong><span>Kategori</span></div>
        </div>
    </div>
</section>

<!-- ===== TOOLBAR ===== -->
<div class="container">
    <div class="video-toolbar" data-aos="fade-up">
        <form class="video-search" method="get" action="<?= base_url('video.php') ?>" role="search">
            <span class="s-ico" aria-hidden="true">🔍</span>
            <input type="text" name="q" value="<?= sanitize($akt_q) ?>" placeholder="Cari judul video, podcast, atau topik..." aria-label="Cari video">
            <?php if ($akt_kategori): ?><input type="hidden" name="kategori" value="<?= sanitize($akt_kategori) ?>"><?php endif; ?>
            <?php if ($akt_tab !== 'semua'): ?><input type="hidden" name="tab" value="<?= sanitize($akt_tab) ?>"><?php endif; ?>
            <?php if ($akt_sort !== 'terbaru'): ?><input type="hidden" name="sort" value="<?= sanitize($akt_sort) ?>"><?php endif; ?>
            <button type="submit">Cari</button>
        </form>
        <div class="video-tabs">
            <div class="video-tab-group">
                <?php foreach ($vid_tabs as $val => $lbl): ?>
                <a href="<?= video_build_query(['tab' => $val, 'page' => '']) ?>" class="video-tab <?= $akt_tab === $val ? 'active' : '' ?>"><?= $lbl ?></a>
                <?php endforeach; ?>
            </div>
            <form class="video-sort" method="get" action="<?= base_url('video.php') ?>">
                <label for="vidSort">Urutkan</label>
                <select id="vidSort" name="sort" onchange="this.form.submit()">
                    <?php if ($akt_kategori): ?><input type="hidden" name="kategori" value="<?= sanitize($akt_kategori) ?>"><?php endif; ?>
                    <?php if ($akt_tab !== 'semua'): ?><input type="hidden" name="tab" value="<?= sanitize($akt_tab) ?>"><?php endif; ?>
                    <?php if ($akt_q): ?><input type="hidden" name="q" value="<?= sanitize($akt_q) ?>"><?php endif; ?>
                    <?php foreach ($vid_sorts as $val => $lbl): ?>
                    <option value="<?= $val ?>" <?= $akt_sort === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php if (!empty($kategori_list)): ?>
        <div class="video-chips">
            <a href="<?= video_build_query(['kategori' => '']) ?>" class="video-chip <?= $akt_kategori === '' ? 'active' : '' ?>">Semua Topik</a>
            <?php foreach ($kategori_list as $k): ?>
            <a href="<?= video_build_query(['kategori' => $k['slug'], 'page' => '']) ?>" class="video-chip <?= $akt_kategori === $k['slug'] ? 'active' : '' ?>">
                <?= sanitize($k['icon'] ?? '📁') ?> <?= sanitize($k['nama']) ?>
                <?php if (!empty($kat_counts[$k['slug']])): ?><span class="cnt"><?= (int)$kat_counts[$k['slug']] ?></span><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== FEATURED ===== -->
<?php if ($featured):
    $f_emb = video_embed($featured['video_url'] ?? '', $featured['video_type'] ?? 'youtube');
    $f_th  = video_thumb($featured);
    $f_grads = ['linear-gradient(135deg,#4c1d95,#831843)','linear-gradient(135deg,#1e1b4b,#7c3aed)','linear-gradient(135deg,#831843,#db2777)'];
    $f_bg = $f_th ? "background-image:url('".htmlspecialchars($f_th, ENT_QUOTES)."')" : "background:".$f_grads[array_rand($f_grads)];
    $f_dur = video_dur_label($featured);
?>
<section class="section" style="padding-top:0;">
    <div class="container">
        <div class="section-header" style="text-align:left; max-width:none; margin-bottom:1.5rem;" data-aos="fade-up">
            <span class="section-tag" style="background:rgba(139,92,246,.12); color:#8b5cf6;">⭐ Sorotan Editor</span>
            <h2 class="section-title" style="font-size:clamp(1.5rem,3vw,2rem); margin-bottom:.25rem;">Tontonan Pilihan</h2>
        </div>
        <div class="video-feat" data-aos="zoom-in"
             data-id="<?= (int)$featured['id'] ?>" data-embed="<?= htmlspecialchars($f_emb['src'], ENT_QUOTES) ?>" data-kind="<?= $f_emb['kind'] ?>"
             data-title="<?= sanitize($featured['judul']) ?>" data-desc="<?= sanitize(excerpt($featured['deskripsi'] ?? '', 400)) ?>"
             data-url="<?= sanitize($featured['video_url']) ?>" data-dur="<?= sanitize($f_dur) ?>"
             data-views="<?= (int)$featured['views'] ?>" data-date="<?= sanitize(format_tanggal_singkat($featured['published_at'] ?? $featured['created_at'])) ?>"
             data-cat="<?= sanitize(($featured['kat_icon'] ?? '📁') . ' ' . ($featured['kat_nama'] ?? 'Umum')) ?>"
             onclick="openVideoPlayer(this)">
            <div class="video-feat-bg" style="<?= $f_bg ?>"></div>
            <div class="video-feat-overlay"></div>
            <div class="video-feat-play">▶</div>
            <div class="video-feat-content">
                <div class="video-feat-badges">
                    <span class="video-feat-badge vb-feat">⭐ Sorotan</span>
                    <span class="video-feat-badge vb-cat"><?= sanitize(($featured['kat_icon'] ?? '📁') . ' ' . ($featured['kat_nama'] ?? 'Umum')) ?></span>
                    <?php if ($featured['is_podcast']): ?><span class="video-feat-badge vb-type">🎙️ Podcast</span><?php else: ?><span class="video-feat-badge vb-type">🎬 Video</span><?php endif; ?>
                </div>
                <h3 class="video-feat-title"><?= sanitize($featured['judul']) ?></h3>
                <p class="video-feat-desc"><?= sanitize(excerpt($featured['deskripsi'] ?? '', 220)) ?></p>
                <div class="video-feat-meta">
                    <?php if ($f_dur): ?><span>⏱️ <?= sanitize($f_dur) ?></span><?php endif; ?>
                    <span>👁 <?= number_format((int)$featured['views']) ?> ditonton</span>
                    <span>📅 <?= sanitize(format_tanggal_singkat($featured['published_at'] ?? $featured['created_at'])) ?></span>
                    <?php if (!empty($featured['prodi_singkatan'])): ?><span>🎓 <?= sanitize($featured['prodi_singkatan']) ?></span><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ===== GRID ===== -->
<section class="section" style="padding-top:<?= $featured ? '0' : '0' ?>;">
    <div class="container">
        <?php if ($akt_q !== '' || $akt_kategori !== '' || $akt_tab !== 'semua'): ?>
        <p style="color:var(--text-muted); font-size:.9rem; margin-bottom:1.5rem;" data-aos="fade-up">
            Menampilkan <strong style="color:var(--text-primary);"><?= count($videos) ?></strong> dari <strong style="color:var(--text-primary);"><?= $total_rows ?></strong> konten
            <?php if ($akt_tab !== 'semua'): ?>pada tab <strong style="color:#8b5cf6;"><?= $vid_tabs[$akt_tab] ?></strong><?php endif; ?>
            <?php if ($akt_kategori): ?>bertopik <strong style="color:#8b5cf6;"><?= sanitize($akt_kategori) ?></strong><?php endif; ?>
            <?php if ($akt_q): ?>mencocokkan "<strong style="color:#8b5cf6;"><?= sanitize($akt_q) ?></strong>"<?php endif; ?>.
            <a href="<?= base_url('video.php') ?>" style="color:#8b5cf6; font-weight:700; margin-left:.5rem;">Reset filter ✕</a>
        </p>
        <?php endif; ?>

        <?php if (empty($videos)): ?>
        <div class="empty-state-premium" data-aos="fade-up" style="text-align:center; padding:4rem 2rem; background:var(--bg-secondary); border:2px dashed var(--border); border-radius:var(--radius-xl,16px);">
            <div style="font-size:3.5rem; margin-bottom:.75rem;">🎬</div>
            <h3 style="font-family:var(--font-display); font-size:1.4rem; margin-bottom:.5rem;">Belum ada video</h3>
            <p style="color:var(--text-muted);">Coba ubah kata kunci, tab, atau kategori.</p>
            <a href="<?= base_url('video.php') ?>" class="btn btn-primary" style="margin-top:1.25rem; background:linear-gradient(135deg,#8b5cf6,#ec4899); color:#fff; border:none;">Lihat Semua Konten</a>
        </div>
        <?php else: ?>
        <div class="video-grid">
            <?php foreach ($videos as $i => $v):
                $emb = video_embed($v['video_url'] ?? '', $v['video_type'] ?? 'youtube');
                $th  = video_thumb($v);
                $dur = video_dur_label($v);
                $grads = ['linear-gradient(135deg,#4c1d95,#831843)','linear-gradient(135deg,#1e1b4b,#7c3aed)','linear-gradient(135deg,#831843,#db2777)','linear-gradient(135deg,#5b21b6,#3b82f6)','linear-gradient(135deg,#9d174d,#f59e0b)'];
                $vt_class = 'vt-' . ($v['is_podcast'] ? 'podcast' : ($v['video_type'] ?: 'other'));
                $vt_label = $v['is_podcast'] ? '🎙️ Podcast' : strtoupper($v['video_type'] ?: 'Video');
            ?>
            <div class="video-card" data-aos="fade-up" data-aos-delay="<?= ($i % 4) * 70 ?>" id="vid-<?= (int)$v['id'] ?>"
                 data-id="<?= (int)$v['id'] ?>" data-embed="<?= htmlspecialchars($emb['src'], ENT_QUOTES) ?>" data-kind="<?= $emb['kind'] ?>"
                 data-title="<?= sanitize($v['judul']) ?>" data-desc="<?= sanitize(excerpt($v['deskripsi'] ?? '', 400)) ?>"
                 data-url="<?= sanitize($v['video_url']) ?>" data-dur="<?= sanitize($dur) ?>"
                 data-views="<?= (int)$v['views'] ?>" data-date="<?= sanitize(format_tanggal_singkat($v['published_at'] ?? $v['created_at'])) ?>"
                 data-cat="<?= sanitize(($v['kat_icon'] ?? '📁') . ' ' . ($v['kat_nama'] ?? 'Umum')) ?>"
                 onclick="openVideoPlayer(this)">
                <div class="video-thumb">
                    <?php if ($th): ?>
                        <img src="<?= htmlspecialchars($th, ENT_QUOTES) ?>" alt="<?= sanitize($v['judul']) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="ph" style="background:<?= $grads[$i % count($grads)] ?>;"><?= $v['is_podcast'] ? '🎙️' : '🎬' ?></div>
                    <?php endif; ?>
                    <span class="video-type-badge <?= $vt_class ?>"><?= $vt_label ?></span>
                    <?php if ($dur): ?><span class="video-dur"><?= sanitize($dur) ?></span><?php endif; ?>
                    <div class="video-play"><div class="video-play-btn">▶</div></div>
                </div>
                <div class="video-body">
                    <div class="video-cat"><?= sanitize(($v['kat_icon'] ?? '📁')) ?> <?= sanitize($v['kat_nama'] ?? 'Umum') ?></div>
                    <h3 class="video-title"><?= sanitize($v['judul']) ?></h3>
                    <p class="video-desc"><?= sanitize(excerpt($v['deskripsi'] ?? '', 140)) ?></p>
                    <div class="video-foot">
                        <div class="vf-left">
                            <span>👁 <?= number_format((int)$v['views']) ?></span>
                            <span>📅 <?= sanitize(format_tanggal_singkat($v['published_at'] ?? $v['created_at'])) ?></span>
                        </div>
                        <?php if (!empty($v['prodi_singkatan'])): ?><span>🎓 <?= sanitize($v['prodi_singkatan']) ?></span><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
        <nav class="video-pagination" aria-label="Navigasi halaman">
            <a href="<?= video_build_query(['page' => $akt_page - 1]) ?>" class="<?= $akt_page <= 1 ? 'disabled' : '' ?>">← Prev</a>
            <?php
            $start = max(1, $akt_page - 2); $end = min($total_pages, $akt_page + 2);
            if ($start > 1) { echo '<a href="'.video_build_query(['page'=>1]).'">1</a>'; if ($start > 2) echo '<span class="disabled">…</span>'; }
            for ($p = $start; $p <= $end; $p++):
                if ($p === $akt_page) echo '<span class="current">'.$p.'</span>';
                else echo '<a href="'.video_build_query(['page'=>$p]).'">'.$p.'</a>';
            endfor;
            if ($end < $total_pages) { if ($end < $total_pages - 1) echo '<span class="disabled">…</span>'; echo '<a href="'.video_build_query(['page'=>$total_pages]).'">'.$total_pages.'</a>'; }
            ?>
            <a href="<?= video_build_query(['page' => $akt_page + 1]) ?>" class="<?= $akt_page >= $total_pages ? 'disabled' : '' ?>">Next →</a>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<!-- ===== CTA SUBSCRIBE ===== -->
<section style="padding-bottom:4rem;">
    <div class="container">
        <div class="video-cta" data-aos="zoom-in">
            <div class="container-inner">
                <h2>Jangan Lewatkan Video Terbaru!</h2>
                <p>Bergabunglah dengan kanal YouTube FKIP UNIMOF untuk kuliah umum, tutorial, dan dokumentasi kegiatan terkini.</p>
                <a href="<?= sanitize($youtube_url) ?>" target="_blank" rel="noopener" class="btn-sub">▶ Subscribe di YouTube</a>
            </div>
        </div>
    </div>
</section>

<!-- ===== MODAL PLAYER ===== -->
<div class="video-modal" id="videoModal" onclick="if(event.target===this)closeVideoPlayer()">
    <div class="video-modal-box">
        <div class="video-modal-head">
            <h3 id="vmTitle">Memuat...</h3>
            <button class="video-modal-close" onclick="closeVideoPlayer()" aria-label="Tutup">✕</button>
        </div>
        <div class="video-player-wrap" id="vmPlayer">
            <div class="video-player-loading"><div class="video-spinner"></div> Memuat pemutar...</div>
        </div>
        <div class="video-modal-foot">
            <p class="video-modal-desc" id="vmDesc"></p>
            <div class="video-modal-meta" id="vmMeta"></div>
            <div class="video-modal-actions" id="vmActions"></div>
        </div>
    </div>
</div>

<!-- TOAST -->
<div class="video-toast" id="videoToast"><span id="vtIcon">✓</span><span id="vtMsg"></span></div>

<script>
(function(){
    'use strict';
    var toast = document.getElementById('videoToast');
    function showToast(msg, icon){
        document.getElementById('vtMsg').textContent = msg;
        document.getElementById('vtIcon').textContent = icon || '✓';
        toast.classList.add('show');
        clearTimeout(showToast._t);
        showToast._t = setTimeout(function(){ toast.classList.remove('show'); }, 3000);
    }

    // ===== COUNT-UP =====
    function anim(el){var t=parseInt(el.dataset.count)||0,d=1600,s=performance.now();
    (function step(n){var p=Math.min((n-s)/d,1),e=1-Math.pow(1-p,3);el.textContent=Math.floor(e*t).toLocaleString('id-ID');if(p<1)requestAnimationFrame(step);})(s);}
    var o=new IntersectionObserver(function(en){en.forEach(function(x){if(x.isIntersecting){anim(x.target);o.unobserve(x.target);}});},{threshold:.4});
    document.querySelectorAll('.count-up').forEach(function(el){o.observe(el);});

    // ===== PLAYER MODAL (lazy iframe) =====
    var modal = document.getElementById('videoModal');
    var playerWrap = document.getElementById('vmPlayer');
    var viewedOnce = {};

    window.openVideoPlayer = function(card){
        var id = card.dataset.id;
        var kind = card.dataset.kind;
        var src = card.dataset.embed;
        document.getElementById('vmTitle').textContent = card.dataset.title || 'Video';
        document.getElementById('vmDesc').textContent = card.dataset.desc || '';
        // meta
        var meta = [];
        if (card.dataset.cat) meta.push('<span>📁 '+card.dataset.cat+'</span>');
        if (card.dataset.dur) meta.push('<span>⏱️ '+card.dataset.dur+'</span>');
        if (card.dataset.date) meta.push('<span>📅 '+card.dataset.date+'</span>');
        var vEl = document.getElementById('vmViews');
        meta.push('<span id="vmViewsInner">👁 '+Number(card.dataset.views||0).toLocaleString('id-ID')+' ditonton</span>');
        document.getElementById('vmMeta').innerHTML = meta.join('');
        // actions
        var origUrl = card.dataset.url || '';
        var acts = '';
        if (origUrl) acts += '<a href="'+origUrl+'" target="_blank" rel="noopener">↗ Buka Asli</a>';
        acts += '<button onclick="copyVideoLink()">🔗 Salin Tautan</button>';
        document.getElementById('vmActions').innerHTML = acts;
        modal.dataset.currentId = id;
        modal.dataset.currentUrl = origUrl;
        modal.dataset.currentTitle = card.dataset.title || '';
        // build player lazily
        playerWrap.innerHTML = '<div class="video-player-loading"><div class="video-spinner"></div> Memuat pemutar...</div>';
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
        setTimeout(function(){
            if (kind === 'iframe' && src) {
                playerWrap.innerHTML = '<iframe src="'+src+'" title="'+(card.dataset.title||'video')+'" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
            } else if (kind === 'video' && src) {
                playerWrap.innerHTML = '<video src="'+src+'" controls autoplay playsinline></video>';
            } else {
                playerWrap.innerHTML = '<div class="video-player-loading" style="flex-direction:column;gap:1rem;">⚠️ Sumber tidak dapat diputar.<br><a href="'+(origUrl||'#')+'" target="_blank" rel="noopener" style="color:#c084fc;text-decoration:underline;">Buka di tab baru</a></div>';
            }
        }, 120);
        // increment view (sekali per sesi per video)
        if (id && !viewedOnce[id]) {
            viewedOnce[id] = true;
            var fd = new FormData(); fd.append('action','video_view'); fd.append('id', id);
            fetch(location.pathname, {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(function(r){return r.json();})
            .then(function(d){
                if (d && d.ok) {
                    var el = document.getElementById('vmViewsInner');
                    if (el) el.innerHTML = '👁 '+Number(d.views).toLocaleString('id-ID')+' ditonton';
                }
            }).catch(function(){});
        }
    };

    window.closeVideoPlayer = function(){
        modal.classList.remove('open');
        document.body.style.overflow = '';
        playerWrap.innerHTML = '<div class="video-player-loading"><div class="video-spinner"></div> Memuat pemutar...</div>'; // stop playback
    };

    window.copyVideoLink = function(){
        var url = modal.dataset.currentUrl || location.href;
        if (navigator.clipboard) navigator.clipboard.writeText(url).then(function(){ showToast('Tautan disalin', '📋'); });
        else showToast('Tautan: '+url, '🔗');
    };

    // ===== KEYBOARD =====
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') closeVideoPlayer();
        var tag = (document.activeElement && document.activeElement.tagName) || '';
        if (e.key === '/' && !e.ctrlKey && !e.metaKey && ['INPUT','TEXTAREA','SELECT'].indexOf(tag) < 0) {
            e.preventDefault(); var s = document.querySelector('.video-search input'); if (s) s.focus();
        }
    });

    console.log('%c🎬 Video & Podcast - EXTREME MULTIMATE', 'color:#8b5cf6;font-size:13px;font-weight:bold');
    console.log('%cShortcuts: / (cari) • Esc (tutup player)', 'color:#64748b;font-size:11px');
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>