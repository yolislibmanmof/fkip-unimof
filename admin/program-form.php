<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$edit = null;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM program_studi WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    // Collect all form data
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

    // Handle file uploads
    $logo = $edit['logo'] ?? null;
    $banner = $edit['banner'] ?? null;

    if (!empty($_FILES['logo']['name'])) {
        $upload_dir = __DIR__ . '/../uploads/prodi_logo/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        
        $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $new_filename = 'logo_' . time() . '.' . $ext;
        
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $new_filename)) {
            if ($logo && file_exists($upload_dir . $logo)) {
                unlink($upload_dir . $logo);
            }
            $logo = $new_filename;
        }
    }

    if (!empty($_FILES['banner']['name'])) {
        $upload_dir = __DIR__ . '/../uploads/prodi_banner/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        
        $ext = pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION);
        $new_filename = 'banner_' . time() . '.' . $ext;
        
        if (move_uploaded_file($_FILES['banner']['tmp_name'], $upload_dir . $new_filename)) {
            if ($banner && file_exists($upload_dir . $banner)) {
                unlink($upload_dir . $banner);
            }
            $banner = $new_filename;
        }
    }

    try {
        if ($edit) {
            // Update existing
            $sql = "UPDATE program_studi SET 
                    kode=?, nama=?, nama_en=?, singkatan=?, jenjang=?, akreditasi=?,
                    ketua_prodi=?, nidn_kaprodi=?, deskripsi=?, visi=?, misi=?,
                    kurikulum=?, prospek_kerja=?, logo=?, banner=?,
                    jumlah_dosen=?, jumlah_mahasiswa=?, status=?, urutan=?,
                    updated_at=NOW()
                    WHERE id=?";
            $pdo->prepare($sql)->execute([
                $kode, $nama, $nama_en, $singkatan,
                $jenjang, $akreditasi, $ketua_prodi,
                $nidn_kaprodi, $deskripsi, $visi, $misi,
                $kurikulum, $prospek_kerja, $logo, $banner,
                $jumlah_dosen, $jumlah_mahasiswa, $status,
                $urutan, $id
            ]);
            flash_message('success', '✅ Program studi berhasil diperbarui.');
        } else {
            // Insert new
            $sql = "INSERT INTO program_studi 
                    (kode, nama, nama_en, singkatan, jenjang, akreditasi,
                    ketua_prodi, nidn_kaprodi, deskripsi, visi, misi,
                    kurikulum, prospek_kerja, logo, banner,
                    jumlah_dosen, jumlah_mahasiswa, status, urutan,
                    created_at, updated_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())";
            $pdo->prepare($sql)->execute([
                $kode, $nama, $nama_en, $singkatan,
                $jenjang, $akreditasi, $ketua_prodi,
                $nidn_kaprodi, $deskripsi, $visi, $misi,
                $kurikulum, $prospek_kerja, $logo, $banner,
                $jumlah_dosen, $jumlah_mahasiswa, $status,
                $urutan
            ]);
            flash_message('success', '✅ Program studi baru berhasil ditambahkan.');
        }
    } catch (Exception $e) {
        flash_message('error', ' Gagal menyimpan: ' . $e->getMessage());
    }
    
    header('Location: program.php');
    exit;
}

$csrf = generate_csrf_token();
$active_menu = 'prodi';
$page_heading = $edit ? 'Edit Program Studi' : 'Tambah Program Studi';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Program Studi', 'program.php'], [$page_heading, null]];

require __DIR__ . '/includes/header.php';
?>

