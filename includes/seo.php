<?php
/**
 * includes/seo.php - SEO Foundation FKIP UNIMOF
 * ------------------------------------------------
 * Menyediakan:
 *  1. Meta tags (title, description, keywords, canonical, robots)
 *  2. Open Graph (Facebook, LinkedIn, WhatsApp)
 *  3. Twitter Card
 *  4. JSON-LD Structured Data:
 *     - EducationalOrganization / CollegeOrUniversity
 *     - WebSite + SearchAction
 *     - WebPage
 *     - BreadcrumbList
 *     - NewsArticle
 *     - EducationEvent
 *
 * Variabel opsional yang dapat diset halaman pemanggil SEBELUM include header.php:
 *   $page_title      : string  - Judul halaman
 *   $page_description: string  - Deskripsi halaman
 *   $page_keywords   : string  - Kata kunci
 *   $page_image      : string  - URL/path gambar utama (OG image)
 *   $seo_type        : string  - 'website' | 'article' | 'profile' | 'video'
 *   $seo_author      : string  - Nama penulis (article)
 *   $seo_published   : string  - Tanggal terbit (article)
 *   $seo_modified    : string  - Tanggal update (article)
 *   $canonical_url   : string  - Override canonical URL
 *   $breadcrumbs     : array   - [['title'=>..., 'url'=>...], ...]
 *   $seo_article     : array   - Data artikel untuk JSON-LD
 *   $seo_event       : array   - Data event untuk JSON-LD
 */

if (!defined('APP_URL')) {
    require_once __DIR__ . '/config.php';
}

/* =====================================================
   HELPERS
===================================================== */

if (!function_exists('seo_e')) {
    function seo_e(?string $str): string
    {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('seo_current_url')) {
    function seo_current_url(): string
    {
        $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $query  = $_SERVER['QUERY_STRING'] ?? '';
        $url = rtrim(APP_URL, '/') . $script;
        if ($query !== '') {
            $url .= '?' . $query;
        }
        return $url;
    }
}

if (!function_exists('seo_canonical')) {
    function seo_canonical(): string
    {
        global $canonical_url;
        return !empty($canonical_url) ? $canonical_url : seo_current_url();
    }
}

if (!function_exists('seo_iso_date')) {
    function seo_iso_date(?string $date): string
    {
        if (empty($date)) return date('c');
        $ts = strtotime($date);
        return $ts ? date('c', $ts) : date('c');
    }
}

if (!function_exists('seo_absolute_url')) {
    function seo_absolute_url(?string $path): string
    {
        $path = (string)$path;
        if ($path === '') return '';
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            return $path;
        }
        return base_url(ltrim($path, '/'));
    }
}

/* =====================================================
   1) META TAGS + OPEN GRAPH + TWITTER CARD
===================================================== */

