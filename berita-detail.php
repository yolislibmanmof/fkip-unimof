<?php
require_once __DIR__ . '/includes/config.php';

$slug = trim($_GET['slug'] ?? '');
$stmt = $pdo->prepare("SELECT * FROM berita WHERE slug = ? AND status = 'Published' LIMIT 1");
$stmt->execute([$slug]);
$b = $stmt->fetch();

if (!$b) {
    header('Location: ' . base_url('berita.php'));
    exit;
}

// Tambah views (aman dengan prepared statement)
$pdo->prepare("UPDATE berita SET views = views + 1 WHERE id = ?")->execute([$b['id']]);

// Berita terkait
$relStmt = $pdo->prepare("SELECT judul, slug, published_at, gambar FROM berita WHERE status = 'Published' AND id != ? AND kategori = ? ORDER BY published_at DESC LIMIT 3");
$relStmt->execute([$b['id'], $b['kategori']]);
$related = $relStmt->fetchAll();

// Hitung waktu baca (200 kata per menit)
$word_count = str_word_count(strip_tags($b['konten'] ?? ''));
$read_time = max(1, ceil($word_count / 200));

$page_title = sanitize($b['judul']);
$page_description = excerpt($b['excerpt'] ?? $b['konten'], 160);
require_once __DIR__ . '/includes/header.php';
?>

<!-- Reading Progress Bar -->
<div class="reading-progress-container">
    <div class="reading-progress-bar" id="readingProgressBar"></div>
</div>

<!-- Hero Section dengan Background Image (jika ada) -->
<section class="article-hero" style="<?= !empty($b['gambar']) ? 'background-image: linear-gradient(to bottom, rgba(10,104,71,0.7), rgba(15,23,42,0.95)), url(' . asset('uploads/' . basename($b['gambar'])) . '); background-size: cover; background-position: center;' : '' ?>">
    <div class="container">
        <nav class="breadcrumb breadcrumb-light" data-aos="fade-down">
            <a href="<?= base_url() ?>">Beranda</a><span>›</span>
            <a href="<?= base_url('berita.php') ?>">Berita</a><span>›</span>
            <span><?= excerpt($b['judul'], 30) ?></span>
        </nav>
        
        <div class="article-hero-content" data-aos="fade-up">
            <span class="article-category-badge"><?= sanitize($b['kategori']) ?></span>
            <h1 class="article-main-title"><?= sanitize($b['judul']) ?></h1>
            
            <div class="article-hero-meta">
                <div class="meta-author">
                    <div class="author-avatar"><?= strtoupper(substr($b['penulis'] ?? 'A', 0, 1)) ?></div>
                    <div>
                        <span class="author-name"><?= sanitize($b['penulis'] ?? 'Humas FKIP') ?></span>
                        <span class="publish-date"><?= format_tanggal($b['published_at'] ?? $b['created_at']) ?></span>
                    </div>
                </div>
                <div class="meta-stats">
                    <span class="meta-stat">⏱️ <?= $read_time ?> menit baca</span>
                    <span class="meta-stat">👁 <?= number_format($b['views']) ?> dibaca</span>
                </div
            </div>
        </div>
    </div>
</section>

