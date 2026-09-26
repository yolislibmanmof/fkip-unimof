<?php
// audit.php - Self-diagnose jebakan umum untuk FILE PUBLIK (jalankan di localhost)
header('Content-Type: text/html; charset=utf-8');
$root = __DIR__;

// ===== KONFIGURASI: Scan file publik (bukan admin) =====
$dirs = ['includes'];  // Hanya scan folder includes
$root_files = true;     // Scan juga file PHP di root (index.php, about.php, dll)

$issues = [];
$checked = 0;

$patterns = [
    // Untuk file publik, TIDAK ada pattern PATH_ADMIN karena public pakai '/includes/' (benar)
    'ADD_SLASHES'  => ['/\baddslashes\s*\(/', 'addslashes() dipakai di dalam <script> (pakai htmlspecialchars)'],
    'ORDER_FIELD'  => ['/ORDER\s+FIELD\s*\(/i', 'ORDER FIELD tanpa BY (harus ORDER BY FIELD)'],
    'EXEC_FETCHALL'=> ['/->\s*execute\s*\([^)]*\)\s*->\s*fetchAll\s*\(/', 'execute()->fetchAll() di-chain (harus pisahkan fetch)'],
    'DUP_CLOSE'    => ['~</main>\s*</div>\s*</body>~', 'header.php menutup </main></body> (seharusnya footer yang menutup)'],
];

// Scan file di root directory (index.php, about.php, kontak.php publik, dll)
if ($root_files) {
    $it = new DirectoryIterator($root);
    foreach ($it as $file) {
        if ($file->isDot() || $file->isDir()) continue;
        if ($file->getExtension() !== 'php') continue;
        
        // Skip file sistem
        if (in_array($file->getFilename(), ['audit.php', 'config.php'])) continue;
        
        $checked++;
        $src = @file_get_contents($file->getPathname());
        if ($src === false) continue;
        
        $rel = $file->getFilename();
        
        foreach ($patterns as $key => [$regex, $msg]) {
            if ($key === 'DUP_CLOSE' && strpos($rel, 'header.php') === false) continue;
            
            if (preg_match($regex, $src)) {
                preg_match_all($regex, $src, $m);
                $lines = [];
                foreach (explode("\n", $src) as $i => $ln) {
                    if (preg_match($regex, $ln)) $lines[] = $i + 1;
                }
                $issues[] = ['file' => $rel, 'type' => $key, 'msg' => $msg, 'lines' => $lines];
            }
        }
    }
}

