<?php
declare(strict_types=1);

date_default_timezone_set('UTC');

define('APP_URL', 'http://localhost/fkip-unimof');
define('APP_DIR', dirname(__DIR__));

$_SESSION = [];

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

$passed = 0;
$failed = 0;

$assert = static function (bool $condition, string $name) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo "[PASS] {$name}\n";
        return;
    }

    $failed++;
    fwrite(STDERR, "[FAIL] {$name}\n");
};

$assert(
    sanitize('<script>alert("x")</script>') === '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;',
    'sanitize escapes HTML'
);

$token = generate_csrf_token();
$assert(
    preg_match('/^[a-f0-9]{64}$/', $token) === 1,
    'CSRF token is 32 random bytes encoded as hex'
);
$assert(verify_csrf_token($token), 'CSRF token verifies');
$assert(!verify_csrf_token('invalid-token'), 'invalid CSRF token is rejected');
$assert(!verify_csrf_token(['unexpected']), 'non-string CSRF input is rejected');

$cleanedHtml = clean_html(
    '<p onclick="alert(1)">Safe text</p>'
    . '<script>alert(2)</script>'
    . '<a href="javascript:alert(3)">Safe link</a>'
);

$assert(
    stripos($cleanedHtml, '<script') === false,
    'HTML sanitizer removes script elements'
);
$assert(
    stripos($cleanedHtml, 'javascript:') === false,
    'HTML sanitizer removes javascript URLs'
);
$assert(
    stripos($cleanedHtml, 'onclick=') === false,
    'HTML sanitizer removes event-handler attributes'
);
$assert(
    str_contains($cleanedHtml, 'Safe text')
        && str_contains($cleanedHtml, 'Safe link'),
    'HTML sanitizer preserves readable content'
);

$assert(
    generate_slug('FKIP UNIMOF 2026') === 'fkip-unimof-2026',
    'slug generation handles ordinary titles'
);
$assert(
    format_tanggal('2026-10-18') === '18 Oktober 2026',
    'Indonesian date formatting works'
);
$assert(
    format_tanggal('not-a-date') === '',
    'invalid dates return an empty string'
);

$assert(
    excerpt('Satu dua tiga empat', 10) === 'Satu dua...',
    'excerpt truncates at a word boundary'
);
$assert(
    truncate_words('satu dua tiga empat', 2) === 'satu dua...',
    'word truncation respects its limit'
);
$assert(
    format_file_size(1048576) === '1 MB',
    'file size formatting handles megabytes'
);

$highlighted = highlight_search('FKIP & UNIMOF', 'UNIMOF');
$assert(
    str_contains($highlighted, '<mark>UNIMOF</mark>')
        && str_contains($highlighted, '&amp;'),
    'search highlighting escapes text and marks the match'
);

$pagination = generate_pagination(3, 5, '/berita.php?page=');
$assert(
    str_contains($pagination, 'aria-current="page"')
        && str_contains($pagination, '/berita.php?page=2')
        && str_contains($pagination, '/berita.php?page=4'),
    'pagination includes current, previous, and next pages'
);

$rateLimitId = 'smoke-test-' . bin2hex(random_bytes(8));
$assert(
    check_rate_limit($rateLimitId, 2, 60),
    'rate limiter allows the first attempt'
);
$assert(
    check_rate_limit($rateLimitId, 2, 60),
    'rate limiter allows attempts up to its configured limit'
);
$assert(
    !check_rate_limit($rateLimitId, 2, 60),
    'rate limiter rejects attempts above its configured limit'
);
$assert(
    rate_limit_remaining($rateLimitId, 2, 60) === 0,
    'rate limiter reports zero remaining attempts after the limit'
);

printf("\n%d passed, %d failed\n", $passed, $failed);

exit($failed === 0 ? 0 : 1);