<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Berita & Pengumuman';
$page_description = 'Kabar terbaru seputar aktivitas akademik, prestasi, dan pengumuman resmi FKIP UNIMOF';

// ===== Filter & Search Logic =====
$kategori  = trim($_GET['kategori'] ?? '');
$q         = trim($_GET['q'] ?? '');
$halaman   = max(1, (int)($_GET['halaman'] ?? 1));
$per_page  = 9; // 3x3 grid looks best
$offset    = ($halaman - 1) * $per_page; // <--- TITIK KOMA DITAMBAHKAN DI SINI

$where  = "WHERE status = 'Published'";
$params = [];

if ($kategori !== '') {
    $where .= " AND kategori = ?";
    $params[] = $kategori;
}
if ($q !== '') {
    $where .= " AND (judul LIKE ? OR excerpt LIKE ? OR konten LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

// Total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM berita $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));

// Fetch news (Featured first)
$stmt = $pdo->prepare("SELECT * FROM berita $where ORDER BY is_featured DESC, published_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$per_page, $offset]));
$berita = $stmt->fetchAll();

// Category counts for chips
$kategoris = ['Akademik','Pengumuman','Prestasi','Kegiatan','Riset','Umum'];
$kat_counts = [];
foreach ($kategoris as $k) {
    $cStmt = $pdo->prepare("SELECT COUNT(*) FROM berita WHERE status = 'Published' AND kategori = ?");
    $cStmt->execute([$k]);
    $kat_counts[$k] = (int)$cStmt->fetchColumn();
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- ===== SCOPED STYLES FOR ULTIMATE NEWS ===== -->
<style>
/* Hero Enhancement */
.page-hero { position: relative; overflow: hidden; }
.page-hero::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 10% 20%, rgba(255,255,255,0.1) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(245,166,35,0.15) 0%, transparent 40%);
    pointer-events: none;
}

/* Toolbar: Search + Filters */
.news-toolbar {
    display: flex; flex-direction: column; gap: 1.5rem;
    margin-bottom: 3rem; padding: 1.5rem;
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); box-shadow: var(--shadow-sm);
}
.news-search-wrap { position: relative; max-width: 500px; width: 100%; }
.news-search-wrap .search-icon {
    position: absolute; left: 1rem; top: 50%; transform: translateY(-50%);
    color: var(--text-muted); pointer-events: none;
}
.news-search-input {
    width: 100%; padding: 0.85rem 1rem 0.85rem 2.75rem;
    border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.95rem; background: var(--bg-secondary);
    transition: all 0.3s;
}
.news-search-input:focus {
    outline: none; border-color: var(--primary); background: var(--bg-primary);
    box-shadow: 0 0 0 4px rgba(10,104,71,0.1);
}

/* Filter Chips Enhanced */
.filter-chips { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.chip {
    padding: 0.6rem 1.25rem; border-radius: 999px; border: 1px solid var(--border);
    background: var(--bg-primary); color: var(--text-secondary);
    font-size: 0.85rem; font-weight: 600; text-decoration: none;
    transition: all 0.3s var(--ease); display: inline-flex; align-items: center; gap: 0.4rem;
}
.chip:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); box-shadow: var(--shadow-sm); }
.chip.active {
    background: var(--primary); color: white; border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(10,104,71,0.25);
}
.chip-count {
    background: rgba(0,0,0,0.08); padding: 0.15rem 0.5rem; border-radius: 999px;
    font-size: 0.7rem; font-weight: 700;
}
.chip.active .chip-count { background: rgba(255,255,255,0.25); }

/* News Grid Enhanced */
.news-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 2rem;
}
.news-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); overflow: hidden;
    transition: all 0.4s var(--ease); display: flex; flex-direction: column; position: relative;
}
.news-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--primary-light); }

/* Featured Card (Spans 2 columns on desktop) */
.news-card.featured { grid-column: span 2; }
.news-card.featured .news-image { height: 280px; }
.news-card.featured .news-title { font-size: 1.5rem; }

/* Image Zoom Effect */
.news-image { position: relative; height: 200px; overflow: hidden; }
.news-image .img-placeholder, .news-image img {
    width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s var(--ease);
}
.news-card:hover .news-image .img-placeholder,
.news-card:hover .news-image img { transform: scale(1.08); }

.news-category {
    position: absolute; top: 1rem; left: 1rem;
    background: rgba(255,255,255,0.95); backdrop-filter: blur(8px);
    color: var(--primary); padding: 0.35rem 0.85rem; border-radius: 999px;
    font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.05em; box-shadow: var(--shadow-sm); z-index: 2;
}
.featured-badge {
    position: absolute; top: 1rem; right: 1rem;
    background: var(--secondary); color: white;
    padding: 0.35rem 0.85rem; border-radius: 999px;
    font-size: 0.75rem; font-weight: 700; display: flex; align-items: center; gap: 0.3rem;
    z-index: 2; box-shadow: var(--shadow-sm);
}

