<?php
// audit.php - Self-diagnose jebakan umum (jalankan di localhost, HANYA baca file lokal)
header('Content-Type: text/html; charset=utf-8');
$root = __DIR__;
$dirs = ['admin', 'includes'];
$issues = [];
$checked = 0;

$patterns = [
    'PATH_ADMIN'   => ['/^(?!.*\/includes\/).*__DIR__\s*\.\s*[\'"]\/includes\//m', 'admin/ pakai "/includes/" (harusnya "../includes/")'],
    'ADD_SLASHES'  => ['/\baddslashes\s*\(/', 'addslashes() dipakai di dalam <script> (pakai htmlspecialchars)'],
    'ORDER_FIELD'  => ['/ORDER\s+FIELD\s*\(/i', 'ORDER FIELD tanpa BY (harus ORDER BY FIELD)'],
    'EXEC_FETCHALL'=> ['/->\s*execute\s*\([^)]*\)\s*->\s*fetchAll\s*\(/', 'execute()->fetchAll() di-chain (harus pisahkan fetch)'],
    'DUP_CLOSE'    => ['~</main>\s*</div>\s*</body>~', 'header.php menutup </main></body> (seharusnya footer yang menutup)'],
];

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
        // Khusus PATH_ADMIN: hanya flag file di dalam admin/
        foreach ($patterns as $key => [$regex, $msg]) {
            if ($key === 'PATH_ADMIN' && strpos($rel, 'admin/') !== 0) continue;
            if ($key === 'DUP_CLOSE' && strpos($rel, 'includes/header.php') === false) continue;
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
?>
<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>Audit FKIP UNIMOF</title>
<style>body{font-family:system-ui,sans-serif;background:#0f172a;color:#e2e8f0;padding:2rem}
h1{color:#10b981}.ok{color:#10b981;font-weight:700}.bad{color:#f87171;font-weight:700}
.card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:1rem;margin:.5rem 0}
code{background:#0f172a;padding:.1rem .4rem;border-radius:4px;color:#fbbf24}
.line{color:#94a3b8;font-size:.85rem}</style></head><body>
<h1>🩺 Audit Jebakan Umum — FKIP UNIMOF</h1>
<p>File PHP diperiksa: <strong><?= $checked ?></strong> &nbsp;|&nbsp; Masalah ditemukan:
<strong class="<?= $issues ? 'bad' : 'ok' ?>"><?= count($issues) ?></strong></p>
<?php if (!$issues): ?>
<div class="card"><span class="ok">✅ LULUS SEMUA.</span> Tidak ada 5 jebakan yang terdeteksi. Struktur include & SQL tampak bersih.</div>
<?php else: foreach ($issues as $i): ?>
<div class="card">
  <strong class="bad">⚠ <?= htmlspecialchars($i['msg']) ?></strong><br>
  <code><?= htmlspecialchars($i['file']) ?></code>
  <span class="line">baris: <?= implode(', ', $i['lines']) ?: '?' ?></span>
</div>
<?php endforeach; endif; ?>
<p class="line">Catatan: scan ini statis (regex). Untuk kepastian, tetap jalankan halaman & lihat console browser.</p>
</body></html>