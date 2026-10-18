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
            <title>Permintaan tidak valid</title>
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

    security_clear_admin_session();

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
    <title>Konfirmasi logout - <?= sanitize(APP_NAME) ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            margin: 0;
            padding: 1.25rem;
            color: #0f172a;
            background: #f1f5f9;
            font-family: system-ui, "Segoe UI", sans-serif;
        }
        main {
            width: min(100%, 28rem);
            padding: 2rem;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            background: #fff;
            box-shadow: 0 20px 50px rgba(15, 23, 42, .12);
            text-align: center;
        }
        h1 { margin-top: 0; }
        p { color: #475569; line-height: 1.6; }
        .actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: .75rem;
            margin-top: 1.5rem;
        }
        button, .cancel {
            min-height: 2.75rem;
            padding: .7rem 1rem;
            border: 1px solid transparent;
            border-radius: .65rem;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }
        button { color: #fff; background: #b91c1c; }
        button:hover { background: #991b1b; }
        .cancel { color: #0f172a; border-color: #cbd5e1; background: #fff; }
        :focus-visible { outline: 3px solid #16a34a; outline-offset: 3px; }
    </style>
</head>
<body>
    <main>
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