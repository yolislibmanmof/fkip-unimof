<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== HELPER LOKAL =====
if (!function_exists('vid_yt_id')) {
    function vid_yt_id($url) {
        $url = (string)$url;
        if (preg_match('~(?:v=|youtu\.be/|shorts/|embed/)([A-Za-z0-9_-]{11})~', $url, $m)) return $m[1];
        return null;
    }
}
if (!function_exists('vid_embed')) {
    function vid_embed($url, $type) {
        $url = trim((string)$url);
        if ($url === '') return ['kind' => 'link', 'src' => '', 'id' => null];
        if ($type === 'youtube' || preg_match('~youtube\.com|youtu\.be~i', $url)) {
            $id = vid_yt_id($url);
            if ($id) return ['kind' => 'iframe', 'src' => "https://www.youtube.com/embed/$id?rel=0&modestbranding=1", 'id' => $id];
        }
        if ($type === 'vimeo' || preg_match('~vimeo\.com~i', $url)) {
            if (preg_match('~(?:vimeo\.com/|player\.vimeo\.com/video/)(\d+)~', $url, $m))
                return ['kind' => 'iframe', 'src' => "https://player.vimeo.com/video/{$m[1]}", 'id' => $m[1]];
        }
        if ($type === 'local' || preg_match('~\.(mp4|webm|ogg)(\?.*)?$~i', $url))
            return ['kind' => 'video', 'src' => $url, 'id' => null];
        return ['kind' => 'link', 'src' => $url, 'id' => null];
    }
}
if (!function_exists('vid_thumb')) {
    function vid_thumb($row) {
        if (!empty($row['thumbnail'])) return asset('uploads/video/' . basename($row['thumbnail']));
        if (($row['video_type'] ?? '') === 'youtube') {
            $id = vid_yt_id($row['video_url'] ?? '');
            if ($id) return "https://img.youtube.com/vi/$id/hqdefault.jpg";
        }
        return '';
    }
}
if (!function_exists('vid_del_img')) {
    function vid_del_img($f) {
        if ($f) { $p = (defined('APP_DIR') ? APP_DIR : dirname(__DIR__, 2)) . '/uploads/video/' . basename($f); if (is_file($p)) @unlink($p); }
    }
}
if (!function_exists('vid_fmt_dur')) {
    function vid_fmt_dur($sec) {
        $sec = (int)$sec; if ($sec <= 0) return '';
        $h = intdiv($sec, 3600); $m = intdiv($sec % 3600, 60); $s = $sec % 60;
        return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
    }
}
if (!function_exists('vid_dur_label')) {
    function vid_dur_label($row) {
        if (!empty($row['duration'])) return $row['duration'];
        return vid_fmt_dur($row['duration_seconds'] ?? 0);
    }
}

$VSTATUS = ['Published','Draft','Archived'];
$VTYPES  = ['youtube','vimeo','local','other'];

// ===== PROSES AKSI POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('error', '❌ Token keamanan tidak valid.');
    } else {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        $ids = array_map('intval', (array)($_POST['ids'] ?? []));
        $back = $_POST['_tab'] ?? 'videos';

        // ---------- VIDEO ----------
        if ($action === 'bulk_delete' && !empty($ids)) {
            $ph = implode(',', array_fill(0,count($ids),'?'));
            $s = $pdo->prepare("SELECT thumbnail FROM video WHERE id IN ($ph) AND thumbnail IS NOT NULL AND thumbnail != ''"); $s->execute($ids);
            foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $t) vid_del_img($t);
            $pdo->prepare("DELETE FROM video WHERE id IN ($ph)")->execute($ids);
            flash_message('success', '✅ '.count($ids).' video dihapus.');
        }
        elseif ($action === 'bulk_publish' && !empty($ids)) {
            $ph = implode(',', array_fill(0,count($ids),'?'));
            $pdo->prepare("UPDATE video SET status='Published', published_at=IFNULL(published_at,NOW()) WHERE id IN ($ph)")->execute($ids);
            flash_message('success', '✅ '.count($ids).' video dipublikasikan.');
        }
        elseif ($action === 'bulk_draft' && !empty($ids)) {
            $ph = implode(',', array_fill(0,count($ids),'?'));
            $pdo->prepare("UPDATE video SET status='Draft' WHERE id IN ($ph)")->execute($ids);
            flash_message('success', '✅ '.count($ids).' video → draft.');
        }
        elseif ($action === 'bulk_archive' && !empty($ids)) {
            $ph = implode(',', array_fill(0,count($ids),'?'));
            $pdo->prepare("UPDATE video SET status='Archived' WHERE id IN ($ph)")->execute($ids);
            flash_message('success', '✅ '.count($ids).' video diarsipkan.');
        }
        elseif ($action === 'bulk_feature' && !empty($ids)) {
            $ph = implode(',', array_fill(0,count($ids),'?'));
            $pdo->prepare("UPDATE video SET is_featured=1 WHERE id IN ($ph)")->execute($ids);
            flash_message('success', '⭐ '.count($ids).' video ditandai sorotan.');
        }
        // 🆕 FIX: Bulk toggle podcast (video ↔ podcast)
        elseif ($action === 'bulk_toggle_podcast' && !empty($ids)) {
            $ph = implode(',', array_fill(0,count($ids),'?'));
            $pdo->prepare("UPDATE video SET is_podcast=IF(is_podcast=1,0,1) WHERE id IN ($ph)")->execute($ids);
            flash_message('success', '🎙️ Tipe konten diubah untuk '.count($ids).' video.');
        }
        elseif ($action === 'delete' && $id) {
            $s = $pdo->prepare("SELECT thumbnail FROM video WHERE id=?"); $s->execute([$id]); vid_del_img($s->fetchColumn());
            $pdo->prepare("DELETE FROM video WHERE id=?")->execute([$id]);
            flash_message('success', '✅ Video dihapus.');
        }
        elseif ($action === 'toggle' && $id) {
            $pdo->prepare("UPDATE video SET status=IF(status='Published','Draft','Published'), published_at=IF(status='Published',published_at,NOW()) WHERE id=?")->execute([$id]);
            flash_message('success', '✅ Status video diubah.');
        }
        elseif ($action === 'toggle_featured' && $id) {
            $pdo->prepare("UPDATE video SET is_featured=IF(is_featured=1,0,1) WHERE id=?")->execute([$id]);
            flash_message('success', '⭐ Status sorotan diubah.');
        }
        elseif ($action === 'toggle_podcast' && $id) {
            $pdo->prepare("UPDATE video SET is_podcast=IF(is_podcast=1,0,1) WHERE id=?")->execute([$id]);
            flash_message('success', '🎙️ Tipe konten diubah (Video ↔ Podcast).');
        }
        elseif ($action === 'duplicate' && $id) {
            $s = $pdo->prepare("SELECT * FROM video WHERE id=?"); $s->execute([$id]); $o = $s->fetch();
            if ($o) {
                $slug = substr($o['slug'],0,170).'-copy-'.time();
                $pdo->prepare("INSERT INTO video (kategori_id,program_studi_id,judul,slug,deskripsi,thumbnail,video_url,video_type,duration,duration_seconds,is_podcast,is_featured,tags,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())")
                    ->execute([$o['kategori_id'],$o['program_studi_id'],$o['judul'].' (Copy)',$slug,$o['deskripsi'],$o['thumbnail'],$o['video_url'],$o['video_type'],$o['duration'],$o['duration_seconds'],$o['is_podcast'],0,$o['tags'],'Draft']);
                flash_message('success', '✅ Video diduplikasi sebagai draft.');
            }
        }
        // ---------- KATEGORI ----------
        elseif ($action === 'save_kategori') {
            $kid = (int)($_POST['kategori_id'] ?? 0);
            $nama = trim($_POST['nama'] ?? '');
            if ($nama === '') { flash_message('error', '❌ Nama kategori wajib diisi.'); }
            else {
                $icon = trim($_POST['icon'] ?? '📁') ?: '📁';
                $desk = trim($_POST['deskripsi'] ?? '');
                $urut = (int)($_POST['urutan'] ?? 0);
                $kst  = in_array($_POST['status'] ?? '', ['Aktif','Non-Aktif'], true) ? $_POST['status'] : 'Aktif';
                $custom = trim($_POST['slug'] ?? '');
                $base = function_exists('generate_slug') ? generate_slug($custom !== '' ? $custom : $nama) : strtolower(preg_replace('/[^a-z0-9]+/i', '-', $custom !== '' ? $custom : $nama));
                $slug = $base; $n = 2;
                while (true) {
                    $chk = $pdo->prepare("SELECT id FROM video_kategori WHERE slug=? AND id!=?"); $chk->execute([$slug,$kid]);
                    if (!$chk->fetchColumn()) break;
                    $slug = $base.'-'.$n++;
                }
                try {
                    if ($kid > 0) {
                        $pdo->prepare("UPDATE video_kategori SET nama=?,slug=?,icon=?,deskripsi=?,urutan=?,status=? WHERE id=?")
                            ->execute([$nama,$slug,$icon,$desk,$urut,$kst,$kid]);
                        flash_message('success', '✅ Kategori diperbarui.');
                    } else {
                        $pdo->prepare("INSERT INTO video_kategori (nama,slug,icon,deskripsi,urutan,status) VALUES (?,?,?,?,?,?)")
                            ->execute([$nama,$slug,$icon,$desk,$urut,$kst]);
                        flash_message('success', '✅ Kategori baru dibuat.');
                    }
                } catch (Exception $e) { flash_message('error', '❌ Gagal: '.$e->getMessage()); }
            }
            $back = 'categories';
        }
        elseif ($action === 'delete_kategori' && $id) {
            $pdo->prepare("DELETE FROM video_kategori WHERE id=?")->execute([$id]);
            flash_message('success', '🗑️ Kategori dihapus (video terkait menjadi tanpa kategori).');
            $back = 'categories';
        }
        elseif ($action === 'toggle_kategori' && $id) {
            $pdo->prepare("UPDATE video_kategori SET status=IF(status='Aktif','Non-Aktif','Aktif') WHERE id=?")->execute([$id]);
            flash_message('success', '✅ Status kategori diubah.');
            $back = 'categories';
        }
    }
    $qs = $_GET; $qs['tab'] = $back; unset($qs['id']);
    header('Location: video.php'.(!empty($qs)?'?'.http_build_query($qs):'')); exit;
}

// ===== EXPORT =====
if (isset($_GET['export'])) {
    $fmt = $_GET['export'];
    $all = $pdo->query("SELECT v.*, vk.nama AS kat_nama FROM video v LEFT JOIN video_kategori vk ON v.kategori_id=vk.id ORDER BY v.created_at DESC")->fetchAll();
    if ($fmt === 'csv' && $all) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="video-fkip-'.date('Y-m-d').'.csv"');
        $out = fopen('php://output','w'); fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['ID','Judul','Slug','Kategori','Tipe','Status','Views','Durasi(s)','Podcast','Sorotan','URL','Tanggal']);
        foreach ($all as $r) fputcsv($out, [$r['id'],$r['judul'],$r['slug'],$r['kat_nama']??'-',$r['video_type'],$r['status'],$r['views'],$r['duration_seconds'],$r['is_podcast']?'Ya':'Tidak',$r['is_featured']?'Ya':'Tidak',$r['video_url'],$r['created_at']]);
        fclose($out); exit;
    }
    if ($fmt === 'json' && $all) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="video-fkip-'.date('Y-m-d').'.json"');
        echo json_encode(['exported_at'=>date('c'),'total'=>count($all),'data'=>$all], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE); exit;
    }
}

// ===== TAB & FILTER =====
$tab = in_array($_GET['tab'] ?? '', ['videos','categories'], true) ? $_GET['tab'] : 'videos';
$q = trim($_GET['q'] ?? '');
$kat_filter = (int)($_GET['kategori'] ?? 0);
$status_filter = trim($_GET['status'] ?? '');
$type_filter = trim($_GET['tipe'] ?? '');
$date_filter = trim($_GET['tanggal'] ?? '');
$featured_filter = isset($_GET['featured']) ? (int)$_GET['featured'] : '';
$view_mode = $_GET['view'] ?? 'table';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_dir = $_GET['dir'] ?? 'desc';
$halaman = max(1, (int)($_GET['halaman'] ?? 1));
$per_page = $view_mode === 'grid' ? 12 : 15;
$offset = ($halaman - 1) * $per_page;

$categories = [];
try { $categories = $pdo->query("SELECT vk.*, (SELECT COUNT(*) FROM video v WHERE v.kategori_id=vk.id) AS vcount FROM video_kategori vk ORDER BY vk.urutan ASC, vk.nama ASC")->fetchAll(); } catch (Exception $e) {}
$categories_aktif = array_values(array_filter($categories, fn($c) => $c['status'] === 'Aktif'));

