<?php
require_once __DIR__ . '/includes/config.php';

// ===== SCHEMA-SAFE: deteksi kolom berita =====
$berita_cols = [];
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `berita`")->fetchAll(PDO::FETCH_COLUMN);
    $berita_cols = [
        'slug'         => in_array('slug', $cols, true),
        'gambar'       => in_array('gambar', $cols, true),
        'konten'       => in_array('konten', $cols, true),
        'excerpt'      => in_array('excerpt', $cols, true),
        'penulis'      => in_array('penulis', $cols, true),
        'views'        => in_array('views', $cols, true),
        'kategori'     => in_array('kategori', $cols, true),
        'status'       => in_array('status', $cols, true),
        'is_featured'  => in_array('is_featured', $cols, true),
        'published_at' => in_array('published_at', $cols, true),
        'created_at'   => in_array('created_at', $cols, true),
    ];
} catch (Exception $e) {
    $berita_cols = array_fill_keys(['slug','gambar','konten','excerpt','penulis','views','kategori','status','is_featured','published_at','created_at'], false);
}

$slug = trim($_GET['slug'] ?? '');
if ($slug === '' || !$berita_cols['slug']) {
    header('Location: ' . base_url('berita.php'));
    exit;
}

$where = "WHERE slug = ?";
if ($berita_cols['status']) $where .= " AND status = 'Published'";

$stmt = $pdo->prepare("SELECT * FROM berita $where LIMIT 1");
$stmt->execute([$slug]);
$b = $stmt->fetch();

if (!$b) {
    header('Location: ' . base_url('berita.php'));
    exit;
}

// Increment views
if ($berita_cols['views']) {
    try {
        $pdo->prepare("UPDATE berita SET views = views + 1 WHERE id = ?")->execute([$b['id']]);
        $b['views'] = ($b['views'] ?? 0) + 1;
    } catch (Exception $e) {}
}

// Related news
$related = [];
if ($berita_cols['kategori'] && !empty($b['kategori'])) {
    try {
        $relWhere = "WHERE status = 'Published' AND id != ? AND kategori = ?";
        if (!$berita_cols['status']) $relWhere = "WHERE id != ? AND kategori = ?";
        $relStmt = $pdo->prepare("SELECT judul, slug, published_at, gambar, kategori FROM berita $relWhere ORDER BY published_at DESC LIMIT 4");
        $relStmt->execute([$b['id'], $b['kategori']]);
        $related = $relStmt->fetchAll();
    } catch (Exception $e) {}
}

// Prev/Next navigation
$prev_news = null;
$next_news = null;
try {
    if ($berita_cols['published_at']) {
        $prevStmt = $pdo->prepare("SELECT judul, slug FROM berita WHERE status='Published' AND published_at < ? ORDER BY published_at DESC LIMIT 1");
        $prevStmt->execute([$b['published_at']]);
        $prev_news = $prevStmt->fetch();

        $nextStmt = $pdo->prepare("SELECT judul, slug FROM berita WHERE status='Published' AND published_at > ? ORDER BY published_at ASC LIMIT 1");
        $nextStmt->execute([$b['published_at']]);
        $next_news = $nextStmt->fetch();
    }
} catch (Exception $e) {}

// Reading time
$word_count = $berita_cols['konten'] ? str_word_count(strip_tags($b['konten'] ?? '')) : 0;
$read_time = max(1, ceil($word_count / 200));

$page_title = sanitize($b['judul']);
$page_description = excerpt($b['excerpt'] ?? $b['konten'] ?? '', 160);
require_once __DIR__ . '/includes/header.php';
?>

<!-- Reading Progress Bar -->
<div class="reading-progress-container">
    <div class="reading-progress-bar" id="readingProgressBar"></div>
</div>

