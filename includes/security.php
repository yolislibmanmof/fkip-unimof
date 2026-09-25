<?php
// includes/security.php - Layer keamanan tingkat tinggi
declare(strict_types=1);

// Prevent clickjacking & XSS
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function sanitize($input): string {
    if (is_null($input)) return '';
    $input = trim((string)$input);
    return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function validate_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function clean_html(string $html): string {
    $allowed = '<p><br><strong><em><u><h1><h2><h3><h4><h5><h6><ul><ol><li><a><img><blockquote><pre><code>';
    return strip_tags($html, $allowed);
}

function generate_slug(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    return strtolower($text);
}

function hash_password(string $password): string {
    // Fallback ke BCRYPT jika server tidak support ARGON2ID
    if (defined('PASSWORD_ARGON2ID')) {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
    return password_hash($password, PASSWORD_BCRYPT);
}

function verify_password(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

function is_logged_in(): bool {
    return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_token']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ' . APP_URL . '/admin/login.php');
        exit;
    }
}