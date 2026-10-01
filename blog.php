<?php
// blog.php - Ide & Wawasan (Blog Dosen) - EXTREME MULTIMATE VERSION
require_once __DIR__ . '/includes/config.php';
$page_title = 'Ide & Wawasan';
$page_description = 'Artikel, opini, dan wawasan dari dosen FKIP UNIMOF seputar pendidikan, riset, teknologi, dan pengabdian masyarakat.';

// =====================================================
// HELPER LOKAL (schema-safe, tidak menyentuh config)
// =====================================================
if (!function_exists('blog_col')) {
    function blog_col($pdo, $table, $col) {
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
if (!function_exists('blog_build_query')) {
    function blog_build_query(array $override = []) {
        $params = array_merge([
            'kategori' => $_GET['kategori'] ?? '',
            'q'        => $_GET['q'] ?? '',
            'sort'     => $_GET['sort'] ?? 'terbaru',
        ], $override);
        $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
        return empty($params) ? '?' : '?' . http_build_query($params);
    }
}

// =====================================================
// KONFIG FILTER
// =====================================================
$blog_kategori_list = ['AI & Teknologi', 'Tips Riset', 'Pendidikan', 'Pengabdian', 'Opini'];
$blog_sorts = [
    'terbaru'   => 'Terbaru',
    'populer'   => 'Terpopuler',
    'diskusi'   => 'Paling Banyak Disukai',
];

$akt_kategori = trim($_GET['kategori'] ?? '');
$akt_q        = trim($_GET['q'] ?? '');
$akt_sort     = array_key_exists($_GET['sort'] ?? '', $blog_sorts) ? $_GET['sort'] : 'terbaru';
$akt_page     = max(1, (int)($_GET['page'] ?? 1));
$per_page     = 9;

// =====================================================
// QUERY: FEATURED (Sorotan Editor)
// =====================================================
$featured = [];
try {
    $sql_f = "SELECT b.*, d.nama AS dosen_nama, d.gelar_depan, d.gelar_belakang, d.foto AS dosen_foto,
                     p.nama AS prodi_nama, p.singkatan AS prodi_singkatan
              FROM blog_artikel b
              LEFT JOIN dosen d ON b.dosen_id = d.id
              LEFT JOIN program_studi p ON b.program_studi_id = p.id
              WHERE b.status = 'Published' AND b.is_featured = 1
              ORDER BY b.published_at DESC
              LIMIT 2";
    $featured = $pdo->query($sql_f)->fetchAll();
} catch (Exception $e) { $featured = []; }
$featured_ids = array_column($featured, 'id');

// =====================================================
// QUERY: LISTING + FILTER + SEARCH + SORT + PAGINATION
// =====================================================
$where = ["b.status = 'Published'"];
$args  = [];
if (!empty($featured_ids)) {
    $in = implode(',', array_map('intval', $featured_ids));
    $where[] = "b.id NOT IN ($in)";
}
if ($akt_kategori !== '' && in_array($akt_kategori, $blog_kategori_list, true)) {
    $where[] = 'b.kategori = ?';
    $args[]  = $akt_kategori;
}
if ($akt_q !== '') {
    $where[] = '(b.judul LIKE ? OR b.excerpt LIKE ? OR b.tags LIKE ?)';
    $like = '%' . $akt_q . '%';
    $args[] = $like; $args[] = $like; $args[] = $like;
}
$where_sql = 'WHERE ' . implode(' AND ', $where);

$order_sql = match ($akt_sort) {
    'populer' => 'b.views DESC, b.published_at DESC',
    'diskusi' => 'b.likes DESC, b.published_at DESC',
    default   => 'b.published_at DESC',
};

// Hitung total untuk pagination
$total_rows = 0;
try {
    $stmt_c = $pdo->prepare("SELECT COUNT(*) FROM blog_artikel b $where_sql");
    $stmt_c->execute($args);
    $total_rows = (int)$stmt_c->fetchColumn();
} catch (Exception $e) {}
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$akt_page    = min($akt_page, $total_pages);
$offset      = ($akt_page - 1) * $per_page;

// Ambil halaman aktif
$blogs = [];
try {
    $stmt = $pdo->prepare("SELECT b.*, d.nama AS dosen_nama, d.gelar_depan, d.gelar_belakang, d.foto AS dosen_foto,
                                  p.nama AS prodi_nama, p.singkatan AS prodi_singkatan
                           FROM blog_artikel b
                           LEFT JOIN dosen d ON b.dosen_id = d.id
                           LEFT JOIN program_studi p ON b.program_studi_id = p.id
                           $where_sql
                           ORDER BY $order_sql
                           LIMIT $per_page OFFSET $offset");
    $stmt->execute($args);
    $blogs = $stmt->fetchAll();
} catch (Exception $e) { $blogs = []; }

// Hitung jumlah per kategori (untuk badge chip)
$kategor_counts = [];
try {
    $kc = $pdo->query("SELECT kategori, COUNT(*) c FROM blog_artikel WHERE status='Published' GROUP BY kategori")->fetchAll(PDO::FETCH_KEY_PAIR);
    $kategor_counts = $kc;
} catch (Exception $e) {}

// Statistik ringkas header
$stat_total_artikel = $total_rows + count($featured);
$stat_total_dosen   = 0;
try { $stat_total_dosen = (int)$pdo->query("SELECT COUNT(DISTINCT dosen_id) FROM blog_artikel WHERE status='Published' AND dosen_id IS NOT NULL")->fetchColumn(); } catch (Exception $e) {}

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ===== BLOG HERO ===== */
.blog-hero {
    background: linear-gradient(135deg, #0a6847 0%, #084d35 45%, #16213e 100%);
    color: #fff; padding: 9rem 0 4rem; position: relative; overflow: hidden;
}
.blog-hero::before {
    content: ''; position: absolute; inset: 0;
    background:
        radial-gradient(circle at 15% 30%, rgba(245,166,35,0.22) 0%, transparent 50%),
        radial-gradient(circle at 85% 70%, rgba(59,130,246,0.18) 0%, transparent 50%);
    animation: blogAurora 18s ease-in-out infinite;
}
@keyframes blogAurora { 0%,100%{transform:translate(0,0) scale(1);} 33%{transform:translate(-25px,15px) scale(1.05);} 66%{transform:translate(20px,-25px) scale(.95);} }
.blog-hero .container { position: relative; z-index: 2; }
.blog-hero .breadcrumb a, .blog-hero .breadcrumb span { color: rgba(255,255,255,0.8); }
.blog-hero .breadcrumb a:hover { color: #fff; }
.blog-hero-title { font-family: var(--font-display); font-size: clamp(2.25rem,5vw,3.5rem); font-weight: 900; line-height: 1.1; margin: 0.5rem 0 1rem; }
.blog-hero-title em { font-style: italic; background: linear-gradient(135deg,#fbbf24,#f59e0b,#ec4899); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
.blog-hero-sub { font-size: 1.1rem; opacity: .92; max-width: 680px; line-height: 1.7; }
.blog-hero-stats { display: flex; gap: 2rem; flex-wrap: wrap; margin-top: 2rem; }
.blog-hero-stat strong { display: block; font-family: var(--font-display); font-size: 2rem; font-weight: 900; color: #fbbf24; line-height: 1; }
.blog-hero-stat span { font-size: .78rem; text-transform: uppercase; letter-spacing: .08em; opacity: .8; }

/* ===== TOOLBAR ===== */
.blog-toolbar {
    background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl,16px);
    padding: 1.25rem; margin: -2.5rem auto 2.5rem; position: relative; z-index: 5;
    box-shadow: var(--shadow-lg,0 10px 15px -3px rgba(0,0,0,.1)); max-width: 1100px;
}
.blog-search { display: flex; gap: .65rem; flex-wrap: wrap; margin-bottom: 1rem; }
.blog-search input {
    flex: 1; min-width: 220px; padding: .75rem 1rem .75rem 2.6rem; border: 2px solid var(--border);
    border-radius: var(--radius-md,12px); background: var(--bg-secondary); color: var(--text-primary);
    font-family: inherit; font-size: .92rem; transition: all .3s;
}
.blog-search { position: relative; }
.blog-search .s-ico { position: absolute; left: .95rem; top: 50%; transform: translateY(-50%); pointer-events: none; opacity: .5; }
.blog-search input:focus { outline: none; border-color: var(--primary); background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(10,104,71,.1); }
.blog-search button { padding: .75rem 1.4rem; border-radius: var(--radius-md,12px); border: none; background: var(--primary); color: #fff; font-weight: 700; cursor: pointer; font-family: inherit; transition: all .25s; }
.blog-search button:hover { background: var(--primary-dark,#084d35); transform: translateY(-2px); }
.blog-sort { display: flex; align-items: center; gap: .5rem; }
.blog-sort label { font-size: .82rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .05em; }
.blog-sort select { padding: .55rem .9rem; border: 2px solid var(--border); border-radius: var(--radius-md,12px); background: var(--bg-secondary); color: var(--text-primary); font-family: inherit; font-size: .88rem; font-weight: 600; cursor: pointer; }
.blog-chips { display: flex; gap: .45rem; flex-wrap: wrap; }
.blog-chip {
    display: inline-flex; align-items: center; gap: .4rem; padding: .45rem .95rem; border-radius: 999px;
    border: 1px solid var(--border); background: var(--bg-primary); color: var(--text-secondary);
    font-size: .8rem; font-weight: 600; text-decoration: none; transition: all .2s;
}
.blog-chip:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); }
.blog-chip.active { background: var(--primary); color: #fff; border-color: var(--primary); }
.blog-chip .cnt { background: rgba(0,0,0,.08); padding: 0 .45rem; border-radius: 999px; font-size: .68rem; font-weight: 800; }
.blog-chip.active .cnt { background: rgba(255,255,255,.25); }

/* ===== FEATURED / SOROTAN ===== */
.blog-featured-grid { display: grid; grid-template-columns: 1.4fr 1fr; gap: 1.5rem; margin-bottom: 3rem; }
.blog-feat-card {
    position: relative; border-radius: var(--radius-xl,16px); overflow: hidden; min-height: 320px;
    display: flex; align-items: flex-end; text-decoration: none; color: #fff;
    box-shadow: var(--shadow-xl,0 20px 25px -5px rgba(0,0,0,.1)); transition: transform .4s, box-shadow .4s;
}
.blog-feat-card:hover { transform: translateY(-6px); box-shadow: 0 30px 60px rgba(0,0,0,.25); }
.blog-feat-bg { position: absolute; inset: 0; background-size: cover; background-position: center; transition: transform .6s; }
.blog-feat-card:hover .blog-feat-bg { transform: scale(1.08); }
.blog-feat-overlay { position: absolute; inset: 0; background: linear-gradient(to top, rgba(2,44,34,.95) 0%, rgba(2,44,34,.4) 50%, transparent 100%); }
.blog-feat-content { position: relative; z-index: 2; padding: 2rem; width: 100%; }
.blog-feat-badge { display: inline-flex; align-items: center; gap: .35rem; padding: .3rem .8rem; border-radius: 999px; background: #f59e0b; color: #1a1a1a; font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; margin-bottom: .85rem; }
.blog-feat-title { font-family: var(--font-display); font-size: 1.6rem; font-weight: 800; line-height: 1.25; margin-bottom: .6rem; }
.blog-feat-excerpt { font-size: .92rem; opacity: .9; line-height: 1.6; margin-bottom: 1rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.blog-feat-meta { display: flex; align-items: center; gap: .65rem; font-size: .8rem; opacity: .85; flex-wrap: wrap; }
.blog-feat-avatar { width: 34px; height: 34px; border-radius: 50%; background: rgba(255,255,255,.2); display: flex; align-items: center; justify-content: center; font-weight: 800; overflow: hidden; flex-shrink: 0; }
.blog-feat-avatar img { width: 100%; height: 100%; object-fit: cover; }

/* ===== GRID ARTIKEL ===== */
.blog-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.75rem; }
.blog-card {
    background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl,16px);
    overflow: hidden; display: flex; flex-direction: column; transition: all .4s cubic-bezier(.4,0,.2,1); text-decoration: none; color: inherit;
}
.blog-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl,0 20px 25px -5px rgba(0,0,0,.12)); border-color: var(--primary); }
.blog-card-img { position: relative; height: 190px; overflow: hidden; }
.blog-card-img .ph, .blog-card-img img { width: 100%; height: 100%; object-fit: cover; transition: transform .6s; }
.blog-card:hover .blog-card-img .ph, .blog-card:hover .blog-card-img img { transform: scale(1.08); }
.blog-card-cat { position: absolute; top: 1rem; left: 1rem; padding: .3rem .75rem; border-radius: 999px; background: rgba(255,255,255,.95); color: var(--primary); font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; backdrop-filter: blur(8px); }
.blog-card-rt { position: absolute; bottom: 1rem; right: 1rem; padding: .25rem .6rem; border-radius: 999px; background: rgba(15,23,42,.75); color: #fff; font-size: .68rem; font-weight: 700; backdrop-filter: blur(6px); }
.blog-card-body { padding: 1.5rem; display: flex; flex-direction: column; flex: 1; }
.blog-card-title { font-size: 1.15rem; font-weight: 800; line-height: 1.35; margin-bottom: .6rem; transition: color .25s; }
.blog-card:hover .blog-card-title { color: var(--primary); }
.blog-card-excerpt { color: var(--text-secondary); font-size: .9rem; line-height: 1.65; margin-bottom: 1.1rem; flex: 1; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.blog-card-foot { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding-top: 1rem; border-top: 1px dashed var(--border); }
.blog-card-author { display: flex; align-items: center; gap: .55rem; min-width: 0; }
.blog-card-avatar { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg,var(--primary),var(--primary-light,#16a34a)); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: .8rem; flex-shrink: 0; overflow: hidden; }
.blog-card-avatar img { width: 100%; height: 100%; object-fit: cover; }
.blog-card-aname { font-size: .8rem; font-weight: 700; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.blog-card-asub { font-size: .68rem; color: var(--text-muted); }
.blog-card-stats { display: flex; gap: .7rem; font-size: .75rem; color: var(--text-muted); flex-shrink: 0; }
.blog-card-stats span { display: inline-flex; align-items: center; gap: .25rem; }

/* ===== PAGINATION ===== */
.blog-pagination { display: flex; gap: .45rem; justify-content: center; align-items: center; margin-top: 3rem; flex-wrap: wrap; }
.blog-pagination a, .blog-pagination span {
    min-width: 42px; height: 42px; padding: 0 .85rem; border-radius: var(--radius-md,12px);
    border: 1px solid var(--border); display: inline-flex; align-items: center; justify-content: center;
    font-size: .9rem; font-weight: 700; color: var(--text-secondary); text-decoration: none; transition: all .2s; background: var(--bg-primary);
}
.blog-pagination a:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); }
.blog-pagination .current { background: var(--primary); color: #fff; border-color: var(--primary); }
.blog-pagination .disabled { opacity: .4; pointer-events: none; }

@media (max-width: 968px) {
    .blog-featured-grid { grid-template-columns: 1fr; }
    .blog-feat-card:first-child { min-height: 360px; }
}
@media (max-width: 640px) {
    .blog-hero { padding: 7rem 0 3rem; }
    .blog-toolbar { margin-top: -1.5rem; }
    .blog-grid { grid-template-columns: 1fr; }
}
</style>

<!-- ===== HERO ===== -->
<section class="blog-hero">
    <div class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= base_url() ?>">Beranda</a><span>›</span><span>Ide &amp; Wawasan</span>
        </nav>
        <h1 class="blog-hero-title" data-aos="fade-up">Wawasan dari <em>Para Pendidik</em></h1>
        <p class="blog-hero-sub" data-aos="fade-up" data-aos-delay="100">
            Artikel, opini, dan gagasan segar dari dosen FKIP UNIMOF — menjelajahi pendidikan, riset, teknologi, dan pengabdian untuk Indonesia Timur.
        </p>
        <div class="blog-hero-stats" data-aos="fade-up" data-aos-delay="200">
            <div class="blog-hero-stat"><strong class="count-up" data-count="<?= $stat_total_artikel ?>">0</strong><span>Artikel Terbit</span></div>
            <div class="blog-hero-stat"><strong class="count-up" data-count="<?= $stat_total_dosen ?>">0</strong><span>Dosen Kontributor</span></div>
            <div class="blog-hero-stat"><strong><?= count($blog_kategori_list) ?></strong><span>Kategori Topik</span></div>
        </div>
    </div>
</section>

<!-- ===== TOOLBAR ===== -->
<div class="container">
    <div class="blog-toolbar" data-aos="fade-up">
        <form class="blog-search" method="get" action="<?= base_url('blog.php') ?>" role="search">
            <span class="s-ico" aria-hidden="true">🔍</span>
            <input type="text" name="q" value="<?= sanitize($akt_q) ?>" placeholder="Cari judul, topik, atau tag artikel..." aria-label="Cari artikel">
            <?php if ($akt_kategori): ?><input type="hidden" name="kategori" value="<?= sanitize($akt_kategori) ?>"><?php endif; ?>
            <?php if ($akt_sort !== 'terbaru'): ?><input type="hidden" name="sort" value="<?= sanitize($akt_sort) ?>"><?php endif; ?>
            <button type="submit">Cari</button>
        </form>
        <div style="display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap;">
            <div class="blog-chips">
                <a href="<?= blog_build_query(['kategori' => '']) ?>" class="blog-chip <?= $akt_kategori === '' ? 'active' : '' ?>">Semua</a>
                <?php foreach ($blog_kategori_list as $k): ?>
                <a href="<?= blog_build_query(['kategori' => $k, 'page' => '']) ?>" class="blog-chip <?= $akt_kategori === $k ? 'active' : '' ?>">
                    <?= sanitize($k) ?>
                    <?php if (!empty($kategor_counts[$k])): ?><span class="cnt"><?= (int)$kategor_counts[$k] ?></span><?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
            <form class="blog-sort" method="get" action="<?= base_url('blog.php') ?>">
                <label for="blogSort">Urutkan</label>
                <select id="blogSort" name="sort" onchange="this.form.submit()">
                    <?php if ($akt_kategori): ?><input type="hidden" name="kategori" value="<?= sanitize($akt_kategori) ?>"><?php endif; ?>
                    <?php if ($akt_q): ?><input type="hidden" name="q" value="<?= sanitize($akt_q) ?>"><?php endif; ?>
                    <?php foreach ($blog_sorts as $val => $lbl): ?>
                    <option value="<?= $val ?>" <?= $akt_sort === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>
</div>

<!-- ===== SOROTAN EDITOR ===== -->
<?php if (!empty($featured)): ?>
<section class="section" style="padding-top:0;">
    <div class="container">
        <div class="section-header" style="text-align:left; max-width:none; margin-bottom:1.75rem;" data-aos="fade-up">
            <span class="section-tag">⭐ Sorotan Editor</span>
            <h2 class="section-title" style="font-size:clamp(1.5rem,3vw,2rem); margin-bottom:.25rem;">Artikel Pilihan Minggu Ini</h2>
        </div>
        <div class="blog-featured-grid">
            <?php foreach ($featured as $f):
                $img = !empty($f['gambar']) ? asset('uploads/blog/' . basename($f['gambar'])) : '';
                $grad = ['linear-gradient(135deg,#0a6847,#16a34a)','linear-gradient(135deg,#16213e,#3b82f6)','linear-gradient(135deg,#f59e0b,#ec4899)'];
                $bg = $img ? "background-image:url('".$img."')" : "background:".$grad[array_rand($grad)];
                $author = trim(($f['gelar_depan'] ?? '') . ' ' . ($f['dosen_nama'] ?? 'Redaksi FKIP') . ' ' . ($f['gelar_belakang'] ?? ''));
                $initial = strtoupper(substr(trim($f['dosen_nama'] ?? 'R'), 0, 1));
            ?>
            <a href="<?= base_url('blog-detail.php?slug=' . urlencode($f['slug'])) ?>" class="blog-feat-card" data-aos="fade-up">
                <div class="blog-feat-bg" style="<?= $bg ?>"></div>
                <div class="blog-feat-overlay"></div>
                <div class="blog-feat-content">
                    <span class="blog-feat-badge">⭐ Sorotan</span>
                    <h3 class="blog-feat-title"><?= sanitize($f['judul']) ?></h3>
                    <p class="blog-feat-excerpt"><?= sanitize(excerpt($f['excerpt'] ?: $f['konten'], 140)) ?></p>
                    <div class="blog-feat-meta">
                        <span class="blog-feat-avatar">
                            <?php if (!empty($f['dosen_foto'])): ?><img src="<?= asset('dosen/' . basename($f['dosen_foto'])) ?>" alt=""><?php else: ?><?= $initial ?><?php endif; ?>
                        </span>
                        <span><?= sanitize($author) ?></span>
                        <span>•</span><span><?= sanitize($f['kategori']) ?></span>
                        <span>•</span><span>📖 <?= (int)($f['reading_time'] ?? 5) ?> mnt</span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ===== GRID ARTIKEL ===== -->
<section class="section" style="padding-top:<?= empty($featured) ? '0' : '0' ?>;">
    <div class="container">
        <?php if ($akt_q !== '' || $akt_kategori !== ''): ?>
        <p style="color:var(--text-muted); font-size:.9rem; margin-bottom:1.5rem;" data-aos="fade-up">
            Menampilkan <strong style="color:var(--text-primary);"><?= count($blogs) ?></strong> dari <strong style="color:var(--text-primary);"><?= $total_rows ?></strong> artikel
            <?php if ($akt_kategori): ?>bertopik <strong style="color:var(--primary);"><?= sanitize($akt_kategori) ?></strong><?php endif; ?>
            <?php if ($akt_q): ?>mencocokkan "<strong style="color:var(--primary);"><?= sanitize($akt_q) ?></strong>"<?php endif; ?>.
            <a href="<?= base_url('blog.php') ?>" style="color:var(--primary); font-weight:700; margin-left:.5rem;">Reset filter ✕</a>
        </p>
        <?php endif; ?>

        <?php if (empty($blogs)): ?>
        <div class="empty-state-premium" data-aos="fade-up">
            <div class="empty-icon-lg">📝</div>
            <h3 style="font-family:var(--font-display); font-size:1.4rem; margin-bottom:.5rem;">Belum ada artikel</h3>
            <p style="color:var(--text-muted);">Coba ubah kata kunci atau pilih kategori lain.</p>
            <a href="<?= base_url('blog.php') ?>" class="btn btn-primary" style="margin-top:1.25rem;">Lihat Semua Artikel</a>
        </div>
        <?php else: ?>
        <div class="blog-grid">
            <?php foreach ($blogs as $i => $b):
                $img = !empty($b['gambar']) ? asset('uploads/blog/' . basename($b['gambar'])) : '';
                $grads = ['linear-gradient(135deg,#0a6847,#16a34a)','linear-gradient(135deg,#16213e,#3b82f6)','linear-gradient(135deg,#f59e0b,#d97706)','linear-gradient(135deg,#8b5cf6,#6d28d9)','linear-gradient(135deg,#ec4899,#db2777)'];
                $author = trim(($b['gelar_depan'] ?? '') . ' ' . ($b['dosen_nama'] ?? 'Redaksi FKIP') . ' ' . ($b['gelar_belakang'] ?? ''));
                $initial = strtoupper(substr(trim($b['dosen_nama'] ?? 'R'), 0, 1));
                $tgl = !empty($b['published_at']) ? format_tanggal_singkat($b['published_at']) : '-';
            ?>
            <a href="<?= base_url('blog-detail.php?slug=' . urlencode($b['slug'])) ?>" class="blog-card" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 80 ?>">
                <div class="blog-card-img">
                    <?php if ($img): ?>
                        <img src="<?= $img ?>" alt="<?= sanitize($b['judul']) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="ph" style="background:<?= $grads[$i % count($grads)] ?>; display:flex; align-items:center; justify-content:center; font-size:3rem; color:#fff;">💡</div>
                    <?php endif; ?>
                    <span class="blog-card-cat"><?= sanitize($b['kategori']) ?></span>
                    <span class="blog-card-rt">📖 <?= (int)($b['reading_time'] ?? 5) ?> mnt</span>
                </div>
                <div class="blog-card-body">
                    <h3 class="blog-card-title"><?= sanitize($b['judul']) ?></h3>
                    <p class="blog-card-excerpt"><?= sanitize(excerpt($b['excerpt'] ?: $b['konten'], 120)) ?></p>
                    <div class="blog-card-foot">
                        <div class="blog-card-author">
                            <span class="blog-card-avatar">
                                <?php if (!empty($b['dosen_foto'])): ?><img src="<?= asset('dosen/' . basename($b['dosen_foto'])) ?>" alt=""><?php else: ?><?= $initial ?><?php endif; ?>
                            </span>
                            <div style="min-width:0;">
                                <div class="blog-card-aname"><?= sanitize($author) ?></div>
                                <div class="blog-card-asub"><?= $tgl ?><?= !empty($b['prodi_singkatan']) ? ' • ' . sanitize($b['prodi_singkatan']) : '' ?></div>
                            </div>
                        </div>
                        <div class="blog-card-stats">
                            <span title="Dibaca">👁 <?= number_format((int)($b['views'] ?? 0)) ?></span>
                            <span title="Disukai">❤️ <?= number_format((int)($b['likes'] ?? 0)) ?></span>
                        </div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
        <nav class="blog-pagination" aria-label="Navigasi halaman">
            <a href="<?= blog_build_query(['page' => $akt_page - 1]) ?>" class="<?= $akt_page <= 1 ? 'disabled' : '' ?>">← Prev</a>
            <?php
            $start = max(1, $akt_page - 2);
            $end   = min($total_pages, $akt_page + 2);
            if ($start > 1) { echo '<a href="'.blog_build_query(['page'=>1]).'">1</a>'; if ($start > 2) echo '<span class="disabled">…</span>'; }
            for ($p = $start; $p <= $end; $p++):
                if ($p === $akt_page) echo '<span class="current">'.$p.'</span>';
                else echo '<a href="'.blog_build_query(['page'=>$p]).'">'.$p.'</a>';
            endfor;
            if ($end < $total_pages) { if ($end < $total_pages - 1) echo '<span class="disabled">…</span>'; echo '<a href="'.blog_build_query(['page'=>$total_pages]).'">'.$total_pages.'</a>'; }
            ?>
            <a href="<?= blog_build_query(['page' => $akt_page + 1]) ?>" class="<?= $akt_page >= $total_pages ? 'disabled' : '' ?>">Next →</a>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<!-- ===== CTA KONTRIBUSI ===== -->
<section class="section" style="padding-top:0;">
    <div class="container">
        <div style="background:linear-gradient(135deg,var(--primary),#064e34); border-radius:var(--radius-xl,16px); padding:3rem 2rem; text-align:center; color:#fff; position:relative; overflow:hidden;" data-aos="zoom-in">
            <div style="position:absolute; inset:0; background:radial-gradient(circle at 20% 50%, rgba(255,255,255,.15), transparent 50%);"></div>
            <div style="position:relative; z-index:2; max-width:640px; margin:0 auto;">
                <h2 style="font-family:var(--font-display); font-size:clamp(1.5rem,3vw,2.25rem); margin-bottom:.75rem; color:#fff;">Ingin Berkontribusi?</h2>
                <p style="opacity:.92; margin-bottom:1.75rem; line-height:1.7;">Dosen, mahasiswa, atau alumni FKIP UNIMOF — bagikan gagasan Anda melalui rubrik Ide &amp; Wawasan.</p>
                <a href="<?= base_url('kontak.php') ?>" class="btn btn-lg" style="background:#fff; color:var(--primary); font-weight:800; border:none;">✍️ Ajukan Tulisan</a>
            </div>
        </div>
    </div>
</section>

<script>
// Count-up untuk statistik hero
(function(){
    function anim(el){var t=parseInt(el.dataset.count)||0,d=1600,s=performance.now();
    (function step(n){var p=Math.min((n-s)/d,1),e=1-Math.pow(1-p,3);el.textContent=Math.floor(e*t).toLocaleString('id-ID');if(p<1)requestAnimationFrame(step);})(s);}
    var o=new IntersectionObserver(function(en){en.forEach(function(x){if(x.isIntersecting){anim(x.target);o.unobserve(x.target);}});},{threshold:.4});
    document.querySelectorAll('.count-up').forEach(function(el){o.observe(el);});
})();
// Shortcut "/" fokus ke search
document.addEventListener('keydown',function(e){
    if(e.key==='/'&&!e.ctrlKey&&!e.metaKey&&['INPUT','TEXTAREA','SELECT'].indexOf(document.activeElement.tagName)<0){
        e.preventDefault();var s=document.querySelector('.blog-search input');if(s)s.focus();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>