<!-- Main Content Section -->
<section class="section article-section">
    <div class="container">
        <div class="article-layout">
            
            <!-- Left: Article Content -->
            <main class="article-main">
                <article class="article-content-prose" id="articleContent">
                    <?= clean_html($b['konten'] ?? '') ?>
                </article>

                <!-- Tags -->
                <?php if (!empty($b['kategori'])): ?>
                <div class="article-tags">
                    <span class="tag-label">Kategori:</span>
                    <a href="<?= base_url('berita.php?kategori=' . urlencode($b['kategori'])) ?>" class="tag-pill"><?= sanitize($b['kategori']) ?></a>
                </div>
                <?php endif; ?>

                <!-- Share Box -->
                <div class="share-box">
                    <h4>Bagikan artikel ini</h4>
                    <div class="share-buttons">
                        <button class="share-btn wa" onclick="shareTo('wa')">💬 WhatsApp</button>
                        <button class="share-btn fb" onclick="shareTo('fb')">📘 Facebook</button>
                        <button class="share-btn copy" onclick="copyLink(this)">🔗 Salin Link</button>
                    </div>
                </div>

                <!-- Author Bio -->
                <div class="author-bio-card">
                    <div class="bio-avatar"><?= strtoupper(substr($b['penulis'] ?? 'A', 0, 1)) ?></div>
                    <div class="bio-info">
                        <h4>Ditulis oleh <?= sanitize($b['penulis'] ?? 'Tim Redaksi FKIP') ?></h4>
                        <p>Artikel ini diterbitkan oleh Fakultas Keguruan dan Ilmu Pendidikan Universitas Muhammadiyah Maumere. Kami berkomitmen menyajikan informasi akademik yang akurat, inspiratif, dan bermanfaat.</p>
                    </div>
                </div>
            </main>

            <!-- Right: Sticky Sidebar -->
            <aside class="article-sidebar">
                <div class="sidebar-sticky">
                    
                    <!-- Auto-generated Table of Contents -->
                    <div class="sidebar-card toc-card" id="tocCard" style="display:none;">
                        <h3 class="toc-title">📑 Daftar Isi</h3>
                        <ul class="toc-list" id="tocList"></ul a>
                    </div>

                    <!-- Related News -->
                    <div class="sidebar-card">
                        <h3 class="sidebar-title">Berita Terkait</h3>
                        <?php if (empty($related)): ?>
                            <p class="empty-small" style="color:var(--text-muted);font-size:.85rem;">Belum ada berita di kategori ini.</p>
                        <?php else: ?>
                            <div class="related-list">
                                <?php foreach ($related as $r): 
                                    $hue = crc32($r['kategori']) % 360;
                                ?>
                                <a href="<?= base_url('berita-detail.php?slug=' . urlencode($r['slug'])) ?>" class="related-item">
                                    <?php if (!empty($r['gambar'])): ?>
                                        <img src="<?= asset('uploads/' . basename($r['gambar'])) ?>" alt="" class="related-img" loading="lazy">
                                    <?php else: ?>
                                        <div class="related-img-placeholder" style="background:linear-gradient(135deg, hsl(<?= $hue ?>,60%,50%), hsl(<?= ($hue+40)%360 ?>,60%,40%))">📰</div>
                                    <?php endif; ?>
                                    <div class="related-info">
                                        <h4><?= sanitize($r['judul']) ?></h4>
                                        <span><?= format_tanggal_singkat($r['published_at']) ?></span>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- CTA Card -->
                    <div class="sidebar-card cta-card">
                        <h3>Butuh Informasi Lebih Lanjut?</h3>
                        <p>Hubungi kami untuk pertanyaan seputar akademik, PMB, atau kerjasama institusi.</p>
                        <a href="<?= base_url('kontak.php') ?>" class="btn btn-primary btn-block">Hubungi Kami</a>
                    </div>
                </div>
            </aside>

        </div>
    </div>
</section>

<!-- Toast Notification -->
<div class="toast" id="toast">✅ Link berhasil disalin ke clipboard!</div>

<!-- ===== SCOPED STYLES ===== -->
<style>
/* Reading Progress Bar */
.reading-progress-container {
    position: fixed; top: 0; left: 0; width: 100%; height: 4px;
    background: rgba(0,0,0,0.1); z-index: 9999;
}
.reading-progress-bar {
    height: 100%; width: 0%; background: linear-gradient(90deg, var(--primary), var(--secondary));
    transition: width 0.1s linear;
}

/* Article Hero */
.article-hero {
    position: relative; padding: 8rem 0 4rem; color: white;
    background-color: var(--accent); /* Fallback jika tidak ada gambar */
}
.article-hero::before {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(to bottom, rgba(10,104,71,0.6), rgba(15,23,42,0.95));
    z-index: 1;
}
.article-hero .container { position: relative; z-index: 2; }
.breadcrumb-light a, .breadcrumb-light span { color: rgba(255,255,255,0.75); }
.breadcrumb-light a:hover { color: white; }

.article-category-badge {
    display: inline-block; padding: 0.4rem 1rem;
    background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2); border-radius: 999px;
    font-size: 0.8rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.05em; margin-bottom: 1rem;
}
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
    background: linear-gradient(135deg, var(--secondary), #fbbf24);
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; color: white; font-size: 1.1rem;
}
.author-name { display: block; font-weight: 700; font-size: 0.95rem; }
.publish-date { display: block; font-size: 0.8rem; opacity: 0.8; }
.meta-stats { display: flex; gap: 1rem; }
.meta-stat {
    display: flex; align-items: center; gap: 0.4rem;
    font-size: 0.85rem; opacity: 0.9; background: rgba(255,255,255,0.1);
    padding: 0.4rem 0.8rem; border-radius: 999px;
}

