<?php
// audit.php - Advanced Security & Anti-Malware Scanner (jalankan di localhost)
header('Content-Type: text/html; charset=utf-8');
$root = __DIR__;

// ===== KONFIGURASI: Scan seluruh proyek =====
$dirs_to_scan = ['.', 'admin', 'includes', 'assets', 'uploads']; 
$exclude_files = ['audit.php', 'config.php']; // Jangan scan diri sendiri

$issues = [];
$checked = 0;
$critical_count = 0;
$warning_count = 0;

// ===== POLA KERENTANAN & MALWARE (Regex) =====
$patterns = [
    'CRITICAL_RCE' => [
        'regex' => '/\b(eval|assert|system|exec|shell_exec|passthru|popen|proc_open)\s*\(\s*[^)]*\$_(POST|GET|REQUEST|COOKIE|SERVER)/i',
        'msg' => '🚨 Remote Code Execution (RCE): Fungsi berbahaya menerima input user secara langsung.',
        'severity' => 'critical'
    ],
    'CRITICAL_OBFUSCATION' => [
        'regex' => '/\b(eval|assert)\s*\(\s*(base64_decode|gzinflate|gzuncompress|str_rot13)\s*\(/i',
        'msg' => '🚨 Kode Terobfuskasi: Rantai decoding (base64/gzinflate) dieksekusi. Ciri khas web shell.',
        'severity' => 'critical'
    ],
    'WARNING_GAMBLING_SPAM' => [
        'regex' => '/(rtp\s+live|slot\s+gacor|link\s+alternatif\s+slot|situs\s+judi\s+online|deposit\s+pulsa\s+tanpa\s+potongan|maxwin\s+gampang)/i',
        'msg' => '⚠️ Potensi Spam Judi Online: Terdeteksi kata kunci yang sering disuntikkan hacker untuk SEO spam.',
        'severity' => 'warning'
    ],
    'WARNING_HIDDEN_ELEMENTS' => [
        'regex' => '/<(iframe|script|div)[^>]*(display\s*:\s*none|visibility\s*:\s*hidden|width\s*=\s*["\']?0|height\s*=\s*["\']?0)/i',
        'msg' => '⚠️ Elemen Tersembunyi: Iframe atau script tersembunyi sering digunakan untuk suntikan malware/iklan judi.',
        'severity' => 'warning'
    ],
    'WARNING_LONG_BASE64' => [
        'regex' => '/[A-Za-z0-9+\/]{300,}={0,2}/',
        'msg' => '⚠️ String Base64 Sangat Panjang: Bisa jadi payload malware atau web shell yang dienkripsi.',
        'severity' => 'warning'
    ],
    'WARNING_SUSPICIOUS_VAR' => [
        'regex' => '/\$[a-zA-Z0-9_]{50,}\s*=/',
        'msg' => '⚠️ Variabel dengan Nama Acak Sangat Panjang: Ciri khas kode PHP yang diobfuscate.',
        'severity' => 'warning'
    ],
    'WARNING_BACKDOOR_AUTH' => [
        'regex' => '/\$_(GET|POST|REQUEST)\s*\[\s*[\'"](pass|pwd|cmd|command|c|exec)[\'"]\s*\]/i',
        'msg' => '⚠️ Potensi Backdoor: Deteksi parameter input yang umum digunakan untuk akses shell tersembunyi.',
        'severity' => 'warning'
    ],
    'WARNING_HTACCESS_ABUSE' => [
        'regex' => '/AddType\s+application\/x-httpd-php/i',
        'msg' => '⚠️ Penyalahgunaan .htaccess: Memaksa server mengeksekusi file non-PHP (seperti .jpg) sebagai script PHP.',
        'severity' => 'warning'
    ]
];