<!-- Article Hero -->
<section class="article-hero-extreme" style="<?= $berita_cols['gambar'] && !empty($b['gambar']) ? 'background-image: linear-gradient(to bottom, rgba(10,104,71,0.7), rgba(15,23,42,0.95)), url(' . asset('uploads/' . basename($b['gambar'])) . '); background-size: cover; background-position: center;' : '' ?>">
    <div class="container">
        <nav class="breadcrumb breadcrumb-light" data-aos="fade-down">
            <a href="<?= base_url() ?>">Beranda</a><span>›</span>
            <a href="<?= base_url('berita.php') ?>">Berita</a><span>›</span>
            <?php if ($berita_cols['kategori'] && !empty($b['kategori'])): ?>
                <a href="<?= base_url('berita.php?kategori=' . urlencode($b['kategori'])) ?>"><?= sanitize($b['kategori']) ?></a><span>›</span>
            <?php endif; ?>
            <span><?= excerpt($b['judul'], 30) ?></span>
        </nav>

        <div class="article-hero-content" data-aos="fade-up">
            <?php if ($berita_cols['kategori'] && !empty($b['kategori'])): ?>
                <a href="<?= base_url('berita.php?kategori=' . urlencode($b['kategori'])) ?>" class="article-category-badge"><?= sanitize($b['kategori']) ?></a>
            <?php endif; ?>
            <h1 class="article-main-title"><?= sanitize($b['judul']) ?></h1>

            <div class="article-hero-meta">
                <div class="meta-author">
                    <div class="author-avatar"><?= strtoupper(substr($b['penulis'] ?? 'A', 0, 1)) ?></div>
                    <div>
                        <span class="author-name"><?= sanitize($b['penulis'] ?? 'Humas FKIP') ?></span>
                        <span class="publish-date"><?= format_tanggal($b['published_at'] ?? $b['created_at'] ?? '') ?></span>
                    </div>
                </div>
                <div class="meta-stats">
                    <span class="meta-stat">⏱️ <?= $read_time ?> menit baca</span>
                    <?php if ($berita_cols['views']): ?>
                        <span class="meta-stat">👁️ <?= number_format($b['views'] ?? 0) ?> dibaca</span>
                    <?php endif; ?>
                    <span class="meta-stat">📝 <?= number_format($word_count) ?> kata</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Reading Mode Toggle (Floating) -->
<div class="reading-controls" id="readingControls">
    <button class="reading-ctrl-btn" onclick="decreaseFontSize()" title="Perkecil font">A-</button>
    <button class="reading-ctrl-btn" onclick="increaseFontSize()" title="Perbesar font">A+</button>
    <button class="reading-ctrl-btn" onclick="toggleReadingMode()" title="Mode baca" id="readingModeBtn">🌙</button>
    <button class="reading-ctrl-btn" onclick="printArticle()" title="Cetak">🖨️</button>
</div>