/* Article Layout */
.article-layout {
    display: grid; grid-template-columns: 1fr 340px; gap: 3rem; align-items: start;
}
.article-main { min-width: 0; }

/* Prose Typography (Premium Reading Experience) */
.article-content-prose {
    font-size: 1.125rem; line-height: 1.85; color: var(--text-primary);
}
.article-content-prose p { margin-bottom: 1.5rem; }
.article-content-prose h2 {
    font-family: var(--font-display); font-size: 1.75rem; font-weight: 800;
    margin: 2.5rem 0 1rem; color: var(--primary); padding-bottom: 0.5rem;
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
    border-left: 4px solid var(--secondary); background: var(--bg-secondary);
    padding: 1.25rem 1.5rem; margin: 2rem 0; border-radius: 0 var(--radius-md) var(--radius-md) 0;
    font-style: italic; color: var(--text-secondary); font-size: 1.1rem;
}
.article-content-prose img {
    max-width: 100%; height: auto; border-radius: var(--radius-lg);
    margin: 2rem 0; box-shadow: var(--shadow-md);
}
.article-content-prose a {
    color: var(--primary); text-decoration: underline; text-decoration-color: rgba(10,104,71,0.3);
    text-underline-offset: 3px; transition: text-decoration-color 0.2s;
}
.article-content-prose a:hover { text-decoration-color: var(--primary); }

/* Drop Cap for first paragraph */
.article-content-prose > p:first-of-type::first-letter {
    float: left; font-size: 3.5rem; line-height: 0.8; font-weight: 800;
    color: var(--primary); margin-right: 0.5rem; margin-top: 0.1rem;
    font-family: var(--font-display);
}

/* Tags */
.article-tags {
    margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--border);
    display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;
}
.tag-label { font-weight: 600; color: var(--text-muted); font-size: 0.9rem; }
.tag-pill {
    padding: 0.4rem 1rem; background: var(--bg-secondary); color: var(--primary);
    border-radius: 999px; font-size: 0.85rem; font-weight: 600; text-decoration: none;
    transition: all 0.2s; border: 1px solid var(--border);
}
.tag-pill:hover { background: var(--primary); color: white; border-color: var(--primary); }

/* Share Box */
.share-box {
    margin-top: 2rem; padding: 1.5rem; background: var(--bg-secondary);
    border-radius: var(--radius-lg); border: 1px solid var(--border);
}
.share-box h4 { margin-bottom: 1rem; font-size: 1rem; }
.share-buttons { display: flex; gap: 0.75rem; flex-wrap: wrap; }
.share-btn {
    padding: 0.6rem 1.2rem; border-radius: var(--radius-md); border: none;
    font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all 0.2s;
    display: inline-flex; align-items: center; gap: 0.5rem; font-family: inherit;
}
.share-btn.wa { background: #25D366; color: white; }
.share-btn.fb { background: #1877F2; color: white; }
.share-btn.copy { background: var(--bg-primary); color: var(--text-primary); border: 1px solid var(--border); }
.share-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }

/* Author Bio */
.author-bio-card {
    margin-top: 2.5rem; display: flex; gap: 1.25rem; padding: 1.5rem;
    background: linear-gradient(135deg, var(--bg-secondary), var(--bg-primary));
    border: 1px solid var(--border); border-radius: var(--radius-lg);
}
.bio-avatar {
    width: 60px; height: 60px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; font-weight: 800;
}
.bio-info h4 { font-size: 1.1rem; margin-bottom: 0.5rem; }
.bio-info p { font-size: 0.9rem; color: var(--text-secondary); line-height: 1.6; margin: 0; }

/* Sidebar Sticky */
.sidebar-sticky { position: sticky; top: 100px; display: flex; flex-direction: column; gap: 1.5rem; }
.sidebar-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.5rem;
}
.sidebar-title, .toc-title {
    font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem;
    padding-bottom: 0.75rem; border-bottom: 2px solid var(--bg-tertiary);
}

