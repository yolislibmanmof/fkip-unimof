<?php
// includes/security.php - Helper keamanan aplikasi
declare(strict_types=1);

// Security headers. CSP perlu disesuaikan tersendiri karena halaman saat ini
// memakai skrip dan style inline.
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

function generate_csrf_token(): string
{
    if (
        !isset($_SESSION['csrf_token'])
        || !is_string($_SESSION['csrf_token'])
        || $_SESSION['csrf_token'] === ''
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token(mixed $token): bool
{
    if (
        !is_string($token)
        || $token === ''
        || !isset($_SESSION['csrf_token'])
        || !is_string($_SESSION['csrf_token'])
        || $_SESSION['csrf_token'] === ''
    ) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

function sanitize(mixed $input): string
{
    if ($input === null || !is_scalar($input)) {
        return '';
    }

    return htmlspecialchars(
        trim((string) $input),
        ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
        'UTF-8'
    );
}

function validate_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Memvalidasi URL untuk atribut href dan src.
 * URL relatif dan fragmen diperbolehkan; skema aktif seperti javascript:
 * dan data: ditolak.
 */
function security_safe_html_url(string $url, string $attribute): ?string
{
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) {
        return null;
    }

    if (str_contains($url, '\\') || str_starts_with($url, '//')) {
        return null;
    }

    $parts = parse_url($url);
    if ($parts === false) {
        return null;
    }

    if (isset($parts['scheme'])) {
        $scheme = strtolower($parts['scheme']);
        $allowedSchemes = $attribute === 'href'
            ? ['http', 'https', 'mailto', 'tel']
            : ['http', 'https'];

        if (!in_array($scheme, $allowedSchemes, true)) {
            return null;
        }
    }

    return $url;
}

/**
 * Membersihkan fragmen HTML dengan allowlist tag dan atribut.
 * Jika DOM tidak tersedia, kembalikan teks yang sudah di-escape sebagai
 * fallback aman, bukan HTML yang belum disanitasi.
 */
function clean_html(string $html): string
{
    if (!class_exists(DOMDocument::class)) {
        return nl2br(
            htmlspecialchars(
                strip_tags($html),
                ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
                'UTF-8'
            )
        );
    }

    $allowedTags = [
        'p', 'br', 'strong', 'em', 'u',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'a', 'img',
        'blockquote', 'pre', 'code',
    ];

    $dangerousTags = [
        'script', 'style', 'iframe', 'object', 'embed',
        'svg', 'math', 'template', 'noscript',
    ];

    $attributesByTag = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
    ];

    $document = new DOMDocument('1.0', 'UTF-8');
    $previousLibxmlSetting = libxml_use_internal_errors(true);

    try {
        $source = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'
            . '<div id="clean-html-root">'
            . $html
            . '</div></body></html>';

        $loaded = $document->loadHTML(
            $source,
            LIBXML_NONET | LIBXML_HTML_NODEFDTD
        );

        if (!$loaded) {
            return htmlspecialchars(
                strip_tags($html),
                ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
                'UTF-8'
            );
        }

        $xpath = new DOMXPath($document);
        $roots = $xpath->query('//*[@id="clean-html-root"]');
        $root = ($roots !== false) ? $roots->item(0) : null;

        if (!$root instanceof DOMElement) {
            return htmlspecialchars(
                strip_tags($html),
                ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
                'UTF-8'
            );
        }

        $sanitizeChildren = static function (DOMNode $parent) use (
            &$sanitizeChildren,
            $allowedTags,
            $dangerousTags,
            $attributesByTag
        ): void {
            $children = [];
            foreach ($parent->childNodes as $child) {
                $children[] = $child;
            }

            foreach ($children as $child) {
                if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) {
                    $parent->removeChild($child);
                    continue;
                }

                if (!$child instanceof DOMElement) {
                    continue;
                }

                $tag = strtolower($child->tagName);

                if (in_array($tag, $dangerousTags, true)) {
                    $parent->removeChild($child);
                    continue;
                }

                if (!in_array($tag, $allowedTags, true)) {
                    $sanitizeChildren($child);

                    while ($child->firstChild !== null) {
                        $parent->insertBefore($child->firstChild, $child);
                    }

                    $parent->removeChild($child);
                    continue;
                }

                $allowedAttributes = $attributesByTag[$tag] ?? [];
                $attributes = [];

                foreach ($child->attributes as $attribute) {
                    $attributes[] = $attribute->name;
                }

                foreach ($attributes as $attributeName) {
                    $attributeName = strtolower($attributeName);

                    if (!in_array($attributeName, $allowedAttributes, true)) {
                        $child->removeAttribute($attributeName);
                        continue;
                    }

                    $value = trim($child->getAttribute($attributeName));

                    if ($attributeName === 'href' || $attributeName === 'src') {
                        $safeUrl = security_safe_html_url($value, $attributeName);

                        if ($safeUrl === null) {
                            $child->removeAttribute($attributeName);
                        } else {
                            $child->setAttribute($attributeName, $safeUrl);
                        }

                        continue;
                    }

                    if (
                        ($attributeName === 'width' || $attributeName === 'height')
                        && !preg_match('/^\d{1,4}$/', $value)
                    ) {
                        $child->removeAttribute($attributeName);
                    }
                }

                if ($tag === 'img' && !$child->hasAttribute('src')) {
                    $parent->removeChild($child);
                    continue;
                }

                if ($tag === 'a' && strtolower($child->getAttribute('target')) === '_blank') {
                    $child->setAttribute('rel', 'noopener noreferrer');
                }

                $sanitizeChildren($child);
            }
        };

        $sanitizeChildren($root);

        $cleaned = '';
        foreach ($root->childNodes as $child) {
            $cleaned .= $document->saveHTML($child);
        }

        return $cleaned;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlSetting);
    }
}

function generate_slug(string $text): string
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text) ?? '';
    $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

    if ($transliterated !== false) {
        $text = $transliterated;
    }

    $text = preg_replace('~[^-\w]+~', '', $text) ?? '';
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text) ?? '';

    return strtolower($text);
}

function hash_password(string $password): string
{
    if (defined('PASSWORD_ARGON2ID')) {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    return password_hash($password, PASSWORD_BCRYPT);
}

function verify_password(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

function is_logged_in(): bool
{
    return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_token']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . APP_URL . '/admin/login.php');
        exit;
    }
}