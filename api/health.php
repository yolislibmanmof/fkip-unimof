<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));

if ($method !== 'GET' && $method !== 'HEAD') {
    header('Allow: GET, HEAD');
    http_response_code(405);

    if ($method !== 'HEAD') {
        echo json_encode([
            'status' => 'error',
            'message' => 'Metode request tidak diizinkan.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    exit;
}

http_response_code(200);

if ($method === 'HEAD') {
    exit;
}

echo json_encode([
    'status' => 'ok',
    'service' => 'fkip-unimof',
    'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);