<!-- Main Content -->
<section class="section article-section-extreme">
    <div class="container">
        <div class="article-layout">

            <!-- Left: Article Content -->
            <main class="article-main-extreme">
                <article class="article-content-prose" id="articleContent">
                    <?= clean_html($b['konten'] ?? '') ?>
                </article>

                <!-- Tags -->
                <?php if ($berita_cols['kategori'] && !empty($b['kategori'])): ?>
                <div class="article-tags-extreme">
                    <span class="tag-label">Kategori:</span>
                    <a href="<?= base_url('berita.php?kategori=' . urlencode($b['kategori'])) ?>" class="tag-pill-extreme"><?= sanitize($b['kategori']) ?></a>
                </div>
                <?php endif; ?>

                <!-- Share Box -->
                <div class="share-box-extreme">
                    <h4>💬 Bagikan artikel ini</h4>
                    <div class="share-buttons-extreme">
                        <button class="share-btn-extreme wa" onclick="shareTo('wa')">💬 WhatsApp</button>
                        <button class="share-btn-extreme fb" onclick="shareTo('fb')">📘 Facebook</button>
                        <button class="share-btn-extreme tw" onclick="shareTo('tw')">🐦 Twitter</button>
                        <button class="share-btn-extreme li" onclick="shareTo('li')">💼 LinkedIn</button>
                        <button class="share-btn-extreme copy" onclick="copyLink(this)">🔗 Salin Link</button>
                    </div>
                </div>

                <!-- Article Navigation (Prev/Next) -->
                <div class="article-nav-extreme">
                    <?php if ($prev_news): ?>
                    <a href="<?= base_url('berita-detail.php?slug=' . urlencode($prev_news['slug'])) ?>" class="article-nav-item prev">
                        <span class="nav-direction">← Sebelumnya</span>
                        <span class="nav-title"><?= sanitize($prev_news['judul']) ?></span>
                    </a>
                    <?php else: ?>
                    <div></div>
                    <?php endif; ?>
                    <?php if ($next_news): ?>
                    <a href="<?= base_url('berita-detail.php?slug=' . urlencode($next_news['slug'])) ?>" class="article-nav-item next">
                        <span class="nav-direction">Selanjutnya →</span>
                        <span class="nav-title"><?= sanitize($next_news['judul']) ?></span>
                    </a>
                    <?php endif; ?>
                </div>

                <!-- Author Bio -->
                <div class="author-bio-card-extreme">
                    <div class="bio-avatar-extreme"><?= strtoupper(substr($b['penulis'] ?? 'A', 0, 1)) ?></div>
                    <div class="bio-info-extreme">
                        <h4>Ditulis oleh <?= sanitize($b['penulis'] ?? 'Tim Redaksi FKIP') ?></h4>
                        <p>Artikel ini diterbitkan oleh Fakultas Keguruan dan Ilmu Pendidikan Universitas Muhammadiyah Maumere. Kami berkomitmen menyajikan informasi akademik yang akurat, inspiratif, dan bermanfaat.</p>
                    </div>
                </div>
            </main>

            <!-- Right: Sticky Sidebar -->
            <aside class="article-sidebar-extreme">
                <div class="sidebar-sticky">

                    <!-- Auto-generated Table of Contents -->
                    <div class="sidebar-card-extreme toc-card-extreme" id="tocCard" style="display:none;">
                        <h3 class="sidebar-title-extreme">📑 Daftar Isi</h3>
                        <ul class="toc-list-extreme" id="tocList"></ul>
                    </div>

                    <!-- Reading Progress Info -->
                    <div class="sidebar-card-extreme progress-card">
                        <h3 class="sidebar-title-extreme">📖 Progress Baca</h3>
                        <div class="progress-info">
                            <div class="progress-stat">
                                <span class="progress-label">Progress</span>
                                <span class="progress-value" id="progressPercent">0%</span>
                            </div>
                            <div class="progress-bar-wrap">
                                <div class="progress-bar-fill" id="progressBarFill" style="width: 0%"></div>
                            </div>
                            <div class="progress-stat">
                                <span class="progress-label">Sisa waktu</span>
                                <span class="progress-value" id="progressRemaining"><?= $read_time ?> mnt</span>
                            </div>
                        </div>
                    </div>

                    <!-- Related News -->
                    <div class="sidebar-card-extreme">
                        <h3 class="sidebar-title-extreme">📰 Berita Terkait</h3>
                        <?php if (empty($related)): ?>
                            <p class="empty-small" style="color:var(--text-muted); font-size:.85rem;">Belum ada berita di kategori ini.</p>
                        <?php else: ?>
                            <div class="related-list-extreme">
                                <?php foreach ($related as $r):
                                    $hue = crc32($r['kategori'] ?? 'umum') % 360;
                                ?>
                                <a href="<?= base_url('berita-detail.php?slug=' . urlencode($r['slug'])) ?>" class="related-item-extreme">
                                    <?php if ($berita_cols['gambar'] && !empty($r['gambar'])): ?>
                                        <img src="<?= asset('uploads/' . basename($r['gambar'])) ?>" alt="" class="related-img-extreme" loading="lazy">
                                    <?php else: ?>
                                        <div class="related-img-placeholder-extreme" style="background:linear-gradient(135deg, hsl(<?= $hue ?>,60%,50%), hsl(<?= ($hue+40)%360 ?>,60%,40%))">📰</div>
                                    <?php endif; ?>
                                    <div class="related-info-extreme">
                                        <h4><?= sanitize($r['judul']) ?></h4>
                                        <span><?= format_tanggal_singkat($r['published_at'] ?? '') ?></span>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- CTA Card -->
                    <div class="sidebar-card-extreme cta-card-extreme">
                        <h3>Butuh Informasi Lebih Lanjut?</h3>
                        <p>Hubungi kami untuk pertanyaan seputar akademik, PMB, atau kerjasama institusi.</p>
                        <a href="<?= base_url('kontak.php') ?>" class="btn btn-primary btn-block">💬 Hubungi Kami</a>
                    </div>
                </div>
            </aside>

        </div>
    </div>
</section>

<!-- Toast -->
<div class="toast-extreme" id="toast">✅ Link berhasil disalin!</div>

