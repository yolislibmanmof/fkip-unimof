<?php
require_once __DIR__ . '/includes/config.php';

echo "<html><head><style>body{font-family:Arial;padding:20px;background:#f5f5f5;} h2{color:#0a6847;} .box{background:white;padding:15px;margin:10px 0;border-radius:8px;border-left:4px solid #0a6847;} .error{border-left-color:#ef4444;} .success{border-left-color:#10b981;} code{background:#f3f4f6;padding:2px 6px;border-radius:3px;}</style></head><body>";

echo "<h1>🔍 Diagnostic Report - FKIP UNIMOF</h1>";

// Test 1: Cek fungsi sanitize
echo "<h2>1. Fungsi sanitize()</h2>";
if (function_exists('sanitize')) {
    echo "<div class='box success'>✅ Fungsi sanitize() <strong>ADA</strong> dan siap digunakan.</div>";
} else {
    echo "<div class='box error'>❌ Fungsi sanitize() TIDAK ADA!</div>";
}

// Test 2: Cek koneksi database
echo "<h2>2. Koneksi Database</h2>";
try {
    $test = $pdo->query("SELECT 1");
    echo "<div class='box success'>✅ Koneksi database <strong>BERHASIL</strong>.</div>";
} catch (Exception $e) {
    echo "<div class='box error'>❌ Koneksi gagal: " . $e->getMessage() . "</div>";
}

// Test 3: Query program studi
echo "<h2>3. Query Program Studi</h2>";
try {
    // Query yang sama dengan program.php
    $stmt = $pdo->query("SELECT * FROM program_studi WHERE status = 'Aktif' ORDER BY urutan ASC, id ASC");
    $prodi = $stmt->fetchAll();
    
    echo "<div class='box success'>✅ Query berhasil. Ditemukan <strong>" . count($prodi) . "</strong> program studi aktif.</div>";
    
    if (count($prodi) > 0) {
        echo "<div class='box'><strong>Data yang ditemukan:</strong><br>";
        echo "<table border='1' cellpadding='8' style='border-collapse:collapse;margin-top:10px;'>";
        echo "<tr style='background:#f3f4f6;'><th>ID</th><th>Kode</th><th>Nama</th><th>Jenjang</th><th>Status</th><th>Urutan</th></tr>";
        foreach ($prodi as $p) {
            echo "<tr>";
            echo "<td>" . sanitize($p['id']) . "</td>";
            echo "<td>" . sanitize($p['kode']) . "</td>";
            echo "<td>" . sanitize($p['nama']) . "</td>";
            echo "<td>" . sanitize($p['jenjang']) . "</td>";
            echo "<td>" . sanitize($p['status']) . "</td>";
            echo "<td>" . sanitize($p['urutan']) . "</td>";
            echo "</tr>";
        }
        echo "</table></div>";
    } else {
        echo "<div class='box error'>⚠️ Tidak ada data dengan status='Aktif' di database!</div>";
        echo "<div class='box'>Cek semua status di database:</div>";
        $all = $pdo->query("SELECT id, nama, status FROM program_studi")->fetchAll();
        echo "<div class='box'><pre>";
        print_r($all);
        echo "</pre></div>";
    }
} catch (Exception $e) {
    echo "<div class='box error'>❌ Query error: " . $e->getMessage() . "</div>";
}

// Test 4: Cek apakah file program.php bisa di-include
echo "<h2>4. Struktur Tabel program_studi</h2>";
try {
    $stmt = $pdo->query("DESCRIBE program_studi");
    $columns = $stmt->fetchAll();
    echo "<div class='box success'>✅ Tabel memiliki <strong>" . count($columns) . "</strong> kolom:</div>";
    echo "<div class='box'><ul>";
    foreach ($columns as $col) {
        echo "<li><code>" . $col['Field'] . "</code> (" . $col['Type'] . ")</li>";
    }
    echo "</ul></div>";
} catch (Exception $e) {
    echo "<div class='box error'>❌ Error: " . $e->getMessage() . "</div>";
}

// Test 5: Cek PHP Version & Extensions
echo "<h2>5. PHP Environment</h2>";
echo "<div class='box'>";
echo "PHP Version: <code>" . phpversion() . "</code><br>";
echo "PDO MySQL: " . (extension_loaded('pdo_mysql') ? '✅' : '❌') . "<br>";
echo "Session: " . (session_status() === PHP_SESSION_ACTIVE ? '✅ Active' : '❌ Inactive') . "<br>";
echo "Timezone: <code>" . date_default_timezone_get() . "</code><br>";
echo "</div>";

echo "<h2>6. Coba Render Data (Simulasi program.php)</h2>";
try {
    $stmt = $pdo->query("SELECT * FROM program_studi WHERE status = 'Aktif' ORDER BY urutan ASC, id ASC LIMIT 3");
    $test_prodi = $stmt->fetchAll();
    
    if (count($test_prodi) > 0) {
        echo "<div class='box success'>✅ Rendering 3 data pertama berhasil:</div>";
        foreach ($test_prodi as $p) {
            echo "<div class='box'>";
            echo "<h3>" . sanitize($p['nama']) . "</h3>";
            echo "<p>Kode: " . sanitize($p['kode']) . "</p>";
            echo "<p>Visi: " . sanitize(substr($p['visi'] ?? 'Kosong', 0, 100)) . "...</p>";
            echo "<p>Misi: " . sanitize(substr($p['misi'] ?? 'Kosong', 0, 100)) . "...</p>";
            echo "</div>";
        }
    }
} catch (Exception $e) {
    echo "<div class='box error'>❌ Error rendering: " . $e->getMessage() . "</div>";
}

echo "<hr><p style='color:#64748b;font-size:0.9rem;'>Diagnostic completed at " . date('Y-m-d H:i:s') . "</p>";
echo "</body></html>";