<?php
// includes/functions.php - Helper aplikasi FKIP UNIMOF
declare(strict_types=1);

function &app_setting_cache(): array
{
    static $cache = [];
    return $cache;
}

function base_url(string $path = ''): string
{
    $base = rtrim((string) APP_URL, '/');
    $path = ltrim($path, '/');

    return $path === '' ? $base . '/' : $base . '/' . $path;
}

function asset(string $path): string
{
    return base_url('assets/' . ltrim($path, '/'));
}

function app_parse_timestamp(string $date): ?int
{
    $timestamp = strtotime($date);
    return $timestamp === false ? null : $timestamp;
}

function format_tanggal(string $date, string $format = 'd F Y'): string
{
    $timestamp = app_parse_timestamp($date);
    if ($timestamp === null) {
        return '';
    }

    $months = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    if ($format === 'd F Y') {
        return date('d', $timestamp)
            . ' ' . $months[(int) date('n', $timestamp)]
            . ' ' . date('Y', $timestamp);
    }

    return date($format, $timestamp);
}

function format_tanggal_singkat(string $date): string
{
    $timestamp = app_parse_timestamp($date);
    if ($timestamp === null) {
        return '';
    }

    $months = [
        'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des',
    ];

    return date('d', $timestamp)
        . ' ' . $months[(int) date('n', $timestamp) - 1]
        . ' ' . date('Y', $timestamp);
}

function format_tanggal_range(string $start_date, ?string $end_date): string
{
    if ($end_date === null || $end_date === '' || $start_date === $end_date) {
        return format_tanggal($start_date);
    }

    $startTimestamp = app_parse_timestamp($start_date);
    $endTimestamp = app_parse_timestamp($end_date);

    if ($startTimestamp === null || $endTimestamp === null) {
        return '';
    }

    if (date('m Y', $startTimestamp) === date('m Y', $endTimestamp)) {
        $months = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];

        return date('d', $startTimestamp)
            . ' - ' . date('d', $endTimestamp)
            . ' ' . $months[(int) date('n', $startTimestamp)]
            . ' ' . date('Y', $startTimestamp);
    }

    return format_tanggal($start_date) . ' s.d. ' . format_tanggal($end_date);
}

function excerpt(string $text, int $length = 150, string $end = '...'): string
{
    $text = strip_tags($text);
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

    if ($length <= 0) {
        return '';
    }

    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }

    $snippet = mb_substr($text, 0, $length, 'UTF-8');
    $lastSpace = mb_strrpos($snippet, ' ', 0, 'UTF-8');

    if ($lastSpace !== false && $lastSpace > 0) {
        $snippet = mb_substr($snippet, 0, $lastSpace, 'UTF-8');
    }

    return rtrim($snippet) . $end;
}

function truncate_words(string $text, int $word_limit = 20, string $end = '...'): string
{
    if ($word_limit <= 0) {
        return '';
    }

    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
    if ($text === '') {
        return '';
    }

    $words = explode(' ', $text);
    if (count($words) <= $word_limit) {
        return $text;
    }

    return implode(' ', array_slice($words, 0, $word_limit)) . $end;
}

function format_file_size(int $bytes, int $precision = 2): string
{
    $bytes = max(0, $bytes);
    $precision = max(0, min(6, $precision));

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $size = (float) $bytes;
    $unitIndex = 0;

    while ($size >= 1024 && $unitIndex < count($units) - 1) {
        $size /= 1024;
        $unitIndex++;
    }

    return round($size, $precision) . ' ' . $units[$unitIndex];
}

function get_setting(string $key, mixed $default = null): mixed
{
    global $pdo;

    $cache = &app_setting_cache();
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT nilai FROM pengaturan WHERE nama_key = ? LIMIT 1'
        );
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();

        $cache[$key] = $result === false ? $default : $result;
        return $cache[$key];
    } catch (PDOException $exception) {
        error_log('Unable to read application setting: ' . $exception->getMessage());
        return $default;
    }
}

if (!function_exists('update_setting')) {
    function update_setting(string $key, mixed $value): void
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO pengaturan (nama_key, nilai) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)'
            );
            $stmt->execute([$key, $value]);

            $cache = &app_setting_cache();
            unset($cache[$key]);
        } catch (PDOException $exception) {
            error_log('Unable to update application setting: ' . $exception->getMessage());
            throw $exception;
        }
    }
}

