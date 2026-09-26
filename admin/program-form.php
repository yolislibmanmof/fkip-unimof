<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// DEBUG: Tampilkan semua error
error_reporting(E_ALL);
ini_set('display_errors', 1);

$id = (int)($_GET['id'] ?? 0);
$edit = null;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM program_studi WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h1>🔍 DEBUG MODE - LANGKAH DEMI LANGKAH</h1>";
    echo "<pre>";
    
    echo "=== LANGKAH 1: Cek Request Method ===\n";
    echo "Method: " . $_SERVER['REQUEST_METHOD'] . "\n\n";
    
    echo "=== LANGKAH 2: Cek CSRF Token ===\n";
    $csrf_token = $_POST['csrf_token'] ?? '';
    echo "Token dari form: " . substr($csrf_token, 0, 20) . "...\n";
    $csrf_valid = verify_csrf_token($csrf_token);
    echo "CSRF Valid: " . ($csrf_valid ? '✅ YA' : ' TIDAK') . "\n\n";
    
    if (!$csrf_valid) {
        echo "❌ STOP: CSRF Token tidak valid!\n";
        echo "Kemungkinan penyebab:\n";
        echo "1. Session expired - coba refresh halaman dan isi form lagi\n";
        echo "2. Cookie tidak aktif di browser\n";
        echo "3. Ada masalah dengan fungsi verify_csrf_token()\n";
        die("</pre><a href='program-form.php?id=$id'>← Kembali ke form</a>");
    }
    
    echo "=== LANGKAH 3: Ambil Data dari Form ===\n";
    $kode = trim($_POST['kode'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $nama_en = trim($_POST['nama_en'] ?? '');
    $singkatan = trim($_POST['singkatan'] ?? '');
    $jenjang = $_POST['jenjang'] ?? 'S1';
    $akreditasi = $_POST['akreditasi'] ?? 'Terakreditasi';
    $ketua_prodi = trim($_POST['ketua_prodi'] ?? '');
    $nidn_kaprodi = trim($_POST['nidn_kaprodi'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $visi = trim($_POST['visi'] ?? '');
    $misi = trim($_POST['misi'] ?? '');
    $kurikulum = trim($_POST['kurikulum'] ?? '');
    $prospek_kerja = trim($_POST['prospek_kerja'] ?? '');
    $jumlah_dosen = (int)($_POST['jumlah_dosen'] ?? 0);
    $jumlah_mahasiswa = (int)($_POST['jumlah_mahasiswa'] ?? 0);
    $status = $_POST['status'] ?? 'Aktif';
    $urutan = (int)($_POST['urutan'] ?? 0);
    
    echo "Kode: '$kode'\n";
    echo "Nama: '$nama'\n";
    echo "Singkatan: '$singkatan'\n";
    echo "NIDN: '$nidn_kaprodi'\n";
    echo "Visi: '$visi'\n";
    echo "Status: '$status'\n\n";
    
    echo "=== LANGKAH 4: Validasi Field Required ===\n";
    if (empty($kode) || empty($nama) || empty($singkatan)) {
        echo "❌ STOP: Field wajib kosong!\n";
        echo "Kode: " . (empty($kode) ? 'KOSONG' : 'OK') . "\n";
        echo "Nama: " . (empty($nama) ? 'KOSONG' : 'OK') . "\n";
        echo "Singkatan: " . (empty($singkatan) ? 'KOSONG' : 'OK') . "\n";
        die("</pre><a href='program-form.php?id=$id'>← Kembali ke form</a>");
    }
    echo "✅ Semua field required terisi\n\n";
    
    echo "=== LANGKAH 5: Persiapan Query Database ===\n";
    echo "ID yang akan diupdate: $id\n";
    echo "Jumlah data yang akan disimpan: 19 field\n\n";
    
    echo "=== LANGKAH 6: Eksekusi UPDATE ===\n";
    try {
        $sql = "UPDATE program_studi SET 
                kode=?, nama=?, nama_en=?, singkatan=?, jenjang=?, akreditasi=?,
                ketua_prodi=?, nidn_kaprodi=?, deskripsi=?, visi=?, misi=?,
                kurikulum=?, prospek_kerja=?, logo=?, banner=?,
                jumlah_dosen=?, jumlah_mahasiswa=?, status=?, urutan=?,
                updated_at=NOW()
                WHERE id=?";
        
        echo "SQL Query:\n$sql\n\n";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $kode, $nama, $nama_en, $singkatan,
            $jenjang, $akreditasi, $ketua_prodi,
            $nidn_kaprodi, $deskripsi, $visi, $misi,
            $kurikulum, $prospek_kerja, 
            $edit['logo'], $edit['banner'],
            $jumlah_dosen, $jumlah_mahasiswa, $status,
            $urutan, $id
        ]);
        
        echo "Hasil execute: " . ($result ? '✅ BERHASIL' : '❌ GAGAL') . "\n";
        echo "Jumlah baris yang diupdate: " . $stmt->rowCount() . "\n\n";
        
        if ($result && $stmt->rowCount() > 0) {
            echo "=== ✅ SUKSES! ===\n";
            echo "Data berhasil disimpan ke database.\n";
            echo "Silakan cek di phpMyAdmin untuk memastikan.\n";
            echo "</pre>";
            echo "<br><br>";
            echo "<a href='program.php' style='padding: 10px 20px; background: #10b981; color: white; text-decoration: none; border-radius: 5px;'>← Kembali ke Daftar Program Studi</a>";
            echo "<a href='program-detail.php?id=$id' style='padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 5px; margin-left: 10px;'>👁️ Lihat di Halaman Publik</a>";
            exit;
        } else {
            echo "=== ⚠️ PERINGATAN ===\n";
            echo "Query berhasil tapi tidak ada baris yang diupdate.\n";
            echo "Kemungkinan: ID $id tidak ditemukan di database.\n";
        }
    } catch (PDOException $e) {
        echo "=== ❌ DATABASE ERROR ===\n";
        echo "Error Code: " . $e->getCode() . "\n";
        echo "Error Message: " . $e->getMessage() . "\n\n";
        echo "Kemungkinan penyebab:\n";
        echo "1. Kolom 'nidn_kaprodi' tidak ada di database\n";
        echo "2. Ada kolom lain yang tidak sesuai dengan struktur database\n";
        echo "3. Tipe data tidak cocok\n";
    }
    
    echo "</pre>";
    echo "<br><br>";
    echo "<a href='program-form.php?id=$id' style='padding: 10px 20px; background: #ef4444; color: white; text-decoration: none; border-radius: 5px;'>← Kembali ke Form</a>";
    exit;
}

$csrf = generate_csrf_token();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Program Studi</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #0a6847; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; color: #333; }
        label .required { color: #ef4444; }
        input[type="text"], input[type="number"], select, textarea {
            width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px;
            font-size: 14px; font-family: inherit;
        }
        input:focus, select:focus, textarea:focus {
            outline: none; border-color: #0a6847;
        }
        textarea { resize: vertical; min-height: 100px; }
        button {
            background: #0a6847; color: white; padding: 15px 30px; border: none;
            border-radius: 5px; font-size: 16px; font-weight: bold; cursor: pointer;
            width: 100%;
        }
        button:hover { background: #084d35; }
        .info-box { background: #e0f2fe; border-left: 4px solid #0284c7; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>✏️ Edit Program Studi (ID: <?= $id ?>)</h1>
        
        <div class="info-box">
            <strong>📝 Instruksi:</strong> Isi form di bawah ini, lalu klik "Simpan Perubahan". 
            Halaman akan menampilkan DEBUG MODE yang menunjukkan setiap langkah proses penyimpanan.
        </div>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            
            <div class="form-group">
                <label>Kode Prodi <span class="required">*</span></label>
                <input type="text" name="kode" required value="<?= htmlspecialchars($edit['kode'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label>Nama Program Studi <span class="required">*</span></label>
                <input type="text" name="nama" required value="<?= htmlspecialchars($edit['nama'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label>Nama (English)</label>
                <input type="text" name="nama_en" value="<?= htmlspecialchars($edit['nama_en'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label>Singkatan <span class="required">*</span></label>
                <input type="text" name="singkatan" required value="<?= htmlspecialchars($edit['singkatan'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label>Jenjang</label>
                <select name="jenjang">
                    <option value="S1" <?= ($edit['jenjang'] ?? 'S1') === 'S1' ? 'selected' : '' ?>>S1</option>
                    <option value="S2" <?= ($edit['jenjang'] ?? '') === 'S2' ? 'selected' : '' ?>>S2</option>
                    <option value="D3" <?= ($edit['jenjang'] ?? '') === 'D3' ? 'selected' : '' ?>>D3</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Akreditasi</label>
                <select name="akreditasi">
                    <option value="Unggul" <?= ($edit['akreditasi'] ?? '') === 'Unggul' ? 'selected' : '' ?>>Unggul</option>
                    <option value="Baik Sekali" <?= ($edit['akreditasi'] ?? '') === 'Baik Sekali' ? 'selected' : '' ?>>Baik Sekali</option>
                    <option value="Baik" <?= ($edit['akreditasi'] ?? '') === 'Baik' ? 'selected' : '' ?>>Baik</option>
                    <option value="Terakreditasi" <?= ($edit['akreditasi'] ?? '') === 'Terakreditasi' ? 'selected' : '' ?>>Terakreditasi</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Ketua Program Studi</label>
                <input type="text" name="ketua_prodi" value="<?= htmlspecialchars($edit['ketua_prodi'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label>NIDN Kaprodi</label>
                <input type="text" name="nidn_kaprodi" value="<?= htmlspecialchars($edit['nidn_kaprodi'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label>Deskripsi</label>
                <textarea name="deskripsi"><?= htmlspecialchars($edit['deskripsi'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Visi</label>
                <textarea name="visi"><?= htmlspecialchars($edit['visi'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Misi</label>
                <textarea name="misi"><?= htmlspecialchars($edit['misi'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Kurikulum</label>
                <textarea name="kurikulum"><?= htmlspecialchars($edit['kurikulum'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Prospek Kerja</label>
                <textarea name="prospek_kerja"><?= htmlspecialchars($edit['prospek_kerja'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Jumlah Dosen</label>
                <input type="number" name="jumlah_dosen" min="0" value="<?= (int)($edit['jumlah_dosen'] ?? 0) ?>">
            </div>
            
            <div class="form-group">
                <label>Jumlah Mahasiswa</label>
                <input type="number" name="jumlah_mahasiswa" min="0" value="<?= (int)($edit['jumlah_mahasiswa'] ?? 0) ?>">
            </div>
            
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="Aktif" <?= ($edit['status'] ?? 'Aktif') === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                    <option value="Non-Aktif" <?= ($edit['status'] ?? '') === 'Non-Aktif' ? 'selected' : '' ?>>Non-Aktif</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Urutan Tampil</label>
                <input type="number" name="urutan" min="0" value="<?= (int)($edit['urutan'] ?? 0) ?>">
            </div>
            
            <button type="submit">💾 Simpan Perubahan (Dengan Debug)</button>
        </form>
        
        <p style="margin-top: 20px; text-align: center;">
            <a href="program.php" style="color: #0a6847;">← Kembali ke Daftar Program Studi</a>
        </p>
    </div>
</body>
</html>