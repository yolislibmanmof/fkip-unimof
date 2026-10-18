<?php
/**
 * sitemap.php - XML Sitemap Generator Otomatis
 * Akses publik: https://domain/sitemap.xml  (via rewrite .htaccess)
 *               https://domain/sitemap.php (langsung)
 */
require_once __DIR__ . '/includes/config.php';

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=3600');

$base = rtrim(APP_URL, '/');
$now  = date('c');
$urls = [];

/**
 * Tambahkan satu URL ke sitemap
 */
function sm_add(array &$urls, string $base, string $path, ?string $lastmod = null, string $changefreq = 'weekly', string $priority = '0.5'): void
{
    $loc = ($path === '') ? $base . '/' : $base . '/' . ltrim($path, '/');
    $urls[] = [
        'loc'        => $loc,
        'lastmod'    => $lastmod,
        'changefreq' => $changefreq,
        'priority'   => $priority,
    ];
}

/* =====================================================
   1) HALAMAN STATIS
===================================================== */
sm_add($urls, $base, '',                $now, 'daily',   '1.0');
sm_add($urls, $base, 'tentang.php',     $now, 'monthly', '0.8');
sm_add($urls, $base, 'program.php',     $now, 'weekly',  '0.9');
sm_add($urls, $base, 'berita.php',      $now, 'daily',   '0.9');
sm_add($urls, $base, 'pmb.php',         $now, 'weekly',  '0.9');
sm_add($urls, $base, 'dosen.php',       $now, 'monthly', '0.7');
sm_add($urls, $base, 'blog.php',        $now, 'weekly',  '0.7');
sm_add($urls, $base, 'agenda.php',      $now, 'weekly',  '0.7');
sm_add($urls, $base, 'galeri.php',      $now, 'weekly',  '0.6');
sm_add($urls, $base, 'prestasi.php',    $now, 'weekly',  '0.7');
sm_add($urls, $base, 'alumni.php',      $now, 'monthly', '0.6');
sm_add($urls, $base, 'beasiswa.php',    $now, 'weekly',  '0.7');
sm_add($urls, $base, 'fasilitas.php',   $now, 'monthly', '0.6');
sm_add($urls, $base, 'akreditasi.php',  $now, 'monthly', '0.6');
sm_add($urls, $base, 'kerjasama.php',   $now, 'monthly', '0.6');
sm_add($urls, $base, 'jurnal.php',      $now, 'monthly', '0.6');
sm_add($urls, $base, 'riset.php',       $now, 'monthly', '0.6');
sm_add($urls, $base, 'video.php',       $now, 'weekly',  '0.6');
sm_add($urls, $base, 'download.php',    $now, 'monthly', '0.5');
sm_add($urls, $base, 'faq.php',         $now, 'monthly', '0.5');
sm_add($urls, $base, 'kontak.php',      $now, 'monthly', '0.5');

/* =====================================================
   2) HALAMAN DINAMIS DARI DATABASE
===================================================== */

// --- Berita (Published) ---
try {
    $stmt = $pdo->query("SELECT slug, COALESCE(updated_at, created_at) AS lm FROM berita WHERE status = 'Published' ORDER BY published_at DESC");
    while ($r = $stmt->fetch()) {
        sm_add($urls, $base, 'berita-detail.php?slug=' . urlencode($r['slug']), $r['lm'], 'weekly', '0.8');
    }
} catch (Exception $e) {}

// --- Blog Artikel (Published) ---
try {
    $stmt = $pdo->query("SELECT slug, COALESCE(updated_at, created_at) AS lm FROM blog_artikel WHERE status = 'Published' ORDER BY published_at DESC");
    while ($r = $stmt->fetch()) {
        sm_add($urls, $base, 'blog-detail.php?slug=' . urlencode($r['slug']), $r['lm'], 'weekly', '0.7');
    }
} catch (Exception $e) {}

// --- Program Studi (Aktif) ---
try {
    $stmt = $pdo->query("SELECT id, COALESCE(updated_at, created_at) AS lm FROM program_studi WHERE status = 'Aktif' ORDER BY urutan ASC");
    while ($r = $stmt->fetch()) {
        sm_add($urls, $base, 'program-detail.php?id=' . (int)$r['id'], $r['lm'], 'monthly', '0.8');
    }
} catch (Exception $e) {}

/* =====================================================
   3) RENDER XML
===================================================== */
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<!-- XML Sitemap FKIP UNIMOF - Generated: ' . $now . ' -->' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($urls as $u) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1, 'UTF-8') . "</loc>\n";
    if (!empty($u['lastmod'])) {
        $ts = strtotime($u['lastmod']);
        if ($ts) {
            echo '    <lastmod>' . date('Y-m-d', $ts) . "</lastmod>\n";
        }
    }
    echo '    <changefreq>' . $u['changefreq'] . "</changefreq>\n";
    echo '    <priority>' . $u['priority'] . "</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>' . "\n";