function get_statistik(): array
{
    global $pdo;
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    try {
        $stmt = $pdo->query('SELECT * FROM statistik ORDER BY id DESC LIMIT 1');
        $data = $stmt->fetch();

        $cache = $data ?: [
            'total_mahasiswa' => 1250,
            'total_dosen' => 68,
            'total_prodi' => 8,
            'total_penelitian' => 45,
            'total_alumni' => 3200,
        ];

        return $cache;
    } catch (PDOException $exception) {
        error_log('Unable to read statistics: ' . $exception->getMessage());
        return [];
    }
}

function get_featured_berita(int $limit = 3): array
{
    global $pdo;

    try {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM berita
             WHERE status = 'Published'
             ORDER BY is_featured DESC, published_at DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, max(1, min(50, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    } catch (PDOException $exception) {
        error_log('Unable to read featured news: ' . $exception->getMessage());
        return [];
    }
}

function get_prodi(): array
{
    global $pdo;

    try {
        $stmt = $pdo->query(
            "SELECT *
             FROM program_studi
             WHERE status = 'Aktif'
             ORDER BY urutan ASC"
        );

        return $stmt->fetchAll();
    } catch (PDOException $exception) {
        error_log('Unable to read study programs: ' . $exception->getMessage());
        return [];
    }
}

function get_agenda_mendatang(int $limit = 5): array
{
    global $pdo;

    try {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM agenda
             WHERE tanggal_mulai >= CURDATE()
               AND status = 'Aktif'
             ORDER BY tanggal_mulai ASC
             LIMIT ?"
        );
        $stmt->bindValue(1, max(1, min(50, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    } catch (PDOException $exception) {
        error_log('Unable to read upcoming agenda: ' . $exception->getMessage());
        return [];
    }
}

function flash_message(string $type, string $message): void
{
    $allowedTypes = ['success', 'error', 'warning', 'info'];
    if (!in_array($type, $allowedTypes, true)) {
        $type = 'info';
    }

    $_SESSION['flash'][$type] = $message;
}

function display_flash(): void
{
    if (empty($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return;
    }

    $icons = [
        'success' => '✅',
        'error' => '❌',
        'warning' => '⚠️',
        'info' => 'ℹ️',
    ];

    foreach ($_SESSION['flash'] as $type => $message) {
        if (!isset($icons[$type]) || !is_scalar($message)) {
            continue;
        }

        $safeType = htmlspecialchars(
            $type,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        $safeMessage = htmlspecialchars(
            (string) $message,
            ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
            'UTF-8'
        );

        echo "<div class='alert alert-{$safeType}' role='alert' data-auto-dismiss='5000'>"
            . "<span style='margin-right:8px'>{$icons[$type]}</span>"
            . "<span>{$safeMessage}</span>"
            . '</div>';
    }

    unset($_SESSION['flash']);
}

function app_upload_directory(string $subdir): ?string
{
    if (!defined('APP_DIR') || !is_string(APP_DIR) || APP_DIR === '') {
        return null;
    }

    $subdir = str_replace('\\', '/', trim($subdir, " \t\n\r\0\x0B/"));
    $segments = $subdir === '' ? [] : explode('/', $subdir);

    foreach ($segments as $segment) {
        if (
            $segment === ''
            || $segment === '.'
            || $segment === '..'
            || !preg_match('/^[a-zA-Z0-9_-]+$/', $segment)
        ) {
            return null;
        }
    }

    $assetsPath = APP_DIR . DIRECTORY_SEPARATOR . 'assets';
    if (!is_dir($assetsPath) && !mkdir($assetsPath, 0755, true) && !is_dir($assetsPath)) {
        return null;
    }

    $assetsRoot = realpath($assetsPath);
    if ($assetsRoot === false) {
        return null;
    }

    $targetPath = $assetsRoot;
    foreach ($segments as $segment) {
        $targetPath .= DIRECTORY_SEPARATOR . $segment;
    }

    if (!is_dir($targetPath) && !mkdir($targetPath, 0755, true) && !is_dir($targetPath)) {
        return null;
    }

    $realTarget = realpath($targetPath);
    $prefix = rtrim($assetsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if (
        $realTarget === false
        || stripos($realTarget . DIRECTORY_SEPARATOR, $prefix) !== 0
    ) {
        return null;
    }

    return $realTarget;
}

function upload_image(
    array $file,
    string $subdir = 'uploads',
    int $max_bytes = 2097152
): array {
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'name' => null];
    }

    if ($errorCode !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload gagal. Silakan coba kembali.'];
    }

    $tmpName = $file['tmp_name'] ?? '';
    if (!is_string($tmpName) || $tmpName === '' || !is_uploaded_file($tmpName)) {
        return ['ok' => false, 'error' => 'File upload tidak valid.'];
    }

    $actualSize = filesize($tmpName);
    if ($actualSize === false || $actualSize > max(0, $max_bytes)) {
        return [
            'ok' => false,
            'error' => 'Ukuran file maksimal '
                . format_file_size(max(0, $max_bytes))
                . '.',
        ];
    }

    if (!class_exists(finfo::class)) {
        return ['ok' => false, 'error' => 'Validasi tipe file tidak tersedia di server.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpName);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!is_string($mime) || !isset($allowed[$mime])) {
        return ['ok' => false, 'error' => 'Format gambar tidak diizinkan.'];
    }

    if (@getimagesize($tmpName) === false) {
        return ['ok' => false, 'error' => 'File bukan gambar valid.'];
    }

    $directory = app_upload_directory($subdir);
    if ($directory === null) {
        error_log('Unable to resolve a safe image upload directory.');
        return ['ok' => false, 'error' => 'Folder upload tidak dapat digunakan.'];
    }

    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($tmpName, $directory . DIRECTORY_SEPARATOR . $name)) {
        error_log('Unable to move uploaded image into its destination directory.');
        return ['ok' => false, 'error' => 'Gagal menyimpan gambar ke server.'];
    }

    return ['ok' => true, 'name' => $name];
}

function upload_file(
    array $file,
    string $subdir = 'downloads',
    array $allowed_mimes = [],
    int $max_bytes = 10485760
): array {
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'Tidak ada file yang dipilih.'];
    }

    if ($errorCode !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload gagal. Silakan coba kembali.'];
    }

    $tmpName = $file['tmp_name'] ?? '';
    if (!is_string($tmpName) || $tmpName === '' || !is_uploaded_file($tmpName)) {
        return ['ok' => false, 'error' => 'File upload tidak valid.'];
    }

    $actualSize = filesize($tmpName);
    if ($actualSize === false || $actualSize > max(0, $max_bytes)) {
        return [
            'ok' => false,
            'error' => 'Ukuran file maksimal '
                . format_file_size(max(0, $max_bytes))
                . '.',
        ];
    }

    if (!class_exists(finfo::class)) {
        return ['ok' => false, 'error' => 'Validasi tipe file tidak tersedia di server.'];
    }

    $extensionsByMime = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/zip' => 'zip',
        'application/x-zip-compressed' => 'zip',
        'application/x-rar-compressed' => 'rar',
        'application/vnd.rar' => 'rar',
        'application/x-7z-compressed' => '7z',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpName);

    if (
        !is_string($mime)
        || !in_array($mime, $allowed_mimes, true)
        || !isset($extensionsByMime[$mime])
    ) {
        return ['ok' => false, 'error' => 'Format file tidak diizinkan.'];
    }

    $directory = app_upload_directory($subdir);
    if ($directory === null) {
        error_log('Unable to resolve a safe document upload directory.');
        return ['ok' => false, 'error' => 'Folder upload tidak dapat digunakan.'];
    }

    $name = bin2hex(random_bytes(16)) . '.' . $extensionsByMime[$mime];
    if (!move_uploaded_file($tmpName, $directory . DIRECTORY_SEPARATOR . $name)) {
        error_log('Unable to move uploaded document into its destination directory.');
        return ['ok' => false, 'error' => 'Gagal menyimpan file ke server.'];
    }

    return [
        'ok' => true,
        'name' => $name,
        'size' => format_file_size((int) $actualSize),
    ];
}

function delete_upload(?string $name, string $subdir = 'uploads'): void
{
    if ($name === null || $name === '' || basename($name) !== $name) {
        return;
    }

    $directory = app_upload_directory($subdir);
    if ($directory === null) {
        error_log('Unable to resolve a safe upload directory for deletion.');
        return;
    }

    $path = $directory . DIRECTORY_SEPARATOR . $name;
    if (is_file($path) && !unlink($path)) {
        error_log('Unable to delete an uploaded file.');
    }
}

function time_ago(int $timestamp): string
{
    $diff = time() - $timestamp;

    if ($diff < 0) {
        return 'Akan datang';
    }
    if ($diff < 60) {
        return 'Baru saja';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' menit lalu';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' jam lalu';
    }
    if ($diff < 604800) {
        return floor($diff / 86400) . ' hari lalu';
    }
    if ($diff < 2592000) {
        return floor($diff / 604800) . ' minggu lalu';
    }
    if ($diff < 31536000) {
        return floor($diff / 2592000) . ' bulan lalu';
    }

    return floor($diff / 31536000) . ' tahun lalu';
}

function highlight_search(string $text, string $q): string
{
    $query = trim($q);
    $safeText = htmlspecialchars(
        $text,
        ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
        'UTF-8'
    );

    if ($query === '') {
        return $safeText;
    }

    $safeQuery = htmlspecialchars(
        $query,
        ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
        'UTF-8'
    );

    $pattern = '/' . preg_quote($safeQuery, '/') . '/iu';
    $result = preg_replace_callback(
        $pattern,
        static fn(array $matches): string => '<mark>' . $matches[0] . '</mark>',
        $safeText
    );

    return $result ?? $safeText;
}

function generate_pagination(
    int $current_page,
    int $total_pages,
    string $base_url
): string {
    $total_pages = max(0, $total_pages);
    if ($total_pages <= 1) {
        return '';
    }

    $current_page = max(1, min($current_page, $total_pages));
    $safeBaseUrl = htmlspecialchars(
        $base_url,
        ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
        'UTF-8'
    );

    $html = '<nav class="pagination-pro" aria-label="Navigasi halaman">'
        . '<div class="page-info">Halaman '
        . $current_page . ' dari ' . $total_pages
        . '</div><div class="page-buttons">';

    if ($current_page > 1) {
        $html .= '<a href="' . $safeBaseUrl . ($current_page - 1)
            . '" class="page-btn" aria-label="Halaman sebelumnya">←</a>';
    } else {
        $html .= '<span class="page-btn disabled" aria-disabled="true">←</span>';
    }

    $range = 2;
    $start = max(1, $current_page - $range);
    $end = min($total_pages, $current_page + $range);

    if ($start > 1) {
        $html .= '<a href="' . $safeBaseUrl . '1" class="page-btn">1</a>';
        if ($start > 2) {
            $html .= '<span class="page-dots" aria-hidden="true">…</span>';
        }
    }

    for ($page = $start; $page <= $end; $page++) {
        if ($page === $current_page) {
            $html .= '<span class="page-btn current" aria-current="page">'
                . $page . '</span>';
        } else {
            $html .= '<a href="' . $safeBaseUrl . $page . '" class="page-btn">'
                . $page . '</a>';
        }
    }

    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            $html .= '<span class="page-dots" aria-hidden="true">…</span>';
        }

        $html .= '<a href="' . $safeBaseUrl . $total_pages . '" class="page-btn">'
            . $total_pages . '</a>';
    }

    if ($current_page < $total_pages) {
        $html .= '<a href="' . $safeBaseUrl . ($current_page + 1)
            . '" class="page-btn" aria-label="Halaman berikutnya">→</a>';
    } else {
        $html .= '<span class="page-btn disabled" aria-disabled="true">→</span>';
    }

    return $html . '</div></nav>';
}

