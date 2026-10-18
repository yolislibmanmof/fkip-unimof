<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

function readiness_respond(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow');

    echo json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    readiness_respond(405, [
        'status' => 'error',
        'message' => 'Metode request tidak diizinkan.',
    ]);
}

$expectedToken = function_exists('app_env')
    ? app_env('HEALTHCHECK_TOKEN')
    : getenv('HEALTHCHECK_TOKEN');

$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$providedToken = '';

if (
    is_string($authorization)
    && preg_match('/^Bearer\s+([^\s]+)$/i', trim($authorization), $matches) === 1
) {
    $providedToken = $matches[1];
}

if (
    !is_string($expectedToken)
    || strlen($expectedToken) < 32
    || $providedToken === ''
    || !hash_equals($expectedToken, $providedToken)
) {
    readiness_respond(404, [
        'status' => 'not_found',
    ]);
}

try {
    $pdo->query('SELECT 1')->fetchColumn();
} catch (Throwable $exception) {
    error_log('Readiness database check failed: ' . $exception->getMessage());

    readiness_respond(503, [
        'status' => 'not_ready',
        'checks' => [
            'database' => 'unavailable',
        ],
    ]);
}

readiness_respond(200, [
    'status' => 'ready',
    'service' => 'fkip-unimof',
    'checks' => [
        'database' => 'ok',
    ],
    'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
]);