// ===== FUNGSI SCANNING =====
function scanDirectory($root, $dirs, $exclude, &$patterns, &$issues, &$checked, &$critical_count, &$warning_count) {
    foreach ($dirs as $dir) {
        $base = $root . ($dir === '.' ? '' : DIRECTORY_SEPARATOR . $dir);
        if (!is_dir($base)) continue;
        
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO)
        );
        
        foreach ($it as $file) {
            $ext = $file->getExtension();
            $filename = $file->getFilename();
            
            // Hanya scan file PHP dan .htaccess
            if ($ext !== 'php' && $filename !== '.htaccess') continue;
            if (in_array($filename, $exclude)) continue;
            
            $checked++;
            $src = @file_get_contents($file->getPathname());
            if ($src === false) continue;
            
            // Skip jika file terlalu besar (misal > 2MB) untuk menghemat memori
            if (strlen($src) > 2000000) continue;
            
            $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
            
            // 1. CEK KHUSUS: File PHP di folder upload/assets (Sangat Mencurigakan!)
            if ($ext === 'php' && preg_match('/\/(uploads|assets|images|files)\//i', $rel)) {
                $issues[] = [
                    'file' => $rel,
                    'type' => 'CRITICAL_SUSPICIOUS_LOCATION',
                    'msg' => '🚨 File PHP di Folder Upload: Folder media seharusnya hanya berisi gambar/dokumen, bukan file eksekusi PHP (Web Shell).',
                    'severity' => 'critical',
                    'lines' => ['N/A (Cek keberadaan file)']
                ];
                $critical_count++;
            }
            
            // 2. CEK POLA REGEX
            foreach ($patterns as $key => $pattern) {
                // Skip pola htaccess jika file bukan .htaccess
                if ($key === 'WARNING_HTACCESS_ABUSE' && $filename !== '.htaccess') continue;
                
                if (preg_match_all($pattern['regex'], $src, $matches, PREG_OFFSET_CAPTURE)) {
                    $lines = [];
                    $lines_src = explode("\n", $src);
                    foreach ($matches[0] as $match) {
                        $offset = $match[1];
                        $line_num = substr_count(substr($src, 0, $offset), "\n") + 1;
                        $lines[] = $line_num;
                    }
                    $lines = array_unique($lines);
                    sort($lines);
                    
                    $issues[] = [
                        'file' => $rel,
                        'type' => $key,
                        'msg' => $pattern['msg'],
                        'severity' => $pattern['severity'],
                        'lines' => $lines
                    ];
                    
                    if ($pattern['severity'] === 'critical') $critical_count++;
                    else $warning_count++;
                }
            }
        }
    }
}