function clean_filename(string $filename): string
{
    $cleaned = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $filename) ?? '';
    return preg_replace('/_+/', '_', $cleaned) ?? '';
}

function check_rate_limit(
    string $identifier,
    int $max_attempts = 5,
    int $window_seconds = 3600
): bool {
    $max_attempts = max(1, $max_attempts);
    $window_seconds = max(1, $window_seconds);
    $key = 'rl_' . hash('sha256', $identifier);
    $now = time();
    $data = $_SESSION[$key] ?? null;

    if (
        !is_array($data)
        || !isset($data['count'], $data['start'])
        || !is_numeric($data['count'])
        || !is_numeric($data['start'])
        || $now - (int) $data['start'] >= $window_seconds
    ) {
        $data = ['count' => 0, 'start' => $now];
    }

    if ((int) $data['count'] >= $max_attempts) {
        $_SESSION[$key] = $data;
        return false;
    }

    $data['count'] = (int) $data['count'] + 1;
    $_SESSION[$key] = $data;

    return true;
}

function rate_limit_remaining(
    string $identifier,
    int $max_attempts = 5,
    int $window_seconds = 3600
): int {
    $max_attempts = max(1, $max_attempts);
    $window_seconds = max(1, $window_seconds);
    $key = 'rl_' . hash('sha256', $identifier);
    $now = time();
    $data = $_SESSION[$key] ?? null;

    if (
        !is_array($data)
        || !isset($data['count'], $data['start'])
        || !is_numeric($data['count'])
        || !is_numeric($data['start'])
        || $now - (int) $data['start'] >= $window_seconds
    ) {
        return $max_attempts;
    }

    return max(0, $max_attempts - (int) $data['count']);
}