$where = 'WHERE 1=1'; $params = [];
if ($q !== '') { $where .= ' AND (v.judul LIKE ? OR v.deskripsi LIKE ? OR v.slug LIKE ? OR v.tags LIKE ?)'; $l="%$q%"; $params[]=$l;$params[]=$l;$params[]=$l;$params[]=$l; }
if ($kat_filter > 0) { $where .= ' AND v.kategori_id = ?'; $params[] = $kat_filter; }
if (in_array($status_filter, $VSTATUS, true)) { $where .= ' AND v.status = ?'; $params[] = $status_filter; }
if ($type_filter === 'video') { $where .= ' AND v.is_podcast = 0'; }
elseif ($type_filter === 'podcast') { $where .= ' AND v.is_podcast = 1'; }
if ($date_filter !== '') { $where .= ' AND DATE(v.created_at) = ?'; $params[] = $date_filter; }
if ($featured_filter !== '') { $where .= ' AND v.is_featured = ?'; $params[] = $featured_filter; }
$valid_sorts = ['created_at','judul','views','duration_seconds','published_at','is_podcast'];
$sort_by = in_array($sort_by, $valid_sorts) ? $sort_by : 'created_at';
$sort_dir = in_array(strtolower($sort_dir), ['asc','desc']) ? strtoupper($sort_dir) : 'DESC';

$total = 0; $videos = []; $video_rows = []; $total_pages = 1;
if ($tab === 'videos') {
    $cs = $pdo->prepare("SELECT COUNT(*) FROM video v $where"); $cs->execute($params); $total = (int)$cs->fetchColumn();
    $total_pages = max(1, (int)ceil($total / $per_page)); $halaman = min($halaman, $total_pages); $offset = ($halaman - 1) * $per_page;
    $st = $pdo->prepare("SELECT v.*, vk.nama AS kat_nama, vk.icon AS kat_icon, vk.slug AS kat_slug, p.singkatan AS prodi_singkatan
        FROM video v LEFT JOIN video_kategori vk ON v.kategori_id=vk.id LEFT JOIN program_studi p ON v.program_studi_id=p.id
        $where ORDER BY v.$sort_by $sort_dir LIMIT ? OFFSET ?");
    $st->execute(array_merge($params, [$per_page, $offset])); $videos = $st->fetchAll();
    foreach ($videos as $v) {
        $emb = vid_embed($v['video_url'] ?? '', $v['video_type'] ?? 'youtube');
        $video_rows[] = [
            'id'=>(int)$v['id'],'judul'=>$v['judul'],'slug'=>$v['slug'],'deskripsi'=>$v['deskripsi'],
            'thumb'=>vid_thumb($v),'embed'=>$emb['src'],'kind'=>$emb['kind'],'url'=>$v['video_url'],
            'type'=>$v['video_type'],'is_podcast'=>(int)$v['is_podcast'],'is_featured'=>(int)$v['is_featured'],
            'views'=>(int)$v['views'],'dur'=>vid_dur_label($v),'durs'=>(int)$v['duration_seconds'],
            'kat'=>($v['kat_icon'] ?? '📁').' '.($v['kat_nama'] ?? 'Umum'),'status'=>$v['status'],
            'tanggal'=>$v['published_at'] ?: $v['created_at'],'prodi'=>$v['prodi_singkatan'] ?? ''
        ];
    }
}

// ===== STATISTIK =====
$stat_published = (int)$pdo->query("SELECT COUNT(*) FROM video WHERE status='Published'")->fetchColumn();
$stat_draft     = (int)$pdo->query("SELECT COUNT(*) FROM video WHERE status='Draft'")->fetchColumn();
$stat_archived  = (int)$pdo->query("SELECT COUNT(*) FROM video WHERE status='Archived'")->fetchColumn();
$stat_views     = (int)$pdo->query("SELECT COALESCE(SUM(views),0) FROM video")->fetchColumn();
$stat_featured  = (int)$pdo->query("SELECT COUNT(*) FROM video WHERE is_featured=1")->fetchColumn();
$stat_podcast   = (int)$pdo->query("SELECT COUNT(*) FROM video WHERE status='Published' AND is_podcast=1")->fetchColumn();
$stat_video     = (int)$pdo->query("SELECT COUNT(*) FROM video WHERE status='Published' AND is_podcast=0")->fetchColumn();
$stat_week      = (int)$pdo->query("SELECT COUNT(*) FROM video WHERE created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetchColumn();
$stat_dur       = (int)$pdo->query("SELECT COALESCE(SUM(duration_seconds),0) FROM video WHERE status='Published'")->fetchColumn();
$avg_views = $stat_published > 0 ? round($stat_views / $stat_published) : 0;
$total_all = max(1, $stat_published + $stat_draft + $stat_archived);
$engagement = min(100, round((($stat_featured*10)+($stat_published*3)+min(100,$stat_views/100))/$total_all));

$cat_stats = [];
try {
    foreach ($pdo->query("SELECT COALESCE(vk.nama,'Tanpa Kategori') nm, COUNT(*) t, COALESCE(SUM(v.views),0) v FROM video v LEFT JOIN video_kategori vk ON v.kategori_id=vk.id WHERE v.status='Published' GROUP BY nm ORDER BY t DESC")->fetchAll() as $r)
        $cat_stats[$r['nm']] = ['count'=>(int)$r['t'],'views'=>(int)$r['v']];
} catch (Exception $e) {}
$top_kat = $cat_stats; arsort($top_kat); $top_kat = array_slice($top_kat, 0, 5, true);
$monthly = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months")); $lb = date('M Y', strtotime("-$i months"));
    $q2 = $pdo->prepare("SELECT COUNT(*) FROM video WHERE DATE_FORMAT(created_at,'%Y-%m')=?"); $q2->execute([$m]);
    $monthly[$lb] = (int)$q2->fetchColumn();
}

$csrf = generate_csrf_token();
$active_menu = 'video';
$page_heading = $tab === 'categories' ? 'Kelola Kategori Video' : 'Kelola Video & Podcast';
$breadcrumbs = [['Dashboard','dashboard.php'], [$page_heading, null]];
require __DIR__ . '/includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ========================================
   VIDEO.PHP - DARK MODE COMPATIBLE STYLES
   ======================================== */

/* ===== THEME VARIABLES ===== */
:root {
    --video-accent: #dc2626;
    --video-accent-light: #ef4444;
    --hero-gradient: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 50%, var(--primary-dark) 100%);
    --accent-gradient: linear-gradient(135deg, var(--primary), var(--primary-light));
    --accent-soft: rgba(10, 104, 71, 0.1);
    --featured-color: #f59e0b;
    --featured-bg: rgba(245, 158, 11, 0.1);
    --podcast-color: #ec4899;
    --podcast-bg: rgba(236, 72, 153, 0.1);
}

[data-theme="dark"] {
    --video-accent: #f87171;
    --video-accent-light: #fca5a5;
    --hero-gradient: linear-gradient(135deg, #1e293b 0%, #334155 50%, #0f172a 100%);
    --accent-gradient: linear-gradient(135deg, var(--primary), var(--primary-light));
    --accent-soft: rgba(16, 163, 74, 0.15);
    --featured-color: #fbbf24;
    --featured-bg: rgba(251, 191, 36, 0.12);
    --podcast-color: #f472b6;
    --podcast-bg: rgba(244, 114, 182, 0.12);
}

[data-theme="midnight"] {
    --video-accent: #f87171;
    --hero-gradient: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #020617 100%);
    --accent-gradient: linear-gradient(135deg, #6366f1, #8b5cf6);
    --accent-soft: rgba(99, 102, 241, 0.15);
}

[data-theme="emerald"] {
    --hero-gradient: linear-gradient(135deg, #059669 0%, #10b981 50%, #047857 100%);
}

/* ===== HERO ===== */
.video-hero {
    background: var(--hero-gradient);
    color: #fff;
    padding: 2rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    position: relative;
    z-index: 1; /* TAMBAHKAN: agar tidak tertutup header */
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(10, 104, 71, 0.25);
    transition: all 0.3s ease;
}

[data-theme="dark"] .video-hero,
[data-theme="midnight"] .video-hero {
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
    border: 1px solid var(--border);
}

.video-hero::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 5px;
    background: linear-gradient(90deg, var(--video-accent), var(--video-accent-light), var(--video-accent));
}

.video-hero::after {
    content: 'MEDIA';
    position: absolute;
    top: 2rem; right: 2rem;
    font-family: 'Georgia', serif;
    font-size: 6rem;
    font-weight: 900;
    color: rgba(255, 255, 255, 0.05);
    letter-spacing: 0.2em;
    pointer-events: none;
}

.video-hero-content {
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
    flex-wrap: wrap;
    z-index: 1;
}

.video-hero h2 {
    font-family: 'Georgia', serif;
    font-size: 2rem;
    font-weight: 900;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.video-hero p {
    opacity: 0.95;
    font-size: 0.95rem;
    max-width: 520px;
    line-height: 1.6;
}

.hero-stats {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    margin-top: 1rem;
}

.hero-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.5rem 1rem;
    background: rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    min-width: 90px;
}

.hero-stat-num {
    font-size: 1.5rem;
    font-weight: 900;
    line-height: 1;
    font-variant-numeric: tabular-nums;
    font-family: 'Georgia', serif;
}

.hero-stat-label {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    opacity: 0.9;
    margin-top: 0.25rem;
}

.engagement-ring {
    width: 110px;
    height: 110px;
    position: relative;
    flex-shrink: 0;
}

.engagement-ring svg {
    transform: rotate(-90deg);
    width: 100%;
    height: 100%;
}

.engagement-ring .ring-bg {
    fill: none;
    stroke: rgba(255, 255, 255, 0.2);
    stroke-width: 8;
}

.engagement-ring .ring-fill {
    fill: none;
    stroke: #fff;
    stroke-width: 8;
    stroke-linecap: round;
    transition: stroke-dasharray 1.5s ease;
}

.engagement-value {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #fff;
}

.engagement-value .score-num {
    font-size: 1.85rem;
    font-weight: 900;
    line-height: 1;
    font-family: 'Georgia', serif;
}

.engagement-value .score-label {
    font-size: 0.65rem;
    opacity: 0.9;
    margin-top: 0.2rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* ===== TABS ===== */
.video-tabs {
    display: flex;
    gap: 0.4rem;
    border-bottom: 2px solid var(--border);
    margin-bottom: 1.5rem;
    margin-top: 1rem; /* TAMBAHKAN: ruang antara hero dan tabs */
    flex-wrap: wrap;
}

.video-tabs a {
    padding: 0.7rem 1.1rem;
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--text-muted);
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}

.video-tabs a:hover { color: var(--text-primary); }

.video-tabs a.on {
    color: var(--primary);
    border-bottom-color: var(--primary);
}

[data-theme="dark"] .video-tabs a.on,
[data-theme="midnight"] .video-tabs a.on {
    color: var(--primary-light);
    border-bottom-color: var(--primary-light);
}

.video-tabs .tpill {
    background: var(--video-accent);
    color: #fff;
    font-size: 0.66rem;
    font-weight: 800;
    padding: 0.1rem 0.5rem;
    border-radius: 99px;
    margin-left: 0.35rem;
}

/* ===== STATS GRID ===== */
.video-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}

.stat-card-ultimate {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: var(--shadow-sm);
    cursor: pointer;
}

.stat-card-ultimate::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: var(--sc, var(--primary));
}

.stat-card-ultimate:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-xl);
    border-color: var(--sc, var(--primary));
}

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
}

.stat-icon-box {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: var(--sc, var(--primary));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: #fff;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.stat-number-ultimate {
    font-family: 'Georgia', serif;
    font-size: 2.25rem;
    font-weight: 900;
    color: var(--sc, var(--primary));
    line-height: 1;
    margin-bottom: 0.25rem;
    font-variant-numeric: tabular-nums;
}

[data-theme="dark"] .stat-number-ultimate,
[data-theme="midnight"] .stat-number-ultimate {
    filter: brightness(1.2);
}

.stat-label-ultimate {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.5rem;
}

.stat-trend-ultimate {
    font-size: 0.72rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-weight: 600;
    padding: 0.25rem 0.55rem;
    border-radius: 999px;
    width: fit-content;
}

.stat-trend-ultimate.up {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}

[data-theme="dark"] .stat-trend-ultimate.up,
[data-theme="midnight"] .stat-trend-ultimate.up {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
}

.stat-trend-ultimate.neutral {
    background: var(--bg-tertiary);
    color: var(--text-muted);
}

