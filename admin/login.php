<?php
// admin/login.php - Halaman Login Admin EXTREME MULTIMATE
require_once __DIR__ . '/../includes/config.php';

// Redirect jika sudah login
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

// ===== RATE LIMITING CHECK =====
$rate_limit_file = __DIR__ . '/../storage/rate_limits/' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '.json';
$rate_limit = ['attempts' => 0, 'last_attempt' => 0, 'blocked_until' => 0];
if (file_exists($rate_limit_file)) {
    $rate_limit = json_decode(file_get_contents($rate_limit_file), true) ?? $rate_limit;
}

$is_blocked = $rate_limit['blocked_until'] > time();
$remaining_block = max(0, $rate_limit['blocked_until'] - time());

$error = '';
$csrf_token = generate_csrf_token();
$show_2fa = false;
$pending_admin_id = 0;

// Check if 2FA is pending
if (isset($_SESSION['2fa_pending']) && $_SESSION['2fa_pending'] > 0) {
    $show_2fa = true;
    $pending_admin_id = $_SESSION['2fa_pending'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF tidak valid. Silakan refresh halaman.';
    } elseif ($is_blocked) {
        $error = "Akun diblokir sementara. Coba lagi dalam " . ceil($remaining_block / 60) . " menit.";
    } elseif ($show_2fa && isset($_POST['otp_code'])) {
        // ===== VERIFY 2FA OTP =====
        $otp_code = trim($_POST['otp_code'] ?? '');
        $admin_id = $_SESSION['2fa_pending'] ?? 0;
        
        if (strlen($otp_code) === 6 && ctype_digit($otp_code)) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ? AND two_factor_secret = ? AND status = 'Active'");
                $stmt->execute([$admin_id, $otp_code]);
                $admin = $stmt->fetch();
                
                if ($admin) {
                    // 2FA verified successfully
                    session_regenerate_id(true);
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_name'] = $admin['full_name'];
                    $_SESSION['admin_role'] = $admin['role'];
                    $_SESSION['admin_token'] = bin2hex(random_bytes(32));
                    $_SESSION['login_time'] = time();
                    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
                    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    unset($_SESSION['2fa_pending']);
                    
                    $updateStmt = $pdo->prepare("UPDATE admins SET last_login = NOW(), last_ip = ?, last_user_agent = ? WHERE id = ?");
                    $updateStmt->execute([$_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', $admin['id']]);
                    
                    // Log successful login
                    try {
                        $pdo->prepare("INSERT INTO admin_login_logs (admin_id, ip_address, user_agent, status, created_at) VALUES (?, ?, ?, 'success', NOW())")
                            ->execute([$admin['id'], $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                    } catch (Exception $e) {}
                    
                    // Reset rate limit
                    @unlink($rate_limit_file);
                    
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = 'Kode OTP tidak valid.';
                }
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan sistem.';
            }
        } else {
            $error = 'Kode OTP harus 6 digit angka.';
        }
    } else {
        // ===== NORMAL LOGIN ATTEMPT =====
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Username dan password wajib diisi.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? AND status = 'Active' LIMIT 1");
                $stmt->execute([$username]);
                $admin = $stmt->fetch();
                
                // Honeypot check
                if (!empty($_POST['website'] ?? '')) {
                    $error = 'Login gagal.';
                } elseif ($admin && verify_password($password, $admin['password'])) {
                    // Check if 2FA is enabled
                    if ($admin['two_factor_enabled'] ?? false) {
                        // Show 2FA screen
                        $_SESSION['2fa_pending'] = $admin['id'];
                        $show_2fa = true;
                        $pending_admin_id = $admin['id'];
                    } else {
                        // Direct login
                        session_regenerate_id(true);
                        $_SESSION['admin_id'] = $admin['id'];
                        $_SESSION['admin_username'] = $admin['username'];
                        $_SESSION['admin_name'] = $admin['full_name'];
                        $_SESSION['admin_role'] = $admin['role'];
                        $_SESSION['admin_token'] = bin2hex(random_bytes(32));
                        $_SESSION['login_time'] = time();
                        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
                        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                        
                        $updateStmt = $pdo->prepare("UPDATE admins SET last_login = NOW(), last_ip = ?, last_user_agent = ? WHERE id = ?");
                        $updateStmt->execute([$_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', $admin['id']]);
                        
                        // Log successful login
                        try {
                            $pdo->prepare("INSERT INTO admin_login_logs (admin_id, ip_address, user_agent, status, created_at) VALUES (?, ?, ?, 'success', NOW())")
                                ->execute([$admin['id'], $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                        } catch (Exception $e) {}
                        
                        // Reset rate limit
                        @unlink($rate_limit_file);
                        
                        header('Location: dashboard.php');
                        exit;
                    }
                } else {
                    $error = 'Username atau password salah.';
                    
                    // Increment rate limit
                    $rate_limit['attempts']++;
                    $rate_limit['last_attempt'] = time();
                    if ($rate_limit['attempts'] >= 5) {
                        $rate_limit['blocked_until'] = time() + 900; // 15 minutes
                    }
                    @mkdir(dirname($rate_limit_file), 0755, true);
                    @file_put_contents($rate_limit_file, json_encode($rate_limit));
                    
                    // Log failed login
                    try {
                        $pdo->prepare("INSERT INTO admin_login_logs (admin_id, ip_address, user_agent, status, created_at) VALUES (NULL, ?, ?, 'failed', NOW())")
                            ->execute([$_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                    } catch (Exception $e) {}
                    
                    sleep(1);
                }
            } catch (Exception $e) {
                error_log($e->getMessage());
                $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
            }
        }
    }
}

// Quotes Islami untuk branding side
$quotes = [
    ['text' => 'Barangsiapa menempuh jalan mencari ilmu, Allah mudahkan jalannya ke surga.', 'author' => 'HR. Muslim'],
    ['text' => 'Sebaik-baik manusia adalah yang paling bermanfaat bagi manusia lain.', 'author' => 'HR. Ahmad'],
    ['text' => 'Ilmu itu lebih baik daripada harta. Ilmu menjagamu, engkau menjaga harta.', 'author' => 'Ali bin Abi Thalib'],
    ['text' => 'Didiklah anak-anakmu sesuai zamannya, karena mereka hidup di zaman mereka.', 'author' => 'Ali bin Abi Thalib'],
    ['text' => 'Tuntutlah ilmu dari buaian hingga ke liang lahat.', 'author' => 'Pepatah Arab'],
    ['text' => 'Orang yang berilmu itu lebih utama daripada orang yang puasa, shalat, dan berjihad.', 'author' => 'HR. Tirmidzi'],
];
$quote = $quotes[array_rand($quotes)];

// Get last login info from cookie
$last_login = $_COOKIE['last_login_username'] ?? null;

// Security info
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$client_browser = 'Unknown';
if (isset($_SERVER['HTTP_USER_AGENT'])) {
    $ua = $_SERVER['HTTP_USER_AGENT'];
    if (strpos($ua, 'Chrome') !== false) $client_browser = 'Chrome';
    elseif (strpos($ua, 'Firefox') !== false) $client_browser = 'Firefox';
    elseif (strpos($ua, 'Safari') !== false) $client_browser = 'Safari';
    elseif (strpos($ua, 'Edge') !== false) $client_browser = 'Edge';
    elseif (strpos($ua, 'Opera') !== false) $client_browser = 'Opera';
}

$attempts_left = max(0, 5 - $rate_limit['attempts']);
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a6847">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <title>Portal Admin | FKIP UNIMOF</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@700;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #0a6847;
            --primary-light: #16a34a;
            --primary-dark: #084d35;
            --accent: #f5a623;
            --accent-light: #fbbf24;
            --dark: #0f172a;
            --text: #334155;
            --muted: #64748b;
            --border: #e2e8f0;
            --error: #dc2626;
            --error-light: #fee2e2;
            --success: #10b981;
            --warning: #f59e0b;
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            --card-bg: #ffffff;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        [data-theme="dark"] {
            --dark: #f1f5f9;
            --text: #cbd5e1;
            --muted: #94a3b8;
            --border: #334155;
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --card-bg: #1e293b;
        }
        html, body { height: 100%; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            color: var(--dark);
            overflow-x: hidden;
            background: var(--dark);
            transition: background 0.3s, color 0.3s;
        }

        /* === LAYOUT === */
        .login-shell {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            min-height: 100vh;
            position: relative;
        }

        /* === BRANDING SIDE (LEFT) === */
        .brand-side {
            position: relative;
            background: linear-gradient(135deg, #0a6847 0%, #084d35 50%, #16213e 100%);
            color: #fff;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
        }
        .brand-side::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 20% 30%, rgba(245,166,35,0.25) 0%, transparent 45%),
                radial-gradient(circle at 80% 70%, rgba(59,130,246,0.2) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, rgba(16,185,129,0.15) 0%, transparent 60%);
            animation: aurora 20s ease-in-out infinite;
        }
        @keyframes aurora {
            0%, 100% { transform: translate(0, 0) scale(1); opacity: 1; }
            33% { transform: translate(-30px, 20px) scale(1.1); opacity: 0.9; }
            66% { transform: translate(20px, -30px) scale(0.95); opacity: 1; }
        }

        /* Particles dengan mouse interaction */
        .particles {
            position: absolute;
            inset: 0;
            overflow: hidden;
            pointer-events: none;
        }
        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(255,255,255,0.6);
            border-radius: 50%;
            animation: float 20s infinite linear;
            transition: transform 0.3s ease-out;
        }
        .particle.mouse-near {
            transform: scale(2);
            background: rgba(245,166,35,0.9);
            box-shadow: 0 0 10px rgba(245,166,35,0.5);
        }
        @keyframes float {
            0% { transform: translateY(100vh) translateX(0); opacity: 0; }
            10% { opacity: 0.8; }
            90% { opacity: 0.8; }
            100% { transform: translateY(-10vh) translateX(50px); opacity: 0; }
        }

        /* Grid pattern */
        .brand-side::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
        }

        .brand-content { position: relative; z-index: 2; display: flex; flex-direction: column; height: 100%; }

        /* Top bar dengan theme toggle */
        .brand-topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        .theme-toggle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            transition: all 0.3s;
        }
        .theme-toggle:hover {
            background: rgba(255,255,255,0.2);
            transform: rotate(180deg);
        }

        /* Logo */
        .brand-logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .logo-mark {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #fff 0%, rgba(255,255,255,0.8) 100%);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 1.75rem;
            font-weight: 900;
            color: var(--primary);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            position: relative;
            animation: logoFloat 3s ease-in-out infinite;
        }
        @keyframes logoFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        .logo-mark::after {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--accent), transparent);
            z-index: -1;
            opacity: 0.5;
            animation: logoGlow 2s ease-in-out infinite;
        }
        @keyframes logoGlow {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 0.8; }
        }
        .logo-text { display: flex; flex-direction: column; }
        .logo-text strong { font-size: 1.1rem; font-weight: 800; letter-spacing: -0.01em; }
        .logo-text span { font-size: 0.78rem; opacity: 0.75; margin-top: 2px; }

        /* Hero */
        .brand-hero { flex: 1; display: flex; flex-direction: column; justify-content: center; }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.18);
            padding: 0.45rem 1rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            width: fit-content;
            margin-bottom: 1.5rem;
        }
        .pulse-dot {
            width: 8px; height: 8px;
            background: #10b981;
            border-radius: 50%;
            position: relative;
        }
        .pulse-dot::after {
            content: '';
            position: absolute; inset: 0;
            background: #10b981;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            to { transform: scale(2.5); opacity: 0; }
        }
        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2rem, 4vw, 3.5rem);
            font-weight: 900;
            line-height: 1.1;
            margin-bottom: 1rem;
            letter-spacing: -0.02em;
        }
        .hero-title .accent-text {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-style: italic;
        }
        .hero-subtitle {
            font-size: 1rem;
            opacity: 0.85;
            line-height: 1.7;
            max-width: 500px;
            margin-bottom: 2.5rem;
        }

        /* Features */
        .feature-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 2.5rem;
        }
        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 12px;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        .feature-item:hover {
            background: rgba(255,255,255,0.15);
            transform: translateX(4px);
        }
        .feature-icon {
            width: 32px;
            height: 32px;
            background: rgba(255,255,255,0.15);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        /* Quote */
        .quote-box {
            padding: 1.25rem 1.5rem;
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.15);
            border-left: 3px solid var(--accent);
            border-radius: 12px;
            font-size: 0.9rem;
            line-height: 1.6;
            font-style: italic;
            position: relative;
        }
        .quote-box::before {
            content: '❝';
            position: absolute;
            top: -10px;
            left: 12px;
            font-size: 2.5rem;
            color: var(--accent);
            opacity: 0.5;
            font-style: normal;
        }
        .quote-author {
            display: block;
            margin-top: 0.5rem;
            font-style: normal;
            font-size: 0.78rem;
            opacity: 0.75;
            font-weight: 600;
        }

        /* Bottom: Clock & Security */
        .brand-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.12);
            flex-wrap: wrap;
            gap: 1rem;
        }
        .live-clock {
            font-variant-numeric: tabular-nums;
        }
        .clock-time {
            font-size: 1.75rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.02em;
        }
        .clock-date {
            font-size: 0.75rem;
            opacity: 0.75;
            margin-top: 4px;
        }
        .security-badges {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .sec-badge {
            padding: 0.3rem 0.65rem;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        .sec-badge.active { background: rgba(16,185,129,0.2); border-color: #10b981; }

        /* === FORM SIDE (RIGHT) === */
        .form-side {
            background: var(--bg-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
            overflow-y: auto;
            transition: background 0.3s;
        }
        .form-side::before {
            content: '';
            position: absolute;
            top: -100px;
            right: -100px;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(10,104,71,0.08) 0%, transparent 70%);
            pointer-events: none;
        }
        .form-side::after {
            content: '';
            position: absolute;
            bottom: -80px;
            left: -80px;
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(245,166,35,0.08) 0%, transparent 70%);
            pointer-events: none;
        }

        .login-container {
            width: 100%;
            max-width: 440px;
            position: relative;
            z-index: 2;
            animation: slideUp 0.7s cubic-bezier(0.2, 0.9, 0.3, 1.1);
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Security Info Bar */
        .security-bar {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            border: 1px solid #93c5fd;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.78rem;
        }
        [data-theme="dark"] .security-bar {
            background: linear-gradient(135deg, #1e3a8a33, #1e40af33);
            border-color: #3b82f6;
            color: #93c5fd;
        }
        .security-bar-icon { font-size: 1.25rem; }
        .security-bar-content { flex: 1; }
        .security-bar-title { font-weight: 700; color: #1e40af; }
        [data-theme="dark"] .security-bar-title { color: #93c5fd; }
        .security-bar-meta { color: #64748b; font-size: 0.72rem; margin-top: 0.15rem; }
        [data-theme="dark"] .security-bar-meta { color: #94a3b8; }

        /* Rate Limit Warning */
        .rate-limit-warning {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 1px solid #fbbf24;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .rate-limit-icon { font-size: 1.5rem; }
        .rate-limit-content { flex: 1; }
        .rate-limit-title { font-weight: 700; color: #92400e; font-size: 0.88rem; }
        .rate-limit-desc { font-size: 0.78rem; color: #a16207; margin-top: 0.15rem; }
        .rate-limit-bar {
            height: 6px;
            background: #fde68a;
            border-radius: 999px;
            margin-top: 0.5rem;
            overflow: hidden;
        }
        .rate-limit-fill {
            height: 100%;
            background: linear-gradient(90deg, #f59e0b, #dc2626);
            border-radius: 999px;
            transition: width 0.5s ease;
        }

        /* Blocked State */
        .blocked-overlay {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border: 2px solid #fca5a5;
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
        }
        .blocked-icon { font-size: 3rem; margin-bottom: 1rem; }
        .blocked-title { font-size: 1.25rem; font-weight: 800; color: #991b1b; margin-bottom: 0.5rem; }
        .blocked-desc { font-size: 0.88rem; color: #dc2626; margin-bottom: 1rem; }
        .blocked-timer {
            font-family: 'JetBrains Mono', monospace;
            font-size: 2rem;
            font-weight: 800;
            color: #dc2626;
            margin-bottom: 1rem;
        }

        .form-header {
            margin-bottom: 2.25rem;
        }
        .form-header .pre-title {
            color: var(--primary);
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .pre-title::before {
            content: '';
            width: 20px;
            height: 2px;
            background: var(--primary);
        }
        .form-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 900;
            color: var(--dark);
            line-height: 1.15;
            margin-bottom: 0.5rem;
            letter-spacing: -0.02em;
        }
        .form-header p {
            color: var(--muted);
            font-size: 0.92rem;
        }

        /* Last Login Info */
        .last-login-info {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.82rem;
        }
        .last-login-icon { font-size: 1.25rem; }
        .last-login-text { flex: 1; }
        .last-login-text strong { color: var(--primary); }
        .last-login-dismiss {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--muted);
            font-size: 1rem;
            padding: 0.25rem;
        }
        .last-login-dismiss:hover { color: var(--error); }

        /* Alert Error dengan Shake */
        .alert-error {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            padding: 0.95rem 1.15rem;
            border-radius: 12px;
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
            border: 1px solid #fca5a5;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            animation: shake 0.5s;
        }
        [data-theme="dark"] .alert-error {
            background: linear-gradient(135deg, #7f1d1d33, #991b1b33);
            border-color: #dc2626;
            color: #fca5a5;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-8px); }
            75% { transform: translateX(8px); }
        }
        .alert-icon {
            width: 28px;
            height: 28px;
            background: #dc2626;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            flex-shrink: 0;
            font-size: 0.9rem;
        }

        /* Form Group dengan Floating Label */
        .form-group {
            margin-bottom: 1.25rem;
            position: relative;
        }
        .input-wrapper {
            position: relative;
        }
        .form-label-float {
            position: absolute;
            left: 3rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 0.95rem;
            pointer-events: none;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            background: var(--bg-primary);
            padding: 0 0.35rem;
        }
        .input-wrapper input:focus ~ .form-label-float,
        .input-wrapper input:not(:placeholder-shown) ~ .form-label-float,
        .input-wrapper input:-webkit-autofill ~ .form-label-float {
            top: 0;
            font-size: 0.72rem;
            color: var(--primary);
            font-weight: 600;
        }
        .form-input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 1.1rem;
            pointer-events: none;
            transition: color 0.25s;
        }
        .input-wrapper input:focus ~ .form-input-icon {
            color: var(--primary);
        }
        .form-input {
            width: 100%;
            padding: 1.05rem 1rem 1.05rem 3rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.25s;
            background: var(--bg-primary);
            color: var(--dark);
        }
        .form-input::placeholder { color: transparent; }
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(10,104,71,0.1);
        }
        .form-input.error {
            border-color: var(--error);
            box-shadow: 0 0 0 4px rgba(220,38,38,0.1);
        }
        .input-wrapper.has-error .form-input-icon { color: var(--error); }

        /* Password Extras */
        .password-wrapper { position: relative; }
        .password-wrapper .form-input { padding-right: 3rem; }
        .password-toggle {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--muted);
            padding: 0.5rem;
            border-radius: 8px;
            font-size: 1.1rem;
            transition: all 0.2s;
            z-index: 2;
        }
        .password-toggle:hover {
            background: var(--bg-tertiary);
            color: var(--primary);
        }

        /* Password Strength Meter */
        .pw-meter {
            margin-top: 0.5rem;
            height: 4px;
            background: var(--bg-tertiary);
            border-radius: 999px;
            overflow: hidden;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .pw-meter.visible { opacity: 1; }
        .pw-meter-bar {
            height: 100%;
            width: 0%;
            border-radius: 999px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .pw-hint {
            margin-top: 0.35rem;
            font-size: 0.72rem;
            color: var(--muted);
            display: flex;
            justify-content: space-between;
            align-items: center;
            min-height: 1em;
        }
        .pw-hint .caps-warn {
            color: var(--error);
            font-weight: 600;
            display: none;
            align-items: center;
            gap: 0.25rem;
        }
        .pw-hint .caps-warn.show { display: inline-flex; }

        /* Remember me & forgot */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
        }
        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            color: var(--text);
            user-select: none;
        }
        .remember-me input { display: none; }
        .remember-check {
            width: 18px;
            height: 18px;
            border: 2px solid var(--border);
            border-radius: 5px;
            position: relative;
            transition: all 0.2s;
            flex-shrink: 0;
        }
        .remember-me input:checked ~ .remember-check {
            background: var(--primary);
            border-color: var(--primary);
        }
        .remember-me input:checked ~ .remember-check::after {
            content: '✓';
            position: absolute;
            inset: 0;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 800;
        }
        .forgot-link {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s;
        }
        .forgot-link:hover { color: var(--primary-dark); }

        /* Submit Button */
        .btn-login {
            width: 100%;
            padding: 1.05rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            font-family: inherit;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            letter-spacing: 0.01em;
        }
        .btn-login::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        .btn-login:hover:not(:disabled)::before {
            width: 400px;
            height: 400px;
        }
        .btn-login:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(10,104,71,0.35);
        }
        .btn-login:active:not(:disabled) { transform: translateY(0); }
        .btn-login:disabled {
            cursor: not-allowed;
            opacity: 0.85;
        }
        .btn-login .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            display: none;
        }
        .btn-login.loading .spinner { display: inline-block; }
        .btn-login.loading .btn-text { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* 2FA Section */
        .twofa-section {
            display: none;
        }
        .twofa-section.active { display: block; }
        .twofa-illustration {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .twofa-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin-bottom: 1rem;
            animation: shieldPulse 2s ease-in-out infinite;
        }
        [data-theme="dark"] .twofa-icon {
            background: linear-gradient(135deg, #1e3a8a33, #1e40af33);
        }
        @keyframes shieldPulse {
            0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(59,130,246,0.4); }
            50% { transform: scale(1.05); box-shadow: 0 0 0 15px rgba(59,130,246,0); }
        }
        .twofa-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        .twofa-desc {
            font-size: 0.88rem;
            color: var(--muted);
            line-height: 1.6;
        }

        /* OTP Input */
        .otp-container {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin: 1.5rem 0;
        }
        .otp-input {
            width: 50px;
            height: 60px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
            font-family: 'JetBrains Mono', monospace;
            background: var(--bg-primary);
            color: var(--dark);
            transition: all 0.2s;
        }
        .otp-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(10,104,71,0.1);
            transform: translateY(-2px);
        }
        .otp-input.filled {
            border-color: var(--primary);
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
        }
        [data-theme="dark"] .otp-input.filled {
            background: linear-gradient(135deg, #052e1633, #14532d33);
        }

        /* Divider */
        .form-divider {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 1.75rem 0 1.25rem;
            color: var(--muted);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-weight: 600;
        }
        .form-divider::before, .form-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* Security Info */
        .security-info {
            background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            padding: 1rem 1.15rem;
            display: flex;
            gap: 0.85rem;
            align-items: flex-start;
        }
        [data-theme="dark"] .security-info {
            background: linear-gradient(135deg, #052e1633, #14532d33);
            border-color: #166534;
        }
        .sec-icon {
            font-size: 1.25rem;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .sec-text { font-size: 0.8rem; color: var(--text); line-height: 1.5; }
        .sec-text strong { color: var(--primary-dark); }
        [data-theme="dark"] .sec-text strong { color: #4ade80; }

        /* Footer */
        .form-footer {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
            text-align: center;
        }
        .form-footer p {
            font-size: 0.82rem;
            color: var(--muted);
            margin-bottom: 0.75rem;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary);
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border: 1px solid var(--border);
            border-radius: 999px;
            transition: all 0.2s;
        }
        .back-link:hover {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
            transform: translateX(-4px);
        }

        .copyright {
            margin-top: 1.25rem;
            font-size: 0.72rem;
            color: var(--muted);
        }
        .copyright code {
            font-family: 'JetBrains Mono', monospace;
            background: var(--bg-tertiary);
            padding: 0.1rem 0.4rem;
            border-radius: 4px;
            font-size: 0.68rem;
        }

        /* Honeypot */
        .honeypot {
            position: absolute;
            left: -9999px;
            opacity: 0;
            pointer-events: none;
        }

        /* Keyboard Shortcuts Help */
        .shortcuts-help {
            position: fixed;
            bottom: 1rem;
            left: 1rem;
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0.5rem 1rem;
            font-size: 0.72rem;
            color: var(--muted);
            box-shadow: var(--shadow);
            display: flex;
            gap: 0.75rem;
            align-items: center;
            z-index: 50;
        }
        .shortcuts-help kbd {
            background: var(--bg-tertiary);
            padding: 0.15rem 0.4rem;
            border-radius: 4px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.68rem;
            border: 1px solid var(--border);
            margin: 0 0.15rem;
            font-weight: 700;
        }

        /* Toast Notification */
        .toast {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            z-index: 10000;
            transform: translateX(calc(100% + 2rem));
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            max-width: 350px;
        }
        .toast.show { transform: translateX(0); }
        .toast-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .toast.success .toast-icon { background: #dcfce7; color: #166534; }
        .toast.error .toast-icon { background: #fee2e2; color: #991b1b; }
        .toast.info .toast-icon { background: #dbeafe; color: #1e40af; }
        .toast-content { flex: 1; }
        .toast-title { font-weight: 700; font-size: 0.88rem; margin-bottom: 0.15rem; }
        .toast-message { font-size: 0.78rem; color: var(--muted); }

        /* Mobile Responsive */
        @media (max-width: 968px) {
            .login-shell { grid-template-columns: 1fr; }
            .brand-side {
                padding: 2rem 1.5rem;
                min-height: auto;
            }
            .brand-hero { display: none; }
            .brand-footer { display: none; }
            .brand-logo { margin-bottom: 0; }
            .form-side { padding: 2rem 1.5rem; min-height: auto; }
            .form-header h1 { font-size: 1.75rem; }
            .shortcuts-help { display: none; }
        }
        @media (max-width: 480px) {
            .feature-list { grid-template-columns: 1fr; }
            .form-options { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
            .otp-input { width: 42px; height: 52px; font-size: 1.25rem; }
            .security-bar { flex-direction: column; text-align: center; }
        }

        /* Focus visible */
        :focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        /* Accessibility: Reduced Motion */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
    <!-- Toast Container -->
    <div class="toast" id="toast">
        <div class="toast-icon" id="toastIcon">✓</div>
        <div class="toast-content">
            <div class="toast-title" id="toastTitle">Success</div>
            <div class="toast-message" id="toastMessage">Operation completed</div>
        </div>
    </div>

    <div class="login-shell">
        <!-- === BRANDING SIDE === -->
        <aside class="brand-side" aria-hidden="true">
            <!-- Particles -->
            <div class="particles" id="particles"></div>
            
            <div class="brand-content">
                <div class="brand-topbar">
                    <div class="brand-logo">
                        <div class="logo-mark">F</div>
                        <div class="logo-text">
                            <strong>FKIP UNIMOF</strong>
                            <span>Admin Management Portal</span>
                        </div>
                    </div>
                    <button class="theme-toggle" id="themeToggle" title="Toggle Dark Mode" aria-label="Toggle dark mode">
                        <span id="themeIcon">🌙</span>
                    </button>
                </div>

                <div class="brand-hero">
                    <div class="hero-badge">
                        <span class="pulse-dot"></span>
                        Sistem Online & Aman
                    </div>
                    <h1 class="hero-title">
                        Mengelola<br>
                        Pendidikan <span class="accent-text">Masa Depan</span>
                    </h1>
                    <p class="hero-subtitle">
                        Portal admin terintegrasi untuk mengelola konten akademik, berita, dan komunikasi Fakultas Keguruan dan Ilmu Pendidikan Universitas Muhammadiyah Maumere.
                    </p>

                    <div class="feature-list">
                        <div class="feature-item">
                            <div class="feature-icon">🛡️</div>
                            <span>Enkripsi End-to-End</span>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">🔐</div>
                            <span>CSRF Protected</span>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">🔑</div>
                            <span>2FA Support</span>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">📊</div>
                            <span>Dashboard Realtime</span>
                        </div>
                    </div>

                    <div class="quote-box">
                        <p><?= htmlspecialchars($quote['text']) ?></p>
                        <span class="quote-author">— <?= htmlspecialchars($quote['author']) ?></span>
                    </div>
                </div>

                <div class="brand-footer">
                    <div class="live-clock">
                        <div class="clock-time" id="clockTime">--:--:--</div>
                        <div class="clock-date" id="clockDate">Memuat...</div>
                    </div>
                    <div class="security-badges">
                        <span class="sec-badge <?= $is_https ? 'active' : '' ?>"><?= $is_https ? '🔒' : '⚠️' ?> <?= $is_https ? 'HTTPS' : 'HTTP' ?></span>
                        <span class="sec-badge active">🛡️ CSRF</span>
                        <span class="sec-badge active">🔑 2FA</span>
                        <span class="sec-badge active">✓ PDO</span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- === FORM SIDE === -->
        <section class="form-side">
            <div class="login-container">
                <!-- Security Info Bar -->
                <div class="security-bar">
                    <span class="security-bar-icon">🔒</span>
                    <div class="security-bar-content">
                        <div class="security-bar-title">Koneksi Aman Terverifikasi</div>
                        <div class="security-bar-meta">
                            IP: <?= htmlspecialchars($client_ip) ?> • Browser: <?= htmlspecialchars($client_browser) ?> • <?= $is_https ? 'SSL/TLS Encrypted' : 'Warning: Not Encrypted' ?>
                        </div>
                    </div>
                </div>

                <?php if ($is_blocked): ?>
                    <!-- BLOCKED STATE -->
                    <div class="blocked-overlay">
                        <div class="blocked-icon">🚫</div>
                        <div class="blocked-title">Akses Diblokir Sementara</div>
                        <div class="blocked-desc">Terlalu banyak percobaan login gagal. Silakan tunggu:</div>
                        <div class="blocked-timer" id="blockTimer"><?= gmdate('i:s', $remaining_block) ?></div>
                        <p style="font-size: 0.82rem; color: #991b1b;">Hubungi administrator jika Anda memerlukan bantuan.</p>
                    </div>
                <?php elseif ($show_2fa): ?>
                    <!-- 2FA VERIFICATION SCREEN -->
                    <div class="twofa-section active">
                        <div class="twofa-illustration">
                            <div class="twofa-icon">🔐</div>
                            <h2 class="twofa-title">Verifikasi Dua Langkah</h2>
                            <p class="twofa-desc">Masukkan kode 6 digit dari aplikasi authenticator Anda untuk melanjutkan.</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert-error" role="alert">
                                <span class="alert-icon">!</span>
                                <div>
                                    <strong>Verifikasi Gagal</strong><br>
                                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" id="otpForm" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            
                            <div class="otp-container">
                                <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="0" autofocus>
                                <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="1">
                                <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="2">
                                <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="3">
                                <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="4">
                                <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="5">
                            </div>
                            <input type="hidden" name="otp_code" id="otpCode">

                            <button type="submit" class="btn-login" id="btnVerify">
                                <span class="btn-text">🔓 Verifikasi & Masuk</span>
                                <span class="spinner"></span>
                            </button>
                        </form>

                        <div style="text-align: center; margin-top: 1.5rem;">
                            <a href="?logout_2fa=1" class="forgot-link" onclick="localStorage.removeItem('2fa_pending'); location.reload();">← Kembali ke login</a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- NORMAL LOGIN FORM -->
                    <div class="form-header">
                        <div class="pre-title">Selamat Datang Kembali</div>
                        <h1>Masuk ke Portal Admin</h1>
                        <p>Gunakan kredensial Anda untuk mengakses dashboard.</p>
                    </div>

                    <?php if ($last_login): ?>
                    <div class="last-login-info" id="lastLoginInfo">
                        <span class="last-login-icon">👋</span>
                        <div class="last-login-text">
                            Login terakhir: <strong><?= htmlspecialchars($last_login) ?></strong>
                        </div>
                        <button class="last-login-dismiss" onclick="document.getElementById('lastLoginInfo').remove()" title="Tutup">✕</button>
                    </div>
                    <?php endif; ?>

                    <?php if ($rate_limit['attempts'] > 0 && !$is_blocked): ?>
                    <div class="rate-limit-warning">
                        <span class="rate-limit-icon">⚠️</span>
                        <div class="rate-limit-content">
                            <div class="rate-limit-title">Percobaan Login: <?= $rate_limit['attempts'] ?>/5</div>
                            <div class="rate-limit-desc">Sisa <?= $attempts_left ?> percobaan sebelum akun diblokir sementara</div>
                            <div class="rate-limit-bar">
                                <div class="rate-limit-fill" style="width: <?= ($rate_limit['attempts'] / 5) * 100 ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert-error" role="alert">
                            <span class="alert-icon">!</span>
                            <div>
                                <strong>Login Gagal</strong><br>
                                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" autocomplete="off" id="loginForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="text" name="website" class="honeypot" tabindex="-1" autocomplete="off">

                        <div class="form-group">
                            <div class="input-wrapper" id="usernameWrap">
                                <span class="form-input-icon">👤</span>
                                <input type="text" id="username" name="username" class="form-input"
                                    required autofocus maxlength="50" placeholder=" "
                                    value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    aria-label="Username">
                                <label class="form-label-float" for="username">Username</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="input-wrapper password-wrapper" id="passwordWrap">
                                <span class="form-input-icon">🔒</span>
                                <input type="password" id="password" name="password" class="form-input"
                                    required maxlength="100" placeholder=" "
                                    aria-label="Password" autocomplete="current-password">
                                <label class="form-label-float" for="password">Password</label>
                                <button type="button" class="password-toggle" id="pwToggle" aria-label="Tampilkan password">
                                    <span id="pwToggleIcon">👁️</span>
                                </button>
                            </div>
                            <div class="pw-meter" id="pwMeter">
                                <div class="pw-meter-bar" id="pwBar"></div>
                            </div>
                            <div class="pw-hint">
                                <span id="pwHint"></span>
                                <span class="caps-warn" id="capsWarn">⚠️ Caps Lock aktif</span>
                            </div>
                        </div>

                        <div class="form-options">
                            <label class="remember-me">
                                <input type="checkbox" name="remember" id="remember">
                                <span class="remember-check"></span>
                                <span>Ingat saya di perangkat ini</span>
                            </label>
                            <a href="#" class="forgot-link" onclick="showToast('info', 'Reset Password', 'Silakan hubungi administrator sistem untuk reset password.'); return false;">Lupa password?</a>
                        </div>

                        <button type="submit" class="btn-login" id="btnLogin">
                            <span class="btn-text">Masuk ke Dashboard →</span>
                            <span class="spinner"></span>
                        </button>
                    </form>
                <?php endif; ?>

                <div class="form-divider">
                    <span>Dilindungi oleh</span>
                </div>

                <div class="security-info">
                    <span class="sec-icon">🛡️</span>
                    <div class="sec-text">
                        Koneksi Anda diamankan dengan <strong>CSRF Token</strong>, <strong>Argon2 Password Hashing</strong>, <strong>Rate Limiting</strong>, dan <strong>2FA Support</strong>. Sesi akan otomatis berakhir setelah 1 jam tidak aktif.
                    </div>
                </div>

                <div class="form-footer">
                    <p>Butuh akses admin? Hubungi Super Administrator.</p>
                    <a href="<?= APP_URL ?>" class="back-link">
                        ← Kembali ke Website Publik
                    </a>
                    <div class="copyright">
                        &copy; <?= date('Y') ?> FKIP UNIMOF • <code>v<?= APP_VERSION ?></code>
                        <?php if (defined('APP_BUILD')): ?> • Build <code><?= APP_BUILD ?></code><?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Keyboard Shortcuts Help -->
    <div class="shortcuts-help">
        <span>💡</span>
        <span><kbd>Esc</kbd> Clear</span>
        <span><kbd>Tab</kbd> Navigate</span>
        <span><kbd>Enter</kbd> Submit</span>
        <span><kbd>D</kbd> Dark Mode</span>
    </div>

    <script>
        // ===== THEME TOGGLE =====
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const html = document.documentElement;
        
        // Load saved theme
        const savedTheme = localStorage.getItem('admin_theme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        themeIcon.textContent = savedTheme === 'dark' ? '☀️' : '🌙';
        
        themeToggle.addEventListener('click', () => {
            const current = html.getAttribute('data-theme');
            const newTheme = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);
            themeIcon.textContent = newTheme === 'dark' ? '☀️' : '🌙';
            localStorage.setItem('admin_theme', newTheme);
            showToast('info', 'Theme Changed', `Switched to ${newTheme} mode`);
        });

        // ===== TOAST NOTIFICATION =====
        function showToast(type, title, message) {
            const toast = document.getElementById('toast');
            const toastIcon = document.getElementById('toastIcon');
            const toastTitle = document.getElementById('toastTitle');
            const toastMessage = document.getElementById('toastMessage');
            
            toast.className = `toast ${type}`;
            toastTitle.textContent = title;
            toastMessage.textContent = message;
            
            const icons = { success: '✓', error: '✕', info: 'ℹ', warning: '⚠' };
            toastIcon.textContent = icons[type] || 'ℹ';
            
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 4000);
        }

        // ===== LIVE CLOCK =====
        function updateClock() {
            const now = new Date();
            const h = String(now.getHours()).padStart(2, '0');
            const m = String(now.getMinutes()).padStart(2, '0');
            const s = String(now.getSeconds()).padStart(2, '0');
            const timeEl = document.getElementById('clockTime');
            if (timeEl) timeEl.textContent = `${h}:${m}:${s}`;
            
            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            const dateEl = document.getElementById('clockDate');
            if (dateEl) dateEl.textContent = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}`;
        }
        updateClock();
        setInterval(updateClock, 1000);

        // ===== BLOCK TIMER =====
        <?php if ($is_blocked): ?>
        let remainingSeconds = <?= $remaining_block ?>;
        const blockTimer = document.getElementById('blockTimer');
        
        function updateBlockTimer() {
            if (remainingSeconds <= 0) {
                location.reload();
                return;
            }
            remainingSeconds--;
            const mins = Math.floor(remainingSeconds / 60);
            const secs = remainingSeconds % 60;
            blockTimer.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }
        setInterval(updateBlockTimer, 1000);
        <?php endif; ?>

        // ===== PARTICLES dengan Mouse Interaction =====
        (function() {
            const container = document.getElementById('particles');
            if (!container) return;
            
            const particles = [];
            for (let i = 0; i < 25; i++) {
                const p = document.createElement('div');
                p.className = 'particle';
                p.style.left = Math.random() * 100 + '%';
                p.style.animationDelay = Math.random() * 20 + 's';
                p.style.animationDuration = (15 + Math.random() * 15) + 's';
                p.style.width = p.style.height = (2 + Math.random() * 4) + 'px';
                p.style.opacity = 0.3 + Math.random() * 0.5;
                container.appendChild(p);
                particles.push({ el: p, x: Math.random() * 100, y: Math.random() * 100 });
            }
            
            // Mouse interaction
            document.addEventListener('mousemove', (e) => {
                const rect = container.getBoundingClientRect();
                const mouseX = ((e.clientX - rect.left) / rect.width) * 100;
                const mouseY = ((e.clientY - rect.top) / rect.height) * 100;
                
                particles.forEach(p => {
                    const dx = p.x - mouseX;
                    const dy = p.y - mouseY;
                    const distance = Math.sqrt(dx * dx + dy * dy);
                    
                    if (distance < 15) {
                        p.el.classList.add('mouse-near');
                    } else {
                        p.el.classList.remove('mouse-near');
                    }
                });
            });
        })();

        <?php if (!$show_2fa && !$is_blocked): ?>
        // ===== PASSWORD TOGGLE =====
        const pwInput = document.getElementById('password');
        const pwToggle = document.getElementById('pwToggle');
        const pwToggleIcon = document.getElementById('pwToggleIcon');
        
        if (pwToggle) {
            pwToggle.addEventListener('click', () => {
                if (pwInput.type === 'password') {
                    pwInput.type = 'text';
                    pwToggleIcon.textContent = '🙈';
                    pwToggle.setAttribute('aria-label', 'Sembunyikan password');
                } else {
                    pwInput.type = 'password';
                    pwToggleIcon.textContent = '👁️';
                    pwToggle.setAttribute('aria-label', 'Tampilkan password');
                }
            });
        }

        // ===== PASSWORD STRENGTH METER =====
        const pwMeter = document.getElementById('pwMeter');
        const pwBar = document.getElementById('pwBar');
        const pwHint = document.getElementById('pwHint');

        function checkStrength(pw) {
            let score = 0;
            if (pw.length >= 8) score++;
            if (pw.length >= 12) score++;
            if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) score++;
            if (/\d/.test(pw)) score++;
            if (/[^A-Za-z0-9]/.test(pw)) score++;
            return score;
        }

        if (pwInput) {
            pwInput.addEventListener('input', function() {
                const val = this.value;
                if (!val) {
                    pwMeter.classList.remove('visible');
                    pwHint.textContent = '';
                    return;
                }
                pwMeter.classList.add('visible');
                const score = checkStrength(val);
                const configs = [
                    { w: '20%', c: '#ef4444', t: 'Sangat Lemah' },
                    { w: '40%', c: '#f97316', t: 'Lemah' },
                    { w: '60%', c: '#eab308', t: 'Sedang' },
                    { w: '80%', c: '#84cc16', t: 'Kuat' },
                    { w: '100%', c: '#10b981', t: 'Sangat Kuat' }
                ];
                const cfg = configs[Math.min(score, 4)];
                pwBar.style.width = cfg.w;
                pwBar.style.background = cfg.c;
                pwHint.textContent = cfg.t;
                pwHint.style.color = cfg.c;
            });
        }

        // ===== CAPS LOCK DETECTION =====
        const capsWarn = document.getElementById('capsWarn');
        function detectCaps(e) {
            if (typeof e.getModifierState === 'function') {
                const caps = e.getModifierState('CapsLock');
                capsWarn.classList.toggle('show', caps && pwInput === document.activeElement);
            }
        }
        if (pwInput) {
            pwInput.addEventListener('keydown', detectCaps);
            pwInput.addEventListener('keyup', detectCaps);
            pwInput.addEventListener('blur', () => capsWarn.classList.remove('show'));
        }

        // ===== FORM SUBMISSION dengan Loading State =====
        const form = document.getElementById('loginForm');
        const btn = document.getElementById('btnLogin');
        
        if (form) {
            form.addEventListener('submit', function(e) {
                const username = document.getElementById('username').value.trim();
                const password = pwInput.value;
                
                if (!username || !password) {
                    e.preventDefault();
                    if (!username) document.getElementById('usernameWrap').classList.add('has-error');
                    if (!password) document.getElementById('passwordWrap').classList.add('has-error');
                    showToast('error', 'Form Incomplete', 'Please fill in all required fields');
                    return;
                }
                
                // Save username for next visit
                document.cookie = `last_login_username=${username};max-age=${30*24*60*60};path=/;SameSite=Lax`;
                
                btn.classList.add('loading');
                btn.disabled = true;
                
                // Safety timeout
                setTimeout(() => {
                    btn.classList.remove('loading');
                    btn.disabled = false;
                }, 5000);
            });
        }

        // Clear error state on input
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('input', function() {
                this.closest('.input-wrapper').classList.remove('has-error');
                this.classList.remove('error');
            });
        });

        // ===== AUTO-FOCUS jika ada error =====
        <?php if ($error): ?>
        const usernameInput = document.getElementById('username');
        if (usernameInput) {
            usernameInput.focus();
            usernameInput.select();
        }
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($show_2fa): ?>
        // ===== OTP INPUT HANDLING =====
        const otpInputs = document.querySelectorAll('.otp-input');
        const otpCode = document.getElementById('otpCode');
        const otpForm = document.getElementById('otpForm');
        
        otpInputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                const value = e.target.value;
                
                // Only allow numbers
                if (!/^\d*$/.test(value)) {
                    e.target.value = '';
                    return;
                }
                
                if (value && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
                
                // Mark as filled
                if (value) {
                    e.target.classList.add('filled');
                } else {
                    e.target.classList.remove('filled');
                }
                
                // Update hidden input
                const code = Array.from(otpInputs).map(i => i.value).join('');
                otpCode.value = code;
            });
            
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    otpInputs[index - 1].focus();
                    otpInputs[index - 1].value = '';
                    otpInputs[index - 1].classList.remove('filled');
                }
            });
            
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const paste = (e.clipboardData || window.clipboardData).getData('text');
                const digits = paste.replace(/\D/g, '').slice(0, 6);
                
                digits.split('').forEach((digit, i) => {
                    if (otpInputs[i]) {
                        otpInputs[i].value = digit;
                        otpInputs[i].classList.add('filled');
                    }
                });
                
                otpCode.value = digits;
                
                if (digits.length === 6) {
                    otpInputs[5].focus();
                } else if (digits.length > 0) {
                    otpInputs[Math.min(digits.length, 5)].focus();
                }
            });
        });
        
        // OTP Form submission
        if (otpForm) {
            otpForm.addEventListener('submit', function(e) {
                const code = otpCode.value;
                if (code.length !== 6) {
                    e.preventDefault();
                    showToast('error', 'Invalid Code', 'Please enter all 6 digits');
                    return;
                }
                
                const btn = document.getElementById('btnVerify');
                btn.classList.add('loading');
                btn.disabled = true;
            });
        }
        <?php endif; ?>

        // ===== KEYBOARD SHORTCUTS =====
        document.addEventListener('keydown', (e) => {
            // Esc = clear form
            if (e.key === 'Escape') {
                const username = document.getElementById('username');
                if (username) username.value = '';
                if (pwInput) pwInput.value = '';
                if (pwMeter) pwMeter.classList.remove('visible');
                if (username) username.focus();
            }
            
            // D = toggle dark mode
            if (e.key === 'd' || e.key === 'D') {
                if (document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                    themeToggle.click();
                }
            }
        });

        // ===== Console signature =====
        console.log('%c🛡️ FKIP UNIMOF Admin Portal - EXTREME MULTIMATE', 'color:#0a6847;font-size:20px;font-weight:bold');
        console.log('%cDilindungi oleh CSRF Token • Argon2 Hash • Rate Limiting • 2FA Support', 'color:#64748b');
        console.log('%cVersion: <?= APP_VERSION ?><?= defined("APP_BUILD") ? " (Build " . APP_BUILD . ")" : "" ?>', 'color:#64748b');
        console.log('%cShortcuts: Esc (Clear) • D (Dark Mode) • Tab (Navigate) • Enter (Submit)', 'color:#94a3b8');
    </script>
</body>
</html>