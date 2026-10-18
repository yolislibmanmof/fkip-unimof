<?php
// includes/security.php - Helper keamanan aplikasi
declare(strict_types=1);

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

function clean_html(string $html): string
{
    if (!class_exists(DOMDocument::class)) {
        return nl2br(htmlspecialchars(
            strip_tags($html),
            ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
            'UTF-8'
        ));
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

        $loaded = $document->loadHTML($source, LIBXML_NONET | LIBXML_HTML_NODEFDTD);
        if (!$loaded) {
            return htmlspecialchars(
                strip_tags($html),
                ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
                'UTF-8'
            );
        }

        $xpath = new DOMXPath($document);
        $roots = $xpath->query('//*[@id="clean-html-root"]');
        $root = $roots !== false ? $roots->item(0) : null;

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
                $attributeNames = [];

                foreach ($child->attributes as $attribute) {
                    $attributeNames[] = strtolower($attribute->name);
                }

                foreach ($attributeNames as $attributeName) {
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

    return strtolower(preg_replace('~-+~', '-', $text) ?? '');
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
    return isset($_SESSION['admin_id'], $_SESSION['admin_token'])
        && is_numeric($_SESSION['admin_id'])
        && is_string($_SESSION['admin_token'])
        && $_SESSION['admin_token'] !== '';
}

function security_clear_admin_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $cookie['path'] !== '' ? $cookie['path'] : '/',
            'domain' => $cookie['domain'],
            'secure' => (bool) $cookie['secure'],
            'httponly' => (bool) $cookie['httponly'],
            'samesite' => $cookie['samesite'] ?? 'Strict',
        ]);
    }

    if (session_status() === PHP_SESSION_ACTIVE && !session_destroy()) {
        error_log('Unable to destroy an invalid admin session.');
    }
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . APP_URL . '/admin/login.php');
        exit;
    }

    $adminId = filter_var($_SESSION['admin_id'], FILTER_VALIDATE_INT);
    $now = time();
    $idleTimeout = 1800;
    $absoluteTimeout = 28800;
    $loginTime = filter_var($_SESSION['login_time'] ?? null, FILTER_VALIDATE_INT);
    $lastActivity = filter_var($_SESSION['last_activity'] ?? null, FILTER_VALIDATE_INT);

    if (
        $adminId === false
        || $adminId < 1
        || $loginTime === false
        || $loginTime === null
        || $lastActivity === false
        || $lastActivity === null
        || $now - $lastActivity > $idleTimeout
        || $now - $loginTime > $absoluteTimeout
    ) {
        security_clear_admin_session();
        header('Location: ' . APP_URL . '/admin/login.php?expired=1');
        exit;
    }

    $currentUserAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $sessionUserAgent = $_SESSION['user_agent'] ?? null;

    if (
        !is_string($sessionUserAgent)
        || !hash_equals($sessionUserAgent, $currentUserAgent)
    ) {
        security_clear_admin_session();
        header('Location: ' . APP_URL . '/admin/login.php?expired=1');
        exit;
    }

    global $pdo;

    try {
        $stmt = $pdo->prepare(
            "SELECT username, full_name, role
             FROM admins
             WHERE id = ? AND status = 'Active'
             LIMIT 1"
        );
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $exception) {
        error_log('Admin session validation failed: ' . $exception->getMessage());
        http_response_code(503);
        exit('Layanan admin sementara tidak tersedia.');
    }

    if (!$admin) {
        security_clear_admin_session();
        header('Location: ' . APP_URL . '/admin/login.php?expired=1');
        exit;
    }

    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_name'] = $admin['full_name'];
    $_SESSION['admin_role'] = $admin['role'];
    $_SESSION['last_activity'] = $now;
}

function require_role(string ...$allowedRoles): void
{
    require_login();

    $role = $_SESSION['admin_role'] ?? '';
    if (!is_string($role) || !in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        exit('Anda tidak memiliki izin untuk mengakses halaman ini.');
    }
}