<style>
/* Reading Progress */
.reading-progress-container {
    position: fixed; top: 0; left: 0; width: 100%; height: 4px;
    background: rgba(0,0,0,0.1); z-index: 9999;
}
.reading-progress-bar {
    height: 100%; width: 0%; background: linear-gradient(90deg, #1e40af, #3b82f6, #60a5fa);
    transition: width 0.1s linear;
}

/* Article Hero */
.article-hero-extreme {
    position: relative; padding: 8rem 0 4rem; color: white;
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    background-color: var(--accent);
}
.article-hero-extreme::before {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(to bottom, rgba(30,64,175,0.6), rgba(15,23,42,0.95));
    z-index: 1;
}
.article-hero-extreme .container { position: relative; z-index: 2; }
.breadcrumb-light a, .breadcrumb-light span { color: rgba(255,255,255,0.75); }
.breadcrumb-light a:hover { color: white; }

.article-category-badge {
    display: inline-block; padding: 0.4rem 1rem;
    background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2); border-radius: 999px;
    font-size: 0.78rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.05em; margin-bottom: 1rem; color: white; text-decoration: none;
    transition: all 0.2s;
}
.article-category-badge:hover { background: rgba(255,255,255,0.25); color: white; }
.article-main-title {
    font-family: var(--font-display); font-size: clamp(2rem, 5vw, 3.5rem);
    font-weight: 900; line-height: 1.15; margin-bottom: 1.5rem;
    letter-spacing: -0.02em;
}
.article-hero-meta {
    display: flex; flex-wrap: wrap; gap: 2rem; align-items: center;
    padding-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.15);
}
.meta-author { display: flex; align-items: center; gap: 0.75rem; }
.author-avatar {
    width: 48px; height: 48px; border-radius: 50%;
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; color: white; font-size: 1.1rem;
}
.author-name { display: block; font-weight: 700; font-size: 0.95rem; }
.publish-date { display: block; font-size: 0.8rem; opacity: 0.8; }
.meta-stats { display: flex; gap: 0.75rem; flex-wrap: wrap; }
.meta-stat {
    display: flex; align-items: center; gap: 0.4rem;
    font-size: 0.82rem; opacity: 0.9; background: rgba(255,255,255,0.1);
    padding: 0.4rem 0.8rem; border-radius: 999px;
    backdrop-filter: blur(10px);
}

