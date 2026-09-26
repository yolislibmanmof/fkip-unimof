<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$edit = null;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM program_studi WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$edit) {
        flash_message('error', '❌ Program studi tidak ditemukan.');
        header('Location: program.php');
        exit;
    }
}

// =====================================================
// DIAGNOSTIK SISI SERVER: catat SETIAP POST + hasil UPDATE
// (tidak bisa dibungkam oleh browser/JS)
// =====================================================
$debug_log_file = __DIR__ . '/../logs/save-debug.log';
$is_post = ($_SERVER['REQUEST_METHOD'] === 'POST');
$csrf_ok = $is_post ? verify_csrf_token($_POST['csrf_token'] ?? '') : false;

if ($is_post) {
    @mkdir(dirname($debug_log_file), 0755, true);
    @file_put_contents($debug_log_file,
        date('Y-m-d H:i:s') .
        ' | POST masuk | csrf=' . ($csrf_ok ? 'OK' : 'FAIL') .
        ' | id=' . $id .
        ' | keys=' . implode(',', array_keys($_POST)) .
        ' | visi="' . mb_substr(trim($_POST['visi'] ?? ''), 0, 40) . '"' .
        "\n", FILE_APPEND);
}

if ($is_post && $csrf_ok) {
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

    if (empty($kode) || empty($nama) || empty($singkatan)) {
        flash_message('error', '❌ Field wajib (Kode, Nama, Singkatan) harus diisi!');
    } else {
        $logo = $edit['logo'] ?? null;
        $banner = $edit['banner'] ?? null;
        $upload_success = true;

        $MAX_LOGO = 2 * 1024 * 1024;
        $MAX_BANNER = 3 * 1024 * 1024;

        if (!empty($_FILES['logo']['name'])) {
            if ($_FILES['logo']['size'] > $MAX_LOGO) {
                $upload_success = false;
                flash_message('error', '❌ Ukuran logo terlalu besar. Maksimal 2MB.');
            } else {
                $upload_dir = __DIR__ . '/../uploads/prodi_logo/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'svg'])) {
                    $new_filename = 'logo_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $new_filename)) {
                        if ($logo && file_exists($upload_dir . $logo)) unlink($upload_dir . $logo);
                        $logo = $new_filename;
                    } else {
                        $upload_success = false;
                        flash_message('error', '❌ Gagal mengupload logo.');
                    }
                } else {
                    $upload_success = false;
                    flash_message('error', '❌ Format file logo tidak valid.');
                }
            }
        }

        if (!empty($_FILES['banner']['name']) && $upload_success) {
            if ($_FILES['banner']['size'] > $MAX_BANNER) {
                $upload_success = false;
                flash_message('error', '❌ Ukuran banner terlalu besar. Maksimal 3MB.');
            } else {
                $upload_dir = __DIR__ . '/../uploads/prodi_banner/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $ext = strtolower(pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $new_filename = 'banner_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['banner']['tmp_name'], $upload_dir . $new_filename)) {
                        if ($banner && file_exists($upload_dir . $banner)) unlink($upload_dir . $banner);
                        $banner = $new_filename;
                    } else {
                        $upload_success = false;
                        flash_message('error', '❌ Gagal mengupload banner.');
                    }
                } else {
                    $upload_success = false;
                    flash_message('error', '❌ Format file banner tidak valid.');
                }
            }
        }

        if ($upload_success) {
            try {
                if ($edit) {
                    $sql = "UPDATE program_studi SET
                            kode=?, nama=?, nama_en=?, singkatan=?, jenjang=?, akreditasi=?,
                            ketua_prodi=?, nidn_kaprodi=?, deskripsi=?, visi=?, misi=?,
                            kurikulum=?, prospek_kerja=?, logo=?, banner=?,
                            jumlah_dosen=?, jumlah_mahasiswa=?, status=?, urutan=?,
                            updated_at=NOW()
                            WHERE id=?";
                    $params = [
                        $kode, $nama, $nama_en, $singkatan,
                        $jenjang, $akreditasi, $ketua_prodi,
                        $nidn_kaprodi, $deskripsi, $visi, $misi,
                        $kurikulum, $prospek_kerja, $logo, $banner,
                        $jumlah_dosen, $jumlah_mahasiswa, $status,
                        $urutan, $id
                    ];
                    $st = $pdo->prepare($sql);
                    $st->execute($params);
                    @file_put_contents($debug_log_file,
                        date('Y-m-d H:i:s') . " | UPDATE OK | affected=" . $st->rowCount() . " | id=$id\n", FILE_APPEND);
                    flash_message('success', '✅ Program studi <strong>' . htmlspecialchars($nama) . '</strong> berhasil diperbarui.');
                } else {
                    $sql = "INSERT INTO program_studi
                            (kode, nama, nama_en, singkatan, jenjang, akreditasi,
                             ketua_prodi, nidn_kaprodi, deskripsi, visi, misi,
                             kurikulum, prospek_kerja, logo, banner,
                             jumlah_dosen, jumlah_mahasiswa, status, urutan,
                             created_at, updated_at)
                            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())";
                    $params = [
                        $kode, $nama, $nama_en, $singkatan,
                        $jenjang, $akreditasi, $ketua_prodi,
                        $nidn_kaprodi, $deskripsi, $visi, $misi,
                        $kurikulum, $prospek_kerja, $logo, $banner,
                        $jumlah_dosen, $jumlah_mahasiswa, $status,
                        $urutan
                    ];
                    $pdo->prepare($sql)->execute($params);
                    $id = (int)$pdo->lastInsertId();
                    @file_put_contents($debug_log_file,
                        date('Y-m-d H:i:s') . " | INSERT OK | id baru=$id\n", FILE_APPEND);
                    flash_message('success', '✅ Program studi <strong>' . htmlspecialchars($nama) . '</strong> berhasil ditambahkan.');
                }

                header('Location: program-form.php?id=' . $id);
                exit;

            } catch (PDOException $e) {
                @file_put_contents($debug_log_file,
                    date('Y-m-d H:i:s') . ' | DB ERROR | ' . $e->getMessage() . "\n", FILE_APPEND);
                if ($e->getCode() == 23000) {
                    flash_message('error', '❌ Kode prodi <strong>' . htmlspecialchars($kode) . '</strong> sudah digunakan.');
                } else {
                    flash_message('error', '❌ Gagal menyimpan: ' . $e->getMessage());
                }
            }
        }
    }
} elseif ($is_post) {
    flash_message('error', '❌ Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
}

$flash_messages = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);
$just_saved = isset($flash_messages['success']);