.news-content { padding: 1.5rem; display: flex; flex-direction: column; flex: 1; }
.news-meta {
    display: flex; gap: 1rem; color: var(--text-muted); font-size: 0.8rem;
    margin-bottom: 0.75rem; align-items: center; flex-wrap: wrap;
}
.read-time { display: inline-flex; align-items: center; gap: 0.25rem; }

.news-title { font-size: 1.15rem; font-weight: 700; line-height: 1.4; margin-bottom: 0.75rem; }
.news-title a {
    color: var(--text-primary); text-decoration: none;
    background-image: linear-gradient(var(--primary), var(--primary));
    background-size: 0% 2px; background-position: 0 100%; background-repeat: no-repeat;
    transition: background-size 0.3s, color 0.3s;
}
.news-title a:hover { color: var(--primary); background-size: 100% 2px; }

.news-excerpt {
    color: var(--text-secondary); font-size: 0.9rem; line-height: 1.6;
    margin-bottom: 1.25rem; flex: 1;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}
.read-more {
    color: var(--primary); font-weight: 600; font-size: 0.9rem; text-decoration: none;
    display: inline-flex; align-items: center; gap: 0.4rem; transition: gap 0.3s;
}
.read-more:hover { gap: 0.8rem; }

/* Empty State Enhanced */
.empty-state-pro {
    text-align: center; padding: 4rem 2rem; background: var(--bg-secondary);
    border-radius: var(--radius-xl); border: 2px dashed var(--border);
}
.empty-icon-lg { font-size: 4rem; margin-bottom: 1rem; opacity: 0.5; animation: float 3s ease-in-out infinite; }
@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }

/* Pagination Enhanced (Smart Ellipsis) */
.pagination-pro {
    display: flex; justify-content: center; align-items: center;
    gap: 0.5rem; margin-top: 4rem; flex-wrap: wrap;
}
.page-btn {
    min-width: 44px; height: 44px; display: flex; align-items: center; justify-content: center;
    border-radius: var(--radius-md); border: 1px solid var(--border);
    background: var(--bg-primary); color: var(--text-secondary);
    font-weight: 600; font-size: 0.9rem; text-decoration: none; transition: all 0.2s;
}
.page-btn:hover:not(.current):not(.disabled) {
    border-color: var(--primary); color: var(--primary); transform: translateY(-2px);
}
.page-btn.current {
    background: var(--primary); color: white; border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(10,104,71,0.3);
}
.page-btn.disabled { opacity: 0.5; cursor: not-allowed; }

@media (max-width: 768px) {
    .news-card.featured { grid-column: span 1; }
    .news-card.featured .news-image { height: 200px; }
    .news-card.featured .news-title { font-size: 1.15rem; }
    .news-toolbar { padding: 1rem; }
}
</style>

<!-- ===== HERO SECTION ===== -->
<section class="page-hero">
    <div class="container">
        <nav class="breadcrumb" data-aos="fade-down">
            <a href="<?= base_url() ?>">Beranda</a><span>›</span><span>Berita & Pengumuman</span>
        </nav>
        <div class="page-hero-content" data-aos="fade-up">
            <span class="page-badge">📰 <?= $total ?> Artikel Terpublikasi</span>
            <h1 class="page-title">Berita & Pengumuman</h1>
            <p class="page-subtitle">Kabar terbaru seputar aktivitas akademik, prestasi, dan pengumuman resmi FKIP UNIMOF</p>
        </div>
    </div>
</section>