<style>
/* Same EXTREME MULTIMATE styles */
.form-layout-extreme { display: grid; grid-template-columns: 1fr; gap: 2rem; }
.form-card-extreme { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); }
.form-header-extreme { margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border); }
.form-header-extreme h2 { font-family: var(--font-display); font-size: 1.75rem; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; color: var(--text-primary); }
.form-section { background: var(--bg-secondary); padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--border); margin-bottom: 1.5rem; }
.form-section-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 1.5rem; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem; padding-bottom: 0.75rem; border-bottom: 2px solid var(--border); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem; }
.form-row:last-child { margin-bottom: 0; }
.form-group { margin-bottom: 0; }
.form-label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; }
.form-label .required { color: #ef4444; margin-left: 0.25rem; }
.form-input, .form-select, .form-textarea { width: 100%; padding: 0.75rem 1rem; border: 2px solid var(--border); border-radius: var(--radius-md); font-family: inherit; font-size: 0.95rem; background: var(--bg-primary); color: var(--text-primary); transition: all 0.3s; }
.form-textarea { resize: vertical; min-height: 100px; line-height: 1.6; }
.form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.form-hint { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem; }

.upload-zone { border: 2px dashed var(--border); border-radius: var(--radius-lg); padding: 2rem; text-align: center; background: var(--bg-primary); transition: all 0.3s; cursor: pointer; position: relative; }
.upload-zone:hover { border-color: var(--primary); background: rgba(10,104,71,0.03); }
.upload-zone input[type="file"] { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
.upload-icon { font-size: 3rem; margin-bottom: 0.75rem; opacity: 0.5; }
.upload-text { font-weight: 600; color: var(--text-primary); margin-bottom: 0.25rem; }
.upload-sub { font-size: 0.85rem; color: var(--text-muted); }

.current-file { margin-top: 1rem; padding: 1rem; background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-md); }
.current-file img { max-width: 150px; border-radius: var(--radius-md); margin-bottom: 0.5rem; }

.submit-btn-extreme { width: 100%; padding: 1.1rem; background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; border: none; border-radius: var(--radius-md); font-family: inherit; font-size: 1rem; font-weight: 700; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin-top: 2rem; }
.submit-btn-extreme:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(10,104,71,0.35); }

@media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } }
</style>