/* ===== CHARTS ===== */
.chart-section {
    display: grid;
    grid-template-columns: 1.3fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.chart-section-3 {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.chart-card {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
}

.chart-card h3 {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-family: 'Georgia', serif;
    color: var(--text-primary);
}

.top-list {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.top-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.75rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
    transition: all 0.2s;
}

.top-item:hover {
    background: var(--bg-tertiary);
    transform: translateX(3px);
    border-color: var(--primary);
}

.top-avatar {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: var(--accent-gradient);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.top-info { flex: 1; min-width: 0; }

.top-name {
    font-weight: 700;
    font-size: 0.88rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--text-primary);
}

.top-item .top-bar {
    height: 4px;
    background: var(--bg-tertiary);
    border-radius: 999px;
    margin-top: 0.3rem;
    overflow: hidden;
}

.top-item .top-bar-fill {
    height: 100%;
    background: var(--accent-gradient);
    border-radius: 999px;
    transition: width 1s ease;
}

.top-count {
    font-size: 0.85rem;
    font-weight: 800;
    color: var(--primary);
    flex-shrink: 0;
    min-width: 30px;
    text-align: right;
}

/* ===== QUICK FILTER PILLS ===== */
.quick-filter-pills {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
    padding: 0.5rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    align-items: center;
}

.pill {
    padding: 0.5rem 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    text-decoration: none;
}

.pill:hover {
    background: var(--bg-tertiary);
    color: var(--text-primary);
    transform: translateY(-1px);
    border-color: var(--primary);
}

.pill.active {
    background: var(--accent-gradient);
    color: #fff;
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(10, 104, 71, 0.25);
}

.pill .pill-count {
    background: rgba(255, 255, 255, 0.25);
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 800;
    min-width: 22px;
    text-align: center;
}

.pill:not(.active) .pill-count {
    background: var(--bg-tertiary);
    color: var(--text-muted);
}

/* ===== ADVANCED FILTER ===== */
.advanced-filter-bar {
    background: var(--bg-secondary);
    padding: 1.25rem;
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
    border: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
}

.advanced-search-form {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
    flex-wrap: wrap;
}

.search-group { flex: 1; min-width: 280px; position: relative; }
.search-input-wrap { position: relative; }

.search-icon-pro {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 1rem;
    color: var(--text-muted);
    pointer-events: none;
}

.search-input-pro {
    width: 100%;
    padding: 0.75rem 3rem 0.75rem 2.75rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    font-size: 0.95rem;
    transition: all 0.3s;
    font-family: inherit;
    background: var(--bg-primary);
    color: var(--text-primary);
}

.search-input-pro:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px var(--accent-soft);
}

.search-input-pro::placeholder { color: var(--text-muted); }

.search-shortcut {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    background: var(--bg-tertiary);
    color: var(--text-muted);
    padding: 0.15rem 0.5rem;
    border-radius: 5px;
    font-size: 0.68rem;
    font-family: monospace;
    font-weight: 600;
    pointer-events: none;
    border: 1px solid var(--border);
}

.search-clear-pro {
    position: absolute;
    right: 2.5rem;
    top: 50%;
    transform: translateY(-50%);
    background: var(--bg-tertiary);
    border: none;
    font-size: 1rem;
    cursor: pointer;
    color: var(--text-muted);
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.search-clear-pro:hover {
    background: var(--video-accent);
    color: #fff;
}

.filter-group {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.filter-select-pro,
.date-filter-pro {
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    font-size: 0.9rem;
    background: var(--bg-primary);
    cursor: pointer;
    transition: all 0.3s;
    font-family: inherit;
    color: var(--text-primary);
}

.filter-select-pro:focus,
.date-filter-pro:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px var(--accent-soft);
}

[data-theme="dark"] .filter-select-pro option,
[data-theme="midnight"] .filter-select-pro option {
    background: var(--bg-primary);
    color: var(--text-primary);
}

.view-toggle-pro {
    display: flex;
    background: var(--bg-tertiary);
    border-radius: var(--radius-md);
    padding: 0.25rem;
    gap: 0.25rem;
    border: 1px solid var(--border);
}

.view-btn-pro {
    padding: 0.5rem 0.85rem;
    border-radius: 7px;
    border: none;
    background: transparent;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    color: var(--text-muted);
    text-decoration: none;
    font-size: 0.82rem;
    font-weight: 600;
    font-family: inherit;
}

.view-btn-pro.active {
    background: var(--bg-primary);
    color: var(--text-primary);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border);
}

.view-btn-pro:hover:not(.active) {
    background: var(--bg-secondary);
    color: var(--text-primary);
}

.btn-reset-filter {
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    background: var(--bg-primary);
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
    font-family: inherit;
    color: var(--text-secondary);
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.btn-reset-filter:hover {
    background: var(--bg-tertiary);
    border-color: var(--primary);
    color: var(--primary);
}

/* ===== CATEGORY CHIPS ===== */
.category-chips-pro {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
    padding: 0.75rem 1rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
    align-items: center;
}

.category-chips-label {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-right: 0.5rem;
}

.chip-pro {
    padding: 0.4rem 0.9rem;
    border-radius: 999px;
    border: 1px solid var(--border);
    background: var(--bg-primary);
    cursor: pointer;
    font-weight: 600;
    font-size: 0.78rem;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    text-decoration: none;
    color: var(--text-secondary);
}

.chip-pro:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    color: var(--primary);
}

.chip-pro.active {
    background: var(--accent-gradient);
    color: #fff;
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(10, 104, 71, 0.25);
}

.chip-count-pro {
    background: var(--bg-tertiary);
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 800;
    min-width: 20px;
    text-align: center;
}

.chip-pro.active .chip-count-pro {
    background: rgba(255, 255, 255, 0.3);
    color: #fff;
}

/* ===== CARD HEADER ===== */
.card-header-pro {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border);
    flex-wrap: wrap;
    gap: 1rem;
}

.header-left h2 {
    font-family: 'Georgia', serif;
    font-size: 1.5rem;
    margin-bottom: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-primary);
}

.count-badge-pro {
    background: var(--accent-gradient);
    color: #fff;
    padding: 0.2rem 0.7rem;
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 700;
}

.header-actions-pro {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.btn-action-pro {
    padding: 0.7rem 1.15rem;
    border-radius: var(--radius-md);
    border: 2px solid var(--border);
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    font-size: 0.85rem;
    font-family: inherit;
    white-space: nowrap;
}

.btn-action-pro:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
    border-color: var(--primary);
    color: var(--primary);
}

.btn-action-pro.primary {
    background: var(--accent-gradient);
    color: #fff;
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(10, 104, 71, 0.25);
}

.btn-action-pro.primary:hover {
    color: #fff;
    box-shadow: 0 8px 20px rgba(10, 104, 71, 0.35);
}

/* Export Dropdown */
.export-dropdown { position: relative; }

.export-menu {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    min-width: 220px;
    display: none;
    z-index: 50;
    overflow: hidden;
}

.export-menu.show {
    display: block;
    animation: menuPop 0.2s ease;
}

@keyframes menuPop {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}

.export-item {
    padding: 0.7rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.7rem;
    color: var(--text-primary);
    text-decoration: none;
    font-size: 0.85rem;
    transition: background 0.15s;
    border-bottom: 1px solid var(--border);
}

.export-item:last-child { border-bottom: none; }
.export-item:hover { background: var(--bg-secondary); }
.export-item-icon { font-size: 1.1rem; width: 22px; text-align: center; }

/* ===== BULK BAR ===== */
.bulk-bar-pro {
    background: var(--accent-gradient);
    color: #fff;
    padding: 1rem 1.5rem;
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
    display: none;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    animation: slideDown 0.3s ease;
    box-shadow: 0 10px 30px rgba(10, 104, 71, 0.3);
    position: relative;
    overflow: hidden;
}

.bulk-bar-pro::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: rgba(255, 255, 255, 0.3);
}

