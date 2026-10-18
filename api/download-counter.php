<?php
// api/download-counter.php - Endpoint penghitung unduhan
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

function download_counter_respond(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function download_counter_normalize_origin(string $url): ?string
{
    $parts = parse_url($url);

    if (
        $parts === false
        || !isset($parts['scheme'], $parts['host'])
        || isset($parts['user'])
        || isset($parts['pass'])
        || isset($parts['path'])
        || isset($parts['query'])
        || isset($parts['fragment'])
    ) {
        return null;
    }

    $scheme = strtolower($parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return null;
    }

    $host = strtolower($parts['host']);
    $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

    return $scheme . '://' . $host . ':' . $port;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    download_counter_respond(405, [
        'ok' => false,
        'message' => 'Metode request tidak diizinkan.',
    ]);
}

$fetchSite = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
if ($fetchSite === 'cross-site') {
    download_counter_respond(403, [
        'ok' => false,
        'message' => 'Request lintas situs tidak diizinkan.',
    ]);
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? null;
if (is_string($origin) && $origin !== '') {
    $requestOrigin = download_counter_normalize_origin($origin);
    $applicationOrigin = download_counter_normalize_origin((string) APP_URL);

    if (
        $requestOrigin === null
        || $applicationOrigin === null
        || !hash_equals($applicationOrigin, $requestOrigin)
    ) {
        download_counter_respond(403, [
            'ok' => false,
            'message' => 'Origin request tidak diizinkan.',
        ]);
    }
}

$contentType = strtolower(trim(
    explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''), 2)[0]
));

if ($contentType !== 'application/json') {
    download_counter_respond(415, [
        'ok' => false,
        'message' => 'Request harus menggunakan JSON.',
    ]);
}

$contentLength = filter_var(
    $_SERVER['CONTENT_LENGTH'] ?? null,
    FILTER_VALIDATE_INT
);

if ($contentLength !== false && $contentLength !== null && $contentLength > 1024) {
    download_counter_respond(413, [
        'ok' => false,
        'message' => 'Ukuran request terlalu besar.',
    ]);
}

$rawBody = file_get_contents('php://input', false, null, 0, 1025);
if ($rawBody === false || strlen($rawBody) > 1024) {
    download_counter_respond(400, [
        'ok' => false,
        'message' => 'Request tidak valid.',
    ]);
}

try {
    $data = json_decode($rawBody, true, 8, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    download_counter_respond(400, [
        'ok' => false,
        'message' => 'Format JSON tidak valid.',
    ]);
}

if (!is_array($data) || !isset($data['id']) || !is_int($data['id']) || $data['id'] < 1) {
    download_counter_respond(400, [
        'ok' => false,
        'message' => 'ID dokumen tidak valid.',
    ]);
}

if (!check_rate_limit('download-counter', 60, 60)) {
    download_counter_respond(429, [
        'ok' => false,
        'message' => 'Terlalu banyak request. Silakan coba lagi nanti.',
    ]);
}

try {
    $stmt = $pdo->prepare(
        "UPDATE downloads
         SET downloads_count = COALESCE(downloads_count, 0) + 1
         WHERE id = ?
           AND status = 'Aktif'"
    );
    $stmt->execute([$data['id']]);

    if ($stmt->rowCount() !== 1) {
        download_counter_respond(404, [
            'ok' => false,
            'message' => 'Dokumen tidak ditemukan atau tidak aktif.',
        ]);
    }

    download_counter_respond(200, [
        'ok' => true,
        'message' => 'Hitungan unduhan diperbarui.',
    ]);
} catch (PDOException $exception) {
    error_log('Download counter update failed: ' . $exception->getMessage());

    download_counter_respond(500, [
        'ok' => false,
        'message' => 'Hitungan unduhan belum dapat diperbarui.',
    ]);
}