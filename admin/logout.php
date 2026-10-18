<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Permintaan tidak valid - <?= sanitize(APP_NAME) ?></title>
            <style>
                body{font-family:system-ui,sans-serif;background:#f1f5f9;color:#0f172a;
                    min-height:100vh;display:grid;place-items:center;margin:0;padding:1rem}
                main{max-width:28rem;background:#fff;border-radius:1rem;padding:2rem;
                    box-shadow:0 10px 30px rgba(15,23,42,.1);text-align:center}
                a{color:#0a6847;font-weight:700}
            </style>
        </head>
        <body>
            <main>
                <h1>Permintaan tidak valid</h1>
                <p>Token keamanan tidak cocok. Muat ulang halaman lalu coba kembali.</p>
                <a href="logout.php">Kembali ke konfirmasi logout</a>
            </main>
        </body>
        </html>
        <?php
        exit;
    }

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

    if (!session_destroy()) {
        error_log('Unable to destroy admin session during logout.');
        http_response_code(500);
        exit('Sesi belum dapat diakhiri. Silakan coba kembali.');
    }

    header('Location: login.php?logged_out=1');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Metode request tidak diizinkan.');
}

$csrfToken = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light">
    <title>Konfirmasi logout - <?= sanitize(APP_NAME) ?></title>
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, "Segoe UI", system-ui, -apple-system, sans-serif;
            color: #0f172a;
            background: #f1f5f9;
        }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            margin: 0;
            padding: 1.25rem;
            background:
                radial-gradient(ellipse at top left, rgba(16,185,129,.12), transparent 40%),
                #f1f5f9;
        }
        main {
            width: min(100%, 28rem);
            padding: clamp(1.5rem, 6vw, 2.5rem);
            border: 1px solid #e2e8f0;
            border-radius: 1.25rem;
            background: #fff;
            box-shadow: 0 24px 60px rgba(15,23,42,.12);
            text-align: center;
        }
        .icon {
            display: grid;
            width: 4rem;
            height: 4rem;
            place-items: center;
            margin: 0 auto 1.25rem;
            border-radius: 1.25rem;
            background: #ecfdf5;
            font-size: 1.8rem;
        }
        h1 { margin: 0 0 .75rem; font-size: clamp(1.5rem, 5vw, 1.9rem); }
        p { margin: 0; color: #475569; line-height: 1.65; }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            justify-content: center;
            margin-top: 1.75rem;
        }
        button, .cancel {
            min-height: 2.8rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: .7rem 1.1rem;
            border: 1px solid transparent;
            border-radius: .7rem;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }
        button {
            color: #fff;
            background: #b91c1c;
        }
        button:hover { background: #991b1b; }
        .cancel {
            color: #0f172a;
            border-color: #cbd5e1;
            background: #fff;
        }
        .cancel:hover { background: #f8fafc; }
        :focus-visible { outline: 3px solid #16a34a; outline-offset: 3px; }
    </style>
</head>
<body>
    <main>
        <div class="icon" aria-hidden="true">🚪</div>
        <h1>Keluar dari admin?</h1>
        <p>Sesi admin Anda akan diakhiri pada perangkat ini.</p>

        <form method="post" action="logout.php">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= sanitize($csrfToken) ?>"
            >
            <div class="actions">
                <button type="submit">Ya, keluar</button>
                <a class="cancel" href="dashboard.php">Batal</a>
            </div>
        </form>
    </main>
</body>
</html>