<div class="form-layout-extreme">
    <div class="form-card-extreme" data-aos="fade-up">
        <div class="form-header-extreme">
            <h2><?= $edit ? '✏️ Edit' : '➕ Tambah' ?> Program Studi</h2>
            <p>Lengkapi semua informasi program studi berikut</p>
        </div>

        <form method="POST" enctype="multipart/form-data" id="programForm">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

            <!-- Informasi Dasar -->
            <div class="form-section">
                <div class="form-section-title">📋 Informasi Dasar</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Kode Prodi <span class="required">*</span></label>
                        <input type="text" name="kode" class="form-input" required placeholder="Contoh: PMAT" value="<?= sanitize($edit['kode'] ?? '') ?>">
                        <div class="form-hint">Kode unik program studi (3-6 karakter)</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Jenjang <span class="required">*</span></label>
                        <select name="jenjang" class="form-select" required>
                            <option value="S1" <?= ($edit['jenjang'] ?? '') === 'S1' ? 'selected' : '' ?>>S1 - Strata 1</option>
                            <option value="S2" <?= ($edit['jenjang'] ?? '') === 'S2' ? 'selected' : '' ?>>S2 - Magister</option>
                            <option value="D3" <?= ($edit['jenjang'] ?? '') === 'D3' ? 'selected' : '' ?>>D3 - Diploma 3</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Nama Program Studi (ID) <span class="required">*</span></label>
                        <input type="text" name="nama" class="form-input" required placeholder="Contoh: Pendidikan Matematika" value="<?= sanitize($edit['nama'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nama Program Studi (EN)</label>
                        <input type="text" name="nama_en" class="form-input" placeholder="Contoh: Mathematics Education" value="<?= sanitize($edit['nama_en'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Singkatan <span class="required">*</span></label>
                        <input type="text" name="singkatan" class="form-input" required placeholder="Contoh: Pend. Mat." value="<?= sanitize($edit['singkatan'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Akreditasi</label>
                        <select name="akreditasi" class="form-select">
                            <option value="Unggul" <?= ($edit['akreditasi'] ?? '') === 'Unggul' ? 'selected' : '' ?>>Unggul</option>
                            <option value="Baik Sekali" <?= ($edit['akreditasi'] ?? '') === 'Baik Sekali' ? 'selected' : '' ?>>Baik Sekali</option>
                            <option value="Baik" <?= ($edit['akreditasi'] ?? '') === 'Baik' ? 'selected' : '' ?>>Baik</option>
                            <option value="Terakreditasi" <?= ($edit['akreditasi'] ?? '') === 'Terakreditasi' ? 'selected' : '' ?>>Terakreditasi</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Informasi Pimpinan -->
            <div class="form-section">
                <div class="form-section-title">👨‍💼 Informasi Pimpinan</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Ketua Program Studi</label>
                        <input type="text" name="ketua_prodi" class="form-input" placeholder="Nama lengkap dengan gelar" value="<?= sanitize($edit['ketua_prodi'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">NIDN Kaprodi</label>
                        <input type="text" name="nidn_kaprodi" class="form-input" placeholder="NIDN Ketua Program Studi" value="<?= sanitize($edit['nidn_kaprodi'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Deskripsi & Informasi Akademik -->
            <div class="form-section">
                <div class="form-section-title">📝 Deskripsi & Informasi Akademik</div>
                
                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label">Deskripsi Singkat</label>
                    <textarea name="deskripsi" class="form-textarea" rows="3" placeholder="Deskripsi singkat program studi..."><?= sanitize($edit['deskripsi'] ?? '') ?></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label">Visi</label>
                    <textarea name="visi" class="form-textarea" rows="4" placeholder="Visi program studi..."><?= sanitize($edit['visi'] ?? '') ?></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label">Misi</label>
                    <textarea name="misi" class="form-textarea" rows="5" placeholder="Misi program studi (satu per baris)..."><?= sanitize($edit['misi'] ?? '') ?></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label">Kurikulum</label>
                    <textarea name="kurikulum" class="form-textarea" rows="4" placeholder="Deskripsi kurikulum..."><?= sanitize($edit['kurikulum'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Prospek Kerja</label>
                    <textarea name="prospek_kerja" class="form-textarea" rows="4" placeholder="Prospek karir lulusan (satu per baris)..."><?= sanitize($edit['prospek_kerja'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Statistik -->
            <div class="form-section">
                <div class="form-section-title">📊 Statistik Program</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Jumlah Dosen</label>
                        <input type="number" name="jumlah_dosen" class="form-input" min="0" value="<?= (int)($edit['jumlah_dosen'] ?? 0) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Jumlah Mahasiswa</label>
                        <input type="number" name="jumlah_mahasiswa" class="form-input" min="0" value="<?= (int)($edit['jumlah_mahasiswa'] ?? 0) ?>">
                    </div>
                </div>
            </div>

            <!-- Upload File -->
            <div class="form-section">
                <div class="form-section-title">🖼️ Logo & Banner</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Logo Program Studi</label>
                        <div class="upload-zone">
                            <input type="file" name="logo" accept="image/*">
                            <div class="upload-icon">📤</div>
                            <div class="upload-text">Klik untuk upload logo</div>
                            <div class="upload-sub">PNG, JPG (maks 2MB)</div>
                        </div>
                        <?php if (!empty($edit['logo'])): ?>
                        <div class="current-file">
                            <img src="<?= base_url('uploads/prodi_logo/' . basename($edit['logo'])) ?>" alt="Logo">
                            <div>Logo saat ini: <?= sanitize($edit['logo']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Banner Program Studi</label>
                        <div class="upload-zone">
                            <input type="file" name="banner" accept="image/*">
                            <div class="upload-icon">🖼️</div>
                            <div class="upload-text">Klik untuk upload banner</div>
                            <div class="upload-sub">PNG, JPG (maks 3MB)</div>
                        </div>
                        <?php if (!empty($edit['banner'])): ?>
                        <div class="current-file">
                            <img src="<?= base_url('uploads/prodi_banner/' . basename($edit['banner'])) ?>" alt="Banner">
                            <div>Banner saat ini: <?= sanitize($edit['banner']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Pengaturan -->
            <div class="form-section">
                <div class="form-section-title">⚙️ Pengaturan</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Status <span class="required">*</span></label>
                        <select name="status" class="form-select" required>
                            <option value="Aktif" <?= ($edit['status'] ?? 'Aktif') === 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
                            <option value="Non-Aktif" <?= ($edit['status'] ?? '') === 'Non-Aktif' ? 'selected' : '' ?>>❌ Non-Aktif</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Urutan Tampil</label>
                        <input type="number" name="urutan" class="form-input" min="0" value="<?= (int)($edit['urutan'] ?? 0) ?>">
                        <div class="form-hint">Urutan tampil di halaman publik (0 = paling atas)</div>
                    </div>
                </div>
            </div>

            <button type="submit" class="submit-btn-extreme">
                <span><?= $edit ? '💾 Perbarui Data' : '✨ Simpan Program' ?></span>
            </button>
        </form>
    </div>
</div>

<script>
console.log('%c🎓 Form Program Studi Lengkap', 'color: #3b82f6; font-size: 16px; font-weight: bold;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>