if (!function_exists('seo_render_meta')) {
    function seo_render_meta(): string
    {
        global $page_title, $page_description, $page_keywords, $page_image;
        global $seo_type, $seo_author, $seo_published, $seo_modified;

        $site_name = get_setting('singkatan', 'FKIP UNIMOF');
        $fac_name  = get_setting('nama_fakultas', 'Fakultas Keguruan dan Ilmu Pendidikan');
        $uni_name  = get_setting('nama_universitas', 'Universitas Muhammadiyah Maumere');

        $is_home = (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php');
        $title = $is_home
            ? $fac_name . ' - ' . $uni_name
            : trim((string)($page_title ?? 'Beranda')) . ' | ' . $site_name;

        $desc = trim((string)($page_description ?? get_setting('deskripsi', '')));
        if (mb_strlen($desc) > 160) {
            $desc = mb_substr($desc, 0, 157) . '...';
        }

        $kw  = (string)($page_keywords ?? 'FKIP, UNIMOF, Universitas Muhammadiyah Maumere, Pendidikan, Maumere, NTT');
        $img = (string)($page_image ?? '');
        if ($img === '' && function_exists('get_active_logo_url')) {
            $img = (string)(get_active_logo_url() ?? '');
        }
        if ($img === '') {
            $img = base_url('assets/images/og-default.jpg');
        }
        $img = seo_absolute_url($img);

        $url  = seo_canonical();
        $type = (string)($seo_type ?? 'website');

        $out = [];

        // --- Meta dasar ---
        $out[] = '<title>' . seo_e($title) . '</title>';
        $out[] = '<meta name="description" content="' . seo_e($desc) . '">';
        $out[] = '<meta name="keywords" content="' . seo_e($kw) . '">';
        $out[] = '<link rel="canonical" href="' . seo_e($url) . '">';
        $out[] = '<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">';
        $out[] = '<meta name="author" content="' . seo_e($site_name) . '">';

        // --- Open Graph ---
        $out[] = '<meta property="og:site_name" content="' . seo_e($site_name) . '">';
        $out[] = '<meta property="og:type" content="' . seo_e($type) . '">';
        $out[] = '<meta property="og:title" content="' . seo_e($title) . '">';
        $out[] = '<meta property="og:description" content="' . seo_e($desc) . '">';
        $out[] = '<meta property="og:url" content="' . seo_e($url) . '">';
        $out[] = '<meta property="og:image" content="' . seo_e($img) . '">';
        $out[] = '<meta property="og:image:alt" content="' . seo_e($title) . '">';
        $out[] = '<meta property="og:locale" content="id_ID">';

        if ($type === 'article') {
            if (!empty($seo_published)) {
                $out[] = '<meta property="article:published_time" content="' . seo_e(seo_iso_date($seo_published)) . '">';
            }
            if (!empty($seo_modified)) {
                $out[] = '<meta property="article:modified_time" content="' . seo_e(seo_iso_date($seo_modified)) . '">';
            }
            if (!empty($seo_author)) {
                $out[] = '<meta property="article:author" content="' . seo_e($seo_author) . '">';
            }
            $out[] = '<meta property="article:section" content="Pendidikan">';
        }

        // --- Twitter Card ---
        $out[] = '<meta name="twitter:card" content="summary_large_image">';
        $out[] = '<meta name="twitter:title" content="' . seo_e($title) . '">';
        $out[] = '<meta name="twitter:description" content="' . seo_e($desc) . '">';
        $out[] = '<meta name="twitter:image" content="' . seo_e($img) . '">';

        return implode("\n    ", $out);
    }
}

/* =====================================================
   2) JSON-LD STRUCTURED DATA
===================================================== */

if (!function_exists('seo_render_jsonld')) {
    function seo_render_jsonld(): string
    {
        global $page_title, $page_description, $page_image;
        global $breadcrumbs, $seo_article, $seo_event;

        $base      = rtrim(APP_URL, '/');
        $org_id    = $base . '/#organization';
        $site_id   = $base . '/#website';
        $site_name = get_setting('singkatan', 'FKIP UNIMOF');

        $logo = '';
        if (function_exists('get_active_logo_url')) {
            $logo = (string)(get_active_logo_url() ?? '');
        }
        if ($logo === '') {
            $logo = base_url('assets/images/og-default.jpg');
        }
        $logo = seo_absolute_url($logo);

        $img = seo_absolute_url((string)($page_image ?? $logo));

        $same_as = array_values(array_filter([
            (string)get_setting('instagram_url', ''),
            (string)get_setting('facebook_url', ''),
            (string)get_setting('youtube_url', ''),
        ]));

        $scripts = [];

        // ---------- GRAPH: Organization + WebSite + WebPage ----------
        $scripts[] = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type'           => ['EducationalOrganization', 'CollegeOrUniversity'],
                    '@id'             => $org_id,
                    'name'            => get_setting('nama_fakultas', 'Fakultas Keguruan dan Ilmu Pendidikan'),
                    'alternateName'   => $site_name,
                    'url'             => $base . '/',
                    'logo'            => ['@type' => 'ImageObject', 'url' => $logo],
                    'description'     => get_setting('deskripsi', ''),
                    'address'         => [
                        '@type'            => 'PostalAddress',
                        'streetAddress'    => get_setting('alamat', ''),
                        'addressLocality'  => 'Maumere',
                        'addressRegion'    => 'Nusa Tenggara Timur',
                        'postalCode'       => '',
                        'addressCountry'   => 'ID',
                    ],
                    'contactPoint'    => [
                        '@type'         => 'ContactPoint',
                        'telephone'     => get_setting('telepon', ''),
                        'email'         => get_setting('email', ''),
                        'contactType'   => 'customer service',
                        'availableLanguage' => ['Indonesian'],
                    ],
                    'sameAs'          => $same_as,
                    'parentOrganization' => [
                        '@type' => 'Organization',
                        'name'  => get_setting('nama_universitas', 'Universitas Muhammadiyah Maumere'),
                    ],
                ],
                [
                    '@type'       => 'WebSite',
                    '@id'         => $site_id,
                    'url'         => $base . '/',
                    'name'        => $site_name,
                    'description' => get_setting('deskripsi', ''),
                    'publisher'   => ['@id' => $org_id],
                    'inLanguage'  => 'id-ID',
                    'potentialAction' => [
                        '@type'       => 'SearchAction',
                        'target'      => $base . '/berita.php?q={search_term_string}',
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
                [
                    '@type'    => 'WebPage',
                    '@id'      => seo_canonical() . '#webpage',
                    'url'      => seo_canonical(),
                    'name'     => (string)($page_title ?? 'Beranda'),
                    'description' => (string)($page_description ?? ''),
                    'isPartOf' => ['@id' => $site_id],
                    'about'    => ['@id' => $org_id],
                    'inLanguage' => 'id-ID',
                ],
            ],
        ];

// ---------- BREADCRUMB ----------
// Support kedua format: 'title'/'name' untuk kompatibilitas
if (!empty($breadcrumbs) && is_array($breadcrumbs)) {
    $items = [];
    $pos = 1;
    foreach ($breadcrumbs as $crumb) {
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $pos++,
            'name'     => (string)($crumb['title'] ?? $crumb['name'] ?? ''),
            'item'     => (string)($crumb['url'] ?? ''),
        ];
    }
            $scripts[] = [
                '@context'       => 'https://schema.org',
                '@type'          => 'BreadcrumbList',
                'itemListElement' => $items,
            ];
        }

        // ---------- ARTICLE ----------
        if (!empty($seo_article) && is_array($seo_article)) {
            $a = $seo_article;
            $article = [
                '@context'         => 'https://schema.org',
                '@type'            => 'NewsArticle',
                'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => seo_canonical()],
                'headline'         => mb_substr((string)($a['headline'] ?? ''), 0, 110),
                'description'      => (string)($a['description'] ?? ''),
                'image'            => [ (string)($a['image'] ?? $img) ],
                'datePublished'    => seo_iso_date($a['datePublished'] ?? null),
                'dateModified'     => seo_iso_date($a['dateModified'] ?? ($a['datePublished'] ?? null)),
                'author'           => [
                    '@type' => 'Person',
                    'name'  => (string)($a['author'] ?? 'Humas FKIP'),
                ],
                'publisher'        => ['@id' => $org_id],
                'articleSection'   => (string)($a['kategori'] ?? 'Umum'),
                'inLanguage'       => 'id-ID',
            ];
            if (!empty($a['wordCount'])) {
                $article['wordCount'] = (int)$a['wordCount'];
            }
            if (!empty($a['keywords'])) {
                $article['keywords'] = (string)$a['keywords'];
            }
            $scripts[] = $article;
        }

// ---------- EVENTS (Multiple EducationEvent) ----------
if (!empty($seo_events) && is_array($seo_events)) {
    foreach ($seo_events as $ev) {
        $scripts[] = [
            '@context'    => 'https://schema.org',
            '@type'       => 'EducationEvent',
            'name'        => (string)($ev['nama'] ?? ''),
            'description' => mb_substr((string)($ev['deskripsi'] ?? ''), 0, 500),
            'startDate'   => seo_iso_date($ev['mulai'] ?? null),
            'endDate'     => seo_iso_date($ev['selesai'] ?? ($ev['mulai'] ?? null)),
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'eventStatus'         => 'https://schema.org/EventScheduled',
            'location'    => [
                '@type'   => 'Place',
                'name'    => (string)($ev['lokasi'] ?? get_setting('nama_universitas', '')),
                'address' => (string)get_setting('alamat', ''),
            ],
            'organizer'   => ['@id' => $org_id],
            'image'       => [ $img ],
        ];
    }
}

// ---------- COURSE (Program Studi) ----------
if (!empty($seo_course) && is_array($seo_course)) {
    $c = $seo_course;
    $scripts[] = [
        '@context' => 'https://schema.org',
        '@type' => 'Course',
        'name' => (string)($c['nama'] ?? ''),
        'description' => mb_substr((string)($c['deskripsi'] ?? ''), 0, 500),
        'provider' => ['@id' => $org_id],
        'url' => seo_canonical(),
        'educationalLevel' => (string)($c['jenjang'] ?? 'S1'),
        'inLanguage' => 'id-ID',
        'teaches' => (string)($c['prospek'] ?? ''),
        'hasCourseInstance' => [
            '@type' => 'CourseInstance',
            'courseMode' => 'Blended',
            'duration' => (string)($c['durasi'] ?? 'P4Y'), // P4Y = 4 tahun (ISO 8601)
        ],
    ];
}

// ---------- RENDER ----------
$out = '';

foreach ($scripts as $script) {
    try {
        $json = json_encode(
            $script,
            JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
                | JSON_INVALID_UTF8_SUBSTITUTE
        );
    } catch (JsonException $exception) {
        error_log('SEO structured data encoding failed: ' . $exception->getMessage());
        continue;
    }

    $out .= '<script type="application/ld+json">'
        . $json
        . '</script>'
        . "\n    ";
}

return $out;
    }
}

/* =====================================================
   3) RENDER GABUNGAN (dipanggil dari header.php)
===================================================== */

if (!function_exists('seo_render_head')) {
    function seo_render_head(): string
    {
        return seo_render_meta() . "\n    " . seo_render_jsonld();
    }
}