<!-- ===== MAIN CONTENT ===== -->
<section class="section">
    <div class="container">
        
        <!-- Toolbar: Search + Filters -->
        <div class="news-toolbar" data-aos="fade-up">
            <form method="GET" action="" class="news-search-wrap">
                <span class="search-icon">🔍</span>
                <input type="text" name="q" class="news-search-input" 
                       placeholder="Cari berita, pengumuman, atau prestasi..." 
                       value="<?= sanitize($q) ?>">
                <?php if ($kategori !== ''): ?>
                    <input type="hidden" name="kategori" value="<?= sanitize($kategori) ?>">
                <?php endif; ?>
            </form>
            
            <div class="filter-chips">
                <a class="chip <?= $kategori === '' && $q === '' ? 'active' : '' ?>" href="<?= base_url('berita.php') ?>">
                    Semua <span class="chip-count"><?= $total ?></span>
                </a>
                <?php foreach ($kategoris as $k): 
                    $count = $kat_counts[$k] ?? 0;
                    // Tampilkan chip jika ada isi ATAU sedang difilter
                    if ($count > 0 || $kategori === $k): 
                ?>
                    <a class="chip <?= $kategori === $k ? 'active' : '' ?>" 
                       href="<?= base_url('berita.php?kategori=' . urlencode($k) . ($q ? '&q=' . urlencode($q) : '')) ?>">
                        <?= $k ?> <span class="chip-count"><?= $count ?></span>
                    </a>
                <?php endif; endforeach; ?>
            </div>
        </div>

        <?php if (empty($berita)): ?>
            <!-- Empty State -->
            <div class="empty-state-pro" data-aos="zoom-in">
                <div class="empty-icon-lg">📭</div>
                <h3>Belum ada berita ditemukan</h3>
                <p style="color:var(--text-muted); max-width:400px; margin:0.5rem auto 1.5rem;">
                    <?php if ($q || $kategori): ?>
                        Coba ubah kata kunci pencarian atau pilih kategori lain.
                    <?php else: ?>
                        Kami sedang menyiapkan konten terbaru untuk Anda. Nantikan update selanjutnya!
                    <?php endif; ?>
                </p>
                <?php if ($q || $kategori): ?>
                    <a href="<?= base_url('berita.php') ?>" class="btn btn-secondary">🔄 Reset Filter</a>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- News Grid -->
            <div class="news-grid">
                <?php foreach ($berita as $index => $b): 
                    $is_featured = !empty($b['is_featured']);
                    // Hitung estimasi waktu baca (200 kata per menit)
                    $word_count = str_word_count(strip_tags($b['konten'] ?? ''));
                    $read_time = max(1, ceil($word_count / 200));
                    
                    // Generate warna unik berdasarkan hash kategori untuk placeholder
                    $hue = crc32($b['kategori']) % 360;
                ?>
                <article class="news-card <?= $is_featured && $index === 0 ? 'featured' : '' ?>" 
                         data-aos="fade-up" data-aos-delay="<?= ($index % 3) * 100 ?>">
                    
                    <div class="news-image">
                        <?php if (!empty($b['gambar'])): ?>
                            <img src="<?= asset('uploads/' . basename($b['gambar'])) ?>" alt="<?= sanitize($b['judul']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="img-placeholder" style="background:linear-gradient(135deg, hsl(<?= $hue ?>, 60%, 50%), hsl(<?= ($hue + 40) % 360 ?>, 60%, 40%));">
                                <span style="font-size:3rem; opacity:0.8;">📰</span>
                            </div>
                        <?php endif; ?>
                        
                        <span class="news-category"><?= sanitize($b['kategori']) ?></span>
                        <?php if ($is_featured): ?>
                            <span class="featured-badge">⭐ Utama</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="news-content">
                        <div class="news-meta">
                            <span>📅 <?= format_tanggal_singkat($b['published_at'] ?? $b['created_at']) ?></span>
                            <span>👁 <?= number_format($b['views']) ?></span>
                            <span class="read-time">⏱️ <?= $read_time ?> mnt</span>
                        </div>
                        
                        <h3 class="news-title">
                            <a href="<?= base_url('berita-detail.php?slug=' . urlencode($b['slug'])) ?>">
                                <?= function_exists('highlight_search') ? highlight_search(sanitize($b['judul']), $q) : sanitize($b['judul']) ?>
                            </a>
                        </h3>
                        
                        <p class="news-excerpt">
                            <?= excerpt($b['excerpt'] ?? $b['konten'], 120) ?>
                        </p>
                        
                        <a class="read-more" href="<?= base_url('berita-detail.php?slug=' . urlencode($b['slug'])) ?>">
                            Baca Selengkapnya 
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap=" round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>

            <!-- Smart Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav class="pagination-pro" data-aos="fade-up">
                <?php if ($halaman > 1): ?>
                    <a href="<?= base_url('berita.php?' . http_build_query(array_merge($_GET, ['halaman' => $halaman - 1]))) ?>" class="page-btn" aria-label="Previous">←</a>
                <?php else: ?>
                    <span class="page-btn disabled">←</span>
                <?php endif; ?>

                <?php
                $range = 2;
                $start = max(1, $halaman - $range);
                $end = min($total_pages, $halaman + $range);
                
                if ($start > 1): ?>
                    <a href="<?= base_url('berita.php?' . http_build_query(array_merge($_GET, ['halaman' => 1]))) ?>" class="page-btn">1</a>
                    <?php if ($start > 2): ?><span class="page-btn disabled">…</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i === $halaman): ?>
                        <span class="page-btn current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= base_url('berita.php?' . http_build_query(array_merge($_GET, ['halaman' => $i]))) ?>" class="page-btn"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end < $total_pages): ?>
                    <?php if ($end < $total_pages - 1): ?><span class="page-btn disabled">…</span><?php endif; ?>
                    <a href="<?= base_url('berita.php?' . http_build_query(array_merge($_GET, ['halaman' => $total_pages]))) ?>" class="page-btn"><?= $total_pages ?></a>
                <?php endif; ?>

                <?php if ($halaman < $total_pages): ?>
                    <a href="<?= base_url('berita.php?' . http_build_query(array_merge($_GET, ['halaman' => $halaman + 1]))) ?>" class="page-btn" aria-label="Next">→</a>
                <?php else: ?>
                    <span class="page-btn disabled">→</span>
                <?php endif; ?>
            </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>