if ($id > 0 && $just_saved) {
    $stmt = $pdo->prepare("SELECT * FROM program_studi WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

$csrf = generate_csrf_token();
$active_menu = 'prodi';
$page_heading = $edit ? 'Edit Program Studi' : 'Tambah Program Studi';
$breadcrumbs = [
    ['Dashboard', 'dashboard.php'],
    ['Program Studi', 'program.php'],
    [$page_heading, null]
];

require __DIR__ . '/includes/header.php';
?>

<style>
.save-info-bar { max-width: 1200px; margin: 0 auto 1.5rem; padding: 1.25rem 1.5rem; background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: space-between; }
.save-info-bar .info-section { display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap; }
.save-info-bar .info-item { display: flex; align-items: center; gap: 0.4rem; font-size: 0.9rem; color: var(--text-secondary); }
.save-info-bar .info-item strong { color: var(--text-primary); font-weight: 700; }
.save-info-bar .info-item .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--primary); }
.save-info-bar .action-group { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.save-info-bar .quick-btn { padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.3s; border: 1px solid var(--border); cursor: pointer; font-family: inherit; }
.save-info-bar .quick-btn.primary { background: var(--primary); color: white; border-color: var(--primary); }
.save-info-bar .quick-btn.primary:hover { background: var(--primary-dark); transform: translateY(-2px); }
.save-info-bar .quick-btn.ghost { background: var(--bg-secondary); color: var(--text-primary); }
.save-info-bar .quick-btn.ghost:hover { background: var(--bg-tertiary); border-color: var(--primary); color: var(--primary); }
.inline-flash { padding: 1rem 1.25rem; border-radius: var(--radius-md); margin: 0 0 1.5rem; font-weight: 600; }
.inline-flash-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
.inline-flash-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
.db-truth { font-family: monospace; font-size: 0.8rem; line-height: 1.7; background: var(--bg-secondary); border: 1px dashed var(--border); border-radius: var(--radius-md); padding: 0.75rem 1rem; margin: 0 0 1.5rem; color: var(--text-secondary); word-break: break-all; }
.form-layout-extreme { display: grid; grid-template-columns: 1fr; gap: 2rem; }
.form-card-extreme { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2.5rem; box-shadow: var(--shadow-lg); position: relative; overflow: hidden; }
.form-card-extreme::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--primary), var(--primary-light), var(--accent)); }
.form-header-extreme { margin-bottom: 2.5rem; padding-bottom: 1.5rem; border-bottom: 2px solid var(--border); }
.form-header-extreme h2 { font-family: var(--font-display); font-size: 1.875rem; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; color: var(--text-primary); font-weight: 800; }
.form-header-extreme p { color: var(--text-secondary); font-size: 0.95rem; }
.form-section { background: var(--bg-secondary); padding: 2rem; border-radius: var(--radius-lg); border: 1px solid var(--border); margin-bottom: 2rem; }
.form-section-title { font-size: 1.15rem; font-weight: 700; margin-bottom: 1.75rem; color: var(--text-primary); display: flex; align-items: center; gap: 0.625rem; padding-bottom: 0.875rem; border-bottom: 2px solid var(--primary); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
.form-row:last-child { margin-bottom: 0; }
.form-group { margin-bottom: 0; }
.form-label { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.625rem; }
.form-label .required { color: #ef4444; margin-left: 0.25rem; font-weight: 800; }
.form-label .label-icon { width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; background: rgba(10, 104, 71, 0.1); color: var(--primary); border-radius: 4px; font-size: 0.75rem; }
.form-input, .form-select, .form-textarea { width: 100%; padding: 0.875rem 1rem; border: 2px solid var(--border); border-radius: var(--radius-md); font-family: inherit; font-size: 0.95rem; background: var(--bg-primary); color: var(--text-primary); transition: all 0.3s; }
.form-textarea { resize: vertical; min-height: 110px; line-height: 1.6; }
.form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(10, 104, 71, 0.12); }
.form-hint { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem; }
.upload-zone { border: 2px dashed var(--border); border-radius: var(--radius-lg); padding: 2.5rem 1.5rem; text-align: center; background: var(--bg-primary); cursor: pointer; position: relative; overflow: hidden; }
.upload-zone:hover { border-color: var(--primary); }
.upload-zone input[type="file"] { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 2; }
.upload-icon { font-size: 3.5rem; margin-bottom: 0.875rem; opacity: 0.75; }
.upload-text { font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem; font-size: 1rem; }
.upload-sub { font-size: 0.85rem; color: var(--text-muted); }
.current-file { margin-top: 1.25rem; padding: 1.25rem; background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-md); display: flex; align-items: center; gap: 1rem; }
.current-file img { max-width: 120px; max-height: 120px; border-radius: var(--radius-md); object-fit: cover; }
.current-file-info { flex: 1; }
.current-file-info .file-name { font-weight: 600; color: var(--text-primary); margin-bottom: 0.25rem; font-size: 0.9rem; }
.current-file-info .file-status { display: inline-flex; font-size: 0.75rem; padding: 0.25rem 0.625rem; background: rgba(10, 104, 71, 0.1); color: var(--primary); border-radius: 999px; font-weight: 600; }
.submit-btn-extreme { width: 100%; padding: 1.25rem; background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; border: none; border-radius: var(--radius-md); font-family: inherit; font-size: 1.05rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.625rem; margin-top: 2.5rem; letter-spacing: 0.02em; text-transform: uppercase; box-shadow: 0 4px 14px rgba(10, 104, 71, 0.25); }
.submit-btn-extreme:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(10, 104, 71, 0.4); }
.save-note { margin-top: 1rem; font-size: 0.8rem; color: var(--text-muted); }
.status-badge { display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; margin-left: 0.5rem; }
.status-badge.edit-mode { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
.status-badge.create-mode { background: rgba(16, 185, 129, 0.1); color: #10b981; }
@media (max-width: 768px) {
    .form-row { grid-template-columns: 1fr; }
    .form-card-extreme { padding: 1.5rem; }
    .form-section { padding: 1.5rem; }
    .current-file { flex-direction: column; text-align: center; }
    .save-info-bar { flex-direction: column; align-items: stretch; }
}
</style>

<?php if ($edit): ?>
<div class="save-info-bar">
    <div class="info-section">
        <div class="info-item"><span class="dot"></span><span>ID:</span><strong><?= (int)$edit['id'] ?></strong></div>
        <div class="info-item"><span class="dot"></span><span>Dibuat:</span><strong><?= date('d M Y', strtotime($edit['created_at'] ?? 'now')) ?></strong></div>
        <div class="info-item"><span class="dot"></span><span>Terakhir Update:</span><strong><?= date('d M Y H:i', strtotime($edit['updated_at'] ?? 'now')) ?></strong></div>
    </div>
    <div class="action-group">
        <button type="submit" form="programForm" class="quick-btn primary">💾 Simpan (dari atas)</button>
        <a href="<?= base_url('program-detail.php?id=' . (int)$edit['id']) ?>" target="_blank" class="quick-btn primary">👁️ Lihat di Publik</a>
        <a href="program-form.php?id=<?= (int)$edit['id'] ?>&debug=1" class="quick-btn ghost">📜 Log Debug</a>
        <a href="program.php" class="quick-btn ghost">📋 Daftar Prodi</a>
    </div>
</div>
<?php endif; ?>

<div class="form-layout-extreme">
    <div class="form-card-extreme">
        <div class="form-header-extreme">
            <h2>
                <?= $edit ? '✏️ Edit' : '➕ Tambah' ?> Program Studi
                <span class="status-badge <?= $edit ? 'edit-mode' : 'create-mode' ?>">
                    <?= $edit ? 'Update Mode' : 'Create Mode' ?>
                </span>
            </h2>
            <p>Lengkapi informasi program studi dengan detail dan akurat. Field bertanda <span style="color:#ef4444;font-weight:600;">*</span> wajib diisi.</p>
        </div>

        <?php foreach ($flash_messages as $type => $m): ?>
        <div class="inline-flash inline-flash-<?= $type ?>"><?= $m ?></div>
        <?php endforeach; ?>

        <?php if ($edit): ?>
        <div class="db-truth">
            🗄️ <strong>Isi database saat ini (id <?= (int)$edit['id'] ?>):</strong><br>
            visi="<?= htmlspecialchars(mb_substr($edit['visi'] ?? '-', 0, 40)) ?>" |
            misi="<?= htmlspecialchars(mb_substr($edit['misi'] ?? '-', 0, 40)) ?>" |
            kurikulum="<?= htmlspecialchars(mb_substr($edit['kurikulum'] ?? '-', 0, 40)) ?>" |
            updated_at=<?= htmlspecialchars($edit['updated_at'] ?? '-') ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['debug']) && file_exists($debug_log_file)): ?>
        <div class="db-truth">
            📜 <strong>logs/save-debug.log (15 baris terakhir):</strong><br>
            <?= nl2br(htmlspecialchars(implode('', array_slice(file($debug_log_file), -15)))) ?>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="programForm" action="<?= sanitize($_SERVER['REQUEST_URI']) ?>">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

            <div class="form-section">
                <div class="form-section-title">📋 Informasi Dasar</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon">🔑</span>Kode Prodi <span class="required">*</span></label>
                        <input type="text" name="kode" class="form-input" required placeholder="Contoh: PMAT" maxlength="10" value="<?= sanitize($edit['kode'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon">🎓</span>Jenjang <span class="required">*</span></label>
                        <select name="jenjang" class="form-select" required>
                            <option value="S1" <?= ($edit['jenjang'] ?? '') === 'S1' ? 'selected' : '' ?>>S1 - Strata 1</option>
                            <option value="S2" <?= ($edit['jenjang'] ?? '') === 'S2' ? 'selected' : '' ?>>S2 - Magister</option>
                            <option value="S3" <?= ($edit['jenjang'] ?? '') === 'S3' ? 'selected' : '' ?>>S3 - Doktor</option>
                            <option value="D3" <?= ($edit['jenjang'] ?? '') === 'D3' ? 'selected' : '' ?>>D3 - Diploma 3</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon">📚</span>Nama Program Studi (ID) <span class="required">*</span></label>
                        <input type="text" name="nama" class="form-input" required placeholder="Contoh: Pendidikan Matematika" maxlength="100" value="<?= sanitize($edit['nama'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon">🌐</span>Nama Program Studi (EN)</label>
                        <input type="text" name="nama_en" class="form-input" placeholder="Contoh: Mathematics Education" maxlength="100" value="<?= sanitize($edit['nama_en'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon">🏷️</span>Singkatan <span class="required">*</span></label>
                        <input type="text" name="singkatan" class="form-input" required placeholder="Contoh: Pend. Mat." maxlength="10" value="<?= sanitize($edit['singkatan'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon">🏆</span>Akreditasi</label>
                        <select name="akreditasi" class="form-select">
                            <option value="Unggul" <?= ($edit['akreditasi'] ?? '') === 'Unggul' ? 'selected' : '' ?>>⭐ Unggul</option>
                            <option value="Baik Sekali" <?= ($edit['akreditasi'] ?? '') === 'Baik Sekali' ? 'selected' : '' ?>>✨ Baik Sekali</option>
                            <option value="Baik" <?= ($edit['akreditasi'] ?? '') === 'Baik' ? 'selected' : '' ?>>✅ Baik</option>
                            <option value="Terakreditasi" <?= ($edit['akreditasi'] ?? '') === 'Terakreditasi' ? 'selected' : '' ?>>📜 Terakreditasi</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title">👨‍💼 Informasi Pimpinan</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon">👤</span>Ketua Program Studi</label>
                        <input type="text" name="ketua_prodi" class="form-input" placeholder="Nama lengkap dengan gelar" maxlength="100" value="<?= sanitize($edit['ketua_prodi'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon">🆔</span>NIDN Kaprodi</label>
                        <input type="text" name="nidn_kaprodi" class="form-input" placeholder="NIDN Ketua Program Studi" maxlength="30" value="<?= sanitize($edit['nidn_kaprodi'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title">📝 Deskripsi & Informasi Akademik</div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label"><span class="label-icon">📄</span>Deskripsi Singkat</label>
                    <textarea name="deskripsi" class="form-textarea" rows="3" placeholder="Deskripsi singkat program studi..."><?= sanitize($edit['deskripsi'] ?? '') ?></textarea>
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label"><span class="label-icon">🎯</span>Visi</label>
                    <textarea name="visi" class="form-textarea" rows="4" placeholder="Visi program studi..."><?= sanitize($edit['visi'] ?? '') ?></textarea>
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label"><span class="label-icon">🚀</span>Misi</label>
                    <textarea name="misi" class="form-textarea" rows="5" placeholder="Misi program studi (satu per baris)..."><?= sanitize($edit['misi'] ?? '') ?></textarea>
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label"><span class="label-icon">📖</span>Kurikulum</label>
                    <textarea name="kurikulum" class="form-textarea" rows="4" placeholder="Deskripsi kurikulum..."><?= sanitize($edit['kurikulum'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label"><span class="label-icon">💼</span>Prospek Kerja</label>
                    <textarea name="prospek_kerja" class="form-textarea" rows="4" placeholder="Prospek karir lulusan (satu per baris)..."><?= sanitize($edit['prospek_kerja'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title">📊 Statistik Program</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon">👨‍🏫</span>Jumlah Dosen</label>
                        <input type="number" name="jumlah_dosen" class="form-input" min="0" value="<?= (int)($edit['jumlah_dosen'] ?? 0) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon">👨🎓</span>Jumlah Mahasiswa</label>
                        <input type="number" name="jumlah_mahasiswa" class="form-input" min="0" value="<?= (int)($edit['jumlah_mahasiswa'] ?? 0) ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title">🖼️ Logo & Banner</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon">🎨</span>Logo Program Studi</label>
                        <div class="upload-zone">
                            <input type="file" name="logo" accept="image/*">
                            <div class="upload-icon">📤</div>
                            <div class="upload-text">Klik untuk pilih file logo</div>
                            <div class="upload-sub">PNG, JPG, WEBP, SVG (maks 2MB)</div>
                        </div>
                        <?php if (!empty($edit['logo'])): ?>
                        <div class="current-file">
                            <img src="<?= base_url('uploads/prodi_logo/' . basename($edit['logo'])) ?>" alt="Logo">
                            <div class="current-file-info">
                                <div class="file-name"><?= sanitize($edit['logo']) ?></div>
                                <div class="file-status">✓ Logo aktif</div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon">🖼️</span>Banner Program Studi</label>
                        <div class="upload-zone">
                            <input type="file" name="banner" accept="image/*">
                            <div class="upload-icon">🖼️</div>
                            <div class="upload-text">Klik untuk pilih file banner</div>
                            <div class="upload-sub">PNG, JPG, WEBP (maks 3MB)</div>
                        </div>
                        <?php if (!empty($edit['banner'])): ?>
                        <div class="current-file">
                            <img src="<?= base_url('uploads/prodi_banner/' . basename($edit['banner'])) ?>" alt="Banner">
                            <div class="current-file-info">
                                <div class="file-name"><?= sanitize($edit['banner']) ?></div>
                                <div class="file-status">✓ Banner aktif</div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title">⚙️ Pengaturan</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon">🔔</span>Status <span class="required">*</span></label>
                        <select name="status" class="form-select" required>
                            <option value="Aktif" <?= ($edit['status'] ?? 'Aktif') === 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
                            <option value="Non-Aktif" <?= ($edit['status'] ?? '') === 'Non-Aktif' ? 'selected' : '' ?>>❌ Non-Aktif</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon">🔢</span>Urutan Tampil</label>
                        <input type="number" name="urutan" class="form-input" min="0" value="<?= (int)($edit['urutan'] ?? 0) ?>">
                    </div>
                </div>
            </div>

            <button type="submit" class="submit-btn-extreme">
                <span><?= $edit ? '💾 Perbarui Data' : '✨ Simpan Program Studi' ?></span>
            </button>
            <div class="save-note">
                ℹ️ Halaman ini sengaja TANPA JavaScript agar klik Simpan tidak bisa dibatalkan oleh skrip apa pun.
                Jika sesuatu masih janggal, buka tombol <strong>📜 Log Debug</strong> di bar atas — isinya dirender langsung oleh server.
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>