/* Table of Contents */
.toc-list { list-style: none; padding: 0; margin: 0; }
.toc-list li { margin-bottom: 0.5rem; }
.toc-list a {
    display: block; padding: 0.5rem 0.75rem; color: var(--text-secondary);
    text-decoration: none; font-size: 0.9rem; border-left: 3px solid transparent;
    border-radius: 0 var(--radius-sm) var(--radius-sm) 0; transition: all 0.2s;
}
.toc-list a:hover { color: var(--primary); background: var(--bg-secondary); }
.toc-list a.active {
    color: var(--primary); font-weight: 600; border-left-color: var(--primary);
    background: rgba(10,104,71,0.05);
}
.toc-list .toc-h3 { padding-left: 1.5rem; font-size: 0.85rem; }

/* Related List */
.related-list { display: flex; flex-direction: column; gap: 1rem; }
.related-item {
    display: flex; gap: 0.75rem; text-decoration: none; color: inherit;
    padding: 0.5rem; border-radius: var(--radius-md); transition: all 0.2s;
}
.related-item:hover { background: var(--bg-secondary); }
.related-img, .related-img-placeholder {
    width: 70px; height: 70px; border-radius: var(--radius-sm);
    object-fit: cover; flex-shrink: 0;
}
.related-img-placeholder {
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; color: white;
}
.related-info h4 {
    font-size: 0.9rem; font-weight: 600; line-height: 1.4; margin-bottom: 0.25rem;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.related-info span { font-size: 0.75rem; color: var(--text-muted); }

/* CTA Card */
.cta-card {
    background: linear-gradient(135deg, var(--primary), var(--accent));
    color: white; border: none; text-align: center;
}
.cta-card h3 { color: white; border-bottom-color: rgba(255,255,255,0.2); }
.cta-card p { font-size: 0.9rem; opacity: 0.9; margin-bottom: 1.25rem; line-height: 1.6; }
.cta-card .btn-primary {
    background: white; color: var(--primary); font-weight: 700;
}
.cta-card .btn-primary:hover { background: var(--secondary); color: white; }

/* Toast */
.toast {
    position: fixed; bottom: 2rem; left: 50%; transform: translateX(-50%) translateY(100px);
    background: var(--dark); color: white; padding: 0.85rem 1.5rem;
    border-radius: 999px; font-size: 0.9rem; font-weight: 600;
    box-shadow: var(--shadow-xl); opacity: 0; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    z-index: 10000; display: flex; align-items: center; gap: 0.5rem;
}
.toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }

/* Responsive */
@media (max-width: 968px) {
    .article-layout { grid-template-columns: 1fr; }
    .article-sidebar { order: 2; }
    .article-main { order: 1; }
    .sidebar-sticky { position: static; }
    .article-hero { padding: 6rem 0 3rem; }
    .article-content-prose > p:first-of-type::first-letter { font-size: 2.5rem; }
}
</style>

<!-- ===== INTERACTIVE SCRIPTS ===== -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Reading Progress Bar
    const progressBar = document.getElementById('readingProgressBar');
    const articleContent = document.getElementById('articleContent');
    
    window.addEventListener('scroll', () => {
        if (!articleContent) return;
        const rect = articleContent.getBoundingClientRect();
        const totalHeight = rect.height + window.innerHeight;
        const progress = ((window.innerHeight - rect.top) / totalHeight) * 100;
        progressBar.style.width = Math.max(0, Math.min(100, progress)) + '%';
    });

    // 2. Auto-generate Table of Contents
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

        // 3. Active TOC Highlight on Scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    document.querySelectorAll('.toc-list a').forEach(a => a.classList.remove('active'));
                    const activeLink = document.querySelector(`.toc-list a[href="#${entry.target.id}"]`);
                    if (activeLink) activeLink.classList.add('active');
                }
            });
        }, { rootMargin: '-100px 0px -60% 0px' });

        headings.forEach(heading => observer.observe(heading));
    }
});

// Share Functions
function shareTo(platform) {
    const url = encodeURIComponent(window.location.href);
    const text = encodeURIComponent('<?= addslashes(sanitize($b['judul'])) ?>');
    let shareUrl = '';
    if (platform === 'wa') shareUrl = `https://wa.me/?text=${text}%20${url}`;
    if (platform === 'fb') shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>