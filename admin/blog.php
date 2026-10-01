<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== HELPER FUNCTIONS =====
if (!function_exists('blog_thumb')) { function blog_thumb($f){ return $f ? asset('uploads/blog/'.basename($f)) : ''; } }
if (!function_exists('blog_del_img')) { function blog_del_img($f){ if($f){ $p=__DIR__.'/../../uploads/blog/'.basename($f); if(is_file($p)) @unlink($p);} } }
if (!function_exists('blog_rt')) { function blog_rt($r){ $v=(int)($r['reading_time']??0); if($v>0)return $v; return max(1,(int)ceil(str_word_count(strip_tags((string)($r['konten']??'')))/200)); } }

// ===== AUTO-UPDATE: arsip artikel lama (> 2 tahun) =====
try { $pdo->exec("UPDATE blog_artikel SET status='Archived' WHERE status='Published' AND created_at < DATE_SUB(NOW(), INTERVAL 2 YEAR)"); } catch (Exception $e) {}

$KATEGORI = ['AI & Teknologi','Tips Riset','Pendidikan','Pengabdian','Opini'];
$STATUS = ['Published','Draft','Archived'];

// ===== PROSES AKSI POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('error', '❌ Token keamanan tidak valid.');
    } else {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        $ids = array_map('intval', (array)($_POST['ids'] ?? []));
        $back = $_POST['_back'] ?? 'articles';

        if ($action === 'bulk_delete' && !empty($ids)) {
            $ph = implode(',', array_fill(0,count($ids),'?'));
            $s = $pdo->prepare("SELECT gambar FROM blog_artikel WHERE id IN ($ph)"); $s->execute($ids);
            foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $g) blog_del_img($g);
            $pdo->prepare("DELETE FROM blog_artikel WHERE id IN ($ph)")->execute($ids);
            flash_message('success', '✅ '.count($ids).' artikel dihapus.');
        }
        elseif ($action === 'bulk_publish' && !empty($ids)) {
            $ph = implode(',', array_fill(0,count($ids),'?'));
            $pdo->prepare("UPDATE blog_artikel SET status='Published', published_at=IFNULL(published_at,NOW()) WHERE id IN ($ph)")->execute($ids);
            flash_message('success', '✅ '.count($ids).' artikel dipublikasikan.');
        }
        elseif ($action === 'bulk_draft' && !empty($ids)) {
            $ph = implode(',', array_fill(0,count($ids),'?'));
            $pdo->prepare("UPDATE blog_artikel SET status='Draft' WHERE id IN ($ph)")->execute($ids);
            flash_message('success', '✅ '.count($ids).' artikel → draft.');
        }
        elseif ($action === 'bulk_archive' && !empty($ids)) {
            $ph = implode(',', array_fill(0,count($ids),'?'));
            $pdo->prepare("UPDATE blog_artikel SET status='Archived' WHERE id IN ($ph)")->execute($ids);
            flash_message('success', '✅ '.count($ids).' artikel diarsipkan.');
        }
        elseif ($action === 'bulk_category' && !empty($ids) && in_array($_POST['new_category']??'',$KATEGORI,true)) {
            $ph = implode(',', array_fill(0,count($ids),'?'));
            $pdo->prepare("UPDATE blog_artikel SET kategori=? WHERE id IN ($ph)")->execute(array_merge([$_POST['new_category']],$ids));
            flash_message('success', '✅ Kategori diubah untuk '.count($ids).' artikel.');
        }
        elseif ($action === 'bulk_feature' && !empty($ids)) {
            $ph = implode(',', array_fill(0,count($ids),'?'));
            $pdo->prepare("UPDATE blog_artikel SET is_featured=1 WHERE id IN ($ph)")->execute($ids);
            flash_message('success', '⭐ '.count($ids).' artikel ditandai sorotan.');
        }
        elseif ($action === 'delete' && $id) {
            $s = $pdo->prepare("SELECT gambar FROM blog_artikel WHERE id=?"); $s->execute([$id]); blog_del_img($s->fetchColumn());
            $pdo->prepare("DELETE FROM blog_artikel WHERE id=?")->execute([$id]);
            flash_message('success', '✅ Artikel dihapus.');
        }
        elseif ($action === 'toggle' && $id) {
            $pdo->prepare("UPDATE blog_artikel SET status=IF(status='Published','Draft','Published'), published_at=IF(status='Published',published_at,NOW()) WHERE id=?")->execute([$id]);
            flash_message('success', '✅ Status artikel diubah.');
        }
        elseif ($action === 'toggle_featured' && $id) {
            $pdo->prepare("UPDATE blog_artikel SET is_featured=IF(is_featured=1,0,1) WHERE id=?")->execute([$id]);
            flash_message('success', '⭐ Status sorotan diubah.');
        }
        elseif ($action === 'duplicate' && $id) {
            $s = $pdo->prepare("SELECT * FROM blog_artikel WHERE id=?"); $s->execute([$id]); $o = $s->fetch();
            if ($o) {
                $slug = substr($o['slug'],0,170).'-copy-'.time();
                $pdo->prepare("INSERT INTO blog_artikel (dosen_id,program_studi_id,judul,slug,excerpt,konten,gambar,kategori,tags,reading_time,is_featured,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?, 'Draft', NOW())")
                    ->execute([$o['dosen_id'],$o['program_studi_id'],$o['judul'].' (Copy)',$slug,$o['excerpt'],$o['konten'],$o['gambar'],$o['kategori'],$o['tags'],$o['reading_time'],0]);
                flash_message('success', '✅ Artikel diduplikasi sebagai draft.');
            }
        }
        elseif (in_array($action,['approve_comment','spam_comment','delete_comment'],true) && $id) {
            if ($action==='delete_comment') { $pdo->prepare("DELETE FROM blog_komentar WHERE id=?")->execute([$id]); flash_message('success','🗑️ Komentar dihapus.'); }
            else { $st = $action==='approve_comment'?'Approved':'Spam'; $pdo->prepare("UPDATE blog_komentar SET status=? WHERE id=?")->execute([$st,$id]); flash_message('success','✅ Komentar → '.$st.'.'); }
            $back='comments';
        }
        elseif (in_array($action,['bulk_approve','bulk_spam','bulk_delete_comment'],true) && !empty($ids)) {
            $ph = implode(',', array_fill(0,count($ids),'?'));
            if ($action==='bulk_delete_comment') { $pdo->prepare("DELETE FROM blog_komentar WHERE id IN ($ph)")->execute($ids); flash_message('success','🗑️ '.count($ids).' komentar dihapus.'); }
            else { $st=$action==='bulk_approve'?'Approved':'Spam'; $pdo->prepare("UPDATE blog_komentar SET status=? WHERE id IN ($ph)")->execute(array_merge([$st],$ids)); flash_message('success','✅ '.count($ids).' komentar → '.$st.'.'); }
            $back='comments';
        }
    }
    $qs = $_GET; $qs['tab']=$back; if(isset($qs['id']))unset($qs['id']);
    header('Location: blog.php'.(!empty($qs)?'?'.http_build_query($qs):'')); exit;
}

// ===== EXPORT =====
if (isset($_GET['export'])) {
    $fmt=$_GET['export'];
    $all=$pdo->query("SELECT b.*, d.nama AS dosen_nama FROM blog_artikel b LEFT JOIN dosen d ON b.dosen_id=d.id ORDER BY b.created_at DESC")->fetchAll();
    if ($fmt==='csv' && $all) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="blog-fkip-'.date('Y-m-d').'.csv"');
        $out=fopen('php://output','w'); fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out,['ID','Judul','Slug','Kategori','Status','Penulis','Views','Likes','Sorotan','Tanggal']);
        foreach($all as $r) fputcsv($out,[$r['id'],$r['judul'],$r['slug'],$r['kategori'],$r['status'],$r['dosen_nama']??'Redaksi',$r['views'],$r['likes'],$r['is_featured']?'Ya':'Tidak',$r['created_at']]);
        fclose($out); exit;
    }
    if ($fmt==='json' && $all) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="blog-fkip-'.date('Y-m-d').'.json"');
        echo json_encode(['exported_at'=>date('c'),'total'=>count($all),'data'=>$all],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE); exit;
    }
}

// ===== TAB & FILTER =====
$tab = in_array($_GET['tab']??'',['articles','comments'],true)?$_GET['tab']:'articles';
$q=trim($_GET['q']??''); $kat_filter=trim($_GET['kategori']??''); $status_filter=trim($_GET['status']??'');
$date_filter=trim($_GET['tanggal']??''); $author_filter=(int)($_GET['dosen']??0); $featured_filter=isset($_GET['featured'])?(int)$_GET['featured']:'';
$view_mode=$_GET['view']??'table'; $sort_by=$_GET['sort']??'created_at'; $sort_dir=$_GET['dir']??'desc';
$halaman=max(1,(int)($_GET['halaman']??1)); $per_page=$view_mode==='grid'?12:15; $offset=($halaman-1)*$per_page;

$where='WHERE 1=1'; $params=[];
if($q!==''){ $where.=' AND (b.judul LIKE ? OR b.konten LIKE ? OR b.slug LIKE ? OR b.tags LIKE ?)'; $l="%$q%"; $params[]=$l;$params[]=$l;$params[]=$l;$params[]=$l; }
if(in_array($kat_filter,$KATEGORI,true)){ $where.=' AND b.kategori=?'; $params[]=$kat_filter; }
if(in_array($status_filter,$STATUS,true)){ $where.=' AND b.status=?'; $params[]=$status_filter; }
if($date_filter!==''){ $where.=' AND DATE(b.created_at)=?'; $params[]=$date_filter; }
if($author_filter>0){ $where.=' AND b.dosen_id=?'; $params[]=$author_filter; }
if($featured_filter!==''){ $where.=' AND b.is_featured=?'; $params[]=$featured_filter; }
$valid_sorts=['created_at','judul','views','likes','kategori','status','published_at'];
$sort_by=in_array($sort_by,$valid_sorts)?$sort_by:'created_at';
$sort_dir=in_array(strtolower($sort_dir),['asc','desc'])?strtoupper($sort_dir):'DESC';