// Scan folder includes
foreach ($dirs as $d) {
    $base = $root . DIRECTORY_SEPARATOR . $d;
    if (!is_dir($base)) continue;
    
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->getExtension() !== 'php') continue;
        $checked++;
        $src = @file_get_contents($file->getPathname());
        if ($src === false) continue;
        
        $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
        
        foreach ($patterns as $key => [$regex, $msg]) {
            if ($key === 'DUP_CLOSE' && strpos($rel, 'includes/header.php') === false) continue;
            
            if (preg_match($regex, $src)) {
                preg_match_all($regex, $src, $m);
                $lines = [];
                foreach (explode("\n", $src) as $i => $ln) {
                    if (preg_match($regex, $ln)) $lines[] = $i + 1;
                }
                $issues[] = ['file' => $rel, 'msg' => $msg, 'lines' => $lines];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit File Publik — FKIP UNIMOF</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
            padding: 2rem;
            line-height: 1.6;
        }
        .container { max-width: 900px; margin: 0 auto; }
        h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #10b981, #34d399);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .subtitle { color: #94a3b8; margin-bottom: 2rem; font-size: 0.95rem; }
        .stats {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .stat-box {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            backdrop-filter: blur(8px);
        }
        .stat-label { font-size: 0.8rem; color: #94a3b8; margin-bottom: 0.25rem; }
        .stat-value { font-size: 1.5rem; font-weight: 800; }
        .stat-value.ok { color: #10b981; }
        .stat-value.bad { color: #f87171; }
        
        .card {
            background: rgba(30, 41, 59, 0.9);
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 1.25rem;
            margin: 0.75rem 0;
            border-left: 4px solid #f87171;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .card.success {
            border-left-color: #10b981;
            background: rgba(16, 185, 129, 0.1);
        }
        .card-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        .card-header.bad { color: #f87171; }
        .card-header.ok { color: #10b981; }
        code {
            background: #0f172a;
            padding: 0.2rem 0.6rem;
            border-radius: 6px;
            color: #fbbf24;
            font-family: 'Fira Code', monospace;
            font-size: 0.85rem;
            display: inline-block;
            margin: 0.25rem 0;
        }
        .line-info {
            color: #94a3b8;
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }
        .note {
            margin-top: 2rem;
            padding: 1rem;
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 8px;
            color: #94a3b8;
            font-size: 0.85rem;
        }
        .file-list {
            margin-top: 2rem;
            padding: 1rem;
            background: rgba(30, 41, 59, 0.6);
            border-radius: 8px;
            font-size: 0.85rem;
        }
        .file-list h3 {
            margin-bottom: 0.75rem;
            color: #cbd5e1;
        }
        .file-item {
            padding: 0.35rem 0;
            color: #94a3b8;
            font-family: monospace;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🩺 Audit File Publik — FKIP UNIMOF</h1>
        <p class="subtitle">Memeriksa file PHP publik (root & includes) untuk jebakan umum</p>
        
        <div class="stats">
            <div class="stat-box">
                <div class="stat-label">File Diperiksa</div>
                <div class="stat-value"><?= $checked ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Masalah Ditemukan</div>
                <div class="stat-value <?= $issues ? 'bad' : 'ok' ?>"><?= count($issues) ?></div>
            </div>
        </div>

        <?php if (!$issues): ?>
        <div class="card success">
            <div class="card-header ok">
                <span>✅</span>
                <span>LULUS SEMUA</span>
            </div>
            <p>Tidak ada jebakan yang terdeteksi di file publik. Struktur include & SQL tampak bersih.</p>
        </div>
        <?php else: ?>
            <?php 
            $grouped = [];
            foreach ($issues as $issue) {
                $type = $issue['msg'];
                if (!isset($grouped[$type])) $grouped[$type] = [];
                $grouped[$type][] = $issue;
            }
            foreach ($grouped as $type => $items): 
            ?>
            <div class="card">
                <div class="card-header bad">
                    <span>⚠️</span>
                    <span><?= htmlspecialchars($type) ?></span>
                    <span style="margin-left: auto; background: #f87171; color: #fff; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.75rem;"><?= count($items) ?></span>
                </div>
                <?php foreach ($items as $item): ?>
                <div style="margin: 0.75rem 0; padding: 0.75rem; background: rgba(15, 23, 42, 0.6); border-radius: 8px;">
                    <code><?= htmlspecialchars($item['file']) ?></code>
                    <div class="line-info">📍 Baris: <?= implode(', ', $item['lines']) ?: '?' ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="file-list">
            <h3>📂 File yang Diperiksa:</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 0.5rem;">
                <?php
                // Tampilkan list file yang discan
                $scanned_files = [];
                
                // Root files
                if ($root_files) {
                    $it = new DirectoryIterator($root);
                    foreach ($it as $file) {
                        if ($file->isDot() || $file->isDir()) continue;
                        if ($file->getExtension() !== 'php') continue;
                        if (in_array($file->getFilename(), ['audit.php', 'config.php'])) continue;
                        $scanned_files[] = $file->getFilename();
                    }
                }
                
                // Includes folder
                $inc_dir = $root . DIRECTORY_SEPARATOR . 'includes';
                if (is_dir($inc_dir)) {
                    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($inc_dir, FilesystemIterator::SKIP_DOTS));
                    foreach ($it as $file) {
                        if ($file->getExtension() !== 'php') continue;
                        $scanned_files[] = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    }
                }
                
                sort($scanned_files);
                foreach ($scanned_files as $f) {
                    echo '<div class="file-item">📄 ' . htmlspecialchars($f) . '</div>';
                }
                ?>
            </div>
        </div>

        <div class="note">
            <strong> Catatan:</strong> Scan ini bersifat statis (regex). Untuk kepastian, tetap jalankan halaman di browser dan periksa console untuk error JavaScript.
        </div>
    </div>
</body>
</html>