.bulk-bar-pro.show { display: flex; }

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.bulk-info-pro {
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.bulk-count {
    background: #fff;
    color: var(--primary);
    padding: 0.25rem 0.7rem;
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 800;
}

.bulk-actions-pro, .bulk-form-inline {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin: 0;
}

.bulk-btn-pro {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
    font-size: 0.82rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.bulk-btn-pro:hover { transform: translateY(-2px); }

.bulk-btn-pro.publish { background: #10b981; color: #fff; }
.bulk-btn-pro.draft { background: #f59e0b; color: #fff; }
.bulk-btn-pro.archive { background: #64748b; color: #fff; }
.bulk-btn-pro.feature { background: #8b5cf6; color: #fff; }
.bulk-btn-pro.podcast { background: var(--podcast-color); color: #fff; }
.bulk-btn-pro.delete { background: #dc2626; color: #fff; }
.bulk-btn-pro.cancel {
    background: transparent;
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

/* ===== TABLE ===== */
.table-wrapper-pro {
    overflow-x: auto;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
    background: var(--bg-primary);
}

.video-table-premium {
    width: 100%;
    border-collapse: collapse;
}

.video-table-premium thead {
    background: var(--bg-secondary);
}

.video-table-premium th {
    padding: 0.85rem 1rem;
    text-align: left;
    font-weight: 700;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
    border-bottom: 2px solid var(--border);
    user-select: none;
    position: sticky;
    top: 0;
    z-index: 5;
    background: var(--bg-secondary);
}

.video-table-premium th[data-sortable] {
    cursor: pointer;
    transition: color 0.2s;
}

.video-table-premium th[data-sortable]:hover {
    color: var(--primary);
}

.video-table-premium th .sort-icon {
    opacity: 0.3;
    margin-left: 0.3rem;
    font-size: 0.7rem;
}

.video-table-premium td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
    transition: all 0.2s;
}

.video-table-premium tbody tr { transition: all 0.2s; }

.video-table-premium tbody tr:hover {
    background: var(--bg-secondary);
}

[data-theme="dark"] .video-table-premium tbody tr:hover,
[data-theme="midnight"] .video-table-premium tbody tr:hover {
    background: var(--bg-tertiary);
}

.video-table-premium tbody tr.selected {
    background: var(--accent-soft);
}

.video-table-premium tbody tr.featured-row {
    background: var(--featured-bg);
}

.row-checkbox {
    width: 18px;
    height: 18px;
    accent-color: var(--primary);
    cursor: pointer;
}

.table-thumb-pro {
    width: 90px;
    height: 54px;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    background: var(--bg-tertiary);
    position: relative;
    flex-shrink: 0;
    transition: transform 0.2s;
}

.table-thumb-pro:hover { transform: scale(1.05); }
.table-thumb-pro img { width: 100%; height: 100%; object-fit: cover; }

.thumb-placeholder-pro {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    background: var(--accent-gradient);
    color: #fff;
}

.thumb-play {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.35);
    color: #fff;
    font-size: 1.2rem;
    opacity: 0;
    transition: opacity 0.2s;
}

.table-thumb-pro:hover .thumb-play { opacity: 1; }

.title-cell-pro strong {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.95rem;
    margin-bottom: 0.25rem;
    line-height: 1.3;
    color: var(--text-primary);
}

.title-cell-pro small {
    display: block;
    font-size: 0.72rem;
    color: var(--text-muted);
    font-family: monospace;
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.title-cell-pro .author-line {
    font-size: 0.72rem;
    color: var(--text-muted);
    margin-top: 0.2rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.featured-icon-pro {
    color: var(--featured-color);
    font-size: 0.95rem;
}

/* ===== BADGES - THEME AWARE ===== */
.badge-pro {
    padding: 0.3rem 0.7rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.badge-published { background: #dcfce7; color: #166534; }
[data-theme="dark"] .badge-published,
[data-theme="midnight"] .badge-published {
    background: rgba(34, 197, 94, 0.2);
    color: #86efac;
}

.badge-draft { background: #fef3c7; color: #92400e; }
[data-theme="dark"] .badge-draft,
[data-theme="midnight"] .badge-draft {
    background: rgba(245, 158, 11, 0.2);
    color: #fcd34d;
}

.badge-archived { background: #f3f4f6; color: #475569; }
[data-theme="dark"] .badge-archived,
[data-theme="midnight"] .badge-archived {
    background: rgba(100, 116, 139, 0.3);
    color: #cbd5e1;
}

.badge-category { background: #dbeafe; color: #1e40af; }
[data-theme="dark"] .badge-category,
[data-theme="midnight"] .badge-category {
    background: rgba(59, 130, 246, 0.2);
    color: #93c5fd;
}

.badge-featured {
    background: var(--featured-bg);
    color: var(--featured-color);
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.badge-youtube { background: #fee2e2; color: #b91c1c; }
[data-theme="dark"] .badge-youtube,
[data-theme="midnight"] .badge-youtube {
    background: rgba(220, 38, 38, 0.2);
    color: #fca5a5;
}

.badge-vimeo { background: #ccfbf1; color: #0f766e; }
[data-theme="dark"] .badge-vimeo,
[data-theme="midnight"] .badge-vimeo {
    background: rgba(20, 184, 166, 0.2);
    color: #5eead4;
}

.badge-local { background: #ede9fe; color: #6d28d9; }
[data-theme="dark"] .badge-local,
[data-theme="midnight"] .badge-local {
    background: rgba(139, 92, 246, 0.2);
    color: #c4b5fd;
}

.badge-other { background: #e2e8f0; color: #475569; }
[data-theme="dark"] .badge-other,
[data-theme="midnight"] .badge-other {
    background: rgba(100, 116, 139, 0.3);
    color: #cbd5e1;
}

.badge-podcast { background: #fce7f3; color: #be185d; }
[data-theme="dark"] .badge-podcast,
[data-theme="midnight"] .badge-podcast {
    background: var(--podcast-bg);
    color: var(--podcast-color);
}

.badge-video { background: #e0e7ff; color: #4338ca; }
[data-theme="dark"] .badge-video,
[data-theme="midnight"] .badge-video {
    background: rgba(99, 102, 241, 0.2);
    color: #a5b4fc;
}

.views-cell-pro { text-align: center; }

.views-cell-pro strong {
    display: block;
    font-size: 1.05rem;
    color: var(--primary);
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}

[data-theme="dark"] .views-cell-pro strong,
[data-theme="midnight"] .views-cell-pro strong {
    color: var(--primary-light);
    filter: brightness(1.2);
}

.views-label {
    font-size: 0.66rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.date-cell-pro { font-size: 0.85rem; color: var(--text-primary); }
.date-cell-pro small { color: var(--text-muted); font-size: 0.72rem; display: block; margin-top: 0.1rem; }

.action-buttons-pro {
    display: flex;
    gap: 0.25rem;
    justify-content: flex-end;
}

.act-btn-pro {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: none;
    background: var(--bg-tertiary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    font-size: 0.95rem;
    text-decoration: none;
    color: var(--text-secondary);
}

.act-btn-pro:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.act-btn-pro.view:hover { background: #4338ca; color: #fff; }
.act-btn-pro.edit:hover { background: #3b82f6; color: #fff; }
.act-btn-pro.toggle:hover { background: #f59e0b; color: #fff; }
.act-btn-pro.feature:hover { background: #8b5cf6; color: #fff; }
.act-btn-pro.podcast:hover { background: var(--podcast-color); color: #fff; }
.act-btn-pro.duplicate:hover { background: #10b981; color: #fff; }
.act-btn-pro.danger:hover { background: #dc2626; color: #fff; }

/* ===== GRID VIEW ===== */
.grid-container-pro {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.25rem;
    padding: 1.5rem;
}

.grid-item-pro {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: all 0.3s;
    position: relative;
    display: flex;
    flex-direction: column;
}

.grid-item-pro::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--cc, var(--primary));
    z-index: 2;
}

.grid-item-pro:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-lg);
    border-color: var(--cc, var(--primary));
}

.grid-item-pro.selected { border-color: var(--primary); background: var(--accent-soft); }

.grid-item-pro.featured-card {
    border-color: var(--featured-color);
    box-shadow: 0 0 0 1px var(--featured-color);
}

.grid-check-pro {
    position: absolute;
    top: 0.75rem;
    left: 0.75rem;
    z-index: 3;
}

.grid-check-pro input {
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: var(--primary);
}

.featured-badge-pro {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #92400e;
    padding: 0.25rem 0.65rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 800;
    z-index: 3;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    box-shadow: 0 2px 6px rgba(245, 158, 11, 0.3);
}

[data-theme="dark"] .featured-badge-pro,
[data-theme="midnight"] .featured-badge-pro {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: #78350f;
}

.grid-image-pro {
    height: 170px;
    overflow: hidden;
    background: var(--bg-tertiary);
    cursor: pointer;
    position: relative;
}

.grid-image-pro img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s;
}

.grid-item-pro:hover .grid-image-pro img { transform: scale(1.05); }

.image-placeholder-pro {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    background: var(--accent-gradient);
    color: #fff;
}

.image-overlay-pro {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
    color: #fff;
    font-size: 2rem;
}

.grid-image-pro:hover .image-overlay-pro { opacity: 1; }

.grid-dur {
    position: absolute;
    bottom: 0.5rem;
    right: 0.5rem;
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
    background: rgba(0, 0, 0, 0.8);
    color: #fff;
    font-size: 0.7rem;
    font-weight: 700;
}

.grid-content-pro {
    padding: 1.25rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.grid-badges-pro {
    display: flex;
    gap: 0.4rem;
    margin-bottom: 0.65rem;
    flex-wrap: wrap;
}

.grid-content-pro h3 {
    font-size: 1rem;
    margin: 0.5rem 0;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    font-family: 'Georgia', serif;
    font-weight: 700;
    color: var(--text-primary);
}

.grid-content-pro p {
    font-size: 0.82rem;
    color: var(--text-muted);
    line-height: 1.5;
    margin-bottom: 1rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    flex: 1;
}

.grid-meta-pro {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.78rem;
    color: var(--text-muted);
    padding-top: 0.75rem;
    border-top: 1px dashed var(--border);
}

.meta-item { display: flex; align-items: center; gap: 0.3rem; }

.meta-item strong {
    color: var(--primary);
    font-weight: 800;
}

[data-theme="dark"] .meta-item strong,
[data-theme="midnight"] .meta-item strong {
    color: var(--primary-light);
}

.grid-actions-pro {
    display: flex;
    border-top: 1px solid var(--border);
    background: var(--bg-secondary);
}

.grid-action-btn {
    flex: 1;
    padding: 0.7rem;
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.2s;
    border-right: 1px solid var(--border);
    color: var(--text-secondary);
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
}

.grid-action-btn:last-child { border-right: none; }
.grid-action-btn:hover { background: var(--bg-primary); }

.grid-action-btn.danger:hover { background: #fee2e2; color: #dc2626; }
[data-theme="dark"] .grid-action-btn.danger:hover,
[data-theme="midnight"] .grid-action-btn.danger:hover {
    background: rgba(220, 38, 38, 0.2);
    color: #fca5a5;
}

.grid-action-btn.feature:hover { background: #f5f3ff; color: #8b5cf6; }
[data-theme="dark"] .grid-action-btn.feature:hover,
[data-theme="midnight"] .grid-action-btn.feature:hover {
    background: rgba(139, 92, 246, 0.2);
    color: #c4b5fd;
}

.grid-action-btn.edit:hover { background: #dbeafe; color: #2563eb; }
[data-theme="dark"] .grid-action-btn.edit:hover,
[data-theme="midnight"] .grid-action-btn.edit:hover {
    background: rgba(59, 130, 246, 0.2);
    color: #93c5fd;
}

.grid-action-btn.podcast:hover { background: var(--podcast-bg); color: var(--podcast-color); }

.select-all-grid {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.88rem;
    padding: 0.5rem 0.75rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    width: fit-content;
    border: 1px solid var(--border);
    color: var(--text-primary);
}

.select-all-grid input {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--primary);
}

/* ===== TIMELINE ===== */
.timeline-view { padding: 1.5rem; }
.timeline-group { margin-bottom: 2rem; }

.timeline-group-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--border);
    position: sticky;
    top: 0;
    background: var(--bg-primary);
    z-index: 5;
    padding-top: 0.5rem;
}

.timeline-month {
    font-family: 'Georgia', serif;
    font-size: 1.5rem;
    font-weight: 900;
    color: var(--primary);
    letter-spacing: -0.02em;
}

[data-theme="dark"] .timeline-month,
[data-theme="midnight"] .timeline-month {
    color: var(--primary-light);
}

.timeline-count {
    background: var(--accent-gradient);
    color: #fff;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
}

.timeline-items {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 0.85rem;
}

.timeline-item {
    display: flex;
    gap: 0.85rem;
    padding: 0.85rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
    transition: all 0.2s;
    cursor: pointer;
    align-items: center;
}

.timeline-item:hover {
    background: var(--bg-tertiary);
    transform: translateX(3px);
    border-color: var(--primary);
}

.timeline-date-box {
    width: 55px;
    text-align: center;
    background: var(--accent-gradient);
    color: #fff;
    border-radius: 8px;
    padding: 0.5rem 0.25rem;
    flex-shrink: 0;
}

.timeline-date-box.featured {
    background: linear-gradient(135deg, var(--featured-color), #d97706);
}

.timeline-day {
    font-size: 1.3rem;
    font-weight: 900;
    line-height: 1;
    font-family: 'Georgia', serif;
}

.timeline-month-small {
    font-size: 0.65rem;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.05em;
}

.timeline-item-info { flex: 1; min-width: 0; }

.timeline-item-name {
    font-weight: 700;
    font-size: 0.88rem;
    margin-bottom: 0.15rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-family: 'Georgia', serif;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    color: var(--text-primary);
}

.timeline-item-role {
    font-size: 0.72rem;
    color: var(--text-muted);
}

/* ===== PAGINATION ===== */
.pagination-premium {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 2px solid var(--border);
    flex-wrap: wrap;
    gap: 1rem;
}

.pagination-info { font-size: 0.88rem; color: var(--text-muted); }
.pagination-info strong { color: var(--text-primary); font-weight: 700; }

.pagination-buttons {
    display: flex;
    gap: 0.4rem;
    align-items: center;
    flex-wrap: wrap;
}

.page-btn-pro {
    padding: 0.5rem 0.85rem;
    border: 2px solid var(--border);
    border-radius: 8px;
    background: var(--bg-primary);
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
    text-decoration: none;
    color: var(--text-primary);
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.85rem;
    font-family: inherit;
}

.page-btn-pro:hover:not(.current) {
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-2px);
}

.page-btn-pro.current {
    background: var(--accent-gradient);
    color: #fff;
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(10, 104, 71, 0.25);
}

.page-dots { padding: 0 0.4rem; color: var(--text-muted); }

/* ===== EMPTY STATE ===== */
.empty-state-premium {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-xl);
    border: 2px dashed var(--border);
}

.empty-animation {
    position: relative;
    width: 140px;
    height: 140px;
    margin: 0 auto 1.5rem;
}

.empty-circle-pro {
    position: absolute;
    inset: 0;
    background: var(--accent-gradient);
    border-radius: 50%;
    animation: emptyPulse 3s ease-in-out infinite;
    opacity: 0.15;
}

@keyframes emptyPulse {
    0%, 100% { transform: scale(1); opacity: 0.15; }
    50% { transform: scale(1.1); opacity: 0.05; }
}

.empty-icon-pro {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    animation: emptyFloat 3s ease-in-out infinite;
}

@keyframes emptyFloat {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}

.empty-state-premium h3 {
    font-size: 1.35rem;
    margin-bottom: 0.5rem;
    font-family: 'Georgia', serif;
    font-weight: 700;
    color: var(--text-primary);
}

.empty-state-premium p {
    color: var(--text-muted);
    max-width: 400px;
    margin: 0 auto 1.5rem;
    line-height: 1.6;
}

.empty-actions-pro {
    display: flex;
    gap: 0.75rem;
    justify-content: center;
    flex-wrap: wrap;
}

/* ===== MODALS ===== */
.modal-overlay-premium {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(10px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    padding: 1.5rem;
}

.modal-overlay-premium.open {
    display: flex;
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background: var(--bg-primary);
    border-radius: var(--radius-xl);
    width: 100%;
    max-width: 760px;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
    border: 1px solid var(--border);
}

@keyframes slideUp {
    from { transform: translateY(20px) scale(0.95); opacity: 0; }
    to { transform: translateY(0) scale(1); opacity: 1; }
}

.modal-header-news {
    padding: 1.5rem 2rem;
    background: var(--hero-gradient);
    color: #fff;
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
    position: relative;
    overflow: hidden;
}

.modal-header-news::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--video-accent), var(--video-accent-light), var(--video-accent));
}

.modal-close-premium {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.2);
    border: none;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1.25rem;
    color: #fff;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
}

.modal-close-premium:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

.modal-title {
    font-family: 'Georgia', serif;
    font-size: 1.4rem;
    font-weight: 800;
    line-height: 1.3;
    position: relative;
    z-index: 1;
    letter-spacing: -0.02em;
}

.modal-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.82rem;
    opacity: 0.95;
    flex-wrap: wrap;
    margin-top: 0.5rem;
    position: relative;
    z-index: 1;
}

.modal-meta-item {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.modal-body { padding: 1.5rem 2rem 2rem; }

.vm-player {
    position: relative;
    width: 100%;
    aspect-ratio: 16/9;
    background: #000;
    border-radius: var(--radius-md);
    overflow: hidden;
    margin-bottom: 1.25rem;
}

.vm-player iframe,
.vm-player video {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    border: 0;
}

.vm-desc {
    font-size: 0.92rem;
    line-height: 1.7;
    color: var(--text-secondary);
    margin-bottom: 1.25rem;
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    border-left: 4px solid var(--primary);
}

.vm-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
    margin-bottom: 1.25rem;
}

.vm-stat {
    padding: 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    text-align: center;
}

.vm-stat-val {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--primary);
    line-height: 1;
    margin-bottom: 0.25rem;
    font-family: 'Georgia', serif;
}

[data-theme="dark"] .vm-stat-val,
[data-theme="midnight"] .vm-stat-val {
    color: var(--primary-light);
    filter: brightness(1.2);
}

.vm-stat-lbl {
    font-size: 0.72rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
}

.modal-actions-list {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

/* Confirm Modal */
.confirm-modal-premium {
    background: var(--bg-primary);
    border-radius: var(--radius-xl);
    padding: 2rem;
    max-width: 420px;
    width: 100%;
    text-align: center;
    animation: zoomIn 0.3s;
    border: 1px solid var(--border);
}

@keyframes zoomIn {
    from { transform: scale(0.9); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

.confirm-icon-premium {
    font-size: 3rem;
    margin-bottom: 1rem;
    animation: bounce 1s;
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.confirm-modal-premium h3 {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
    font-family: 'Georgia', serif;
    color: var(--text-primary);
}

.confirm-modal-premium p {
    color: var(--text-muted);
    margin-bottom: 1.5rem;
    line-height: 1.6;
}

.confirm-actions-premium {
    display: flex;
    gap: 0.75rem;
    justify-content: center;
}

mark {
    background: #fef08a;
    padding: 0 0.15rem;
    border-radius: 2px;
    font-weight: 700;
}

[data-theme="dark"] mark,
[data-theme="midnight"] mark {
    background: #fde047;
    color: #713f12;
}

/* ===== KATEGORI MODAL ===== */
.cat-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.cat-field { margin-bottom: 1rem; }
.cat-field.full { grid-column: 1 / -1; }

.cat-field label {
    display: block;
    font-size: 0.82rem;
    font-weight: 700;
    margin-bottom: 0.4rem;
    color: var(--text-primary);
}

.cat-field input,
.cat-field select,
.cat-field textarea {
    width: 100%;
    padding: 0.7rem 0.9rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-family: inherit;
    font-size: 0.9rem;
    transition: all 0.2s;
}

.cat-field input:focus,
.cat-field select:focus,
.cat-field textarea:focus {
    outline: none;
    border-color: var(--primary);
    background: var(--bg-primary);
}

.cat-field textarea {
    min-height: 80px;
    resize: vertical;
}

.cat-icon-row {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.cat-icon-row input {
    max-width: 90px;
    text-align: center;
    font-size: 1.4rem;
}

.emoji-quick {
    display: flex;
    gap: 0.3rem;
    flex-wrap: wrap;
}

.emoji-quick button {
    width: 36px;
    height: 36px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--bg-primary);
    cursor: pointer;
    font-size: 1.1rem;
    transition: all 0.15s;
}

.emoji-quick button:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
}

.cat-actions {
    display: flex;
    gap: 0.6rem;
    justify-content: flex-end;
    margin-top: 1rem;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 1024px) {
    .chart-section { grid-template-columns: 1fr; }
    .video-stats-grid { grid-template-columns: repeat(2, 1fr); }
    .vm-stats { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
    .video-hero-content { flex-direction: column; text-align: center; }
    .engagement-ring { margin: 0 auto; }
    .hero-stats { justify-content: center; }
    .card-header-pro { flex-direction: column; align-items: flex-start; }
    .header-actions-pro { width: 100%; }
    .btn-action-pro { flex: 1; justify-content: center; }
    .advanced-search-form { flex-direction: column; }
    .search-group { min-width: 100%; }
    .filter-group { width: 100%; }
    .filter-select-pro, .date-filter-pro, .btn-reset-filter { flex: 1; min-width: 0; }
    .grid-container-pro { grid-template-columns: 1fr; padding: 1rem; }
    .video-table-premium { min-width: 980px; }
    .timeline-items { grid-template-columns: 1fr; }
    .cat-form-grid { grid-template-columns: 1fr; }
}

@media (max-width: 640px) {
    .video-stats-grid { grid-template-columns: 1fr; }
    .stat-number-ultimate { font-size: 1.85rem; }
    .bulk-bar-pro { flex-direction: column; align-items: stretch; }
    .bulk-actions-pro, .bulk-form-inline { flex-direction: column; }
    .bulk-btn-pro { width: 100%; }
    .category-chips-pro { padding: 0.5rem; }
    .chip-pro { font-size: 0.72rem; padding: 0.35rem 0.7rem; }
}

@media print {
    .advanced-filter-bar, .bulk-bar-pro, .action-buttons-pro, .chart-section,
    .chart-section-3, .video-stats-grid, .video-hero, .category-chips-pro,
    .modal-overlay-premium, .pagination-premium, .header-actions-pro,
    .video-tabs, .quick-filter-pills {
        display: none !important;
    }
}
</style>

<!-- ===== HERO ===== -->
<div class="video-hero" data-aos="fade-down">
  <div class="video-hero-content">
    <div>
      <h2>🎬 Newsroom Video &amp; Podcast</h2>
      <p>Kelola konten multimedia, sorotan, dan kategori. Pantau tayangan, durasi total, dan performa kanal visual FKIP UNIMOF.</p>
      <div class="hero-stats">
        <div class="hero-stat"><span class="hero-stat-num"><?= $stat_published+$stat_draft+$stat_archived ?></span><span class="hero-stat-label">Total</span></div>
        <div class="hero-stat"><span class="hero-stat-num"><?= number_format($stat_views) ?></span><span class="hero-stat-label">Views</span></div>
        <div class="hero-stat"><span class="hero-stat-num"><?= vid_fmt_dur($stat_dur) ?: '0:00' ?></span><span class="hero-stat-label">Durasi</span></div>
        <div class="hero-stat"><span class="hero-stat-num"><?= $avg_views ?></span><span class="hero-stat-label">Avg/Video</span></div>
      </div>
    </div>
    <div class="engagement-ring" title="Skor Engagement">
      <svg viewBox="0 0 36 36">
        <circle cx="18" cy="18" r="15.915" class="ring-bg"/>
        <circle cx="18" cy="18" r="15.915" class="ring-fill" style="stroke-dasharray:<?= $engagement ?>,100"/>
      </svg>
      <div class="engagement-value">
        <div class="score-num"><?= $engagement ?></div>
        <div class="score-label">Engage</div>
      </div>
    </div>
  </div>
</div>

<!-- ===== TABS ===== -->
<div class="video-tabs" data-aos="fade-up">
  <a href="?tab=videos" class="<?= $tab==='videos'?'on':'' ?>">🎬 Video &amp; Podcast <span style="color:var(--text-muted);font-weight:600">(<?= $stat_published+$stat_draft+$stat_archived ?>)</span></a>
  <a href="?tab=categories" class="<?= $tab==='categories'?'on':'' ?>">📁 Kategori <span style="color:var(--text-muted);font-weight:600">(<?= count($categories) ?>)</span></a>
</div>

<?php if($tab==='videos'): ?>
<!-- ===== STATS ===== -->
<div class="video-stats-grid" data-aos="fade-up">
  <!-- 🆕 FIX: Icon box now has emoji -->
  <div class="stat-card-ultimate" style="--sc:#10b981">
    <div class="stat-header"><div class="stat-icon-box">📺</div></div>
    <div class="stat-number-ultimate count-up" data-target="<?= $stat_published ?>">0</div>
    <div class="stat-label-ultimate">Published</div>
    <div class="stat-trend-ultimate up">+<?= $stat_week ?> minggu ini</div>
  </div>
  <div class="stat-card-ultimate" style="--sc:#f59e0b">
    <div class="stat-header"><div class="stat-icon-box">📝</div></div>
    <div class="stat-number-ultimate count-up" data-target="<?= $stat_draft ?>">0</div>
    <div class="stat-label-ultimate">Draft</div>
    <div class="stat-trend-ultimate neutral">⏳ Perlu review</div>
  </div>
  <div class="stat-card-ultimate" style="--sc:#3b82f6">
    <div class="stat-header"><div class="stat-icon-box">👁️</div></div>
    <div class="stat-number-ultimate count-up" data-target="<?= $stat_views ?>">0</div>
    <div class="stat-label-ultimate">Total Views</div>
    <div class="stat-trend-ultimate up">📊 Avg <?= $avg_views ?>/video</div>
  </div>
  <div class="stat-card-ultimate" style="--sc:#8b5cf6">
    <div class="stat-header"><div class="stat-icon-box">⭐</div></div>
    <div class="stat-number-ultimate count-up" data-target="<?= $stat_featured ?>">0</div>
    <div class="stat-label-ultimate">Sorotan</div>
    <div class="stat-trend-ultimate neutral">🌟 Highlight</div>
  </div>
  <div class="stat-card-ultimate" style="--sc:#ec4899">
    <div class="stat-header"><div class="stat-icon-box">🎙️</div></div>
    <div class="stat-number-ultimate count-up" data-target="<?= $stat_podcast ?>">0</div>
    <div class="stat-label-ultimate">Podcast</div>
    <div class="stat-trend-ultimate neutral">🔊 Audio</div>
  </div>
  <div class="stat-card-ultimate" style="--sc:#06b6d4">
    <div class="stat-header"><div class="stat-icon-box">🎬</div></div>
    <div class="stat-number-ultimate count-up" data-target="<?= $stat_video ?>">0</div>
    <div class="stat-label-ultimate">Video</div>
    <div class="stat-trend-ultimate neutral">📹 Visual</div>
  </div>
</div>

<!-- ===== CHARTS ===== -->
<?php if(!empty($cat_stats)||!empty($monthly)): ?>
<div class="chart-section" data-aos="fade-up">
  <div class="chart-card"><h3>📊 Tren Publikasi (6 Bulan)</h3><div id="trendChart"></div></div>
  <div class="chart-card"><h3>🏆 Top Kategori (by Views)</h3>
    <?php if(empty($top_kat)): ?><div style="text-align:center;padding:2rem;color:var(--text-muted)"><div style="font-size:2.5rem;opacity:.4;margin-bottom:.5rem">📁</div><div>Belum ada data</div></div>
    <?php else: $mx=max(array_column($top_kat,'views')); ?><div class="top-list"><?php foreach($top_kat as $nm=>$d): ?><div class="top-item"><div class="top-avatar">📁</div><div class="top-info"><div class="top-name"><?= sanitize($nm) ?></div><div class="top-bar"><div class="top-bar-fill" style="width:<?= $mx>0?($d['views']/$mx)*100:0 ?>%"></div></div></div><div class="top-count"><?= number_format($d['views']) ?></div></div><?php endforeach; ?></div><?php endif; ?>
  </div>
</div>
<div class="chart-section-3" data-aos="fade-up"><div class="chart-card"><h3>📁 Distribusi Kategori (Published)</h3><div id="categoryChart"></div></div></div>
<?php endif; ?>

<!-- ===== QUICK FILTER ===== -->
<div class="quick-filter-pills" data-aos="fade-up">
  <a href="video.php?tab=videos" class="pill <?= empty($status_filter)&&empty($type_filter)?'active':'' ?>">📺 Semua <span class="pill-count"><?= $stat_published+$stat_draft+$stat_archived ?></span></a>
  <a href="video.php?tab=videos&status=Published" class="pill <?= $status_filter==='Published'?'active':'' ?>">🟢 Published <span class="pill-count"><?= $stat_published ?></span></a>
  <a href="video.php?tab=videos&status=Draft" class="pill <?= $status_filter==='Draft'?'active':'' ?>">📝 Draft <span class="pill-count"><?= $stat_draft ?></span></a>
  <a href="video.php?tab=videos&tipe=video" class="pill <?= $type_filter==='video'?'active':'' ?>">🎬 Video <span class="pill-count"><?= $stat_video ?></span></a>
  <a href="video.php?tab=videos&tipe=podcast" class="pill <?= $type_filter==='podcast'?'active':'' ?>">🎙️ Podcast <span class="pill-count"><?= $stat_podcast ?></span></a>
  <div style="flex:1"></div>
  <a href="video.php?tab=videos&featured=1" class="pill <?= $featured_filter===1?'active':'' ?>">⭐ Sorotan <span class="pill-count"><?= $stat_featured ?></span></a>
</div>

<div style="background:var(--bg-primary);border:1px solid var(--border);border-radius:var(--radius-xl);padding:1.5rem;box-shadow:var(--shadow-sm)">
  <div class="card-header-pro">
    <div class="header-left"><h2>🎬 Daftar Video <span class="count-badge-pro"><?= $total ?></span></h2><p style="color:var(--text-muted);font-size:.82rem;margin-top:.25rem">Kelola konten video &amp; podcast FKIP UNIMOF</p></div>
    <div class="header-actions-pro">
      <div class="export-dropdown"><button class="btn-action-pro" onclick="toggleExportMenu(event)"><span>📥</span><span>Export</span><span>▾</span></button>
        <div class="export-menu" id="exportMenu">
          <a href="?<?= http_build_query(array_merge($_GET,['export'=>'csv'])) ?>" class="export-item"><span class="export-item-icon">📊</span><div><div style="font-weight:600">Export CSV</div><div style="font-size:.72rem;color:var(--text-muted)">Excel/Spreadsheet</div></div></a>
          <a href="?<?= http_build_query(array_merge($_GET,['export'=>'json'])) ?>" class="export-item"><span class="export-item-icon">🔧</span><div><div style="font-weight:600">Export JSON</div><div style="font-size:.72rem;color:var(--text-muted)">Integrasi API</div></div></a>
          <a href="#" onclick="window.print();return false" class="export-item"><span class="export-item-icon">🖨️</span><div><div style="font-weight:600">Print PDF</div><div style="font-size:.72rem;color:var(--text-muted)">Cetak laporan</div></div></a>
        </div>
      </div>
      <a href="video-form.php" class="btn-action-pro primary"><span>➕</span><span>Tambah Video</span></a>
    </div>
  </div>

  <!-- ADVANCED FILTER -->
  <div class="advanced-filter-bar">
    <form method="GET" class="advanced-search-form" id="searchForm">
      <input type="hidden" name="tab" value="videos">
      <div class="search-group"><div class="search-input-wrap"><span class="search-icon-pro">🔍</span>
        <input type="text" name="q" class="search-input-pro" placeholder="Cari judul, deskripsi, slug, tag..." value="<?= sanitize($q) ?>" autocomplete="off">
        <?php if($q!==''): ?><button type="button" class="search-clear-pro" onclick="clearSearch()">✕</button><?php endif; ?><span class="search-shortcut">/</span>
      </div></div>
      <div class="filter-group">
        <select name="kategori" class="filter-select-pro" onchange="this.form.submit()"><option value="">📁 Semua Kategori</option><?php foreach($categories_aktif as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $kat_filter===(int)$c['id']?'selected':'' ?>><?= sanitize(($c['icon']??'📁').' '.$c['nama']) ?></option><?php endforeach; ?></select>
        <select name="status" class="filter-select-pro" onchange="this.form.submit()"><option value="">📊 Semua Status</option><option value="Published" <?= $status_filter==='Published'?'selected':'' ?>>🟢 Published</option><option value="Draft" <?= $status_filter==='Draft'?'selected':'' ?>>📝 Draft</option><option value="Archived" <?= $status_filter==='Archived'?'selected':'' ?>>📦 Archived</option></select>
        <select name="tipe" class="filter-select-pro" onchange="this.form.submit()"><option value="">🎞️ Semua Tipe</option><option value="video" <?= $type_filter==='video'?'selected':'' ?>>🎬 Video</option><option value="podcast" <?= $type_filter==='podcast'?'selected':'' ?>>🎙️ Podcast</option></select>
        <input type="date" name="tanggal" class="date-filter-pro" value="<?= sanitize($date_filter) ?>" onchange="this.form.submit()" title="Filter tanggal">
        <button type="button" class="btn-reset-filter" onclick="resetFilters()" title="Reset">🔄 Reset</button>
      </div>
      <input type="hidden" name="view" value="<?= sanitize($view_mode) ?>">
    </form>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-top:.75rem">
      <div class="view-toggle-pro">
        <a href="?<?= http_build_query(array_merge($_GET,['view'=>'table'])) ?>" class="view-btn-pro <?= $view_mode==='table'?'active':'' ?>">📋 <span>Tabel</span></a>
        <a href="?<?= http_build_query(array_merge($_GET,['view'=>'grid'])) ?>" class="view-btn-pro <?= $view_mode==='grid'?'active':'' ?>">🎴 <span>Grid</span></a>
        <a href="?<?= http_build_query(array_merge($_GET,['view'=>'timeline'])) ?>" class="view-btn-pro <?= $view_mode==='timeline'?'active':'' ?>">📅 <span>Timeline</span></a>
      </div>
      <div style="display:flex;gap:.5rem;align-items:center;font-size:.78rem;color:var(--text-muted)"><span>Sort:</span>
        <select onchange="sortTable(this.value)" style="padding:.4rem .75rem;border:1px solid var(--border);border-radius:6px;font-size:.78rem;background:var(--bg-primary);cursor:pointer;color:var(--text-primary)">
          <option value="created_at-desc" <?= $sort_by==='created_at'&&$sort_dir==='DESC'?'selected':'' ?>>Terbaru</option>
          <option value="created_at-asc" <?= $sort_by==='created_at'&&$sort_dir==='ASC'?'selected':'' ?>>Terlama</option>
          <option value="views-desc" <?= $sort_by==='views'&&$sort_dir==='DESC'?'selected':'' ?>>Most Viewed</option>
          <option value="duration_seconds-desc" <?= $sort_by==='duration_seconds'&&$sort_dir==='DESC'?'selected':'' ?>>Terpanjang</option>
          <option value="judul-asc" <?= $sort_by==='judul'&&$sort_dir==='ASC'?'selected':'' ?>>Judul A-Z</option>
        </select>
      </div>
    </div>
  </div>

  <!-- CATEGORY CHIPS -->
  <div class="category-chips-pro"><div class="category-chips-label">📁 Kategori:</div>
    <a class="chip-pro <?= $kat_filter===0?'active':'' ?>" href="?<?= http_build_query(array_merge($_GET,['kategori'=>'','halaman'=>1])) ?>">Semua</a>
    <?php foreach($categories_aktif as $c): ?>
      <a class="chip-pro <?= $kat_filter===(int)$c['id']?'active':'' ?>" href="?<?= http_build_query(array_merge($_GET,['kategori'=>(int)$c['id'],'halaman'=>1])) ?>"><?= sanitize($c['icon']??'📁') ?> <?= sanitize($c['nama']) ?> <span class="chip-count-pro"><?= (int)$c['vcount'] ?></span></a>
    <?php endforeach; ?>
  </div>

  <!-- BULK BAR -->
  <div class="bulk-bar-pro" id="bulkBar">
    <div class="bulk-info-pro"><span class="bulk-count" id="bulkCount">0</span><span>video dipilih</span></div>
    <form method="POST" class="bulk-form-inline" id="bulkForm">
      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>"><input type="hidden" name="_tab" value="videos">
      <div class="bulk-actions-pro">
        <button type="submit" name="action" value="bulk_publish" class="bulk-btn-pro publish" onclick="return confirm('Publikasikan terpilih?')">📤 Publish</button>
        <button type="submit" name="action" value="bulk_draft" class="bulk-btn-pro draft" onclick="return confirm('Ubah ke draft?')">📝 Draft</button>
        <button type="submit" name="action" value="bulk_archive" class="bulk-btn-pro archive" onclick="return confirm('Arsipkan terpilih?')">📦 Archive</button>
        <button type="submit" name="action" value="bulk_feature" class="bulk-btn-pro feature" onclick="return confirm('Tandai sorotan?')">⭐ Sorotan</button>
        <!-- 🆕 FIX: Bulk toggle podcast -->
        <button type="submit" name="action" value="bulk_toggle_podcast" class="bulk-btn-pro podcast" onclick="return confirm('Toggle tipe konten (Video ↔ Podcast) untuk video terpilih?')">🎙️ Toggle Podcast</button>
        <button type="submit" name="action" value="bulk_delete" class="bulk-btn-pro delete" onclick="return confirm('HAPUS PERMANEN terpilih?')">🗑️ Hapus</button>
      </div>
    </form>
    <button class="bulk-btn-pro cancel" onclick="clearSelection()">Batal</button>
  </div>

  <?php if(empty($videos)): ?>
  <div class="empty-state-premium"><div class="empty-animation"><div class="empty-circle-pro"></div><div class="empty-icon-pro">🎬</div></div>
    <h3><?= ($q||$kat_filter||$status_filter||$type_filter||$date_filter)?'Tidak ada hasil untuk filter ini':'Belum ada video' ?></h3>
    <p><?= ($q||$kat_filter||$status_filter||$type_filter||$date_filter)?'Coba ubah kata kunci atau filter.':'Mulai unggah video/podcast pertama untuk kanal multimedia FKIP UNIMOF.' ?></p>
    <div class="empty-actions-pro"><?php if($q||$kat_filter||$status_filter||$type_filter||$date_filter): ?><a href="video.php?tab=videos" class="btn-action-pro">🔄 Reset Filter</a><?php endif; ?><a href="video-form.php" class="btn-action-pro primary">➕ Buat Video Pertama</a></div>
  </div>

  <?php elseif($view_mode==='grid'): ?>
  <div style="padding:1.5rem"><label class="select-all-grid"><input type="checkbox" id="selectAllGrid" onchange="toggleSelectAll('grid')"><span>Pilih Semua (<?= count($videos) ?>)</span></label>
    <div class="grid-container-pro" style="padding:0"><?php $cols=['#10b981','#dc2626','#3b82f6','#8b5cf6','#f59e0b','#ec4899']; foreach($videos as $i=>$v): $col=$cols[$i%count($cols)]; $th=vid_thumb($v); $dur=vid_dur_label($v); $emb=vid_embed($v['video_url']??'',$v['video_type']??'youtube'); ?>
      <div class="grid-item-pro <?= $v['is_featured']?'featured-card':'' ?>" style="--cc:<?= $col ?>" data-id="<?= $v['id'] ?>">
        <div class="grid-check-pro"><input type="checkbox" class="row-check" value="<?= $v['id'] ?>" form="bulkForm" name="ids[]" onchange="updateBulkBar()"></div>
        <?php if($v['is_featured']): ?><span class="featured-badge-pro">⭐ Sorotan</span><?php endif; ?>
        <div class="grid-image-pro" onclick="showDetail(<?= $v['id'] ?>)">
          <?php if($th): ?><img src="<?= htmlspecialchars($th,ENT_QUOTES) ?>" alt="<?= sanitize($v['judul']) ?>" loading="lazy"><?php else: ?><div class="image-placeholder-pro"><?= $v['is_podcast']?'🎙️':'🎬' ?></div><?php endif; ?><div class="image-overlay-pro">▶</div>
          <?php if($dur): ?><span class="grid-dur"><?= sanitize($dur) ?></span><?php endif; ?>
        </div>
        <div class="grid-content-pro"><div class="grid-badges-pro"><span class="badge-pro badge-<?= strtolower($v['status']) ?>"><?= $v['status'] ?></span><span class="badge-pro badge-<?= $v['is_podcast']?'podcast':'video' ?>"><?= $v['is_podcast']?'🎙️ Podcast':'🎬 Video' ?></span><span class="badge-pro badge-category"><?= sanitize(($v['kat_icon']??'📁').' '.($v['kat_nama']??'Umum')) ?></span></div>
          <h3><?= sanitize($v['judul']) ?></h3><p><?= excerpt($v['deskripsi']?:'',100) ?></p>
          <div class="grid-meta-pro"><span class="meta-item">👁️ <strong><?= number_format($v['views']) ?></strong></span><span class="meta-item">📅 <?= format_tanggal_singkat($v['published_at']?:$v['created_at']) ?></span></div>
        </div>
        <div class="grid-actions-pro">
          <button class="grid-action-btn" onclick="showDetail(<?= $v['id'] ?>)" title="Preview">👁️</button>
          <a href="video-form.php?id=<?= $v['id'] ?>" class="grid-action-btn edit" title="Edit">✏️</a>
          <button class="grid-action-btn" onclick="quickAction(<?= $v['id'] ?>,'toggle')" title="Toggle"><?= $v['status']==='Published'?'📤':'📥' ?></button>
          <button class="grid-action-btn feature" onclick="quickAction(<?= $v['id'] ?>,'toggle_featured')" title="Sorotan"><?= $v['is_featured']?'⭐':'☆' ?></button>
          <!-- 🆕 FIX: Emoji podcast button now has proper icon -->
          <button class="grid-action-btn podcast" onclick="quickAction(<?= $v['id'] ?>,'toggle_podcast')" title="Video↔Podcast"><?= $v['is_podcast']?'🎬':'🎙️' ?></button>
          <button class="grid-action-btn danger" onclick="confirmDelete(<?= $v['id'] ?>,<?= json_encode(sanitize($v['judul']),JSON_UNESCAPED_UNICODE) ?>)" title="Hapus">🗑️</button>
        </div>
      </div>
    <?php endforeach; ?></div>
  </div>

  <?php elseif($view_mode==='timeline'): ?>
  <div class="timeline-view"><?php $grp=[]; foreach($videos as $v){ $lb=date('F Y',strtotime($v['created_at'])); $grp[$lb][]=$v; } foreach($grp as $m=>$its): ?>
    <div class="timeline-group"><div class="timeline-group-header"><span style="font-size:1.5rem">📅</span><span class="timeline-month"><?= $m ?></span><span class="timeline-count"><?= count($its) ?> video</span></div>
      <div class="timeline-items"><?php foreach($its as $v): ?>
        <div class="timeline-item" onclick="showDetail(<?= $v['id'] ?>)"><div class="timeline-date-box <?= $v['is_featured']?'featured':'' ?>"><div class="timeline-day"><?= date('d',strtotime($v['created_at'])) ?></div><div class="timeline-month-small"><?= date('M',strtotime($v['created_at'])) ?></div></div>
          <div class="timeline-item-info"><div class="timeline-item-name"><?php if($v['is_featured']): ?><span style="color:var(--featured-color)">⭐</span><?php endif; ?><?= sanitize($v['judul']) ?></div><div class="timeline-item-role"><?= sanitize(($v['kat_nama']??'Umum')) ?> • <?= $v['is_podcast']?'🎙️':'🎬' ?> • <?= $v['status'] ?> • 👁️ <?= number_format($v['views']) ?></div></div>
        </div>
      <?php endforeach; ?></div>
    </div>
  <?php endforeach; ?></div>

  <?php else: ?>
  <div class="table-wrapper-pro"><table class="video-table-premium" id="videoTable"><thead><tr>
    <th style="width:40px"><input type="checkbox" id="selectAll" onchange="toggleSelectAll('table')"></th>
    <th style="width:100px">Thumbnail</th><th data-sortable="judul">Judul &amp; Info <span class="sort-icon">↕</span></th>
    <th data-sortable="is_podcast" style="width:90px">Tipe <span class="sort-icon">↕</span></th>
    <th style="width:130px">Kategori</th>
    <th data-sortable="status" style="width:100px">Status <span class="sort-icon">↕</span></th>
    <th data-sortable="views" style="width:80px">Views <span class="sort-icon">↕</span></th>
    <th data-sortable="duration_seconds" style="width:80px">Durasi <span class="sort-icon">↕</span></th>
    <th data-sortable="created_at" style="width:110px">Tanggal <span class="sort-icon">↕</span></th>
    <th style="width:230px;text-align:right">Aksi</th>
  </tr></thead><tbody>
    <?php foreach($videos as $v): $th=vid_thumb($v); $dur=vid_dur_label($v); ?>
    <tr data-id="<?= $v['id'] ?>" class="<?= $v['is_featured']?'featured-row':'' ?>">
      <td><input type="checkbox" class="row-check" value="<?= $v['id'] ?>" form="bulkForm" name="ids[]" onchange="updateBulkBar()"></td>
      <td><div class="table-thumb-pro" onclick="showDetail(<?= $v['id'] ?>)"><?php if($th): ?><img src="<?= htmlspecialchars($th,ENT_QUOTES) ?>" alt="" loading="lazy"><?php else: ?><div class="thumb-placeholder-pro"><?= $v['is_podcast']?'🎙️':'🎬' ?></div><?php endif; ?><div class="thumb-play">▶</div></div></td>
      <td><div class="title-cell-pro"><strong><?php if($v['is_featured']): ?><span class="featured-icon-pro" title="Sorotan">⭐</span><?php endif; ?><?= sanitize($v['judul']) ?></strong><small><?= sanitize($v['slug']) ?></small><div class="author-line">🔗 <?= sanitize($v['video_type']??'youtube') ?><?= !empty($v['prodi_singkatan'])?' • '.sanitize($v['prodi_singkatan']):'' ?></div></div></td>
      <td><span class="badge-pro badge-<?= $v['is_podcast']?'podcast':'video' ?>"><?= $v['is_podcast']?'🎙️':'🎬' ?></span></td>
      <td><span class="badge-pro badge-category"><?= sanitize(($v['kat_icon']??'📁').' '.($v['kat_nama']??'—')) ?></span></td>
      <td><span class="badge-pro badge-<?= strtolower($v['status']) ?>"><?= $v['status'] ?></span></td>
      <td><div class="views-cell-pro"><strong><?= number_format($v['views']) ?></strong><span class="views-label">views</span></div></td>
      <td><div class="views-cell-pro"><strong style="font-size:.9rem"><?= $dur!==''?sanitize($dur):'—' ?></strong></div></td>
      <td><div class="date-cell-pro"><div><?= date('d M Y',strtotime($v['created_at'])) ?></div><small><?= date('H:i',strtotime($v['created_at'])) ?> WITA</small></div></td>
      <td><div class="action-buttons-pro">
        <button class="act-btn-pro view" onclick="showDetail(<?= $v['id'] ?>)" title="Preview">👁️</button>
        <a href="video-form.php?id=<?= $v['id'] ?>" class="act-btn-pro edit" title="Edit">✏️</a>
        <button class="act-btn-pro toggle" onclick="quickAction(<?= $v['id'] ?>,'toggle')" title="Toggle"><?= $v['status']==='Published'?'📤':'📥' ?></button>
        <button class="act-btn-pro feature" onclick="quickAction(<?= $v['id'] ?>,'toggle_featured')" title="Sorotan"><?= $v['is_featured']?'⭐':'☆' ?></button>
        <!-- 🆕 FIX: Emoji podcast button now has proper icon -->
        <button class="act-btn-pro podcast" onclick="quickAction(<?= $v['id'] ?>,'toggle_podcast')" title="Video↔Podcast"><?= $v['is_podcast']?'🎬':'🎙️' ?></button>
        <button class="act-btn-pro duplicate" onclick="quickAction(<?= $v['id'] ?>,'duplicate')" title="Duplikat">📋</button>
        <button class="act-btn-pro danger" onclick="confirmDelete(<?= $v['id'] ?>,<?= json_encode(sanitize($v['judul']),JSON_UNESCAPED_UNICODE) ?>)" title="Hapus">🗑️</button>
      </div></td>
    </tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>

  <?php if($total_pages>1): $bq=$_GET; ?>
  <nav class="pagination-premium"><div class="pagination-info">Menampilkan <strong><?= min($offset+1,$total) ?>-<?= min($offset+$per_page,$total) ?></strong> dari <strong><?= $total ?></strong> video</div>
    <div class="pagination-buttons">
      <?php if($halaman>1): ?><a href="?<?= http_build_query(array_merge($bq,['halaman'=>$halaman-1])) ?>" class="page-btn-pro">← Prev</a><?php endif;
      for($i=1;$i<=$total_pages;$i++){ if($i===1||$i===$total_pages||($i>=$halaman-2&&$i<=$halaman+2)){ echo $i===$halaman?"<span class='page-btn-pro current'>$i</span>":"<a href='?".http_build_query(array_merge($bq,['halaman'=>$i]))."' class='page-btn-pro'>$i</a>"; } elseif($i===$halaman-3||$i===$halaman+3){ echo "<span class='page-dots'>…</span>"; } }
      if($halaman<$total_pages): ?><a href="?<?= http_build_query(array_merge($bq,['halaman'=>$halaman+1])) ?>" class="page-btn-pro">Next →</a><?php endif; ?>
    </div>
  </nav>
  <?php endif; ?>
</div>

<?php else: /* ===== TAB KATEGORI ===== */ ?>
<div style="background:var(--bg-primary);border:1px solid var(--border);border-radius:var(--radius-xl);padding:1.5rem;box-shadow:var(--shadow-sm)">
  <div class="card-header-pro">
    <div class="header-left"><h2>📁 Kategori Video <span class="count-badge-pro"><?= count($categories) ?></span></h2><p style="color:var(--text-muted);font-size:.82rem;margin-top:.25rem">Kelola pengelompokan konten video &amp; podcast</p></div>
    <div class="header-actions-pro"><button class="btn-action-pro primary" onclick="openCatModal(null)"><span>➕</span><span>Tambah Kategori</span></button></div>
  </div>

  <?php if(empty($categories)): ?>
  <div class="empty-state-premium"><div class="empty-animation"><div class="empty-circle-pro"></div><div class="empty-icon-pro">📁</div></div>
    <h3>Belum ada kategori</h3><p>Buat kategori untuk mengelompokkan video (mis. Kuliah Umum, Tutorial, Podcast).</p>
    <div class="empty-actions-pro"><button class="btn-action-pro primary" onclick="openCatModal(null)">➕ Buat Kategori Pertama</button></div>
  </div>
  <?php else: ?>
  <div class="table-wrapper-pro"><table class="video-table-premium"><thead><tr>
    <th style="width:60px">Ikon</th><th>Nama &amp; Slug</th><th style="width:90px">Urutan</th><th style="width:120px">Status</th><th style="width:90px">Video</th><th style="width:140px;text-align:right">Aksi</th>
  </tr></thead><tbody>
    <?php foreach($categories as $c): ?>
    <tr data-catid="<?= (int)$c['id'] ?>">
      <td style="text-align:center;font-size:1.6rem"><?= sanitize($c['icon']?:'📁') ?></td>
      <td><div class="title-cell-pro"><strong><?= sanitize($c['nama']) ?></strong><small><?= sanitize($c['slug']) ?></small><?php if(!empty($c['deskripsi'])): ?><div class="author-line">📄 <?= excerpt($c['deskripsi'],60) ?></div><?php endif; ?></div></td>
      <td><div class="views-cell-pro"><strong><?= (int)$c['urutan'] ?></strong></div></td>
      <td><span class="badge-pro <?= $c['status']==='Aktif'?'badge-published':'badge-archived' ?>"><?= $c['status'] ?></span></td>
      <td><div class="views-cell-pro"><strong><?= (int)$c['vcount'] ?></strong><span class="views-label">video</span></div></td>
      <td><div class="action-buttons-pro">
        <button class="act-btn-pro edit" onclick="openCatModal(<?= (int)$c['id'] ?>)" title="Edit">✏️</button>
        <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>"><input type="hidden" name="action" value="toggle_kategori"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="_tab" value="categories">
          <!-- 🆕 FIX: Toggle button now has proper eye emoji -->
          <button class="act-btn-pro toggle" title="Toggle Status"><?= $c['status']==='Aktif'?'👁️':'🙈' ?></button>
        </form>
        <form method="POST" style="display:inline" onsubmit="return confirm('Hapus kategori «<?= sanitize(addslashes($c['nama'])) ?>»?')"><input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>"><input type="hidden" name="action" value="delete_kategori"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="_tab" value="categories"><button class="act-btn-pro danger" title="Hapus">🗑️</button></form>
      </div></td>
    </tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ===== DETAIL MODAL ===== -->
<div class="modal-overlay-premium" id="detailModal" onclick="if(event.target===this)closeDetailModal()"><div class="modal-content"><button class="modal-close-premium" onclick="closeDetailModal()">✕</button>
  <div class="modal-header-news"><h2 class="modal-title" id="detailTitle">-</h2><div class="modal-meta"><span class="modal-meta-item" id="detailCat">📁 -</span><span class="modal-meta-item" id="detailType">-</span><span class="modal-meta-item" id="detailDate">📅 -</span><span class="modal-meta-item" id="detailStatus">-</span></div></div>
  <div class="modal-body">
    <div class="vm-player" id="detailPlayer"></div>
    <div class="vm-desc" id="detailDesc">-</div>
    <div class="vm-stats" id="detailStats"></div>
    <div class="modal-actions-list" id="detailActions"></div>
  </div>
</div></div>

<!-- ===== MODAL KATEGORI ===== -->
<div class="modal-overlay-premium" id="catModal" onclick="if(event.target===this)closeCatModal()"><div class="modal-content" style="max-width:640px"><button class="modal-close-premium" onclick="closeCatModal()">✕</button>
  <div class="modal-header-news"><h2 class="modal-title" id="catModalTitle">➕ Kategori Baru</h2></div>
  <div class="modal-body">
    <form method="POST" id="catForm">
      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
      <input type="hidden" name="action" value="save_kategori">
      <input type="hidden" name="_tab" value="categories">
      <input type="hidden" name="kategori_id" id="catId" value="0">
      <div class="cat-form-grid">
        <div class="cat-field full"><label>Nama Kategori *</label><input type="text" name="nama" id="catNama" required maxlength="100" placeholder="mis. Kuliah Umum"></div>
        <div class="cat-field"><label>Ikon (emoji)</label><div class="cat-icon-row"><input type="text" name="icon" id="catIcon" maxlength="4" value="📁"><div class="emoji-quick"><?php foreach(['🎓','📖','🎙️','🎉','📢','🔬','🎬','🎵','⭐','🏆'] as $em): ?><button type="button" onclick="document.getElementById('catIcon').value='<?= $em ?>'"><?= $em ?></button><?php endforeach; ?></div></div></div>
        <div class="cat-field"><label>Urutan</label><input type="number" name="urutan" id="catUrutan" min="0" max="999" value="0"></div>
        <div class="cat-field full"><label>Slug (opsional)</label><input type="text" name="slug" id="catSlug" maxlength="100" placeholder="kosongkan = otomatis dari nama"></div>
        <div class="cat-field full"><label>Deskripsi</label><textarea name="deskripsi" id="catDesk" maxlength="500" placeholder="Deskripsi singkat kategori..."></textarea></div>
        <div class="cat-field full"><label>Status</label><select name="status" id="catStatus"><option value="Aktif">🟢 Aktif</option><option value="Non-Aktif">⚪ Non-Aktif</option></select></div>
      </div>
      <div class="cat-actions"><button type="button" class="btn-action-pro" onclick="closeCatModal()">Batal</button><button type="submit" class="btn-action-pro primary">💾 Simpan Kategori</button></div>
    </form>
  </div>
</div></div>

<!-- ===== CONFIRM MODAL ===== -->
<div class="modal-overlay-premium" id="confirmModal" onclick="if(event.target===this)closeConfirm()"><div class="confirm-modal-premium"><div class="confirm-icon-premium" id="confirmIcon">⚠️</div><h3 id="confirmTitle">Konfirmasi</h3><p id="confirmMessage">Apakah Anda yakin?</p><div class="confirm-actions-premium"><button class="btn-action-pro" onclick="closeConfirm()">Batal</button><button class="btn-action-pro" id="confirmOk" style="background:var(--video-accent);color:#fff;border-color:var(--video-accent)">Ya, Lanjutkan</button></div></div></div>

<!-- ===== HIDDEN FORMS ===== -->
<form id="quickActionForm" method="POST" style="display:none"><input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>"><input type="hidden" name="_tab" value="videos"><input type="hidden" name="id" id="qaId"><input type="hidden" name="action" id="qaAction"></form>
<form id="deleteForm" method="POST" style="display:none"><input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>"><input type="hidden" name="_tab" value="videos"><input type="hidden" name="id" id="delId"><input type="hidden" name="action" value="delete"></form>

<script>
const videoData = <?= json_encode($video_rows, JSON_UNESCAPED_UNICODE) ?>;
const catData = <?= json_encode(array_map(fn($c)=>['id'=>(int)$c['id'],'nama'=>$c['nama'],'slug'=>$c['slug'],'icon'=>$c['icon'],'deskripsi'=>$c['deskripsi'],'urutan'=>(int)$c['urutan'],'status'=>$c['status']], $categories), JSON_UNESCAPED_UNICODE) ?>;

function animateCount(el){const t=parseInt(el.dataset.target)||0,d=1800,s=performance.now();(function n(now){const p=Math.min((now-s)/d,1),e=1-Math.pow(1-p,3);el.textContent=Math.floor(e*t).toLocaleString('id-ID');if(p<1)requestAnimationFrame(n);})(s);}
const cObs=new IntersectionObserver(es=>es.forEach(x=>{if(x.isIntersecting){animateCount(x.target);cObs.unobserve(x.target);}}),{threshold:.3});
document.querySelectorAll('.count-up').forEach(el=>cObs.observe(el));

<?php if(!empty($monthly)): ?>new ApexCharts(document.querySelector("#trendChart"),{series:[{name:'Video',data:<?= json_encode(array_values($monthly)) ?>}],chart:{type:'area',height:260,toolbar:{show:false}},colors:['#dc2626'],fill:{type:'gradient',gradient:{shadeIntensity:1,opacityFrom:.5,opacityTo:.1}},stroke:{curve:'smooth',width:3},xaxis:{categories:<?= json_encode(array_keys($monthly)) ?>,labels:{style:{fontSize:'10px'}}},yaxis:{labels:{style:{fontSize:'11px'}}},dataLabels:{enabled:false},tooltip:{y:{formatter:v=>v+' video'}}}).render();<?php endif; ?>
<?php if(!empty($cat_stats)): ?>new ApexCharts(document.querySelector("#categoryChart"),{series:<?= json_encode(array_column($cat_stats,'count')) ?>,labels:<?= json_encode(array_keys($cat_stats)) ?>,chart:{type:'donut',height:300},colors:['#1e293b','#dc2626','#10b981','#3b82f6','#8b5cf6','#f59e0b','#ec4899'],plotOptions:{pie:{donut:{size:'70%',labels:{show:true,total:{show:true,label:'Total',formatter:()=><?= array_sum(array_column($cat_stats,'count')) ?>}}}}},dataLabels:{enabled:true,style:{fontSize:'11px',fontWeight:700}},legend:{position:'bottom',fontSize:'11px'},stroke:{show:true,colors:['var(--bg-primary)'],width:3}}).render();<?php endif; ?>

let sT;document.querySelector('.search-input-pro')?.addEventListener('input',function(){clearTimeout(sT);const v=this.value;sT=setTimeout(()=>{const u=new URL(window.location);v?u.searchParams.set('q',v):u.searchParams.delete('q');u.searchParams.set('halaman','1');window.location=u;},500);});
function clearSearch(){const u=new URL(window.location);u.searchParams.delete('q');u.searchParams.set('halaman','1');window.location=u;}
function resetFilters(){if(confirm('Reset semua filter?'))window.location='video.php?tab=videos';}
function sortTable(v){const[s,d]=v.split('-');const u=new URL(window.location);u.searchParams.set('sort',s);u.searchParams.set('dir',d);window.location=u;}
function toggleExportMenu(e){e.stopPropagation();document.getElementById('exportMenu').classList.toggle('show');}
document.addEventListener('click',e=>{if(!e.target.closest('.export-dropdown'))document.getElementById('exportMenu')?.classList.remove('show');});

function toggleSelectAll(t){const m=t==='grid'?document.getElementById('selectAllGrid'):document.getElementById('selectAll');document.querySelectorAll('.row-check').forEach(cb=>{cb.checked=m.checked;cb.closest('[data-id]')?.classList.toggle('selected',m.checked);});updateBulkBar();}
document.querySelectorAll('.row-check').forEach(cb=>cb.addEventListener('change',function(){this.closest('[data-id]')?.classList.toggle('selected',this.checked);updateBulkBar();}));
function updateBulkBar(){const c=document.querySelectorAll('.row-check:checked').length;const el=document.getElementById('bulkCount');if(el)el.textContent=c;document.getElementById('bulkBar')?.classList.toggle('show',c>0);const all=document.querySelectorAll('.row-check'),ck=all.length>0&&c===all.length;const sa=document.getElementById('selectAll');if(sa)sa.checked=ck;const sg=document.getElementById('selectAllGrid');if(sg)sg.checked=ck;}
function clearSelection(){document.querySelectorAll('.row-check').forEach(cb=>cb.checked=false);document.querySelectorAll('[data-id]').forEach(r=>r.classList.remove('selected'));const sa=document.getElementById('selectAll');if(sa)sa.checked=false;const sg=document.getElementById('selectAllGrid');if(sg)sg.checked=false;updateBulkBar();}

function showDetail(id){const d=videoData.find(v=>v.id==id);if(!d)return;
  document.getElementById('detailTitle').textContent=d.judul;
  document.getElementById('detailCat').textContent='📁 '+d.kat;
  document.getElementById('detailType').innerHTML=d.is_podcast?'<span class="badge-pro badge-podcast">🎙️ Podcast</span>':'<span class="badge-pro badge-video">🎬 Video</span>';
  document.getElementById('detailDate').textContent='📅 '+formatDate(d.tanggal);
  document.getElementById('detailStatus').innerHTML=`<span class="badge-pro badge-${d.status.toLowerCase()}">${d.status}</span>`;
  const pl=document.getElementById('detailPlayer');
  if(d.kind==='iframe'&&d.embed)pl.innerHTML=`<iframe src="${d.embed}" title="${d.judul.replace(/"/g,'')}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`;
  else if(d.kind==='video'&&d.embed)pl.innerHTML=`<video src="${d.embed}" controls autoplay playsinline></video>`;
  else pl.innerHTML=`<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#fff">⚠️ Sumber tidak dapat diputar di sini.<br><a href="${d.url}" target="_blank" style="color:#c084fc">Buka asli ↗</a></div>`;
  document.getElementById('detailDesc').textContent=d.deskripsi||'Tidak ada deskripsi.';
  document.getElementById('detailStats').innerHTML=`<div class="vm-stat"><div class="vm-stat-val">${(d.views||0).toLocaleString('id-ID')}</div><div class="vm-stat-lbl">Views</div></div><div class="vm-stat"><div class="vm-stat-val">${d.dur||'—'}</div><div class="vm-stat-lbl">Durasi</div></div><div class="vm-stat"><div class="vm-stat-val">${d.type||'—'}</div><div class="vm-stat-lbl">Sumber</div></div>`;
  document.getElementById('detailActions').innerHTML=`<a href="video-form.php?id=${d.id}" class="btn-action-pro primary" style="justify-content:flex-start">✏️ Edit Video</a><button onclick="quickAction(${d.id},'toggle');closeDetailModal()" class="btn-action-pro" style="justify-content:flex-start">🔄 Toggle Status (${d.status})</button><button onclick="quickAction(${d.id},'toggle_featured');closeDetailModal()" class="btn-action-pro" style="justify-content:flex-start">⭐ ${d.is_featured?'Unfeature':'Feature'}</button><button onclick="quickAction(${d.id},'toggle_podcast');closeDetailModal()" class="btn-action-pro" style="justify-content:flex-start">${d.is_podcast?'🎬 Ubah ke Video':'🎙️ Ubah ke Podcast'}</button><a href="${d.url}" target="_blank" class="btn-action-pro" style="justify-content:flex-start">↗ Buka Sumber Asli</a>`;
  document.getElementById('detailModal').classList.add('open');document.body.style.overflow='hidden';}
function closeDetailModal(){document.getElementById('detailModal').classList.remove('open');document.getElementById('detailPlayer').innerHTML='';document.body.style.overflow='';}

function openCatModal(id){const f=document.getElementById('catForm');f.reset();document.getElementById('catId').value=0;document.getElementById('catIcon').value='📁';document.getElementById('catUrutan').value=0;document.getElementById('catStatus').value='Aktif';
  if(id){const c=catData.find(x=>x.id==id);if(c){document.getElementById('catModalTitle').textContent='✏️ Edit: '+c.nama;document.getElementById('catId').value=c.id;document.getElementById('catNama').value=c.nama;document.getElementById('catIcon').value=c.icon||'📁';document.getElementById('catSlug').value=c.slug||'';document.getElementById('catDesk').value=c.deskripsi||'';document.getElementById('catUrutan').value=c.urutan;document.getElementById('catStatus').value=c.status;} }
  else document.getElementById('catModalTitle').textContent='➕ Kategori Baru';
  document.getElementById('catModal').classList.add('open');document.body.style.overflow='hidden';setTimeout(()=>document.getElementById('catNama').focus(),100);}
function closeCatModal(){document.getElementById('catModal').classList.remove('open');document.body.style.overflow='';}

let cc=null;function showConfirm(t,m,i,cb){document.getElementById('confirmTitle').textContent=t;document.getElementById('confirmMessage').textContent=m;document.getElementById('confirmIcon').textContent=i;cc=cb;document.getElementById('confirmModal').classList.add('open');}
function closeConfirm(){document.getElementById('confirmModal').classList.remove('open');cc=null;}
document.getElementById('confirmOk').addEventListener('click',()=>{if(cc)cc();closeConfirm();});
function quickAction(id,a){const c={toggle:{t:'Ubah Status?',m:'Status video diubah (Published ↔ Draft).',i:'🔄'},duplicate:{t:'Duplikat Video?',m:'Salinan dibuat sebagai draft baru.',i:'📋'},toggle_featured:{t:'Toggle Sorotan?',m:'Status sorotan diubah.',i:'⭐'},toggle_podcast:{t:'Ubah Tipe Konten?',m:'Video ↔ Podcast akan ditukar.',i:'🎙️'}}[a];showConfirm(c.t,c.m,c.i,()=>{document.getElementById('qaId').value=id;document.getElementById('qaAction').value=a;document.getElementById('quickActionForm').submit();});}
function confirmDelete(id,t){showConfirm('Hapus Video Permanen?',`"${t}" dihapus permanen.`,'⚠️',()=>{document.getElementById('delId').value=id;document.getElementById('deleteForm').submit();});}
function formatDate(s){if(!s)return'-';const M=['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'],d=new Date(s);return`${d.getDate()} ${M[d.getMonth()]} ${d.getFullYear()} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`;}
function showToast(m,type='info'){if(window.AdminPanel?.Toast)window.AdminPanel.Toast.show(m,type);else console.log(`[${type}] ${m}`);}

document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeDetailModal();closeConfirm();closeCatModal();}if(e.key==='/'&&!e.ctrlKey&&!e.metaKey&&!e.altKey&&!['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)){e.preventDefault();document.querySelector('.search-input-pro')?.focus();}if((e.ctrlKey||e.metaKey)&&e.key==='n'){e.preventDefault();window.location.href='video-form.php';}if((e.ctrlKey||e.metaKey)&&e.key==='e'){e.preventDefault();window.location=location.pathname+'?tab=videos&export=csv';}});
function hlSearch(){const q=<?= json_encode($q, JSON_UNESCAPED_UNICODE) ?>;if(!q)return;document.querySelectorAll('.title-cell-pro strong,.grid-content-pro h3,.timeline-item-name').forEach(el=>{if(!el.querySelector('mark')){el.innerHTML=el.innerHTML.replace(new RegExp('('+q.replace(/[.*+?${}()|[\]\\]/g,'\\$&')+')','gi'),'<mark>$1</mark>');}});}
hlSearch();
console.log('%c🎬 Newsroom Video & Podcast - DARK MODE READY','color:var(--primary);font-size:16px;font-weight:bold');
console.log('%cShortcuts: / (Cari), Ctrl+N (Tambah), Ctrl+E (Export CSV), Esc (Tutup)','color:#64748b');
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>