/* Reading Controls (Floating) */
.reading-controls {
    position: fixed; top: 50%; right: 1.5rem; transform: translateY(-50%);
    display: flex; flex-direction: column; gap: 0.5rem; z-index: 1000;
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 0.5rem;
    box-shadow: var(--shadow-lg);
}
.reading-ctrl-btn {
    width: 40px; height: 40px; border-radius: 8px; border: none;
    background: var(--bg-secondary); color: var(--text-primary);
    font-size: 0.95rem; font-weight: 700; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.2s; font-family: inherit;
}
.reading-ctrl-btn:hover { background: #3b82f6; color: white; transform: scale(1.05); }

/* Article Layout */
.article-section-extreme { padding-top: 3rem; }
.article-layout {
    display: grid; grid-template-columns: 1fr 340px; gap: 3rem; align-items: start;
}
.article-main-extreme { min-width: 0; }

/* Prose Typography */
.article-content-prose {
    font-size: 1.125rem; line-height: 1.85; color: var(--text-primary);
}
.article-content-prose p { margin-bottom: 1.5rem; }
.article-content-prose h2 {
    font-family: var(--font-display); font-size: 1.75rem; font-weight: 800;
    margin: 2.5rem 0 1rem; color: #1e40af; padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--bg-tertiary);
}
.article-content-prose h3 {
    font-size: 1.35rem; font-weight: 700; margin: 2rem 0 0.75rem; color: var(--text-primary);
}
.article-content-prose ul, .article-content-prose ol {
    margin-bottom: 1.5rem; padding-left: 1.5rem;
}
.article-content-prose li { margin-bottom: 0.5rem; }
.article-content-prose blockquote {
    border-left: 4px solid #3b82f6; background: var(--bg-secondary);
    padding: 1.25rem 1.5rem; margin: 2rem 0; border-radius: 0 var(--radius-md) var(--radius-md) 0;
    font-style: italic; color: var(--text-secondary); font-size: 1.1rem;
}
.article-content-prose img {
    max-width: 100%; height: auto; border-radius: var(--radius-lg);
    margin: 2rem 0; box-shadow: var(--shadow-md);
}
.article-content-prose a {
    color: #3b82f6; text-decoration: underline; text-decoration-color: rgba(59,130,246,0.3);
    text-underline-offset: 3px; transition: text-decoration-color 0.2s;
}
.article-content-prose a:hover { text-decoration-color: #3b82f6; }

/* Drop Cap */
.article-content-prose > p:first-of-type::first-letter {
    float: left; font-size: 3.5rem; line-height: 0.8; font-weight: 800;
    color: #1e40af; margin-right: 0.5rem; margin-top: 0.1rem;
    font-family: var(--font-display);
}

/* Reading Mode */
body.reading-mode { background: #f5f5dc; }
body.reading-mode .article-content-prose { color: #333; font-size: 1.2rem; line-height: 2; }
body.reading-mode .article-content-prose h2 { color: #1a365d; }
body.reading-mode .article-sidebar-extreme { display: none; }
body.reading-mode .reading-controls { background: #fff8dc; }

/* Tags */
.article-tags-extreme {
    margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--border);
    display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;
}
.tag-label { font-weight: 600; color: var(--text-muted); font-size: 0.9rem; }
.tag-pill-extreme {
    padding: 0.4rem 1rem; background: var(--bg-secondary); color: #3b82f6;
    border-radius: 999px; font-size: 0.85rem; font-weight: 600; text-decoration: none;
    transition: all 0.2s; border: 1px solid var(--border);
}
.tag-pill-extreme:hover { background: #3b82f6; color: white; border-color: #3b82f6; }

/* Share Box */
.share-box-extreme {
    margin-top: 2rem; padding: 1.5rem; background: var(--bg-secondary);
    border-radius: var(--radius-lg); border: 1px solid var(--border);
}
.share-box-extreme h4 { margin-bottom: 1rem; font-size: 1rem; }
.share-buttons-extreme { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.share-btn-extreme {
    padding: 0.6rem 1rem; border-radius: var(--radius-md); border: none;
    font-weight: 600; font-size: 0.82rem; cursor: pointer; transition: all 0.2s;
    display: inline-flex; align-items: center; gap: 0.5rem; font-family: inherit;
}
.share-btn-extreme.wa { background: #25D366; color: white; }
.share-btn-extreme.fb { background: #1877F2; color: white; }
.share-btn-extreme.tw { background: #1DA1F2; color: white; }
.share-btn-extreme.li { background: #0A66C2; color: white; }
.share-btn-extreme.copy { background: var(--bg-primary); color: var(--text-primary); border: 1px solid var(--border); }
.share-btn-extreme:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); filter: brightness(1.1); }

/* Article Navigation */
.article-nav-extreme {
    display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;
    margin-top: 2.5rem; padding-top: 2rem; border-top: 1px solid var(--border);
}
.article-nav-item {
    padding: 1.25rem; background: var(--bg-secondary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); text-decoration: none; transition: all 0.3s;
    display: flex; flex-direction: column; gap: 0.5rem;
}
.article-nav-item:hover { border-color: #3b82f6; transform: translateY(-3px); box-shadow: var(--shadow-md); }
.article-nav-item.next { text-align: right; }
.nav-direction { font-size: 0.75rem; color: #3b82f6; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
.nav-title {
    font-size: 0.95rem; font-weight: 600; color: var(--text-primary); line-height: 1.4;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}

/* Author Bio */
.author-bio-card-extreme {
    margin-top: 2.5rem; display: flex; gap: 1.25rem; padding: 1.5rem;
    background: linear-gradient(135deg, var(--bg-secondary), var(--bg-primary));
    border: 1px solid var(--border); border-radius: var(--radius-lg);
}
.bio-avatar-extreme {
    width: 60px; height: 60px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; font-weight: 800;
}
.bio-info-extreme h4 { font-size: 1.1rem; margin-bottom: 0.5rem; }
.bio-info-extreme p { font-size: 0.9rem; color: var(--text-secondary); line-height: 1.6; margin: 0; }

/* Sidebar */
.sidebar-sticky { position: sticky; top: 100px; display: flex; flex-direction: column; gap: 1.25rem; }
.sidebar-card-extreme {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.25rem;
}
.sidebar-title-extreme {
    font-size: 1rem; font-weight: 700; margin-bottom: 1rem;
    padding-bottom: 0.75rem; border-bottom: 2px solid var(--bg-tertiary);
}

/* Progress Card */
.progress-card .progress-info { display: flex; flex-direction: column; gap: 0.75rem; }
.progress-stat { display: flex; justify-content: space-between; font-size: 0.85rem; }
.progress-label { color: var(--text-muted); }
.progress-value { font-weight: 700; color: #3b82f6; font-family: 'JetBrains Mono', monospace; }
.progress-bar-wrap { height: 8px; background: var(--bg-tertiary); border-radius: 999px; overflow: hidden; }
.progress-bar-fill {
    height: 100%; background: linear-gradient(90deg, #3b82f6, #1e40af);
    border-radius: 999px; transition: width 0.3s ease;
}

/* TOC */
.toc-list-extreme { list-style: none; padding: 0; margin: 0; }
.toc-list-extreme li { margin-bottom: 0.4rem; }
.toc-list-extreme a {
    display: block; padding: 0.5rem 0.75rem; color: var(--text-secondary);
    text-decoration: none; font-size: 0.88rem; border-left: 3px solid transparent;
    border-radius: 0 var(--radius-sm) var(--radius-sm) 0; transition: all 0.2s;
}
.toc-list-extreme a:hover { color: #3b82f6; background: var(--bg-secondary); }
.toc-list-extreme a.active {
    color: #3b82f6; font-weight: 600; border-left-color: #3b82f6;
    background: rgba(59,130,246,0.05);
}
.toc-list-extreme .toc-h3 { padding-left: 1.5rem; font-size: 0.82rem; }

/* Related */
.related-list-extreme { display: flex; flex-direction: column; gap: 0.75rem; }
.related-item-extreme {
    display: flex; gap: 0.75rem; text-decoration: none; color: inherit;
    padding: 0.5rem; border-radius: var(--radius-md); transition: all 0.2s;
}
.related-item-extreme:hover { background: var(--bg-secondary); transform: translateX(3px); }
.related-img-extreme, .related-img-placeholder-extreme {
    width: 70px; height: 70px; border-radius: var(--radius-sm);
    object-fit: cover; flex-shrink: 0;
}
.related-img-placeholder-extreme {
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; color: white;
}
.related-info-extreme h4 {
    font-size: 0.88rem; font-weight: 600; line-height: 1.4; margin-bottom: 0.25rem;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    color: var(--text-primary);
}
.related-info-extreme span { font-size: 0.72rem; color: var(--text-muted); }

/* CTA */
.cta-card-extreme {
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white; border: none; text-align: center;
}
.cta-card-extreme h3 { color: white; border-bottom-color: rgba(255,255,255,0.2); }
.cta-card-extreme p { font-size: 0.88rem; opacity: 0.9; margin-bottom: 1.25rem; line-height: 1.6; }
.cta-card-extreme .btn-primary {
    background: white; color: #1e40af; font-weight: 700;
}
.cta-card-extreme .btn-primary:hover { background: #fbbf24; color: white; }

/* Toast */
.toast-extreme {
    position: fixed; bottom: 2rem; left: 50%; transform: translateX(-50%) translateY(100px);
    background: var(--dark); color: white; padding: 0.85rem 1.5rem;
    border-radius: 999px; font-size: 0.9rem; font-weight: 600;
    box-shadow: var(--shadow-xl); opacity: 0; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    z-index: 10000; display: flex; align-items: center; gap: 0.5rem;
}
.toast-extreme.show { transform: translateX(-50%) translateY(0); opacity: 1; }

/* Print Styles */
@media print {
    .reading-controls, .article-sidebar-extreme, .share-box-extreme,
    .article-nav-extreme, .breadcrumb-light, .reading-progress-container { display: none !important; }
    .article-hero-extreme { background: none !important; color: black !important; padding: 2rem 0 !important; }
    .article-hero-extreme::before { display: none; }
    .article-layout { grid-template-columns: 1fr !important; }
    body { font-size: 12pt; }
}

@media (max-width: 968px) {
    .article-layout { grid-template-columns: 1fr; }
    .article-sidebar-extreme { order: 2; }
    .article-main-extreme { order: 1; }
    .sidebar-sticky { position: static; }
    .article-hero-extreme { padding: 6rem 0 3rem; }
    .article-content-prose > p:first-of-type::first-letter { font-size: 2.5rem; }
    .reading-controls { top: auto; bottom: 1rem; right: 1rem; transform: none; flex-direction: row; }
    .article-nav-extreme { grid-template-columns: 1fr; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const progressBar = document.getElementById('readingProgressBar');
    const progressBarFill = document.getElementById('progressBarFill');
    const progressPercent = document.getElementById('progressPercent');
    const progressRemaining = document.getElementById('progressRemaining');
    const articleContent = document.getElementById('articleContent');
    const totalReadTime = <?= $read_time ?>;

    // Reading Progress
    window.addEventListener('scroll', () => {
        if (!articleContent) return;
        const rect = articleContent.getBoundingClientRect();
        const totalHeight = rect.height + window.innerHeight;
        const scrolled = window.innerHeight - rect.top;
        const progress = Math.max(0, Math.min(100, (scrolled / totalHeight) * 100));

        progressBar.style.width = progress + '%';
        if (progressBarFill) progressBarFill.style.width = progress + '%';
        if (progressPercent) progressPercent.textContent = Math.round(progress) + '%';
        if (progressRemaining) {
            const remaining = Math.max(0, Math.round(totalReadTime * (1 - progress/100)));
            progressRemaining.textContent = remaining + ' mnt';
        }
    });

    // Auto-generate TOC
    const headings = articleContent.querySelectorAll('h2, h3');
    const tocList = document.getElementById('tocList');
    const tocCard = document.getElementById('tocCard');

    if (headings.length >= 2) {
        tocCard.style.display = 'block';
        headings.forEach((heading, index) => {
            if (!heading.id) heading.id = 'heading-' + index;

            const li = document.createElement('li');
            const a = document.createElement('a');
            a.href = '#' + heading.id;
            a.textContent = heading.textContent;
            a.classList.add(heading.tagName.toLowerCase() === 'h3' ? 'toc-h3' : 'toc-h2');

            a.addEventListener('click', (e) => {
                e.preventDefault();
                heading.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });

            li.appendChild(a);
            tocList.appendChild(li);
        });

        // Active TOC highlight
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    document.querySelectorAll('.toc-list-extreme a').forEach(a => a.classList.remove('active'));
                    const activeLink = document.querySelector(`.toc-list-extreme a[href="#${entry.target.id}"]`);
                    if (activeLink) activeLink.classList.add('active');
                }
            });
        }, { rootMargin: '-100px 0px -60% 0px' });

        headings.forEach(heading => observer.observe(heading));
    }
});

// Font Size Controls
let currentFontSize = 1.125;
function increaseFontSize() {
    if (currentFontSize < 1.5) {
        currentFontSize += 0.1;
        document.querySelector('.article-content-prose').style.fontSize = currentFontSize + 'rem';
    }
}
function decreaseFontSize() {
    if (currentFontSize > 0.9) {
        currentFontSize -= 0.1;
        document.querySelector('.article-content-prose').style.fontSize = currentFontSize + 'rem';
    }
}

// Reading Mode Toggle
let readingMode = false;
function toggleReadingMode() {
    readingMode = !readingMode;
    document.body.classList.toggle('reading-mode', readingMode);
    document.getElementById('readingModeBtn').textContent = readingMode ? '☀️' : '🌙';
}

// Print
function printArticle() {
    window.print();
}

// Share
function shareTo(platform) {
    const url = encodeURIComponent(window.location.href);
    const text = encodeURIComponent(<?= json_encode(sanitize($b['judul'])) ?>);
    let shareUrl = '';
    if (platform === 'wa') shareUrl = `https://wa.me/?text=${text}%20${url}`;
    if (platform === 'fb') shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
    if (platform === 'tw') shareUrl = `https://twitter.com/intent/tweet?text=${text}&url=${url}`;
    if (platform === 'li') shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${url}`;
    if (shareUrl) window.open(shareUrl, '_blank', 'width=600,height=400');
}

function copyLink(btn) {
    navigator.clipboard.writeText(window.location.href).then(() => {
        const toast = document.getElementById('toast');
        toast.classList.add('show');
        const originalText = btn.innerHTML;
        btn.innerHTML = '✅ Tersalin!';
        setTimeout(() => {
            toast.classList.remove('show');
            btn.innerHTML = originalText;
        }, 2500);
    });
}

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    if (e.key === 'r' && !e.ctrlKey && !e.metaKey && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
        toggleReadingMode();
    }
    if (e.key === '+' && e.ctrlKey) { e.preventDefault(); increaseFontSize(); }
    if (e.key === '-' && e.ctrlKey) { e.preventDefault(); decreaseFontSize(); }
});

console.log('%c📖 Berita Detail FKIP UNIMOF - EXTREME MULTIMATE', 'color:#1e40af;font-size:16px;font-weight:bold');
console.log('%cShortcuts: R (Reading mode) • Ctrl+/- (Font size)', 'color:#64748b');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>