$total=0; $articles=[]; $total_pages=1;
if($tab==='articles'){
    $cs=$pdo->prepare("SELECT COUNT(*) FROM blog_artikel b $where"); $cs->execute($params); $total=(int)$cs->fetchColumn();
    $total_pages=max(1,(int)ceil($total/$per_page)); $halaman=min($halaman,$total_pages); $offset=($halaman-1)*$per_page;
    $st=$pdo->prepare("SELECT b.*, d.nama AS dosen_nama, d.gelar_depan, d.gelar_belakang, d.foto AS dosen_foto, p.singkatan AS prodi_singkatan
        FROM blog_artikel b LEFT JOIN dosen d ON b.dosen_id=d.id LEFT JOIN program_studi p ON b.program_studi_id=p.id
        $where ORDER BY b.$sort_by $sort_dir LIMIT ? OFFSET ?");
    $st->execute(array_merge($params,[$per_page,$offset])); $articles=$st->fetchAll();
}

// ===== STATISTIK =====
$stat_published=(int)$pdo->query("SELECT COUNT(*) FROM blog_artikel WHERE status='Published'")->fetchColumn();
$stat_draft=(int)$pdo->query("SELECT COUNT(*) FROM blog_artikel WHERE status='Draft'")->fetchColumn();
$stat_archived=(int)$pdo->query("SELECT COUNT(*) FROM blog_artikel WHERE status='Archived'")->fetchColumn();
$stat_views=(int)$pdo->query("SELECT COALESCE(SUM(views),0) FROM blog_artikel")->fetchColumn();
$stat_likes=(int)$pdo->query("SELECT COALESCE(SUM(likes),0) FROM blog_artikel")->fetchColumn();
$stat_featured=(int)$pdo->query("SELECT COUNT(*) FROM blog_artikel WHERE is_featured=1")->fetchColumn();
$stat_week=(int)$pdo->query("SELECT COUNT(*) FROM blog_artikel WHERE created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetchColumn();
$stat_pending=(int)$pdo->query("SELECT COUNT(*) FROM blog_komentar WHERE status='Pending'")->fetchColumn();
$stat_kom_total=(int)$pdo->query("SELECT COUNT(*) FROM blog_komentar")->fetchColumn();
$avg_views=$stat_published>0?round($stat_views/$stat_published):0;
$total_all=max(1,$stat_published+$stat_draft+$stat_archived);
$engagement=min(100,round((($stat_featured*10)+($stat_published*3)+min(100,($stat_views+$stat_likes)/100))/$total_all));

$cat_stats=[]; foreach($pdo->query("SELECT kategori,COUNT(*) t,SUM(views) v FROM blog_artikel WHERE status='Published' GROUP BY kategori ORDER BY t DESC")->fetchAll() as $r) $cat_stats[$r['kategori']]=['count'=>(int)$r['t'],'views'=>(int)$r['v']];
$author_stats=$pdo->query("SELECT d.nama, COUNT(*) t FROM blog_artikel b JOIN dosen d ON b.dosen_id=d.id WHERE b.status='Published' GROUP BY b.dosen_id,d.nama ORDER BY t DESC LIMIT 5")->fetchAll();
$monthly=[]; for($i=5;$i>=0;$i--){ $m=date('Y-m',strtotime("-$i months")); $lb=date('M Y',strtotime("-$i months")); $q2=$pdo->prepare("SELECT COUNT(*) FROM blog_artikel WHERE DATE_FORMAT(created_at,'%Y-%m')=?"); $q2->execute([$m]); $monthly[$lb]=(int)$q2->fetchColumn(); }
$unique_dosen=$pdo->query("SELECT DISTINCT b.dosen_id,d.nama FROM blog_artikel b JOIN dosen d ON b.dosen_id=d.id ORDER BY d.nama")->fetchAll();
$csrf=generate_csrf_token();

$comments=[]; $com_total=0; $com_pages=1; $com_page=max(1,(int)($_GET['cpage']??1)); $com_per=15;
if($tab==='comments'){
    $f_cs=in_array($_GET['f_cs']??'',['Pending','Approved','Spam','all'],true)?$_GET['f_cs']:'Pending'; $f_cq=trim($_GET['f_cq']??'');
    $cw='WHERE 1=1'; $cp=[];
    if($f_cs!=='all'){ $cw.=' AND k.status=?'; $cp[]=$f_cs; }
    if($f_cq!==''){ $cw.=' AND (k.nama LIKE ? OR k.komentar LIKE ? OR a.judul LIKE ?)'; $l="%$f_cq%"; $cp[]=$l;$cp[]=$l;$cp[]=$l; }
    $cc=$pdo->prepare("SELECT COUNT(*) FROM blog_komentar k LEFT JOIN blog_artikel a ON k.artikel_id=a.id $cw"); $cc->execute($cp); $com_total=(int)$cc->fetchColumn();
    $com_pages=max(1,(int)ceil($com_total/$com_per)); $com_page=min($com_page,$com_pages); $coff=($com_page-1)*$com_per;
    $cq=$pdo->prepare("SELECT k.*, a.judul AS aj, a.slug AS aslug FROM blog_komentar k LEFT JOIN blog_artikel a ON k.artikel_id=a.id $cw ORDER BY k.created_at DESC LIMIT ? OFFSET ?");
    $cq->execute(array_merge($cp,[$com_per,$coff])); $comments=$cq->fetchAll();
}

$active_menu='blog'; $page_heading='Ide & Wawasan';
$breadcrumbs=[['Dashboard','dashboard.php'],['Ide & Wawasan',null]];
require __DIR__ . '/includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ========================================
   BLOG.PHP - DARK MODE COMPATIBLE STYLES
   ======================================== */

/* ===== THEME VARIABLES (extend header vars) ===== */
:root {
    --blog-accent: #dc2626;
    --blog-accent-light: #ef4444;
    --blog-accent-dark: #991b1b;
    --hero-gradient: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 50%, var(--primary-dark) 100%);
    --accent-gradient: linear-gradient(135deg, var(--primary), var(--primary-light));
    --accent-soft: rgba(10, 104, 71, 0.1);
    --featured-color: #f59e0b;
    --featured-bg: rgba(245, 158, 11, 0.1);
}

[data-theme="dark"] {
    --blog-accent: #f87171;
    --blog-accent-light: #fca5a5;
    --blog-accent-dark: #ef4444;
    --hero-gradient: linear-gradient(135deg, #1e293b 0%, #334155 50%, #0f172a 100%);
    --accent-gradient: linear-gradient(135deg, var(--primary), var(--primary-light));
    --accent-soft: rgba(16, 163, 74, 0.15);
    --featured-color: #fbbf24;
    --featured-bg: rgba(251, 191, 36, 0.12);
}

[data-theme="midnight"] {
    --blog-accent: #f87171;
    --hero-gradient: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #020617 100%);
    --accent-gradient: linear-gradient(135deg, #6366f1, #8b5cf6);
    --accent-soft: rgba(99, 102, 241, 0.15);
}

[data-theme="emerald"] {
    --hero-gradient: linear-gradient(135deg, #059669 0%, #10b981 50%, #047857 100%);
}

/* ===== HERO SECTION ===== */
.blog-hero {
    background: var(--hero-gradient);
    color: #fff;
    padding: 2rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(10, 104, 71, 0.25);
    transition: all 0.3s ease;
}

[data-theme="dark"] .blog-hero,
[data-theme="midnight"] .blog-hero {
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
    border: 1px solid var(--border);
}

.blog-hero::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 5px;
    background: linear-gradient(90deg, var(--blog-accent), var(--blog-accent-light), var(--blog-accent));
}

.blog-hero::after {
    content: 'INSIGHT';
    position: absolute;
    top: 2rem; right: 2rem;
    font-family: 'Georgia', serif;
    font-size: 5rem;
    font-weight: 900;
    color: rgba(255, 255, 255, 0.05);
    letter-spacing: 0.2em;
    pointer-events: none;
}

.blog-hero-content {
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
    flex-wrap: wrap;
    z-index: 1;
}

.blog-hero h2 {
    font-family: 'Georgia', serif;
    font-size: 2rem;
    font-weight: 900;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.blog-hero p {
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

/* Engagement Ring */
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
.blog-tabs {
    display: flex;
    gap: 0.4rem;
    border-bottom: 2px solid var(--border);
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.blog-tabs a {
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

.blog-tabs a:hover { color: var(--text-primary); }

.blog-tabs a.on {
    color: var(--primary);
    border-bottom-color: var(--primary);
}

[data-theme="dark"] .blog-tabs a.on,
[data-theme="midnight"] .blog-tabs a.on {
    color: var(--primary-light);
    border-bottom-color: var(--primary-light);
}

.blog-tabs .tpill {
    background: var(--blog-accent);
    color: #fff;
    font-size: 0.66rem;
    font-weight: 800;
    padding: 0.1rem 0.5rem;
    border-radius: 99px;
    margin-left: 0.35rem;
}

/* ===== STATS GRID ===== */
.blog-stats-grid {
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

/* Top Authors */
.top-authors-list {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.top-author-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.75rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
    transition: all 0.2s;
}

.top-author-item:hover {
    background: var(--bg-tertiary);
    transform: translateX(3px);
    border-color: var(--primary);
}

.top-author-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--accent-gradient);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.85rem;
    flex-shrink: 0;
}

.top-author-info {
    flex: 1;
    min-width: 0;
}

.top-author-name {
    font-weight: 700;
    font-size: 0.88rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--text-primary);
}

.top-author-bar {
    height: 4px;
    background: var(--bg-tertiary);
    border-radius: 999px;
    margin-top: 0.3rem;
    overflow: hidden;
}

.top-author-bar-fill {
    height: 100%;
    background: var(--accent-gradient);
    border-radius: 999px;
    transition: width 1s ease;
}

.top-author-count {
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

.search-group {
    flex: 1;
    min-width: 280px;
    position: relative;
}

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
    background: var(--blog-accent);
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

/* Dark mode form inputs */
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
.bulk-btn-pro.delete { background: #dc2626; color: #fff; }
.bulk-btn-pro.cancel {
    background: transparent;
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.bulk-category-select {
    padding: 0.5rem 0.85rem;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    font-size: 0.82rem;
    cursor: pointer;
    background: rgba(255, 255, 255, 0.15);
    color: #fff;
    font-family: inherit;
}

.bulk-category-select option {
    background: var(--bg-primary);
    color: var(--text-primary);
}

/* ===== TABLE VIEW ===== */
.table-wrapper-pro {
    overflow-x: auto;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
    background: var(--bg-primary);
}

.blog-table-premium {
    width: 100%;
    border-collapse: collapse;
}

.blog-table-premium thead {
    background: var(--bg-secondary);
}

.blog-table-premium th {
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

.blog-table-premium th[data-sortable] {
    cursor: pointer;
    transition: color 0.2s;
}

.blog-table-premium th[data-sortable]:hover {
    color: var(--primary);
}

.blog-table-premium th .sort-icon {
    opacity: 0.3;
    margin-left: 0.3rem;
    font-size: 0.7rem;
}

.blog-table-premium td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
    transition: all 0.2s;
}

.blog-table-premium tbody tr { transition: all 0.2s; }

.blog-table-premium tbody tr:hover {
    background: var(--bg-secondary);
}

[data-theme="dark"] .blog-table-premium tbody tr:hover,
[data-theme="midnight"] .blog-table-premium tbody tr:hover {
    background: var(--bg-tertiary);
}

.blog-table-premium tbody tr.selected {
    background: var(--accent-soft);
}

.blog-table-premium tbody tr.featured-row {
    background: var(--featured-bg);
}

.row-checkbox {
    width: 18px;
    height: 18px;
    accent-color: var(--primary);
    cursor: pointer;
}

.table-thumb-pro {
    width: 70px;
    height: 50px;
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

/* Badges - THEME AWARE */
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

.badge-published {
    background: #dcfce7;
    color: #166534;
}

[data-theme="dark"] .badge-published,
[data-theme="midnight"] .badge-published {
    background: rgba(34, 197, 94, 0.2);
    color: #86efac;
}

.badge-draft {
    background: #fef3c7;
    color: #92400e;
}

[data-theme="dark"] .badge-draft,
[data-theme="midnight"] .badge-draft {
    background: rgba(245, 158, 11, 0.2);
    color: #fcd34d;
}

.badge-archived {
    background: #f3f4f6;
    color: #475569;
}

[data-theme="dark"] .badge-archived,
[data-theme="midnight"] .badge-archived {
    background: rgba(100, 116, 139, 0.3);
    color: #cbd5e1;
}

.badge-category {
    background: #dbeafe;
    color: #1e40af;
}

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

.views-cell-pro { text-align: center; }

.views-cell-pro strong {
    display: block;
    font-size: 1.1rem;
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
    font-size: 0.68rem;
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
    animation: starPulse 2s infinite;
}

[data-theme="dark"] .featured-badge-pro,
[data-theme="midnight"] .featured-badge-pro {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: #78350f;
}

@keyframes starPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.grid-image-pro {
    height: 180px;
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

/* ===== TIMELINE VIEW ===== */
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
    max-width: 800px;
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
    padding: 2rem;
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
    background: linear-gradient(90deg, var(--blog-accent), var(--blog-accent-light), var(--blog-accent));
}

.modal-close-premium {
    position: absolute;
    top: 1rem; right: 1rem;
    width: 40px; height: 40px;
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

.modal-category-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.8rem;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    margin-bottom: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    position: relative;
    z-index: 1;
}

.modal-title {
    font-family: 'Georgia', serif;
    font-size: 1.75rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
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
    position: relative;
    z-index: 1;
}

.modal-meta-item {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.modal-body { padding: 2rem; }

.detail-tabs {
    display: flex;
    gap: 0.25rem;
    border-bottom: 2px solid var(--border);
    margin-bottom: 1.5rem;
    overflow-x: auto;
    scrollbar-width: none;
}

.detail-tabs::-webkit-scrollbar { display: none; }

.detail-tab {
    padding: 0.7rem 1.1rem;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    color: var(--text-muted);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
    font-size: 0.88rem;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.detail-tab.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
}

.detail-tab:hover:not(.active) { color: var(--text-primary); }

.detail-tab-content { display: none; animation: fadeInTab 0.3s; }
.detail-tab-content.active { display: block; }

@keyframes fadeInTab {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}

.article-image-preview {
    width: 100%;
    height: 280px;
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-bottom: 1.5rem;
    background: var(--bg-tertiary);
    position: relative;
}

.article-image-preview img { width: 100%; height: 100%; object-fit: cover; }

.article-image-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    background: var(--accent-gradient);
    color: #fff;
}

.article-content-preview {
    font-size: 0.95rem;
    line-height: 1.7;
    color: var(--text-secondary);
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    border-left: 4px solid var(--primary);
    max-height: 300px;
    overflow-y: auto;
}

.article-content-preview p { margin-bottom: 0.75rem; }

.article-stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.article-stat-item {
    padding: 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    text-align: center;
    transition: all 0.2s;
}

.article-stat-item:hover {
    background: var(--bg-tertiary);
    transform: translateY(-2px);
}

.article-stat-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--primary);
    line-height: 1;
    margin-bottom: 0.25rem;
    font-family: 'Georgia', serif;
}

[data-theme="dark"] .article-stat-value,
[data-theme="midnight"] .article-stat-value {
    color: var(--primary-light);
    filter: brightness(1.2);
}

.article-stat-label {
    font-size: 0.72rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
}

.reading-time-box {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.75rem 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    margin-bottom: 1rem;
    font-size: 0.85rem;
    color: var(--text-primary);
}

.reading-time-box .icon { font-size: 1.25rem; }

.reading-time-box .value {
    font-weight: 700;
    color: var(--primary);
}

.modal-actions-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.social-share-preview {
    padding: 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    margin-top: 1rem;
}

.social-share-label {
    font-size: 0.72rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
    margin-bottom: 0.65rem;
}

.social-share-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.share-btn {
    padding: 0.5rem 0.85rem;
    border-radius: 8px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
    font-size: 0.82rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    text-decoration: none;
    color: #fff;
}

.share-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.share-btn.twitter { background: #1da1f2; }
.share-btn.facebook { background: #1877f2; }
.share-btn.whatsapp { background: #25d366; }
.share-btn.copy { background: #64748b; }

/* Image Modal */
.image-modal-premium {
    background: var(--bg-primary);
    border-radius: var(--radius-lg);
    max-width: 90vw;
    max-height: 90vh;
    overflow: hidden;
    position: relative;
    animation: zoomIn 0.3s;
    box-shadow: 0 30px 80px rgba(0, 0, 0, 0.4);
}

@keyframes zoomIn {
    from { transform: scale(0.9); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

.modal-header-premium {
    padding: 1rem 1.5rem;
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border);
    font-weight: 700;
    font-size: 1rem;
    max-width: 600px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--text-primary);
}

.modal-body-premium {
    max-height: 70vh;
    overflow: auto;
    background: var(--bg-tertiary);
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-body-premium img {
    max-width: 100%;
    max-height: 70vh;
    object-fit: contain;
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

/* ===== KOMENTAR SECTION ===== */
.cmt {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.1rem 1.25rem;
    margin-bottom: 0.85rem;
    display: flex;
    gap: 1rem;
    border-left: 4px solid var(--cm, #64748b);
}

.cmt.pending { --cm: #f59e0b; }
.cmt.approved { --cm: #10b981; }
.cmt.spam { --cm: #dc2626; opacity: 0.7; }

.cmt .av {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: var(--accent-gradient);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    flex-shrink: 0;
}

.cmt .bd { flex: 1; min-width: 0; }

.cmt .top {
    display: flex;
    gap: 0.6rem;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 0.35rem;
}

.cmt .nm { font-weight: 700; color: var(--text-primary); }
.cmt .em { font-size: 0.75rem; color: var(--text-muted); }
.cmt .dt { font-size: 0.72rem; color: var(--text-muted); margin-left: auto; }

.cmt .tx {
    color: var(--text-secondary);
    font-size: 0.9rem;
    line-height: 1.6;
    white-space: pre-wrap;
    word-break: break-word;
}

.cmt .on-art {
    font-size: 0.76rem;
    color: var(--primary);
    margin-top: 0.5rem;
    display: inline-flex;
    gap: 0.35rem;
    align-items: center;
    text-decoration: none;
}

.cmt .on-art:hover { text-decoration: underline; }

.cmt .acts {
    display: flex;
    gap: 0.4rem;
    margin-top: 0.7rem;
    flex-wrap: wrap;
}

.cmt-check { display: flex; align-items: flex-start; }

.cmt-check input {
    margin-top: 0.4rem;
    width: 18px;
    height: 18px;
    accent-color: var(--primary);
    cursor: pointer;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 1024px) {
    .chart-section { grid-template-columns: 1fr; }
    .blog-stats-grid { grid-template-columns: repeat(2, 1fr); }
    .article-stats-grid { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
    .blog-hero-content { flex-direction: column; text-align: center; }
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
    .blog-table-premium { min-width: 900px; }
    .timeline-items { grid-template-columns: 1fr; }
}

@media (max-width: 640px) {
    .blog-stats-grid { grid-template-columns: 1fr; }
    .stat-number-ultimate { font-size: 1.85rem; }
    .bulk-bar-pro { flex-direction: column; align-items: stretch; }
    .bulk-actions-pro, .bulk-form-inline { flex-direction: column; }
    .bulk-btn-pro, .bulk-category-select { width: 100%; }
    .category-chips-pro { padding: 0.5rem; }
    .chip-pro { font-size: 0.72rem; padding: 0.35rem 0.7rem; }
}

/* ===== PRINT ===== */
@media print {
    .advanced-filter-bar, .bulk-bar-pro, .action-buttons-pro, .chart-section,
    .blog-stats-grid, .blog-hero, .category-chips-pro, .modal-overlay-premium,
    .pagination-premium, .header-actions-pro, .blog-tabs {
        display: none !important;
    }
    .table-wrapper-pro { box-shadow: none; border: 1px solid #ddd; }
}
</style>

<!-- ===== HERO ===== -->
<div class="blog-hero" data-aos="fade-down">
  <div class="blog-hero-content">
    <div>
      <h2>💡 Newsroom Ide &amp; Wawasan</h2>
      <p>Kelola artikel wawasan dosen, pantau engagement pembaca, dan moderasi diskusi komentar dalam satu panel terpadu.</p>
      <div class="hero-stats">
        <div class="hero-stat"><span class="hero-stat-num"><?= $stat_published+$stat_draft+$stat_archived ?></span><span class="hero-stat-label">Total</span></div>
        <div class="hero-stat"><span class="hero-stat-num"><?= number_format($stat_views) ?></span><span class="hero-stat-label">Views</span></div>
        <div class="hero-stat"><span class="hero-stat-num"><?= number_format($stat_likes) ?></span><span class="hero-stat-label">Likes</span></div>
        <div class="hero-stat"><span class="hero-stat-num"><?= $avg_views ?></span><span class="hero-stat-label">Avg/Art</span></div>
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
<div class="blog-tabs" data-aos="fade-up">
  <a href="?tab=articles" class="<?= $tab==='articles'?'on':'' ?>">📝 Artikel <span style="color:var(--text-muted);font-weight:600">(<?= $stat_published+$stat_draft+$stat_archived ?>)</span></a>
  <a href="?tab=comments" class="<?= $tab==='comments'?'on':'' ?>">💬 Moderasi Komentar <span style="color:var(--text-muted);font-weight:600">(<?= $stat_kom_total ?>)</span> <?php if($stat_pending>0): ?><span class="tpill"><?= $stat_pending ?> baru</span><?php endif; ?></a>
</div>

<?php if($tab==='articles'): ?>
<!-- ===== STATS GRID ===== -->
<div class="blog-stats-grid" data-aos="fade-up">
  <div class="stat-card-ultimate" style="--sc:#10b981"><div class="stat-header"><div class="stat-icon-box">📄</div></div><div class="stat-number-ultimate count-up" data-target="<?= $stat_published ?>">0</div><div class="stat-label-ultimate">Published</div><div class="stat-trend-ultimate up">+<?= $stat_week ?> minggu ini</div></div>
  <div class="stat-card-ultimate" style="--sc:#f59e0b"><div class="stat-header"><div class="stat-icon-box">📝</div></div><div class="stat-number-ultimate count-up" data-target="<?= $stat_draft ?>">0</div><div class="stat-label-ultimate">Draft</div><div class="stat-trend-ultimate neutral">⏳ Perlu review</div></div>
  <div class="stat-card-ultimate" style="--sc:#3b82f6"><div class="stat-header"><div class="stat-icon-box">👁️</div></div><div class="stat-number-ultimate count-up" data-target="<?= $stat_views ?>">0</div><div class="stat-label-ultimate">Total Views</div><div class="stat-trend-ultimate up">📊 Avg <?= $avg_views ?>/art</div></div>
  <div class="stat-card-ultimate" style="--sc:#ec4899"><div class="stat-header"><div class="stat-icon-box">❤️</div></div><div class="stat-number-ultimate count-up" data-target="<?= $stat_likes ?>">0</div><div class="stat-label-ultimate">Total Likes</div><div class="stat-trend-ultimate neutral">💖 Engagement</div></div>
  <div class="stat-card-ultimate" style="--sc:#8b5cf6"><div class="stat-header"><div class="stat-icon-box">⭐</div></div><div class="stat-number-ultimate count-up" data-target="<?= $stat_featured ?>">0</div><div class="stat-label-ultimate">Sorotan</div><div class="stat-trend-ultimate neutral">🌟 Highlight</div></div>
  <div class="stat-card-ultimate" style="--sc:#64748b"><div class="stat-header"><div class="stat-icon-box">📦</div></div><div class="stat-number-ultimate count-up" data-target="<?= $stat_archived ?>">0</div><div class="stat-label-ultimate">Archived</div><div class="stat-trend-ultimate neutral">🗃️ Arsip</div></div>
</div>

<!-- ===== CHARTS ===== -->
<?php if(!empty($cat_stats)||!empty($monthly)): ?>
<div class="chart-section" data-aos="fade-up">
  <div class="chart-card"><h3>📊 Tren Publikasi (6 Bulan)</h3><div id="trendChart"></div></div>
  <div class="chart-card"><h3>🏆 Top Penulis (Dosen)</h3>
    <?php if(empty($author_stats)): ?><div style="text-align:center;padding:2rem;color:var(--text-muted)"><div style="font-size:2.5rem;opacity:.4;margin-bottom:.5rem">✍️</div><div>Belum ada data penulis</div></div>
    <?php else: $mx=max(array_column($author_stats,'t')); ?><div class="top-authors-list"><?php foreach($author_stats as $a): ?><div class="top-author-item"><div class="top-author-avatar"><?= strtoupper(substr($a['nama'],0,1)) ?></div><div class="top-author-info"><div class="top-author-name"><?= sanitize($a['nama']) ?></div><div class="top-author-bar"><div class="top-author-bar-fill" style="width:<?= ($a['t']/$mx)*100 ?>%"></div></div></div><div class="top-author-count"><?= $a['t'] ?></div></div><?php endforeach; ?></div><?php endif; ?>
  </div>
</div>
<div class="chart-section" data-aos="fade-up"><div class="chart-card" style="grid-column:1/-1"><h3>📁 Distribusi Kategori (Published)</h3><div id="categoryChart"></div></div></div>
<?php endif; ?>

<!-- ===== QUICK FILTER ===== -->
<div class="quick-filter-pills" data-aos="fade-up">
  <a href="blog.php?tab=articles" class="pill <?= empty($status_filter)&&empty($kat_filter)?'active':'' ?>">📄 Semua <span class="pill-count"><?= $stat_published+$stat_draft+$stat_archived ?></span></a>
  <a href="blog.php?tab=articles&status=Published" class="pill <?= $status_filter==='Published'?'active':'' ?>">🟢 Published <span class="pill-count"><?= $stat_published ?></span></a>
  <a href="blog.php?tab=articles&status=Draft" class="pill <?= $status_filter==='Draft'?'active':'' ?>">📝 Draft <span class="pill-count"><?= $stat_draft ?></span></a>
  <a href="blog.php?tab=articles&status=Archived" class="pill <?= $status_filter==='Archived'?'active':'' ?>">📦 Archived <span class="pill-count"><?= $stat_archived ?></span></a>
  <div style="flex:1"></div>
  <a href="blog.php?tab=articles&featured=1" class="pill <?= $featured_filter===1?'active':'' ?>">⭐ Sorotan <span class="pill-count"><?= $stat_featured ?></span></a>
</div>

<div style="background:var(--bg-primary);border:1px solid var(--border);border-radius:var(--radius-xl);padding:1.5rem;box-shadow:var(--shadow-sm)">
  <div class="card-header-pro">
    <div class="header-left"><h2>📝 Daftar Artikel <span class="count-badge-pro"><?= $total ?></span></h2><p style="color:var(--text-muted);font-size:.82rem;margin-top:.25rem">Kelola wawasan &amp; ide dari dosen FKIP UNIMOF</p></div>
    <div class="header-actions-pro">
      <div class="export-dropdown"><button class="btn-action-pro" onclick="toggleExportMenu(event)"><span>📥</span><span>Export</span><span>▾</span></button>
        <div class="export-menu" id="exportMenu">
          <a href="?<?= http_build_query(array_merge($_GET,['export'=>'csv'])) ?>" class="export-item"><span class="export-item-icon">📊</span><div><div style="font-weight:600">Export CSV</div><div style="font-size:.72rem;color:var(--text-muted)">Excel/Spreadsheet</div></div></a>
          <a href="?<?= http_build_query(array_merge($_GET,['export'=>'json'])) ?>" class="export-item"><span class="export-item-icon">🔧</span><div><div style="font-weight:600">Export JSON</div><div style="font-size:.72rem;color:var(--text-muted)">Integrasi API</div></div></a>
          <a href="#" onclick="window.print();return false" class="export-item"><span class="export-item-icon">🖨️</span><div><div style="font-weight:600">Print PDF</div><div style="font-size:.72rem;color:var(--text-muted)">Cetak laporan</div></div></a>
        </div>
      </div>
      <a href="blog-form.php" class="btn-action-pro primary"><span>➕</span><span>Tambah Artikel</span></a>
    </div>
  </div>

  <!-- ADVANCED FILTER -->
  <div class="advanced-filter-bar">
    <form method="GET" class="advanced-search-form" id="searchForm">
      <input type="hidden" name="tab" value="articles">
      <div class="search-group"><div class="search-input-wrap"><span class="search-icon-pro">🔍</span>
        <input type="text" name="q" class="search-input-pro" placeholder="Cari judul, konten, slug, tag..." value="<?= sanitize($q) ?>" autocomplete="off">
        <?php if($q!==''): ?><button type="button" class="search-clear-pro" onclick="clearSearch()">✕</button><?php endif; ?><span class="search-shortcut">/</span>
      </div></div>
      <div class="filter-group">
        <select name="kategori" class="filter-select-pro" onchange="this.form.submit()"><option value="">📁 Semua Kategori</option><?php foreach($KATEGORI as $k): ?><option value="<?= $k ?>" <?= $kat_filter===$k?'selected':'' ?>><?= $k ?></option><?php endforeach; ?></select>
        <select name="status" class="filter-select-pro" onchange="this.form.submit()"><option value="">📊 Semua Status</option><option value="Published" <?= $status_filter==='Published'?'selected':'' ?>>🟢 Published</option><option value="Draft" <?= $status_filter==='Draft'?'selected':'' ?>>📝 Draft</option><option value="Archived" <?= $status_filter==='Archived'?'selected':'' ?>>📦 Archived</option></select>
        <?php if(!empty($unique_dosen)): ?><select name="dosen" class="filter-select-pro" onchange="this.form.submit()"><option value="">✍️ Semua Penulis</option><?php foreach($unique_dosen as $ud): ?><option value="<?= (int)$ud['dosen_id'] ?>" <?= $author_filter===(int)$ud['dosen_id']?'selected':'' ?>><?= sanitize($ud['nama']) ?></option><?php endforeach; ?></select><?php endif; ?>
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
          <option value="likes-desc" <?= $sort_by==='likes'&&$sort_dir==='DESC'?'selected':'' ?>>Most Liked</option>
          <option value="judul-asc" <?= $sort_by==='judul'&&$sort_dir==='ASC'?'selected':'' ?>>Judul A-Z</option>
        </select>
      </div>
    </div>
  </div>

  <!-- CATEGORY CHIPS -->
  <div class="category-chips-pro"><div class="category-chips-label">📁 Kategori:</div>
    <a class="chip-pro <?= $kat_filter===''?'active':'' ?>" href="?<?= http_build_query(array_merge($_GET,['kategori'=>'','halaman'=>1])) ?>">Semua</a>
    <?php $cicons=['AI & Teknologi'=>'🤖','Tips Riset'=>'🔬','Pendidikan'=>'🎓','Pengabdian'=>'🤝','Opini'=>'💭'];
    foreach($KATEGORI as $k): $cs2=$pdo->prepare("SELECT COUNT(*) FROM blog_artikel WHERE kategori=?"); $cs2->execute([$k]); $cn=(int)$cs2->fetchColumn(); ?>
      <a class="chip-pro <?= $kat_filter===$k?'active':'' ?>" href="?<?= http_build_query(array_merge($_GET,['kategori'=>$k,'halaman'=>1])) ?>"><?= $cicons[$k]??'📄' ?> <?= $k ?> <span class="chip-count-pro"><?= $cn ?></span></a>
    <?php endforeach; ?>
  </div>

  <!-- BULK BAR -->
  <div class="bulk-bar-pro" id="bulkBar">
    <div class="bulk-info-pro"><span class="bulk-count" id="bulkCount">0</span><span>artikel dipilih</span></div>
    <form method="POST" class="bulk-form-inline" id="bulkForm">
      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>"><input type="hidden" name="_back" value="articles">
      <div class="bulk-actions-pro">
        <button type="submit" name="action" value="bulk_publish" class="bulk-btn-pro publish" onclick="return confirm('Publikasikan terpilih?')">📤 Publish</button>
        <button type="submit" name="action" value="bulk_draft" class="bulk-btn-pro draft" onclick="return confirm('Ubah ke draft?')">📝 Draft</button>
        <button type="submit" name="action" value="bulk_archive" class="bulk-btn-pro archive" onclick="return confirm('Arsipkan terpilih?')">📦 Archive</button>
        <button type="submit" name="action" value="bulk_feature" class="bulk-btn-pro feature" onclick="return confirm('Tandai sorotan?')">⭐ Sorotan</button>
        <select name="new_category" class="bulk-category-select" onchange="if(this.value){if(confirm('Ubah kategori?')){const b=document.createElement('input');b.type='hidden';b.name='action';b.value='bulk_category';this.form.appendChild(b);this.form.submit();}}">
          <option value="">📁 Ubah Kategori...</option><?php foreach($KATEGORI as $k): ?><option value="<?= $k ?>"><?= $k ?></option><?php endforeach; ?>
        </select>
        <button type="submit" name="action" value="bulk_delete" class="bulk-btn-pro delete" onclick="return confirm('HAPUS PERMANEN terpilih?')">🗑️ Hapus</button>
      </div>
    </form>
    <button class="bulk-btn-pro cancel" onclick="clearSelection()">Batal</button>
  </div>

  <?php if(empty($articles)): ?>
  <div class="empty-state-premium"><div class="empty-animation"><div class="empty-circle-pro"></div><div class="empty-icon-pro">📭</div></div>
    <h3><?= ($q||$kat_filter||$status_filter||$date_filter)?'Tidak ada hasil untuk filter ini':'Belum ada artikel' ?></h3>
    <p><?= ($q||$kat_filter||$status_filter||$date_filter)?'Coba ubah kata kunci atau filter.':'Mulailah menulis wawasan pertama untuk dibagikan kepada pembaca.' ?></p>
    <div class="empty-actions-pro"><?php if($q||$kat_filter||$status_filter||$date_filter): ?><a href="blog.php?tab=articles" class="btn-action-pro">🔄 Reset Filter</a><?php endif; ?><a href="blog-form.php" class="btn-action-pro primary">➕ Buat Artikel Pertama</a></div>
  </div>

  <?php elseif($view_mode==='grid'): ?>
  <div style="padding:1.5rem"><label class="select-all-grid"><input type="checkbox" id="selectAllGrid" onchange="toggleSelectAll('grid')"><span>Pilih Semua (<?= count($articles) ?>)</span></label>
    <div class="grid-container-pro" style="padding:0"><?php $cols=['#10b981','#dc2626','#3b82f6','#8b5cf6','#f59e0b','#ec4899']; foreach($articles as $i=>$a): $col=$cols[$i%count($cols)]; $th=blog_thumb($a['gambar']); ?>
      <div class="grid-item-pro <?= $a['is_featured']?'featured-card':'' ?>" style="--cc:<?= $col ?>" data-id="<?= $a['id'] ?>">
        <div class="grid-check-pro"><input type="checkbox" class="row-check" value="<?= $a['id'] ?>" form="bulkForm" name="ids[]" onchange="updateBulkBar()"></div>
        <?php if($a['is_featured']): ?><span class="featured-badge-pro">⭐ Sorotan</span><?php endif; ?>
        <div class="grid-image-pro" onclick="previewImage('<?= e($th) ?>',<?= json_encode(sanitize($a['judul']),JSON_UNESCAPED_UNICODE) ?>)">
          <?php if($th): ?><img src="<?= e($th) ?>" alt="<?= sanitize($a['judul']) ?>" loading="lazy"><?php else: ?><div class="image-placeholder-pro">💡</div><?php endif; ?><div class="image-overlay-pro">🔍</div>
        </div>
        <div class="grid-content-pro"><div class="grid-badges-pro"><span class="badge-pro badge-<?= strtolower($a['status']) ?>"><?= $a['status'] ?></span><span class="badge-pro badge-category"><?= sanitize($a['kategori']) ?></span></div>
          <h3><?= sanitize($a['judul']) ?></h3><p><?= excerpt($a['excerpt']?:strip_tags($a['konten']),100) ?></p>
          <div class="grid-meta-pro"><span class="meta-item">👁️ <strong><?= number_format($a['views']) ?></strong></span><span class="meta-item">❤️ <strong><?= number_format($a['likes']) ?></strong></span><span class="meta-item">📅 <?= format_tanggal_singkat($a['created_at']) ?></span></div>
        </div>
        <div class="grid-actions-pro">
          <button class="grid-action-btn" onclick="showDetail(<?= $a['id'] ?>)" title="Preview">👁️</button>
          <a href="blog-form.php?id=<?= $a['id'] ?>" class="grid-action-btn edit" title="Edit">✏️</a>
          <button class="grid-action-btn" onclick="quickAction(<?= $a['id'] ?>,'toggle')" title="Toggle"><?= $a['status']==='Published'?'📤':'' ?></button>
          <button class="grid-action-btn feature" onclick="quickAction(<?= $a['id'] ?>,'toggle_featured')" title="Sorotan"><?= $a['is_featured']?'⭐':'☆' ?></button>
          <button class="grid-action-btn" onclick="quickAction(<?= $a['id'] ?>,'duplicate')" title="Duplikat">📋</button>
          <button class="grid-action-btn danger" onclick="confirmDelete(<?= $a['id'] ?>,<?= json_encode(sanitize($a['judul']),JSON_UNESCAPED_UNICODE) ?>)" title="Hapus">🗑️</button>
        </div>
      </div>
    <?php endforeach; ?></div>
  </div>

  <?php elseif($view_mode==='timeline'): ?>
  <div class="timeline-view"><?php $grp=[]; foreach($articles as $a){ $lb=date('F Y',strtotime($a['created_at'])); $grp[$lb][]=$a; } foreach($grp as $m=>$its): ?>
    <div class="timeline-group"><div class="timeline-group-header"><span style="font-size:1.5rem">📅</span><span class="timeline-month"><?= $m ?></span><span class="timeline-count"><?= count($its) ?> artikel</span></div>
      <div class="timeline-items"><?php foreach($its as $a): ?>
        <div class="timeline-item" onclick="showDetail(<?= $a['id'] ?>)"><div class="timeline-date-box <?= $a['is_featured']?'featured':'' ?>"><div class="timeline-day"><?= date('d',strtotime($a['created_at'])) ?></div><div class="timeline-month-small"><?= date('M',strtotime($a['created_at'])) ?></div></div>
          <div class="timeline-item-info"><div class="timeline-item-name"><?php if($a['is_featured']): ?><span style="color:var(--featured-color)">⭐</span><?php endif; ?><?= sanitize($a['judul']) ?></div><div class="timeline-item-role"><?= sanitize($a['kategori']) ?> • <?= $a['status'] ?> • 👁️ <?= number_format($a['views']) ?> • ❤️ <?= number_format($a['likes']) ?></div></div>
        </div>
      <?php endforeach; ?></div>
    </div>
  <?php endforeach; ?></div>

  <?php else: ?>
  <div class="table-wrapper-pro"><table class="blog-table-premium" id="blogTable"><thead><tr>
    <th style="width:40px"><input type="checkbox" id="selectAll" onchange="toggleSelectAll('table')"></th>
    <th style="width:80px">Gambar</th><th data-sortable="judul">Judul &amp; Penulis <span class="sort-icon">↕</span></th>
    <th data-sortable="kategori" style="width:130px">Kategori <span class="sort-icon">↕</span></th>
    <th data-sortable="status" style="width:100px">Status <span class="sort-icon">↕</span></th>
    <th data-sortable="views" style="width:90px">Views <span class="sort-icon">↕</span></th>
    <th data-sortable="likes" style="width:80px">Likes <span class="sort-icon">↕</span></th>
    <th data-sortable="created_at" style="width:120px">Tanggal <span class="sort-icon">↕</span></th>
    <th style="width:230px;text-align:right">Aksi</th>
  </tr></thead><tbody>
    <?php foreach($articles as $a): $th=blog_thumb($a['gambar']); $au=trim(($a['gelar_depan']??'').' '.($a['dosen_nama']??'Redaksi FKIP').' '.($a['gelar_belakang']??'')); ?>
    <tr data-id="<?= $a['id'] ?>" class="<?= $a['is_featured']?'featured-row':'' ?>">
      <td><input type="checkbox" class="row-check" value="<?= $a['id'] ?>" form="bulkForm" name="ids[]" onchange="updateBulkBar()"></td>
      <td><div class="table-thumb-pro" onclick="previewImage('<?= e($th) ?>',<?= json_encode(sanitize($a['judul']),JSON_UNESCAPED_UNICODE) ?>)"><?php if($th): ?><img src="<?= e($th) ?>" alt="" loading="lazy"><?php else: ?><div class="thumb-placeholder-pro">💡</div><?php endif; ?></div></td>
      <td><div class="title-cell-pro"><strong><?php if($a['is_featured']): ?><span class="featured-icon-pro" title="Sorotan">⭐</span><?php endif; ?><?= sanitize($a['judul']) ?></strong><small><?= sanitize($a['slug']) ?></small><div class="author-line">✍️ <?= sanitize($au) ?><?= !empty($a['prodi_singkatan'])?' • '.sanitize($a['prodi_singkatan']):'' ?></div></div></td>
      <td><span class="badge-pro badge-category"><?= sanitize($a['kategori']) ?></span></td>
      <td><span class="badge-pro badge-<?= strtolower($a['status']) ?>"><?= $a['status'] ?></span></td>
      <td><div class="views-cell-pro"><strong><?= number_format($a['views']) ?></strong><span class="views-label">views</span></div></td>
      <td><div class="views-cell-pro"><strong style="color:#ec4899"><?= number_format($a['likes']) ?></strong><span class="views-label">likes</span></div></td>
      <td><div class="date-cell-pro"><div><?= date('d M Y',strtotime($a['created_at'])) ?></div><small><?= date('H:i',strtotime($a['created_at'])) ?> WITA</small></div></td>
      <td><div class="action-buttons-pro">
        <button class="act-btn-pro view" onclick="showDetail(<?= $a['id'] ?>)" title="Preview">👁️</button>
        <a href="blog-form.php?id=<?= $a['id'] ?>" class="act-btn-pro edit" title="Edit">✏️</a>
        <button class="act-btn-pro toggle" onclick="quickAction(<?= $a['id'] ?>,'toggle')" title="Toggle"><?= $a['status']==='Published'?'📤':'📥' ?></button>
        <button class="act-btn-pro feature" onclick="quickAction(<?= $a['id'] ?>,'toggle_featured')" title="Sorotan"><?= $a['is_featured']?'⭐':'☆' ?></button>
        <button class="act-btn-pro duplicate" onclick="quickAction(<?= $a['id'] ?>,'duplicate')" title="Duplikat">📋</button>
        <button class="act-btn-pro danger" onclick="confirmDelete(<?= $a['id'] ?>,<?= json_encode(sanitize($a['judul']),JSON_UNESCAPED_UNICODE) ?>)" title="Hapus">🗑️</button>
      </div></td>
    </tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>

  <?php if($total_pages>1): $bq=$_GET; ?>
  <nav class="pagination-premium"><div class="pagination-info">Menampilkan <strong><?= min($offset+1,$total) ?>-<?= min($offset+$per_page,$total) ?></strong> dari <strong><?= $total ?></strong> artikel</div>
    <div class="pagination-buttons">
      <?php if($halaman>1): ?><a href="?<?= http_build_query(array_merge($bq,['halaman'=>$halaman-1])) ?>" class="page-btn-pro">← Prev</a><?php endif;
      for($i=1;$i<=$total_pages;$i++){ if($i===1||$i===$total_pages||($i>=$halaman-2&&$i<=$halaman+2)){ echo $i===$halaman?"<span class='page-btn-pro current'>$i</span>":"<a href='?".http_build_query(array_merge($bq,['halaman'=>$i]))."' class='page-btn-pro'>$i</a>"; } elseif($i===$halaman-3||$i===$halaman+3){ echo "<span class='page-dots'>…</span>"; } }
      if($halaman<$total_pages): ?><a href="?<?= http_build_query(array_merge($bq,['halaman'=>$halaman+1])) ?>" class="page-btn-pro">Next →</a><?php endif; ?>
    </div>
  </nav>
  <?php endif; ?>
</div>

<?php else: /* ===== TAB KOMENTAR ===== */ ?>
<div style="background:var(--bg-primary);border:1px solid var(--border);border-radius:var(--radius-xl);padding:1.5rem;box-shadow:var(--shadow-sm)">
  <div class="card-header-pro"><div class="header-left"><h2>💬 Moderasi Komentar <span class="count-badge-pro"><?= $com_total ?></span></h2><p style="color:var(--text-muted);font-size:.82rem;margin-top:.25rem">Setujui, tandai spam, atau hapus diskusi pembaca</p></div>
    <div class="header-actions-pro"><span class="pill <?= ($f_cs??'')==='Pending'||!isset($f_cs)?'active':'' ?>" style="cursor:default">⏳ Pending: <?= $stat_pending ?></span></div>
  </div>
  <div class="advanced-filter-bar"><form method="GET" class="advanced-search-form"><input type="hidden" name="tab" value="comments">
    <div class="search-group"><div class="search-input-wrap"><span class="search-icon-pro">🔍</span><input type="text" name="f_cq" class="search-input-pro" placeholder="Cari nama, isi komentar, atau judul artikel..." value="<?= sanitize($_GET['f_cq']??'') ?>"></div></div>
    <div class="filter-group"><select name="f_cs" class="filter-select-pro" onchange="this.form.submit()">
      <option value="Pending" <?= ($_GET['f_cs']??'Pending')==='Pending'?'selected':'' ?>>⏳ Pending</option>
      <option value="Approved" <?= ($_GET['f_cs']??'')==='Approved'?'selected':'' ?>>✅ Approved</option>
      <option value="Spam" <?= ($_GET['f_cs']??'')==='Spam'?'selected':'' ?>>🚫 Spam</option>
      <option value="all" <?= ($_GET['f_cs']??'')==='all'?'selected':'' ?>>📋 Semua</option>
    </select><button type="submit" class="btn-action-pro">🔍 Terapkan</button></div>
  </form></div>

  <?php if(!empty($comments)): ?>
  <form method="POST" id="cmtBulkForm"><input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>"><input type="hidden" name="_back" value="comments"><input type="hidden" name="action" id="cmtBulkAction" value="">
    <div class="bulk-bar-pro show" style="display:flex"><div class="bulk-info-pro"><span class="bulk-count" id="cmtCount">0</span><span>komentar dipilih</span></div>
      <div class="bulk-actions-pro">
        <button type="button" class="bulk-btn-pro publish" onclick="cmtBulk('bulk_approve')">✅ Approve</button>
        <button type="button" class="bulk-btn-pro draft" onclick="cmtBulk('bulk_spam')">🚫 Spam</button>
        <button type="button" class="bulk-btn-pro delete" onclick="cmtBulk('bulk_delete_comment')">🗑️ Hapus</button>
      </div>
    </div>
    <?php foreach($comments as $cm): $ini=strtoupper(substr(trim($cm['nama']),0,1)); ?>
    <div class="cmt <?= strtolower($cm['status']) ?>">
      <label class="cmt-check"><input type="checkbox" name="ids[]" value="<?= (int)$cm['id'] ?>" onchange="cmtUpdateCount()"></label>
      <div class="av"><?= e($ini) ?></div>
      <div class="bd"><div class="top"><span class="nm"><?= sanitize($cm['nama']) ?></span><?php if(!empty($cm['email'])): ?><span class="em">· <?= sanitize($cm['email']) ?></span><?php endif; ?><span class="badge-pro badge-<?= $cm['status']==='Approved'?'published':($cm['status']==='Spam'?'archived':'draft') ?>"><?= $cm['status'] ?></span><span class="dt"><?= date('d M Y, H:i',strtotime($cm['created_at'])) ?></span></div>
        <div class="tx"><?= sanitize($cm['komentar']) ?></div>
        <?php if(!empty($cm['aj'])): ?><a class="on-art" href="<?= base_url('blog-detail.php?slug='.urlencode($cm['aslug'])) ?>" target="_blank">📄 <?= excerpt($cm['aj'],55) ?></a><?php endif; ?>
        <div class="acts">
          <?php if($cm['status']!=='Approved'): ?><button type="button" class="bulk-btn-pro publish" onclick="cmtSingle('approve_comment',<?= (int)$cm['id'] ?>)">✅ Approve</button><?php endif; ?>
          <?php if($cm['status']!=='Spam'): ?><button type="button" class="bulk-btn-pro draft" onclick="cmtSingle('spam_comment',<?= (int)$cm['id'] ?>)">🚫 Spam</button><?php endif; ?>
          <button type="button" class="bulk-btn-pro delete" onclick="cmtSingle('delete_comment',<?= (int)$cm['id'] ?>)">🗑️ Hapus</button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </form>
  <?php if($com_pages>1): $cq2=$_GET; ?>
  <nav class="pagination-premium"><div class="pagination-info">Halaman <strong><?= $com_page ?></strong> dari <strong><?= $com_pages ?></strong></div><div class="pagination-buttons">
    <?php if($com_page>1): ?><a href="?<?= http_build_query(array_merge($cq2,['cpage'=>$com_page-1])) ?>" class="page-btn-pro">← Prev</a><?php endif;
    for($i=1;$i<=$com_pages;$i++){ if($i===1||$i===$com_pages||($i>=$com_page-2&&$i<=$com_page+2)){ echo $i===$com_page?"<span class='page-btn-pro current'>$i</span>":"<a href='?".http_build_query(array_merge($cq2,['cpage'=>$i]))."' class='page-btn-pro'>$i</a>"; } elseif($i===$com_page-3||$i===$com_page+3){ echo "<span class='page-dots'>…</span>"; } }
    if($com_page<$com_pages): ?><a href="?<?= http_build_query(array_merge($cq2,['cpage'=>$com_page+1])) ?>" class="page-btn-pro">Next →</a><?php endif; ?>
  </div></nav>
  <?php endif; ?>
  <?php else: ?>
  <div class="empty-state-premium"><div class="empty-animation"><div class="empty-circle-pro"></div><div class="empty-icon-pro">🎉</div></div><h3>Tidak ada komentar pada filter ini</h3><p>Diskusi pembaca akan muncul di sini untuk dimoderasi.</p></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ===== IMAGE MODAL ===== -->
<div class="modal-overlay-premium" id="imageModal" onclick="if(event.target===this)closeImageModal()"><div class="image-modal-premium"><button class="modal-close-premium" onclick="closeImageModal()">✕</button><div class="modal-header-premium" id="imageModalTitle"></div><div class="modal-body-premium"><img id="imageModalImg" src="" alt=""></div></div></div>

<!-- ===== DETAIL MODAL ===== -->
<div class="modal-overlay-premium" id="detailModal" onclick="if(event.target===this)closeDetailModal()"><div class="modal-content"><button class="modal-close-premium" onclick="closeDetailModal()">✕</button>
  <div class="modal-header-news"><div class="modal-category-badge" id="detailCategory">-</div><h2 class="modal-title" id="detailTitle">-</h2><div class="modal-meta"><span class="modal-meta-item" id="detailAuthor">✍️ -</span><span class="modal-meta-item" id="detailDate">📅 -</span><span class="modal-meta-item" id="detailStatus">-</span><span class="modal-meta-item" id="detailRT">⏱️ -</span></div></div>
  <div class="modal-body"><div class="detail-tabs"><button class="detail-tab active" onclick="switchDetailTab('preview',this)">📖 Preview</button><button class="detail-tab" onclick="switchDetailTab('stats',this)">📊 Statistik</button><button class="detail-tab" onclick="switchDetailTab('actions',this)">⚡ Aksi</button></div>
    <div class="detail-tab-content active" id="tab-preview"><div class="article-image-preview" id="detailImage"></div><div class="article-content-preview" id="detailContent">-</div></div>
    <div class="detail-tab-content" id="tab-stats"><div class="article-stats-grid" id="detailStats"></div></div>
    <div class="detail-tab-content" id="tab-actions"><div class="modal-actions-list" id="detailActions"></div><div class="social-share-preview"><div class="social-share-label">🔗 Bagikan Artikel</div><div class="social-share-buttons" id="shareButtons"></div></div></div>
  </div>
</div></div>

<!-- ===== CONFIRM MODAL ===== -->
<div class="modal-overlay-premium" id="confirmModal" onclick="if(event.target===this)closeConfirm()"><div class="confirm-modal-premium"><div class="confirm-icon-premium" id="confirmIcon">⚠️</div><h3 id="confirmTitle">Konfirmasi</h3><p id="confirmMessage">Apakah Anda yakin?</p><div class="confirm-actions-premium"><button class="btn-action-pro" onclick="closeConfirm()">Batal</button><button class="btn-action-pro" id="confirmOk" style="background:var(--blog-accent);color:#fff;border-color:var(--blog-accent)">Ya, Lanjutkan</button></div></div></div>

<!-- ===== HIDDEN FORMS ===== -->
<form id="quickActionForm" method="POST" style="display:none"><input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>"><input type="hidden" name="_back" value="articles"><input type="hidden" name="id" id="qaId"><input type="hidden" name="action" id="qaAction"></form>
<form id="deleteForm" method="POST" style="display:none"><input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>"><input type="hidden" name="_back" value="articles"><input type="hidden" name="id" id="delId"><input type="hidden" name="action" value="delete"></form>
<form id="cmtSingleForm" method="POST" style="display:none"><input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>"><input type="hidden" name="_back" value="comments"><input type="hidden" name="action" id="csAction"><input type="hidden" name="id" id="csId"></form>

<script>
const blogData = <?= json_encode($articles, JSON_UNESCAPED_UNICODE) ?>;
function animateCount(el){const t=parseInt(el.dataset.target)||0,d=1800,s=performance.now();(function n(now){const p=Math.min((now-s)/d,1),e=1-Math.pow(1-p,3);el.textContent=Math.floor(e*t).toLocaleString('id-ID');if(p<1)requestAnimationFrame(n);})(s);}
const cObs=new IntersectionObserver(es=>es.forEach(x=>{if(x.isIntersecting){animateCount(x.target);cObs.unobserve(x.target);}}),{threshold:.3});
document.querySelectorAll('.count-up').forEach(el=>cObs.observe(el));
<?php if(!empty($monthly)): ?>new ApexCharts(document.querySelector("#trendChart"),{series:[{name:'Artikel',data:<?= json_encode(array_values($monthly)) ?>}],chart:{type:'area',height:260,toolbar:{show:false}},colors:['var(--primary)'],fill:{type:'gradient',gradient:{shadeIntensity:1,opacityFrom:.5,opacityTo:.1}},stroke:{curve:'smooth',width:3},xaxis:{categories:<?= json_encode(array_keys($monthly)) ?>,labels:{style:{fontSize:'10px'}}},yaxis:{labels:{style:{fontSize:'11px'}}},dataLabels:{enabled:false},tooltip:{y:{formatter:v=>v+' artikel'}}}).render();<?php endif; ?>
<?php if(!empty($cat_stats)): ?>new ApexCharts(document.querySelector("#categoryChart"),{series:<?= json_encode(array_column($cat_stats,'count')) ?>,labels:<?= json_encode(array_keys($cat_stats)) ?>,chart:{type:'donut',height:280},colors:['#10b981','#dc2626','#3b82f6','#8b5cf6','#f59e0b'],plotOptions:{pie:{donut:{size:'70%',labels:{show:true,total:{show:true,label:'Total',formatter:()=><?= array_sum(array_column($cat_stats,'count')) ?>}}}}},dataLabels:{enabled:true,style:{fontSize:'11px',fontWeight:700}},legend:{position:'bottom',fontSize:'11px'},stroke:{show:true,colors:['var(--bg-primary)'],width:3}}).render();<?php endif; ?>
let sT;document.querySelector('.search-input-pro')?.addEventListener('input',function(){clearTimeout(sT);const v=this.value;sT=setTimeout(()=>{const u=new URL(window.location);v?u.searchParams.set('q',v):u.searchParams.delete('q');u.searchParams.set('halaman','1');window.location=u;},500);});
function clearSearch(){const u=new URL(window.location);u.searchParams.delete('q');u.searchParams.set('halaman','1');window.location=u;}
function resetFilters(){if(confirm('Reset semua filter?'))window.location='blog.php?tab=articles';}
function sortTable(v){const[s,d]=v.split('-');const u=new URL(window.location);u.searchParams.set('sort',s);u.searchParams.set('dir',d);window.location=u;}
function toggleExportMenu(e){e.stopPropagation();document.getElementById('exportMenu').classList.toggle('show');}
document.addEventListener('click',e=>{if(!e.target.closest('.export-dropdown'))document.getElementById('exportMenu')?.classList.remove('show');});
function toggleSelectAll(t){const m=t==='grid'?document.getElementById('selectAllGrid'):document.getElementById('selectAll');document.querySelectorAll('.row-check').forEach(cb=>{cb.checked=m.checked;cb.closest('[data-id]')?.classList.toggle('selected',m.checked);});updateBulkBar();}
document.querySelectorAll('.row-check').forEach(cb=>cb.addEventListener('change',function(){this.closest('[data-id]')?.classList.toggle('selected',this.checked);updateBulkBar();}));
function updateBulkBar(){const c=document.querySelectorAll('.row-check:checked').length;document.getElementById('bulkCount').textContent=c;document.getElementById('bulkBar')?.classList.toggle('show',c>0);const all=document.querySelectorAll('.row-check'),ck=all.length>0&&c===all.length;const sa=document.getElementById('selectAll');if(sa)sa.checked=ck;const sg=document.getElementById('selectAllGrid');if(sg)sg.checked=ck;}
function clearSelection(){document.querySelectorAll('.row-check').forEach(cb=>cb.checked=false);document.querySelectorAll('[data-id]').forEach(r=>r.classList.remove('selected'));const sa=document.getElementById('selectAll');if(sa)sa.checked=false;const sg=document.getElementById('selectAllGrid');if(sg)sg.checked=false;updateBulkBar();}
function previewImage(src,title){if(!src||!/\.(jpg|jpeg|png|webp|gif)(\?.*)?$/i.test(src)){showToast('Gambar tidak tersedia','error');return;}document.getElementById('imageModalImg').src=src;document.getElementById('imageModalTitle').textContent=title||'Preview';document.getElementById('imageModal').classList.add('open');}
function closeImageModal(){document.getElementById('imageModal').classList.remove('open');}
function showDetail(id){const d=blogData.find(b=>b.id==id);if(!d)return;const au=((d.gelar_depan||'')+' '+(d.dosen_nama||'Redaksi FKIP')+' '+(d.gelar_belakang||'')).trim();
  document.getElementById('detailTitle').textContent=d.judul||'-';document.getElementById('detailCategory').textContent=(d.kategori||'-')+(d.is_featured?' • ⭐ Sorotan':'');document.getElementById('detailAuthor').textContent='✍️ '+au;document.getElementById('detailDate').textContent='📅 '+formatDate(d.created_at);document.getElementById('detailStatus').innerHTML=`<span class="badge-pro badge-${d.status.toLowerCase()}">${d.status}</span>`;
  const rt=Math.max(1,Math.ceil((d.konten||'').replace(/<[^>]+>/g,'').split(/\s+/).filter(w=>w).length/200));document.getElementById('detailRT').textContent='⏱️ '+rt+' mnt';
  const ic=document.getElementById('detailImage');ic.innerHTML=d.gambar?`<img src="${location.origin}/uploads/blog/${d.gambar}" alt="">`:'<div class="article-image-placeholder">💡</div>';
  const ct=d.konten||d.excerpt||'Tidak ada konten';document.getElementById('detailContent').innerHTML=ct.length>1500?ct.substring(0,1500)+'<div style="margin-top:1rem;padding:.75rem;background:rgba(10,104,71,.1);border-radius:8px;color:var(--primary);font-weight:600;font-size:.85rem">📖 Edit untuk melihat seluruh konten...</div>':ct;
  const wc=(d.konten||'').replace(/<[^>]+>/g,'').split(/\s+/).filter(w=>w).length;
  document.getElementById('detailStats').innerHTML=`<div class="article-stat-item"><div class="article-stat-value">${(d.views||0).toLocaleString('id-ID')}</div><div class="article-stat-label">Views</div></div><div class="article-stat-item"><div class="article-stat-value">${(d.likes||0).toLocaleString('id-ID')}</div><div class="article-stat-label">Likes</div></div><div class="article-stat-item"><div class="article-stat-value">${wc}</div><div class="article-stat-label">Kata</div></div>`;
  document.getElementById('detailActions').innerHTML=`<a href="blog-form.php?id=${d.id}" class="btn-action-pro primary" style="justify-content:flex-start">✏️ Edit Artikel</a><button onclick="quickAction(${d.id},'toggle');closeDetailModal()" class="btn-action-pro" style="justify-content:flex-start">🔄 Toggle Status (${d.status})</button><button onclick="quickAction(${d.id},'toggle_featured');closeDetailModal()" class="btn-action-pro" style="justify-content:flex-start">⭐ ${d.is_featured?'Unfeature':'Feature'}</button><button onclick="quickAction(${d.id},'duplicate');closeDetailModal()" class="btn-action-pro" style="justify-content:flex-start">📋 Duplikasi</button>`;
  const su=location.origin+'<?= base_url('blog-detail.php?slug=') ?>'+encodeURIComponent(d.slug||d.id), st=`💡 ${d.judul}\n\n${d.excerpt||''}\n\nBaca di FKIP UNIMOF`;
  document.getElementById('shareButtons').innerHTML=`<a href="https://twitter.com/intent/tweet?text=${encodeURIComponent(st)}&url=${encodeURIComponent(su)}" target="_blank" class="share-btn twitter">🐦 Twitter</a><a href="https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(su)}" target="_blank" class="share-btn facebook">📘 Facebook</a><a href="https://wa.me/?text=${encodeURIComponent(st+'\n'+su)}" target="_blank" class="share-btn whatsapp">💬 WhatsApp</a><button onclick="copyShareLink('${su}')" class="share-btn copy">📋 Copy</button>`;
  document.getElementById('detailModal').classList.add('open');document.body.style.overflow='hidden';}
function closeDetailModal(){document.getElementById('detailModal').classList.remove('open');document.body.style.overflow='';}
function switchDetailTab(t,b){document.querySelectorAll('.detail-tab').forEach(x=>x.classList.remove('active'));document.querySelectorAll('.detail-tab-content').forEach(x=>x.classList.remove('active'));b.classList.add('active');document.getElementById('tab-'+t).classList.add('active');}
function copyShareLink(u){if(navigator.clipboard){navigator.clipboard.writeText(u);showToast('Link disalin','success');}}
function formatDate(s){if(!s)return'-';const M=['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'],d=new Date(s);return`${d.getDate()} ${M[d.getMonth()]} ${d.getFullYear()} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`;}
let cc=null;function showConfirm(t,m,i,cb){document.getElementById('confirmTitle').textContent=t;document.getElementById('confirmMessage').textContent=m;document.getElementById('confirmIcon').textContent=i;cc=cb;document.getElementById('confirmModal').classList.add('open');}
function closeConfirm(){document.getElementById('confirmModal').classList.remove('open');cc=null;}
document.getElementById('confirmOk').addEventListener('click',()=>{if(cc)cc();closeConfirm();});
function quickAction(id,a){const c={toggle:{t:'Ubah Status?',m:'Status artikel diubah (Published ↔ Draft).',i:'🔄'},duplicate:{t:'Duplikat Artikel?',m:'Salinan dibuat sebagai draft baru.',i:'📋'},toggle_featured:{t:'Toggle Sorotan?',m:'Status sorotan diubah.',i:'⭐'}}[a];showConfirm(c.t,c.m,c.i,()=>{document.getElementById('qaId').value=id;document.getElementById('qaAction').value=a;document.getElementById('quickActionForm').submit();});}
function confirmDelete(id,t){showConfirm('Hapus Artikel Permanen?',`"${t}" dihapus permanen beserta komentarnya. TIDAK BISA dibatalkan!`,'⚠️',()=>{document.getElementById('delId').value=id;document.getElementById('deleteForm').submit();});}
function showToast(m,type='info'){if(window.AdminPanel?.Toast)window.AdminPanel.Toast.show(m,type);else if(window.showToast)window.showToast(m,type);else console.log(`[${type}] ${m}`);}
function cmtUpdateCount(){const c=document.querySelectorAll('#cmtBulkForm input[name="ids[]"]:checked').length;const el=document.getElementById('cmtCount');if(el)el.textContent=c;}
function cmtBulk(a){const f=document.getElementById('cmtBulkForm');if(!f.querySelector('input[name="ids[]"]:checked')){showToast('Pilih minimal satu komentar','error');return;}if(a==='bulk_delete_comment'&&!confirm('Hapus permanen komentar terpilih?'))return;document.getElementById('cmtBulkAction').value=a;f.submit();}
function cmtSingle(a,id){if(a==='delete_comment'&&!confirm('Hapus komentar ini?'))return;document.getElementById('csAction').value=a;document.getElementById('csId').value=id;document.getElementById('cmtSingleForm').submit();}
document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeImageModal();closeConfirm();closeDetailModal();}if(e.key==='/'&&!e.ctrlKey&&!e.metaKey&&!e.altKey&&!['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)){e.preventDefault();document.querySelector('.search-input-pro')?.focus();}if((e.ctrlKey||e.metaKey)&&e.key==='n'){e.preventDefault();window.location.href='blog-form.php';}if((e.ctrlKey||e.metaKey)&&e.key==='e'){e.preventDefault();window.location=location.pathname+'?tab=articles&export=csv';}});
function hlSearch(){const q=<?= json_encode($q, JSON_UNESCAPED_UNICODE) ?>;if(!q)return;document.querySelectorAll('.title-cell-pro strong,.grid-content-pro h3,.timeline-item-name').forEach(el=>{if(!el.querySelector('mark')){el.innerHTML=el.innerHTML.replace(new RegExp('('+q.replace(/[.*+?${}()|[\]\\]/g,'\\$&')+')','gi'),'<mark>$1</mark>');}});}
hlSearch();
console.log('%c💡 Newsroom Ide & Wawasan - DARK MODE READY','color:var(--primary);font-size:16px;font-weight:bold');
console.log('%cShortcuts: / (Cari), Ctrl+N (Tambah), Ctrl+E (Export CSV), Esc (Tutup)','color:#64748b');
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>