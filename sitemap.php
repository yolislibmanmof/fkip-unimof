<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

function sm_add_url(array &$urls, string $base, string $path, ?string $lastmod = null): void
{
    $url = $path === ''
        ? rtrim($base, '/') . '/'
        : rtrim($base, '/') . '/' . ltrim($path, '/');

    $entry = ['loc' => $url];

    if ($lastmod !== null) {
        $timestamp = strtotime($lastmod);
        if ($timestamp !== false) {
            $entry['lastmod'] = date('Y-m-d', $timestamp);
        }
    }

    $urls[$url] = $entry;
}

function sm_escape_xml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

$base = rtrim((string) APP_URL, '/');
$urls = [];

$staticPages = [
    '',
    'tentang.php',
    'program.php',
    'berita.php',
    'pmb.php',
    'dosen.php',
    'blog.php',
    'agenda.php',
    'galeri.php',
    'prestasi.php',
    'alumni.php',
    'beasiswa.php',
    'fasilitas.php',
    'akreditasi.php',
    'kerjasama.php',
    'jurnal.php',
    'riset.php',
    'video.php',
    'download.php',
    'faq.php',
    'kontak.php',
];

foreach ($staticPages as $page) {
    sm_add_url($urls, $base, $page);
}

$dynamicQueries = [
    [
        "SELECT slug, COALESCE(updated_at, created_at) AS lastmod
         FROM berita
         WHERE status = 'Published'
           AND slug IS NOT NULL
           AND slug <> ''
         ORDER BY published_at DESC",
        static function (array &$urls, string $base, array $row): void {
            sm_add_url(
                $urls,
                $base,
                'berita-detail.php?slug=' . rawurlencode((string) $row['slug']),
                isset($row['lastmod']) ? (string) $row['lastmod'] : null
            );
        },
    ],
    [
        "SELECT slug, COALESCE(updated_at, created_at) AS lastmod
         FROM blog_artikel
         WHERE status = 'Published'
           AND slug IS NOT NULL
           AND slug <> ''
         ORDER BY published_at DESC",
        static function (array &$urls, string $base, array $row): void {
            sm_add_url(
                $urls,
                $base,
                'blog-detail.php?slug=' . rawurlencode((string) $row['slug']),
                isset($row['lastmod']) ? (string) $row['lastmod'] : null
            );
        },
    ],
    [
        "SELECT id, COALESCE(updated_at, created_at) AS lastmod
         FROM program_studi
         WHERE status = 'Aktif'
         ORDER BY urutan ASC",
        static function (array &$urls, string $base, array $row): void {
            $id = filter_var($row['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id === false || $id === null || $id < 1) {
                return;
            }

            sm_add_url(
                $urls,
                $base,
                'program-detail.php?id=' . $id,
                isset($row['lastmod']) ? (string) $row['lastmod'] : null
            );
        },
    ],
];

foreach ($dynamicQueries as [$sql, $addRow]) {
    try {
        $stmt = $pdo->query($sql);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $addRow($urls, $base, $row);
        }
    } catch (PDOException $exception) {
        error_log('Sitemap query failed: ' . $exception->getMessage());
    }
}

$xml = new XMLWriter();
$xml->openMemory();
$xml->startDocument('1.0', 'UTF-8');
$xml->setIndent(true);
$xml->startElement('urlset');
$xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

foreach ($urls as $entry) {
    $xml->startElement('url');
    $xml->writeElement('loc', $entry['loc']);

    if (isset($entry['lastmod'])) {
        $xml->writeElement('lastmod', $entry['lastmod']);
    }

    $xml->endElement();
}

$xml->endElement();
$xml->endDocument();

$output = $xml->outputMemory();
$etag = '"' . hash('sha256', $output) . '"';

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=3600, stale-while-revalidate=300');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');

$ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
if (is_string($ifNoneMatch)) {
    $providedEtags = array_map('trim', explode(',', $ifNoneMatch));

    if (in_array('*', $providedEtags, true) || in_array($etag, $providedEtags, true)) {
        http_response_code(304);
        exit;
    }
}

echo $output;