// Jalankan scan
scanDirectory($root, $dirs_to_scan, $exclude_files, $patterns, $issues, $checked, $critical_count, $warning_count);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Security Audit — FKIP UNIMOF</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
            padding: 2rem;
            line-height: 1.6;
        }
        .container { max-width: 1000px; margin: 0 auto; }
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
        .stats { display: flex; gap: 1.5rem; margin-bottom: 2rem; flex-wrap: wrap; }
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
        .stat-value.warning { color: #f59e0b; }
        
        .card {
            background: rgba(30, 41, 59, 0.9);
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 1.25rem;
            margin: 0.75rem 0;
            border-left: 4px solid;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn { from { opacity: 0; transform: translateX(-10px); } to { opacity: 1; transform: translateX(0); } }
        .card.critical { border-left-color: #ef4444; }
        .card.warning { border-left-color: #f59e0b; }
        .card.success { border-left-color: #10b981; background: rgba(16, 185, 129, 0.1); }
        
        .card-header { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; font-weight: 700; }
        .card-header.critical { color: #ef4444; }
        .card-header.warning { color: #f59e0b; }
        .card-header.ok { color: #10b981; }
        
        .badge { padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; margin-left: auto; }
        .badge.critical { background: #ef4444; color: white; }
        .badge.warning { background: #f59e0b; color: #0f172a; }
        
        code {
            background: #0f172a; padding: 0.2rem 0.6rem; border-radius: 6px; color: #fbbf24;
            font-family: 'Fira Code', monospace; font-size: 0.85rem; display: inline-block; margin: 0.25rem 0; word-break: break-all;
        }
        .line-info { color: #94a3b8; font-size: 0.85rem; margin-top: 0.5rem; }
        
        .note {
            margin-top: 2rem; padding: 1rem; background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 8px; color: #94a3b8; font-size: 0.85rem;
        }
        .file-list { margin-top: 2rem; padding: 1rem; background: rgba(30, 41, 59, 0.6); border-radius: 8px; font-size: 0.85rem; }
        .file-list h3 { margin-bottom: 0.75rem; color: #cbd5e1; }
        .file-item { padding: 0.35rem 0; color: #94a3b8; font-family: monospace; font-size: 0.8rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🛡️ Advanced Security Audit — FKIP UNIMOF</h1>
        <p class="subtitle">Memindai kerentanan, web shell, dan potensi suntikan spam judi online di seluruh file proyek</p>
        
        <div class="stats">
            <div class="stat-box">
                <div class="stat-label">File Diperiksa</div>
                <div class="stat-value"><?= $checked ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Critical Issues</div>
                <div class="stat-value <?= $critical_count > 0 ? 'bad' : 'ok' ?>"><?= $critical_count ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Warnings</div>
                <div class="stat-value <?= $warning_count > 0 ? 'warning' : 'ok' ?>"><?= $warning_count ?></div>
            </div>
        </div>

        <?php if (empty($issues)): ?>
        <div class="card success">
            <div class="card-header ok">
                <span>✅</span>
                <span>TIDAK ADA ANCAMAN TERDETEKSI</span>
            </div>
            <p>Seluruh file bersih dari pola malware, web shell, dan kerentanan keamanan yang dikenal.</p>
        </div>
        <?php else: ?>
            <?php 
            $grouped = [];
            foreach ($issues as $issue) {
                $key = $issue['severity'] . '_' . $issue['type'];
                if (!isset($grouped[$key])) {
                    $grouped[$key] = ['severity' => $issue['severity'], 'type' => $issue['type'], 'msg' => $issue['msg'], 'items' => []];
                }
                $grouped[$key]['items'][] = $issue;
            }
            
            uksort($grouped, function($a, $b) {
                if (strpos($a, 'critical') !== false) return -1;
                if (strpos($b, 'critical') !== false) return 1;
                return 0;
            });
            
            foreach ($grouped as $group): 
            ?>
            <div class="card <?= $group['severity'] ?>">
                <div class="card-header <?= $group['severity'] ?>">
                    <span><?= $group['severity'] === 'critical' ? '🚨' : '⚠️' ?></span>
                    <span><?= htmlspecialchars($group['type']) ?></span>
                    <span class="badge <?= $group['severity'] ?>"><?= count($group['items']) ?></span>
                </div>
                <p style="color: #cbd5e1; margin-bottom: 1rem; font-size: 0.9rem;"><?= htmlspecialchars($group['msg']) ?></p>
                <?php foreach ($group['items'] as $item): ?>
                <div style="margin: 0.5rem 0; padding: 0.75rem; background: rgba(15, 23, 42, 0.6); border-radius: 8px;">
                    <code><?= htmlspecialchars($item['file']) ?></code>
                    <div class="line-info">📍 Baris: <?= implode(', ', $item['lines']) ?: '?' ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="file-list">
            <h3>📂 File yang Diperiksa (<?= $checked ?> file):</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 0.5rem; max-height: 300px; overflow-y: auto;">
                <?php
                $scanned_files = [];
                foreach ($dirs_to_scan as $dir) {
                    $base = $root . ($dir === '.' ? '' : DIRECTORY_SEPARATOR . $dir);
                    if (!is_dir($base)) continue;
                    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
                    foreach ($it as $file) {
                        if ($file->getExtension() !== 'php' && $file->getFilename() !== '.htaccess') continue;
                        if (in_array($file->getFilename(), $exclude_files)) continue;
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
            <strong>🛡️ Catatan Keamanan:</strong> 
            <ul style="margin-left: 1.2rem; margin-top: 0.5rem;">
                <li>Hasil <span style="color:#f59e0b">WARNING</span> bisa jadi <em>false positive</em> (kode sah yang terlihat mencurigakan).</li>
                <li>Hasil <span style="color:#ef4444">CRITICAL</span> (terutama "File PHP di Folder Upload") harus segera diperiksa dan dihapus jika bukan file sah Anda.</li>
                <li>Pastikan folder <code>uploads/</code> dan <code>assets/</code> memiliki proteksi <code>.htaccess</code> agar tidak bisa mengeksekusi script PHP.</li>
            </ul>
        </div>
    </div>
</body>
</html>