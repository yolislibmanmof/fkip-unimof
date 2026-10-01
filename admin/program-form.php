<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$duplicate_from = (int)($_GET['duplicate'] ?? 0);
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
} elseif ($duplicate_from > 0) {
    $stmt = $pdo->prepare("SELECT * FROM program_studi WHERE id = ?");
    $stmt->execute([$duplicate_from]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($source) {
        $edit = $source;
        $edit['id'] = null;
        $edit['nama'] = $source['nama'] . ' (Copy)';
        $edit['kode'] = $source['kode'] . '2';
        $edit['logo'] = null;
        $edit['banner'] = null;
        flash_message('info', '📋 Menduplikasi program studi: ' . htmlspecialchars($source['nama']));
    }
}

// =====================================================
// DIAGNOSTIK SISI SERVER: catat SETIAP POST + hasil UPDATE
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
        ' | nama="' . mb_substr(trim($_POST['nama'] ?? ''), 0, 30) . '"' .
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

    // Validasi
    if (empty($kode) || empty($nama) || empty($singkatan)) {
        flash_message('error', '❌ Field wajib (Kode, Nama, Singkatan) harus diisi!');
    } else {
        // Validasi jenjang
        $valid_jenjang = ['S1', 'S2', 'S3', 'D3'];
        if (!in_array($jenjang, $valid_jenjang)) $jenjang = 'S1';

        // Validasi akreditasi
        $valid_akreditasi = ['Unggul', 'Baik Sekali', 'Baik', 'Terakreditasi'];
        if (!in_array($akreditasi, $valid_akreditasi)) $akreditasi = 'Terakreditasi';

        $logo = $edit['logo'] ?? null;
        $banner = $edit['banner'] ?? null;
        $upload_success = true;

        // Upload logo
        if (!empty($_FILES['logo']['name'])) {
            if ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
                $upload_success = false;
                flash_message('error', '❌ Ukuran logo terlalu besar. Maksimal 2MB.');
            } else {
                $upload_dir = __DIR__ . '/../uploads/prodi_logo/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'svg'])) {
                    $new_filename = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
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

        // Upload banner
        if (!empty($_FILES['banner']['name']) && $upload_success) {
            if ($_FILES['banner']['size'] > 3 * 1024 * 1024) {
                $upload_success = false;
                flash_message('error', '❌ Ukuran banner terlalu besar. Maksimal 3MB.');
            } else {
                $upload_dir = __DIR__ . '/../uploads/prodi_banner/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $ext = strtolower(pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $new_filename = 'banner_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
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
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Program Studi', 'program.php'], [$page_heading, null]];

require __DIR__ . '/includes/header.php';
?>

<style>
/* ===== FORM LAYOUT ===== */
.form-layout-extreme {
    display: grid;
    grid-template-columns: 1.3fr 1fr;
    gap: 2rem;
    align-items: start;
}

/* ===== PROGRESS INDICATOR ===== */
.progress-indicator {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: var(--shadow-sm);
}
.progress-bar-wrap {
    flex: 1;
    height: 8px;
    background: var(--bg-tertiary);
    border-radius: 999px;
    overflow: hidden;
}
.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #ef4444 0%, #f59e0b 50%, #6366f1 100%);
    transition: width 0.4s ease;
    border-radius: 999px;
}
.progress-stats {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.85rem;
    font-weight: 600;
}
.progress-percent {
    color: #6366f1;
    font-weight: 800;
    font-size: 1.1rem;
    font-variant-numeric: tabular-nums;
}
.progress-label { color: var(--text-muted); }

/* ===== SAVE INFO BAR ===== */
.save-info-bar {
    max-width: 1200px;
    margin: 0 auto 1.5rem;
    padding: 1rem 1.5rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: center;
    justify-content: space-between;
}
.save-info-bar .info-section { display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap; }
.save-info-bar .info-item { display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; color: var(--text-secondary); }
.save-info-bar .info-item strong { color: var(--text-primary); font-weight: 700; }
.save-info-bar .info-item .dot { width: 8px; height: 8px; border-radius: 50%; background: #6366f1; }
.save-info-bar .action-group { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.save-info-bar .quick-btn {
    padding: 0.45rem 0.85rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    transition: all 0.3s;
    border: 1px solid var(--border);
    cursor: pointer;
    font-family: inherit;
}
.save-info-bar .quick-btn.primary { background: #6366f1; color: white; border-color: #6366f1; }
.save-info-bar .quick-btn.primary:hover { background: #4338ca; transform: translateY(-2px); }
.save-info-bar .quick-btn.ghost { background: var(--bg-secondary); color: var(--text-primary); }
.save-info-bar .quick-btn.ghost:hover { background: var(--bg-tertiary); border-color: #6366f1; color: #6366f1; }

/* Inline Flash */
.inline-flash { padding: 0.85rem 1.25rem; border-radius: var(--radius-md); margin: 0 0 1.5rem; font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem; }
.inline-flash-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
.inline-flash-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

/* DB Truth Box */
.db-truth {
    font-family: 'Courier New', monospace;
    font-size: 0.78rem;
    line-height: 1.7;
    background: var(--bg-secondary);
    border: 1px dashed var(--border);
    border-radius: var(--radius-md);
    padding: 0.75rem 1rem;
    margin: 0 0 1.5rem;
    color: var(--text-secondary);
    word-break: break-all;
}

/* Form Card */
.form-card-extreme {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 2rem;
    box-shadow: var(--shadow-lg);
    position: relative;
    overflow: hidden;
}
.form-card-extreme::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, #4338ca, #6366f1, #8b5cf6);
}
.form-header-extreme {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    flex-wrap: wrap;
}
.form-header-extreme h2 {
    font-family: 'Georgia', serif;
    font-size: 1.75rem;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    letter-spacing: -0.01em;
}
.form-header-extreme p { color: var(--text-muted); font-size: 0.9rem; }
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.3rem 0.85rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}
.status-badge.edit-mode { background: rgba(59,130,246,0.1); color: #3b82f6; }
.status-badge.create-mode { background: rgba(99,102,241,0.1); color: #6366f1; }

/* ===== QUICK TEMPLATES ===== */
.template-section {
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
}
.template-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text-muted);
    margin-bottom: 0.65rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.template-buttons { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.template-btn {
    padding: 0.5rem 0.85rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
    color: var(--text-secondary);
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.template-btn:hover {
    background: linear-gradient(135deg, #6366f1, #4338ca);
    color: white;
    border-color: #6366f1;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(99,102,241,0.25);
}

.form-section {
    background: var(--bg-secondary);
    padding: 1.5rem;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
    margin-bottom: 1.5rem;
    transition: border-color 0.2s;
}
.form-section:focus-within { border-color: #6366f1; }
.form-section-title {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-family: 'Georgia', serif;
}
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}
.form-row:last-child { margin-bottom: 0; }
.form-group { margin-bottom: 0; position: relative; }
.form-label {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}
.form-label .required { color: #ef4444; margin-left: 0.15rem; font-weight: 800; }
.form-label .label-icon {
    width: 18px; height: 18px;
    background: rgba(99,102,241,0.1);
    color: #6366f1;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
}

.form-input, .form-select, .form-textarea {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    font-family: inherit;
    font-size: 0.95rem;
    background: var(--bg-primary);
    color: var(--text-primary);
    transition: all 0.3s;
}
.form-textarea { resize: vertical; min-height: 100px; line-height: 1.6; }
.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99,102,241,0.1);
}
.form-input.error, .form-select.error {
    border-color: #ef4444;
    box-shadow: 0 0 0 4px rgba(239,68,68,0.1);
    animation: shake 0.4s;
}
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}
.form-hint {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.35rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

/* ===== JENJANG & AKREDITASI SELECTOR ===== */
.jenjang-selector {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.5rem;
    margin-top: 0.5rem;
}
.jenjang-option {
    padding: 0.75rem 0.5rem;
    background: var(--bg-primary);
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.82rem;
    font-weight: 700;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}
.jenjang-option:hover {
    border-color: #6366f1;
    transform: translateY(-2px);
}
.jenjang-option.selected {
    background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
    border-color: #6366f1;
    color: #4338ca;
}
.jenjang-option .jenjang-icon {
    font-size: 1.5rem;
    line-height: 1;
}
.jenjang-option small {
    font-size: 0.68rem;
    color: var(--text-muted);
    font-weight: 500;
}
.jenjang-option.selected small { color: #4338ca; }
.jenjang-option input[type="radio"] { display: none; }

.akreditasi-selector {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.5rem;
    margin-top: 0.5rem;
}
.akreditasi-option {
    padding: 0.65rem 0.5rem;
    background: var(--bg-primary);
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.78rem;
    font-weight: 700;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}
.akreditasi-option:hover {
    border-color: #6366f1;
    transform: translateY(-2px);
}
.akreditasi-option.selected {
    border-color: #6366f1;
    color: white;
}
.akreditasi-option.selected[data-value="Unggul"] { background: linear-gradient(135deg, #fbbf24, #f59e0b); border-color: #f59e0b; color: white; }
.akreditasi-option.selected[data-value="Baik Sekali"] { background: linear-gradient(135deg, #94a3b8, #64748b); border-color: #64748b; color: white; }
.akreditasi-option.selected[data-value="Baik"] { background: linear-gradient(135deg, #cd7f32, #b87333); border-color: #b87333; color: white; }
.akreditasi-option.selected[data-value="Terakreditasi"] { background: linear-gradient(135deg, #6366f1, #4338ca); border-color: #4338ca; color: white; }
.akreditasi-option .akreditasi-icon {
    font-size: 1.5rem;
    line-height: 1;
}
.akreditasi-option input[type="radio"] { display: none; }

/* ===== SMART BUILDER (Visi/Misi) ===== */
.smart-builder {
    margin-top: 0.75rem;
    padding: 0.85rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
}
.smart-builder-header {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--text-muted);
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.smart-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}
.smart-chip {
    padding: 0.35rem 0.7rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    color: var(--text-secondary);
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.smart-chip:hover {
    background: #e0e7ff;
    border-color: #c7d2fe;
    color: #4338ca;
    transform: translateY(-1px);
}

/* ===== UPLOAD ZONE ===== */
.upload-zone {
    border: 2px dashed var(--border);
    border-radius: var(--radius-lg);
    padding: 2rem 1rem;
    text-align: center;
    background: var(--bg-primary);
    transition: all 0.3s;
    cursor: pointer;
    position: relative;
    min-height: 140px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.upload-zone:hover, .upload-zone.dragover {
    border-color: #6366f1;
    background: rgba(99,102,241,0.03);
}
.upload-zone.dragover {
    border-style: solid;
    box-shadow: 0 0 0 4px rgba(99,102,241,0.1);
}
.upload-zone-icon {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
    animation: float 3s ease-in-out infinite;
}
@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}
.upload-zone-text { font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem; font-size: 0.9rem; }
.upload-zone-sub { font-size: 0.78rem; color: var(--text-muted); }
.upload-zone-info {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    margin-top: 0.5rem;
    font-size: 0.68rem;
    color: var(--text-muted);
    flex-wrap: wrap;
}
.upload-zone-info span {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0.15rem 0.5rem;
    background: var(--bg-secondary);
    border-radius: 999px;
}
.upload-zone input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
}

.image-preview {
    display: none;
    margin-top: 0.75rem;
    padding: 0.85rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    align-items: center;
    gap: 0.75rem;
    animation: slideInUp 0.3s;
}
@keyframes slideInUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.image-preview.show { display: flex; }
.image-preview-img {
    width: 70px;
    height: 70px;
    border-radius: 10px;
    background: var(--bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 1px solid var(--border);
    flex-shrink: 0;
}
.image-preview-img img { width: 100%; height: 100%; object-fit: contain; background: white; }
.image-preview-info { flex: 1; min-width: 0; }
.image-preview-info h4 {
    font-size: 0.85rem;
    font-weight: 700;
    margin-bottom: 0.15rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.image-preview-info small { font-size: 0.72rem; color: var(--text-muted); display: block; }
.image-preview-remove {
    background: #fee2e2;
    color: #dc2626;
    border: none;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9rem;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.image-preview-remove:hover {
    background: #dc2626;
    color: white;
    transform: rotate(90deg);
}

.current-image-box {
    margin-top: 0.75rem;
    padding: 0.75rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border: 1px solid var(--border);
}
.current-image-box img {
    width: 50px;
    height: 50px;
    object-fit: contain;
    border-radius: 6px;
    background: white;
    padding: 0.25rem;
    flex-shrink: 0;
}
.file-status {
    display: inline-flex;
    font-size: 0.7rem;
    padding: 0.15rem 0.5rem;
    background: rgba(99,102,241,0.1);
    color: #6366f1;
    border-radius: 999px;
    font-weight: 600;
    margin-top: 0.25rem;
}

/* ===== STATS INPUT ===== */
.stats-input-wrap {
    position: relative;
}
.stats-badge {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    background: linear-gradient(135deg, #6366f1, #4338ca);
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 800;
    pointer-events: none;
}
.ratio-visual {
    margin-top: 0.75rem;
    padding: 0.75rem;
    background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
    border: 1px solid #c7d2fe;
    border-radius: var(--radius-md);
    display: none;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.85rem;
    color: #4338ca;
    font-weight: 600;
}
[data-theme="dark"] .ratio-visual {
    background: linear-gradient(135deg, #4338ca22, #6366f133);
    border-color: #6366f1;
    color: #c7d2fe;
}
.ratio-visual.show { display: flex; }
.ratio-visual-icon { font-size: 1.5rem; }
.ratio-visual strong {
    font-family: 'Georgia', serif;
    font-size: 1.1rem;
    color: #4338ca;
}

/* ===== SUBMIT BUTTON ===== */
.submit-btn-extreme {
    width: 100%;
    padding: 1.1rem;
    background: linear-gradient(135deg, #6366f1, #4338ca);
    color: white;
    border: none;
    border-radius: var(--radius-md);
    font-family: inherit;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 1rem;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}
.submit-btn-extreme:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(99,102,241,0.35);
}
.submit-btn-extreme:disabled { opacity: 0.7; cursor: not-allowed; }
.submit-btn-extreme .spinner {
    width: 20px;
    height: 20px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
    display: none;
}
.submit-btn-extreme.loading .spinner { display: inline-block; }
.submit-btn-extreme.loading .btn-text { display: none; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Secondary Actions */
.secondary-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 1rem;
    flex-wrap: wrap;
}
.secondary-btn {
    flex: 1;
    padding: 0.75rem;
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    font-family: inherit;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    text-decoration: none;
    min-width: 120px;
}
.secondary-btn:hover {
    background: var(--bg-tertiary);
    border-color: #6366f1;
    color: #6366f1;
    transform: translateY(-1px);
}

/* Shortcuts Legend */
.shortcuts-legend {
    text-align: center;
    margin-top: 1rem;
    font-size: 0.78rem;
    color: var(--text-muted);
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}
.shortcuts-legend kbd {
    background: var(--bg-secondary);
    padding: 0.15rem 0.4rem;
    border-radius: 4px;
    font-family: monospace;
    font-size: 0.72rem;
    border: 1px solid var(--border);
    margin: 0 0.15rem;
}

/* ===== PREVIEW CARD ===== */
.preview-card-extreme {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 1.5rem;
    box-shadow: var(--shadow-lg);
    position: sticky;
    top: 100px;
    max-height: calc(100vh - 120px);
    overflow-y: auto;
}
.preview-header {
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.preview-header h3 {
    font-family: 'Georgia', serif;
    font-size: 1.15rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.preview-actions { display: flex; gap: 0.4rem; }
.preview-action-btn {
    width: 32px; height: 32px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--bg-secondary);
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    text-decoration: none;
    color: var(--text-secondary);
}
.preview-action-btn:hover {
    background: #6366f1;
    color: white;
    border-color: #6366f1;
    transform: translateY(-2px);
}

/* Prodi Card Preview */
.prodi-card-preview {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-bottom: 1rem;
    position: relative;
}
.prodi-card-preview::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: var(--card-accent, #6366f1);
    z-index: 2;
}

.preview-banner-box {
    width: 100%;
    height: 120px;
    background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    overflow: hidden;
    position: relative;
}
.preview-banner-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.preview-jenjang-badge {
    position: absolute;
    top: 0.75rem;
    left: 0.75rem;
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(8px);
    padding: 0.3rem 0.75rem;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #4338ca;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 0.25rem;
    z-index: 2;
}
.preview-akreditasi-badge {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    padding: 0.3rem 0.75rem;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 700;
    z-index: 2;
    background: white;
    border: 1px solid #c7d2fe;
}

.preview-body {
    padding: 1rem;
}
.preview-title {
    font-family: 'Georgia', serif;
    font-size: 1.1rem;
    font-weight: 800;
    margin-bottom: 0.25rem;
    line-height: 1.3;
    letter-spacing: -0.01em;
}
.preview-code {
    font-family: monospace;
    font-size: 0.72rem;
    font-weight: 700;
    color: #6366f1;
    margin-bottom: 0.75rem;
}
.preview-meta {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    margin-bottom: 0.75rem;
}
.preview-meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.78rem;
    color: var(--text-secondary);
}
.preview-meta-item .meta-icon {
    width: 22px;
    height: 22px;
    background: var(--bg-primary);
    border-radius: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    flex-shrink: 0;
}
.preview-meta-item strong {
    color: var(--text-primary);
    font-weight: 600;
    flex: 1;
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.preview-desc {
    background: var(--bg-primary);
    padding: 0.75rem;
    border-radius: var(--radius-md);
    font-size: 0.78rem;
    line-height: 1.5;
    color: var(--text-secondary);
    min-height: 50px;
    border-left: 3px solid #6366f1;
    font-style: italic;
}
.preview-desc::before {
    content: '"';
    font-size: 1.25rem;
    color: #6366f1;
    line-height: 0.5;
    display: block;
    margin-bottom: 0.35rem;
    opacity: 0.5;
}
.preview-empty { color: var(--text-muted); font-style: italic; }

/* Visi Preview Section */
.visi-preview-section {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}
.visi-preview-label {
    font-size: 0.72rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.visi-preview-box {
    background: var(--bg-secondary);
    padding: 0.75rem;
    border-radius: var(--radius-md);
    font-size: 0.78rem;
    line-height: 1.5;
    color: var(--text-secondary);
    margin-bottom: 0.75rem;
    border-left: 3px solid #f59e0b;
    min-height: 40px;
}
.misi-preview-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.misi-preview-list li {
    background: var(--bg-secondary);
    padding: 0.5rem 0.75rem;
    margin-bottom: 0.35rem;
    border-radius: var(--radius-md);
    font-size: 0.75rem;
    color: var(--text-secondary);
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    line-height: 1.5;
}
.misi-preview-list li::before {
    content: '✓';
    color: #10b981;
    font-weight: 800;
    flex-shrink: 0;
}

/* Stats preview */
.preview-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.4rem;
    margin-top: 0.75rem;
    padding: 0.75rem;
    background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
    border: 1px solid #c7d2fe;
    border-radius: var(--radius-md);
}
[data-theme="dark"] .preview-stats {
    background: linear-gradient(135deg, #4338ca22, #6366f133);
    border-color: #6366f1;
}
.preview-stat-item {
    text-align: center;
    padding: 0.4rem;
    background: white;
    border-radius: 6px;
    border: 1px solid #c7d2fe;
}
[data-theme="dark"] .preview-stat-item {
    background: rgba(67,56,202,0.3);
    border-color: #6366f1;
}
.preview-stat-value {
    font-size: 1rem;
    font-weight: 800;
    color: #4338ca;
    line-height: 1;
    margin-bottom: 0.15rem;
    font-family: 'Georgia', serif;
}
.preview-stat-label {
    font-size: 0.6rem;
    color: #4338ca;
    text-transform: uppercase;
    font-weight: 600;
}

/* QR Preview */
.share-preview {
    margin-top: 0.75rem;
    padding: 0.75rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    text-align: center;
}
.share-preview-label {
    font-size: 0.68rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
    margin-bottom: 0.35rem;
}
.share-preview-qr {
    display: inline-block;
    padding: 0.4rem;
    background: white;
    border-radius: 6px;
}
.share-preview-qr img { width: 80px; height: 80px; }

/* ===== AUTOSAVE ===== */
.autosave-indicator {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 999px;
    padding: 0.65rem 1.15rem;
    box-shadow: var(--shadow-lg);
    display: flex;
    align-items: center;
    gap: 0.65rem;
    font-size: 0.82rem;
    font-weight: 600;
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.3s;
    z-index: 100;
}
.autosave-indicator.show { opacity: 1; transform: translateY(0); }
.autosave-indicator.saving { background: #fef3c7; border-color: #fcd34d; color: #92400e; }
.autosave-indicator.saved { background: #dcfce7; border-color: #86efac; color: #166534; }

/* ===== RESPONSIVE ===== */
@media (max-width: 968px) {
    .form-layout-extreme { grid-template-columns: 1fr; }
    .preview-card-extreme { position: static; order: -1; max-height: none; }
    .form-row { grid-template-columns: 1fr; }
    .jenjang-selector, .akreditasi-selector { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
    .form-card-extreme, .preview-card-extreme { padding: 1.25rem; }
    .template-buttons { flex-direction: column; }
    .shortcuts-legend { font-size: 0.7rem; }
    .preview-stats { grid-template-columns: 1fr; }
    .secondary-actions { flex-direction: column; }
    .save-info-bar { flex-direction: column; align-items: stretch; }
}
</style>

<!-- Progress Indicator -->
<div class="progress-indicator" data-aos="fade-down">
    <span style="font-size: 1.25rem;">🎓</span>
    <div class="progress-bar-wrap">
        <div class="progress-bar-fill" id="progressBar" style="width: 0%"></div>
    </div>
    <div class="progress-stats">
        <span class="progress-percent" id="progressPercent">0%</span>
        <span class="progress-label">Kelengkapan Data</span>
    </div>
</div>

<?php if ($edit): ?>
<div class="save-info-bar">
    <div class="info-section">
        <div class="info-item"><span class="dot"></span><span>ID:</span><strong><?= (int)$edit['id'] ?></strong></div>
        <div class="info-item"><span class="dot"></span><span>Dibuat:</span><strong><?= date('d M Y', strtotime($edit['created_at'] ?? 'now')) ?></strong></div>
        <div class="info-item"><span class="dot"></span><span>Update:</span><strong><?= date('d M Y H:i', strtotime($edit['updated_at'] ?? 'now')) ?></strong></div>
    </div>
    <div class="action-group">
        <button type="submit" form="programForm" class="quick-btn primary">💾 Simpan</button>
        <?php if ($edit): ?>
        <a href="<?= base_url('program-detail.php?id=' . (int)$edit['id']) ?>" target="_blank" class="quick-btn primary">👁️ Publik</a>
        <?php endif; ?>
        <a href="program-form.php?id=<?= (int)$edit['id'] ?>&debug=1" class="quick-btn ghost">📜 Log</a>
        <a href="program.php" class="quick-btn ghost">📋 Daftar</a>
    </div>
</div>
<?php endif; ?>

<?php foreach ($flash_messages as $type => $m): ?>
<div class="inline-flash inline-flash-<?= $type ?>"><?= $m ?></div>
<?php endforeach; ?>

<?php if ($edit && isset($_GET['debug'])): ?>
<div class="db-truth">
    🗄️ <strong>Database Truth (id <?= (int)$edit['id'] ?>):</strong><br>
    visi="<?= htmlspecialchars(mb_substr($edit['visi'] ?? '-', 0, 50)) ?>" |
    misi="<?= htmlspecialchars(mb_substr($edit['misi'] ?? '-', 0, 50)) ?>" |
    kurikulum="<?= htmlspecialchars(mb_substr($edit['kurikulum'] ?? '-', 0, 50)) ?>" |
    updated_at=<?= htmlspecialchars($edit['updated_at'] ?? '-') ?>
</div>
<?php endif; ?>

<?php if (isset($_GET['debug']) && file_exists($debug_log_file)): ?>
<div class="db-truth">
    📜 <strong>logs/save-debug.log (15 baris terakhir):</strong><br>
    <?= nl2br(htmlspecialchars(implode('', array_slice(file($debug_log_file), -15)))) ?>
</div>
<?php endif; ?>

<div class="form-layout-extreme">
    <!-- LEFT: FORM -->
    <div class="form-card-extreme" data-aos="fade-right">
        <div class="form-header-extreme">
            <div>
                <h2><?= $edit ? '✏️ Edit' : '➕ Tambah' ?> Program Studi</h2>
                <p><?= $edit ? 'Perbarui informasi program studi dengan lengkap.' : 'Lengkapi informasi program studi baru.' ?></p>
            </div>
            <span class="status-badge <?= $edit ? 'edit-mode' : 'create-mode' ?>">
                <?= $edit ? '✏️ Update Mode' : '✨ Create Mode' ?>
            </span>
        </div>

        <!-- Quick Templates -->
        <div class="template-section" data-aos="fade-up">
            <div class="template-label">⚡ Template Cepat (Klik untuk auto-fill)</div>
            <div class="template-buttons">
                <button type="button" class="template-btn" onclick="applyTemplate('pmat')">🧮 Pend. Matematika</button>
                <button type="button" class="template-btn" onclick="applyTemplate('pbio')">🧬 Pend. Biologi</button>
                <button type="button" class="template-btn" onclick="applyTemplate('pfis')">⚛️ Pend. Fisika</button>
                <button type="button" class="template-btn" onclick="applyTemplate('pkim')">🧪 Pend. Kimia</button>
                <button type="button" class="template-btn" onclick="applyTemplate('pbsi')">🇬🇧 Pend. B. Inggris</button>
                <button type="button" class="template-btn" onclick="applyTemplate('bind')">📚 B. Indonesia</button>
                <button type="button" class="template-btn" onclick="applyTemplate('peko')">💰 Pend. Ekonomi</button>
                <button type="button" class="template-btn" onclick="applyTemplate('pkn')">🏛️ Pend. PKn</button>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="programForm" action="<?= sanitize($_SERVER['REQUEST_URI']) ?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

            <!-- Section 1: Informasi Dasar -->
            <div class="form-section">
                <div class="form-section-title">📋 Informasi Dasar</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🔑</span>
                            Kode Prodi <span class="required">*</span>
                        </label>
                        <input type="text" id="kode" name="kode" class="form-input" required
                               placeholder="Contoh: PMAT" maxlength="10"
                               value="<?= sanitize($edit['kode'] ?? '') ?>">
                        <div class="form-hint">Kode unik program studi (maks 10 karakter)</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🏷️</span>
                            Singkatan <span class="required">*</span>
                        </label>
                        <input type="text" id="singkatan" name="singkatan" class="form-input" required
                               placeholder="Contoh: Pend. Mat." maxlength="15"
                               value="<?= sanitize($edit['singkatan'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🎓</span>
                            Nama Program Studi (ID) <span class="required">*</span>
                        </label>
                        <input type="text" id="nama" name="nama" class="form-input" required
                               placeholder="Contoh: Pendidikan Matematika" maxlength="100"
                               value="<?= sanitize($edit['nama'] ?? '') ?>">
                        <div class="form-hint" style="display: flex; justify-content: space-between;">
                            <span>Nama dalam Bahasa Indonesia</span>
                            <span><span id="namaCounter"><?= strlen($edit['nama'] ?? '') ?></span>/100</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🌐</span>
                            Nama (English)
                        </label>
                        <input type="text" id="nama_en" name="nama_en" class="form-input"
                               placeholder="Contoh: Mathematics Education" maxlength="100"
                               value="<?= sanitize($edit['nama_en'] ?? '') ?>">
                    </div>
                </div>

                <!-- Jenjang Selector -->
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">
                        <span class="label-icon">🎓</span>
                        Jenjang <span class="required">*</span>
                    </label>
                    <div class="jenjang-selector" id="jenjangSelector">
                        <?php
                        $jenjang_list = [
                            'S1' => ['icon' => '🎓', 'label' => 'Strata 1', 'desc' => 'Sarjana (4 thn)'],
                            'S2' => ['icon' => '🎓', 'label' => 'Strata 2', 'desc' => 'Magister (2 thn)'],
                            'S3' => ['icon' => '🎓', 'label' => 'Strata 3', 'desc' => 'Doktor (3-4 thn)'],
                            'D3' => ['icon' => '🎓', 'label' => 'Diploma 3', 'desc' => 'Diploma (3 thn)']
                        ];
                        foreach ($jenjang_list as $j => $info):
                            $selected = ($edit['jenjang'] ?? 'S1') === $j;
                        ?>
                        <label class="jenjang-option <?= $selected ? 'selected' : '' ?>" data-value="<?= $j ?>">
                            <input type="radio" name="jenjang" value="<?= $j ?>" <?= $selected ? 'checked' : '' ?>>
                            <span class="jenjang-icon"><?= $info['icon'] ?></span>
                            <span><?= $info['label'] ?></span>
                            <small><?= $info['desc'] ?></small>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Akreditasi Selector -->
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">🏆</span>
                        Akreditasi
                    </label>
                    <div class="akreditasi-selector" id="akreditasiSelector">
                        <?php
                        $akreditasi_list = [
                            'Unggul' => ['icon' => '⭐'],
                            'Baik Sekali' => ['icon' => '✨'],
                            'Baik' => ['icon' => '✅'],
                            'Terakreditasi' => ['icon' => '📜']
                        ];
                        foreach ($akreditasi_list as $a => $info):
                            $selected = ($edit['akreditasi'] ?? 'Terakreditasi') === $a;
                        ?>
                        <label class="akreditasi-option <?= $selected ? 'selected' : '' ?>" data-value="<?= $a ?>">
                            <input type="radio" name="akreditasi" value="<?= $a ?>" <?= $selected ? 'checked' : '' ?>>
                            <span class="akreditasi-icon"><?= $info['icon'] ?></span>
                            <span><?= $a ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Section 2: Informasi Pimpinan -->
            <div class="form-section">
                <div class="form-section-title">👨‍💼 Informasi Pimpinan</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">👤</span>
                            Ketua Program Studi
                        </label>
                        <input type="text" id="ketua_prodi" name="ketua_prodi" class="form-input"
                               placeholder="Nama lengkap dengan gelar" maxlength="100"
                               value="<?= sanitize($edit['ketua_prodi'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🆔</span>
                            NIDN Kaprodi
                        </label>
                        <input type="text" id="nidn_kaprodi" name="nidn_kaprodi" class="form-input"
                               placeholder="NIDN Ketua Program Studi" maxlength="30"
                               value="<?= sanitize($edit['nidn_kaprodi'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Section 3: Deskripsi & Informasi Akademik -->
            <div class="form-section">
                <div class="form-section-title">📝 Deskripsi & Informasi Akademik</div>
                
                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label">
                        <span class="label-icon">📄</span>
                        Deskripsi Singkat
                    </label>
                    <textarea id="deskripsi" name="deskripsi" class="form-textarea" rows="3"
                              placeholder="Deskripsi singkat program studi..."
                              maxlength="1000"><?= sanitize($edit['deskripsi'] ?? '') ?></textarea>
                    <div class="form-hint" style="display: flex; justify-content: space-between;">
                        <span>Deskripsi untuk halaman publik</span>
                        <span><span id="deskripsiWords">0</span> kata • <span id="deskripsiCounter"><?= strlen($edit['deskripsi'] ?? '') ?></span>/1000</span>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label">
                        <span class="label-icon">🎯</span>
                        Visi
                    </label>
                    <textarea id="visi" name="visi" class="form-textarea" rows="3"
                              placeholder="Visi program studi..."
                              maxlength="2000"><?= sanitize($edit['visi'] ?? '') ?></textarea>
                    <div class="smart-builder">
                        <div class="smart-builder-header">💡 Frase Visi Umum:</div>
                        <div class="smart-chips">
                            <span class="smart-chip" onclick="addPhrase('visi', 'Menjadi program studi unggul dan berdaya saing global pada tahun 2030.')">+ Unggul 2030</span>
                            <span class="smart-chip" onclick="addPhrase('visi', 'Menjadi pusat pengembangan ilmu pendidikan yang inovatif dan berkarakter.')">+ Inovatif</span>
                            <span class="smart-chip" onclick="addPhrase('visi', 'Menghasilkan lulusan yang kompeten, berkarakter, dan siap berkontribusi bagi masyarakat.')">+ Lulusan Kompeten</span>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label">
                        <span class="label-icon">🚀</span>
                        Misi
                    </label>
                    <textarea id="misi" name="misi" class="form-textarea" rows="5"
                              placeholder="Misi program studi (satu per baris)..."
                              maxlength="3000"><?= sanitize($edit['misi'] ?? '') ?></textarea>
                    <div class="form-hint" style="display: flex; justify-content: space-between;">
                        <span>Satu misi per baris</span>
                        <span><span id="misiLines">0</span> poin • <span id="misiCounter"><?= strlen($edit['misi'] ?? '') ?></span>/3000</span>
                    </div>
                    <div class="smart-builder">
                        <div class="smart-builder-header">💡 Template Misi:</div>
                        <div class="smart-chips">
                            <span class="smart-chip" onclick="addMisi('Menyelenggarakan pendidikan dan pengajaran berkualitas tinggi.')">+ Pendidikan</span>
                            <span class="smart-chip" onclick="addMisi('Melaksanakan penelitian yang bermanfaat bagi pengembangan ilmu pengetahuan.')">+ Penelitian</span>
                            <span class="smart-chip" onclick="addMisi('Melaksanakan pengabdian kepada masyarakat berbasis hasil penelitian.')">+ Pengabdian</span>
                            <span class="smart-chip" onclick="addMisi('Menjalin kerjasama dengan berbagai instansi dalam dan luar negeri.')">+ Kerjasama</span>
                            <span class="smart-chip" onclick="addMisi('Mengembangkan karakter mahasiswa yang berakhlak mulia dan profesional.')">+ Karakter</span>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label">
                        <span class="label-icon">📖</span>
                        Kurikulum
                    </label>
                    <textarea id="kurikulum" name="kurikulum" class="form-textarea" rows="3"
                              placeholder="Deskripsi kurikulum yang diterapkan..."
                              maxlength="2000"><?= sanitize($edit['kurikulum'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">💼</span>
                        Prospek Kerja
                    </label>
                    <textarea id="prospek_kerja" name="prospek_kerja" class="form-textarea" rows="4"
                              placeholder="Prospek karir lulusan (satu per baris)..."
                              maxlength="2000"><?= sanitize($edit['prospek_kerja'] ?? '') ?></textarea>
                    <div class="smart-builder">
                        <div class="smart-builder-header">💡 Prospek Kerja Umum:</div>
                        <div class="smart-chips">
                            <span class="smart-chip" onclick="addProspek('Guru di sekolah menengah (SMP/SMA/SMK)')">+ Guru</span>
                            <span class="smart-chip" onclick="addProspek('Dosen di perguruan tinggi')">+ Dosen</span>
                            <span class="smart-chip" onclick="addProspek('Peneliti di lembaga penelitian')">+ Peneliti</span>
                            <span class="smart-chip" onclick="addProspek('Konsultan pendidikan')">+ Konsultan</span>
                            <span class="smart-chip" onclick="addProspek('Pengembang kurikulum')">+ Kurikulum</span>
                            <span class="smart-chip" onclick="addProspek('Wirausaha di bidang pendidikan')">+ Wirausaha</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 4: Statistik Program -->
            <div class="form-section">
                <div class="form-section-title">📊 Statistik Program</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">👨‍🏫</span>
                            Jumlah Dosen
                        </label>
                        <div class="stats-input-wrap">
                            <input type="number" id="jumlah_dosen" name="jumlah_dosen" class="form-input"
                                   min="0" max="500"
                                   value="<?= (int)($edit['jumlah_dosen'] ?? 0) ?>">
                            <span class="stats-badge" id="dosenBadge">0</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">👨‍🎓</span>
                            Jumlah Mahasiswa
                        </label>
                        <div class="stats-input-wrap">
                            <input type="number" id="jumlah_mahasiswa" name="jumlah_mahasiswa" class="form-input"
                                   min="0" max="10000"
                                   value="<?= (int)($edit['jumlah_mahasiswa'] ?? 0) ?>">
                            <span class="stats-badge" id="mahasiswaBadge">0</span>
                        </div>
                    </div>
                </div>
                <div class="ratio-visual" id="ratioVisual">
                    <span class="ratio-visual-icon">📊</span>
                    <div>
                        <strong id="ratioValue">0:1</strong>
                        <div style="font-size: 0.75rem; opacity: 0.8;" id="ratioStatus">Rasio mahasiswa:dosen</div>
                    </div>
                </div>
            </div>

            <!-- Section 5: Logo & Banner -->
            <div class="form-section">
                <div class="form-section-title">🖼️ Logo & Banner</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🎨</span>
                            Logo Program Studi
                        </label>
                        <div class="upload-zone" id="uploadLogo">
                            <input type="file" id="logo" name="logo" accept="image/*">
                            <div class="upload-zone-icon">📤</div>
                            <div class="upload-zone-text">Pilih file logo</div>
                            <div class="upload-zone-info">
                                <span>🖼️ PNG/JPG/SVG</span>
                                <span>📦 Max 2MB</span>
                            </div>
                        </div>
                        <div class="image-preview" id="logoPreview">
                            <div class="image-preview-img" id="logoPreviewImg"><span>🎨</span></div>
                            <div class="image-preview-info">
                                <h4 id="logoName">-</h4>
                                <small id="logoSize">-</small>
                            </div>
                            <button type="button" class="image-preview-remove" onclick="removeLogo()" title="Hapus">✕</button>
                        </div>
                        <?php if (!empty($edit['logo'])): ?>
                        <div class="current-image-box">
                            <img src="<?= base_url('uploads/prodi_logo/' . basename($edit['logo'])) ?>" alt="Logo">
                            <div style="flex: 1; min-width: 0;">
                                <strong><?= sanitize($edit['logo']) ?></strong>
                                <div class="file-status">✓ Logo aktif</div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🖼️</span>
                            Banner Program Studi
                        </label>
                        <div class="upload-zone" id="uploadBanner">
                            <input type="file" id="banner" name="banner" accept="image/*">
                            <div class="upload-zone-icon">🖼️</div>
                            <div class="upload-zone-text">Pilih file banner</div>
                            <div class="upload-zone-info">
                                <span>🖼️ PNG/JPG</span>
                                <span>📦 Max 3MB</span>
                            </div>
                        </div>
                        <div class="image-preview" id="bannerPreview">
                            <div class="image-preview-img" id="bannerPreviewImg"><span>🖼️</span></div>
                            <div class="image-preview-info">
                                <h4 id="bannerName">-</h4>
                                <small id="bannerSize">-</small>
                            </div>
                            <button type="button" class="image-preview-remove" onclick="removeBanner()" title="Hapus">✕</button>
                        </div>
                        <?php if (!empty($edit['banner'])): ?>
                        <div class="current-image-box">
                            <img src="<?= base_url('uploads/prodi_banner/' . basename($edit['banner'])) ?>" alt="Banner" style="object-fit: cover;">
                            <div style="flex: 1; min-width: 0;">
                                <strong><?= sanitize($edit['banner']) ?></strong>
                                <div class="file-status">✓ Banner aktif</div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Section 6: Pengaturan -->
            <div class="form-section">
                <div class="form-section-title">⚙️ Pengaturan</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🔔</span>
                            Status <span class="required">*</span>
                        </label>
                        <select id="status" name="status" class="form-select" required>
                            <option value="Aktif" <?= ($edit['status'] ?? 'Aktif') === 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
                            <option value="Non-Aktif" <?= ($edit['status'] ?? '') === 'Non-Aktif' ? 'selected' : '' ?>>❌ Non-Aktif</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🔢</span>
                            Urutan Tampil
                        </label>
                        <input type="number" id="urutan" name="urutan" class="form-input"
                               min="0" max="999"
                               value="<?= (int)($edit['urutan'] ?? 0) ?>">
                        <div class="form-hint">Urutan tampilan di halaman publik</div>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="submit-btn-extreme" id="submitBtn">
                <span class="btn-text"><?= $edit ? '💾 Perbarui Data' : '✨ Simpan Program Studi' ?></span>
                <span class="spinner"></span>
            </button>

            <!-- Secondary Actions -->
            <div class="secondary-actions">
                <?php if ($edit && $id > 0): ?>
                <a href="?duplicate=<?= $id ?>" class="secondary-btn" onclick="return confirm('Duplikasi data ini?')">
                    📋 Duplikasi
                </a>
                <?php endif; ?>
                <a href="program.php" class="secondary-btn">← Kembali</a>
                <button type="reset" class="secondary-btn" onclick="return confirm('Reset semua field?')">🔄 Reset</button>
            </div>

            <!-- Shortcuts Legend -->
            <div class="shortcuts-legend">
                <span>💡 Shortcuts:</span>
                <span><kbd>Ctrl</kbd>+<kbd>S</kbd> Simpan</span>
                <span><kbd>Ctrl</kbd>+<kbd>K</kbd> Template</span>
                <span><kbd>Esc</kbd> Batal</span>
            </div>
        </form>
    </div>

    <!-- RIGHT: LIVE PREVIEW -->
    <div class="preview-card-extreme" data-aos="fade-left">
        <div class="preview-header">
            <div>
                <h3>👁️ Live Preview</h3>
                <p style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.15rem;">Tampilan publik</p>
            </div>
            <div class="preview-actions">
                <button class="preview-action-btn" onclick="togglePreviewMode()" title="Toggle mode">🔄</button>
                <button class="preview-action-btn" onclick="sharePreview()" title="Share">🔗</button>
                <?php if ($edit && $id > 0): ?>
                <a href="<?= base_url('program-detail.php?id=' . (int)$edit['id']) ?>" class="preview-action-btn" target="_blank" title="Lihat publik">🌐</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Prodi Card Preview -->
        <div class="prodi-card-preview" id="previewCard">
            <div class="preview-banner-box" id="previewBannerBox">
                <span id="previewBannerIcon">🎓</span>
                <span class="preview-jenjang-badge" id="previewJenjangBadge">🎓 S1</span>
                <span class="preview-akreditasi-badge" id="previewAkreditasiBadge">📜 Terakreditasi</span>
            </div>
            <div class="preview-body">
                <h2 class="preview-title" id="previewTitle">Nama program studi akan muncul di sini...</h2>
                <div class="preview-code" id="previewCode">KODE • Singkatan</div>

                <div class="preview-meta">
                    <div class="preview-meta-item" id="previewKaprodiItem" style="display: none;">
                        <span class="meta-icon">👤</span>
                        <strong id="previewKaprodi">-</strong>
                    </div>
                    <div class="preview-meta-item">
                        <span class="meta-icon">👨‍🎓</span>
                        <strong id="previewMahasiswa">0 Mahasiswa</strong>
                    </div>
                    <div class="preview-meta-item">
                        <span class="meta-icon">👨‍🏫</span>
                        <strong id="previewDosen">0 Dosen</strong>
                    </div>
                </div>

                <div class="preview-desc" id="previewDesc">
                    <span class="preview-empty">Deskripsi program studi akan muncul di sini...</span>
                </div>

                <!-- Stats -->
                <div class="preview-stats">
                    <div class="preview-stat-item">
                        <div class="preview-stat-value" id="previewRatioStat">0:1</div>
                        <div class="preview-stat-label">Rasio</div>
                    </div>
                    <div class="preview-stat-item">
                        <div class="preview-stat-value" id="previewStatusStat">✅</div>
                        <div class="preview-stat-label">Status</div>
                    </div>
                    <div class="preview-stat-item">
                        <div class="preview-stat-value" id="previewUrutan">#0</div>
                        <div class="preview-stat-label">Urutan</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Visi & Misi Preview -->
        <div class="visi-preview-section">
            <div class="visi-preview-label">🎯 Visi</div>
            <div class="visi-preview-box" id="previewVisi">
                <span class="preview-empty">Visi program studi akan muncul di sini...</span>
            </div>

            <div class="visi-preview-label" style="margin-top: 0.75rem;">🚀 Misi</div>
            <ul class="misi-preview-list" id="previewMisi">
                <li><span class="preview-empty">Misi akan muncul di sini...</span></li>
            </ul>
        </div>

        <!-- QR Code -->
        <div class="share-preview">
            <div class="share-preview-label">🔗 QR Program Studi</div>
            <div class="share-preview-qr">
                <img id="qrCode" src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=FKIP-UNIMOF-Prodi" alt="QR">
            </div>
        </div>
    </div>
</div>

<!-- Autosave Indicator -->
<div class="autosave-indicator" id="autosaveIndicator">
    <span id="autosaveIcon">💾</span>
    <span id="autosaveText">Menyimpan...</span>
</div>

<script>
// ===== DATA =====
const jenjangConfig = {
    'S1': { icon: '🎓', label: 'Strata 1' },
    'S2': { icon: '🎓', label: 'Strata 2' },
    'S3': { icon: '🎓', label: 'Strata 3' },
    'D3': { icon: '🎓', label: 'Diploma 3' }
};

const akreditasiIcons = {
    'Unggul': '⭐',
    'Baik Sekali': '✨',
    'Baik': '✅',
    'Terakreditasi': '📜'
};

// ===== TEMPLATES =====
const templates = {
    pmat: {
        kode: 'PMAT', nama: 'Pendidikan Matematika', nama_en: 'Mathematics Education', singkatan: 'Pend. Mat.',
        jenjang: 'S1', akreditasi: 'Unggul',
        ketua_prodi: 'Dr. Budi Santoso, M.Pd.', nidn_kaprodi: '0001018501',
        deskripsi: 'Program Studi Pendidikan Matematika FKIP UNIMOF menghasilkan guru matematika yang profesional, inovatif, dan berkarakter Islami. Lulusan mampu mengajar matematika dengan metode modern dan teknologi terkini.',
        visi: 'Menjadi program studi Pendidikan Matematika unggul di Indonesia Timur yang menghasilkan pendidik matematika berkarakter dan berdaya saing global pada tahun 2030.',
        misi: 'Menyelenggarakan pendidikan matematika yang berkualitas tinggi dan berbasis teknologi.\nMelaksanakan penelitian pendidikan matematika yang inovatif dan aplikatif.\nMelaksanakan pengabdian kepada masyarakat dalam bidang literasi matematika.\nMengembangkan karakter mahasiswa yang berakhlak mulia dan profesional.',
        kurikulum: 'Kurikulum KKNI berbasis Merdeka Belajar Kampus Merdeka (MBKM) dengan total 144 SKS yang ditempuh dalam 8 semester.',
        prospek_kerja: 'Guru Matematika di SMP/SMA/SMK\nPengajar di lembaga bimbingan belajar\nPeneliti pendidikan matematika\nPengembang kurikulum matematika\nDosen (setelah S2)',
        jumlah_dosen: 12, jumlah_mahasiswa: 180, status: 'Aktif', urutan: 1
    },
    pbio: {
        kode: 'PBIO', nama: 'Pendidikan Biologi', nama_en: 'Biology Education', singkatan: 'Pend. Bio',
        jenjang: 'S1', akreditasi: 'Unggul',
        ketua_prodi: 'Dr. Siti Aminah, M.Si.', nidn_kaprodi: '0002038702',
        deskripsi: 'Program Studi Pendidikan Biologi menghasilkan guru biologi yang kompeten dalam penguasaan konsep dan keterampilan laboratorium. Didukung laboratorium modern dengan peralatan terkini.',
        visi: 'Menjadi program studi Pendidikan Biologi yang unggul dan menjadi rujukan dalam pengembangan pendidikan sains hayati di Indonesia Timur.',
        misi: 'Menyelenggarakan pendidikan biologi berbasis riset dan praktikum.\nMengembangkan penelitian biologi terapan yang bermanfaat bagi masyarakat.\nMelaksanakan pengabdian berbasis konservasi biodiversitas lokal NTT.\nMenghasilkan lulusan yang berkarakter dan siap menghadapi tantangan pendidikan modern.',
        kurikulum: 'Kurikulum berbasis riset dengan penekanan pada praktikum laboratorium dan penelitian lapangan. Total 146 SKS dalam 8 semester.',
        prospek_kerja: 'Guru Biologi di SMP/SMA\nLaboran di lembaga penelitian\nPeneliti biodiversitas\nKonsultan lingkungan\nPengembang media pembelajaran biologi',
        jumlah_dosen: 10, jumlah_mahasiswa: 150, status: 'Aktif', urutan: 2
    },
    pfis: {
        kode: 'PFIS', nama: 'Pendidikan Fisika', nama_en: 'Physics Education', singkatan: 'Pend. Fis',
        jenjang: 'S1', akreditasi: 'Baik Sekali',
        ketua_prodi: 'Dr. Ahmad Rizki, M.Si.', nidn_kaprodi: '0003047803',
        deskripsi: 'Program Studi Pendidikan Fisika menghasilkan pendidik fisika yang mampu mengajarkan konsep fisika dengan pendekatan eksperimental dan teknologi modern.',
        visi: 'Menjadi program studi yang unggul dalam pendidikan fisika berbasis eksperimen dan teknologi di kawasan Indonesia Timur.',
        misi: 'Menyelenggarakan pendidikan fisika yang berkualitas dengan pendekatan eksperimen.\nMelaksanakan penelitian fisika terapan dan pendidikan fisika.\nMelaksanakan pengabdian masyarakat melalui literasi sains.\nMenghasilkan guru fisika yang profesional dan inovatif.',
        kurikulum: 'Kurikulum berbasis eksperimen dengan total 146 SKS. Mahasiswa dilatih untuk merancang dan melaksanakan eksperimen fisika modern.',
        prospek_kerja: 'Guru Fisika di SMA/SMK\nPeneliti di bidang fisika terapan\nInstruktur laboratorium\nPengembang alat peraga fisika\nWirausaha di bidang edukasi sains',
        jumlah_dosen: 8, jumlah_mahasiswa: 95, status: 'Aktif', urutan: 3
    },
    pkim: {
        kode: 'PKIM', nama: 'Pendidikan Kimia', nama_en: 'Chemistry Education', singkatan: 'Pend. Kim',
        jenjang: 'S1', akreditasi: 'Baik Sekali',
        ketua_prodi: 'Dr. Rina Wulandari, M.Si.', nidn_kaprodi: '0004059004',
        deskripsi: 'Program Studi Pendidikan Kimia menghasilkan guru kimia yang kompeten dengan penguasaan laboratorium dan teknologi pembelajaran modern.',
        visi: 'Menjadi program studi yang menghasilkan pendidik kimia berkarakter dan berdaya saing di tingkat nasional.',
        misi: 'Menyelenggarakan pendidikan kimia berbasis praktikum dan riset.\nMelaksanakan penelitian kimia dan pendidikan kimia yang aplikatif.\nMelaksanakan pengabdian masyarakat berbasis kimia terapan.\nMenghasilkan lulusan yang siap menjadi guru kimia profesional.',
        kurikulum: 'Kurikulum berbasis praktikum dengan total 146 SKS. Menekankan pada keterampilan laboratorium dan analisis kimia.',
        prospek_kerja: 'Guru Kimia di SMA/SMK\nAnalis laboratorium\nPeneliti kimia\nQuality control di industri\nPengajar bimbel kimia',
        jumlah_dosen: 9, jumlah_mahasiswa: 110, status: 'Aktif', urutan: 4
    },
    pbsi: {
        kode: 'PBSI', nama: 'Pendidikan Bahasa dan Sastra Inggris', nama_en: 'English Education', singkatan: 'Pend. B. Ing',
        jenjang: 'S1', akreditasi: 'Unggul',
        ketua_prodi: 'Dr. Andi Pratama, M.Pd.', nidn_kaprodi: '0003059003',
        deskripsi: 'Program Studi Pendidikan Bahasa Inggris menghasilkan guru bahasa Inggris yang fasih, terampil mengajar, dan berwawasan global. Didukung native speaker dan program pertukaran internasional.',
        visi: 'Menjadi program studi unggulan dalam pendidikan bahasa Inggris yang menghasilkan pendidik berdaya saing global pada tahun 2028.',
        misi: 'Menyelenggarakan pendidikan bahasa Inggris yang berstandar internasional.\nMengembangkan kemampuan literasi bahasa Inggris melalui teknologi.\nMelaksanakan penelitian pendidikan bahasa Inggris yang inovatif.\nMenghasilkan lulusan yang mampu bersaing di pasar global.',
        kurikulum: 'Kurikulum berstandar CEFR dengan total 146 SKS. Mahasiswa diwajibkan mengikuti program immersion dan sertifikasi TOEFL/IELTS.',
        prospek_kerja: 'Guru Bahasa Inggris di SMP/SMA\nPengajar di lembaga kursus\nTranslator profesional\nContent writer bahasa Inggris\nDosen (setelah S2)',
        jumlah_dosen: 15, jumlah_mahasiswa: 220, status: 'Aktif', urutan: 5
    },
    bind: {
        kode: 'BSIND', nama: 'Bahasa dan Sastra Indonesia', nama_en: 'Indonesian Language and Literature', singkatan: 'B. Ind',
        jenjang: 'S1', akreditasi: 'Baik Sekali',
        ketua_prodi: 'Dr. Maria Flores, M.Hum.', nidn_kaprodi: '0005067105',
        deskripsi: 'Program Studi Bahasa dan Sastra Indonesia menghasilkan ahli bahasa, sastrawan, editor, dan peneliti sastra Indonesia dengan penguasaan literasi yang mendalam.',
        visi: 'Menjadi pusat pengembangan bahasa dan sastra Indonesia yang unggul dan berkarakter kearifan lokal NTT.',
        misi: 'Menyelenggarakan pendidikan bahasa dan sastra Indonesia berkualitas tinggi.\nMelaksanakan penelitian bahasa dan sastra Indonesia yang inovatif.\nMelestarikan sastra lokal NTT melalui penelitian dan pengabdian.\nMenghasilkan lulusan yang kompeten di bidang kebahasaan.',
        kurikulum: 'Kurikulum berbasis literasi dengan total 144 SKS. Menekankan pada kajian sastra, linguistik, dan penulisan kreatif.',
        prospek_kerja: 'Editor di penerbit\nPenulis profesional\nPeneliti bahasa dan sastra\nJurnalis dan content creator\nPengajar bahasa Indonesia untuk penutur asing (BIPA)',
        jumlah_dosen: 11, jumlah_mahasiswa: 140, status: 'Aktif', urutan: 6
    },
    peko: {
        kode: 'PEKO', nama: 'Pendidikan Ekonomi', nama_en: 'Economics Education', singkatan: 'Pend. Eko',
        jenjang: 'S1', akreditasi: 'Unggul',
        ketua_prodi: 'Dr. Petrus Kleden, M.Pd.', nidn_kaprodi: '0006078206',
        deskripsi: 'Program Studi Pendidikan Ekonomi menghasilkan guru ekonomi yang memahami teori ekonomi modern, digital ekonomi, dan mampu mengintegrasikan teknologi dalam pembelajaran.',
        visi: 'Menjadi program studi pendidikan ekonomi unggulan yang menghasilkan pendidik ekonomi berwawasan digital dan entrepreneurship.',
        misi: 'Menyelenggarakan pendidikan ekonomi yang relevan dengan perkembangan zaman.\nMelaksanakan penelitian ekonomi pendidikan dan ekonomi terapan.\nMelaksanakan pengabdian masyarakat dalam literasi ekonomi.\nMenghasilkan guru ekonomi yang profesional dan inovatif.',
        kurikulum: 'Kurikulum berbasis ekonomi digital dengan total 146 SKS. Menekankan pada ekonomi kreatif dan literasi keuangan.',
        prospek_kerja: 'Guru Ekonomi di SMA/SMK\nKonsultan keuangan\nPengusaha UMKM\nAnalis ekonomi\nDosen ekonomi (setelah S2)',
        jumlah_dosen: 10, jumlah_mahasiswa: 165, status: 'Aktif', urutan: 7
    },
    pkn: {
        kode: 'PKN', nama: 'Pendidikan Pancasila dan Kewarganegaraan', nama_en: 'Civics Education', singkatan: 'Pend. PKn',
        jenjang: 'S1', akreditasi: 'Baik Sekali',
        ketua_prodi: 'Dr. Agnes Doa, M.Pd.', nidn_kaprodi: '0007089307',
        deskripsi: 'Program Studi Pendidikan PKn menghasilkan guru PKn yang memahami nilai-nilai Pancasila, demokrasi, dan HAM, serta mampu membentuk karakter warga negara yang baik.',
        visi: 'Menjadi program studi yang unggul dalam menghasilkan pendidik PKn yang berkarakter Pancasila dan berwawasan kebangsaan.',
        misi: 'Menyelenggarakan pendidikan PKn berbasis nilai-nilai Pancasila.\nMelaksanakan penelitian tentang pendidikan karakter dan kewarganegaraan.\nMelaksanakan pengabdian masyarakat dalam pendidikan demokrasi.\nMenghasilkan guru PKn yang berkarakter dan profesional.',
        kurikulum: 'Kurikulum berbasis nilai Pancasila dengan total 144 SKS. Menekankan pada pendidikan karakter, demokrasi, dan HAM.',
        prospek_kerja: 'Guru PKn di SMP/SMA\nPeneliti pendidikan karakter\nFasilitator demokrasi dan HAM\nPegawai pemerintahan\nPengembang kurikulum PKn',
        jumlah_dosen: 8, jumlah_mahasiswa: 125, status: 'Aktif', urutan: 8
    }
};

function applyTemplate(key) {
    const t = templates[key];
    if (!t) return;

    document.getElementById('kode').value = t.kode;
    document.getElementById('nama').value = t.nama;
    document.getElementById('nama_en').value = t.nama_en;
    document.getElementById('singkatan').value = t.singkatan;
    document.getElementById('ketua_prodi').value = t.ketua_prodi;
    document.getElementById('nidn_kaprodi').value = t.nidn_kaprodi;
    document.getElementById('deskripsi').value = t.deskripsi;
    document.getElementById('visi').value = t.visi;
    document.getElementById('misi').value = t.misi;
    document.getElementById('kurikulum').value = t.kurikulum;
    document.getElementById('prospek_kerja').value = t.prospek_kerja;
    document.getElementById('jumlah_dosen').value = t.jumlah_dosen;
    document.getElementById('jumlah_mahasiswa').value = t.jumlah_mahasiswa;
    document.getElementById('status').value = t.status;
    document.getElementById('urutan').value = t.urutan;

    // Set jenjang
    document.querySelectorAll('#jenjangSelector .jenjang-option').forEach(el => {
        el.classList.toggle('selected', el.dataset.value === t.jenjang);
        el.querySelector('input').checked = (el.dataset.value === t.jenjang);
    });

    // Set akreditasi
    document.querySelectorAll('#akreditasiSelector .akreditasi-option').forEach(el => {
        el.classList.toggle('selected', el.dataset.value === t.akreditasi);
        el.querySelector('input').checked = (el.dataset.value === t.akreditasi);
    });

    // Update all counters
    updateAllCounters();
    updateStatsBadges();
    updateRatioVisual();
    updatePreview();
    updateProgress();
    triggerAutosave();

    showToast('Template Diterapkan', `Template "${key.toUpperCase()}" berhasil diisi`, 'success');
}

// ===== SMART PHRASES =====
function addPhrase(field, phrase) {
    const textarea = document.getElementById(field);
    const current = textarea.value.trim();
    textarea.value = current ? current + ' ' + phrase : phrase;
    updateAllCounters();
    updatePreview();
    updateProgress();
    triggerAutosave();
    textarea.focus();
    showToast('Frase Ditambahkan', phrase.substring(0, 40) + '...', 'success');
}

function addMisi(phrase) {
    const textarea = document.getElementById('misi');
    const current = textarea.value.trim();
    textarea.value = current ? current + '\n' + phrase : phrase;
    updateAllCounters();
    updatePreview();
    updateProgress();
    triggerAutosave();
    textarea.focus();
    showToast('Misi Ditambahkan', phrase.substring(0, 40) + '...', 'success');
}

function addProspek(phrase) {
    const textarea = document.getElementById('prospek_kerja');
    const current = textarea.value.trim();
    textarea.value = current ? current + '\n' + phrase : phrase;
    updateAllCounters();
    updatePreview();
    updateProgress();
    triggerAutosave();
    textarea.focus();
    showToast('Prospek Ditambahkan', phrase, 'success');
}

// ===== JENJANG & AKREDITASI SELECTOR =====
document.querySelectorAll('#jenjangSelector .jenjang-option').forEach(opt => {
    opt.addEventListener('click', () => {
        document.querySelectorAll('#jenjangSelector .jenjang-option').forEach(o => o.classList.remove('selected'));
        opt.classList.add('selected');
        opt.querySelector('input').checked = true;
        updatePreview();
        triggerAutosave();
        updateProgress();
    });
});

document.querySelectorAll('#akreditasiSelector .akreditasi-option').forEach(opt => {
    opt.addEventListener('click', () => {
        document.querySelectorAll('#akreditasiSelector .akreditasi-option').forEach(o => o.classList.remove('selected'));
        opt.classList.add('selected');
        opt.querySelector('input').checked = true;
        updatePreview();
        triggerAutosave();
        updateProgress();
    });
});

// ===== STATS BADGES & RATIO =====
function updateStatsBadges() {
    const dosen = parseInt(document.getElementById('jumlah_dosen').value) || 0;
    const mahasiswa = parseInt(document.getElementById('jumlah_mahasiswa').value) || 0;
    document.getElementById('dosenBadge').textContent = dosen;
    document.getElementById('mahasiswaBadge').textContent = mahasiswa;
}

function updateRatioVisual() {
    const dosen = parseInt(document.getElementById('jumlah_dosen').value) || 0;
    const mahasiswa = parseInt(document.getElementById('jumlah_mahasiswa').value) || 0;
    const visual = document.getElementById('ratioVisual');
    const ratioValue = document.getElementById('ratioValue');
    const ratioStatus = document.getElementById('ratioStatus');

    if (dosen > 0 && mahasiswa > 0) {
        const ratio = Math.round(mahasiswa / dosen);
        ratioValue.textContent = ratio + ':1';
        visual.classList.add('show');

        let status = '';
        let icon = '';
        if (ratio <= 15) {
            status = '✅ Rasio ideal (sangat baik)';
            icon = '✅';
        } else if (ratio <= 25) {
            status = '👍 Rasio baik';
            icon = '👍';
        } else if (ratio <= 35) {
            status = '⚠️ Rasio cukup tinggi';
            icon = '⚠️';
        } else {
            status = '🚨 Rasio terlalu tinggi';
            icon = '🚨';
        }
        ratioStatus.textContent = status;

        // Update preview stat
        const previewRatio = document.getElementById('previewRatioStat');
        if (previewRatio) previewRatio.textContent = ratio + ':1';
    } else {
        visual.classList.remove('show');
    }
}

// ===== DRAG & DROP UPLOADS =====
function setupUpload(zoneId, inputId, previewId, previewImgId, nameId, sizeId, maxSizeMB) {
    const zone = document.getElementById(zoneId);
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    const previewImg = document.getElementById(previewImgId);
    const nameEl = document.getElementById(nameId);
    const sizeEl = document.getElementById(sizeId);

    ['dragenter', 'dragover'].forEach(ev => {
        zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.add('dragover'); });
    });
    ['dragleave', 'drop'].forEach(ev => {
        zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.remove('dragover'); });
    });
    zone.addEventListener('drop', e => {
        if (e.dataTransfer.files.length > 0) handleFile(e.dataTransfer.files[0]);
    });
    input.addEventListener('change', e => {
        if (e.target.files.length > 0) handleFile(e.target.files[0]);
    });

    function handleFile(file) {
        if (!file.type.startsWith('image/')) {
            showToast('Error', 'Hanya file gambar yang diizinkan!', 'error');
            return;
        }
        if (file.size > maxSizeMB * 1024 * 1024) {
            showToast('Error', `Ukuran maksimal ${maxSizeMB}MB!`, 'error');
            return;
        }

        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;

        nameEl.textContent = file.name;
        sizeEl.textContent = formatFileSize(file.size);

        const reader = new FileReader();
        reader.onload = e => {
            previewImg.innerHTML = `<img src="${e.target.result}" alt="">`;

            // Update preview card
            if (zoneId === 'uploadLogo') {
                // Logo - show small
            } else {
                // Banner - update preview banner
                document.getElementById('previewBannerBox').innerHTML = `
                    <img src="${e.target.result}" alt="">
                    <span class="preview-jenjang-badge" id="previewJenjangBadge">🎓 ${document.querySelector('input[name="jenjang"]:checked')?.value || 'S1'}</span>
                    <span class="preview-akreditasi-badge" id="previewAkreditasiBadge">${akreditasiIcons[document.querySelector('input[name="akreditasi"]:checked')?.value] || '📜'} ${document.querySelector('input[name="akreditasi"]:checked')?.value || 'Terakreditasi'}</span>
                `;
            }
        };
        reader.readAsDataURL(file);

        preview.classList.add('show');
        zone.style.display = 'none';
        updatePreview();
        triggerAutosave();
    }
}

setupUpload('uploadLogo', 'logo', 'logoPreview', 'logoPreviewImg', 'logoName', 'logoSize', 2);
setupUpload('uploadBanner', 'banner', 'bannerPreview', 'bannerPreviewImg', 'bannerName', 'bannerSize', 3);

function removeLogo() {
    document.getElementById('logo').value = '';
    document.getElementById('logoPreview').classList.remove('show');
    document.getElementById('uploadLogo').style.display = 'flex';
}

function removeBanner() {
    document.getElementById('banner').value = '';
    document.getElementById('bannerPreview').classList.remove('show');
    document.getElementById('uploadBanner').style.display = 'flex';
    updatePreview();
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// ===== LIVE PREVIEW =====
function updatePreview() {
    const nama = document.getElementById('nama').value || 'Nama program studi akan muncul di sini...';
    const kode = document.getElementById('kode').value || 'KODE';
    const singkatan = document.getElementById('singkatan').value || 'Singkatan';
    const kaprodi = document.getElementById('ketua_prodi').value || '';
    const dosen = parseInt(document.getElementById('jumlah_dosen').value) || 0;
    const mahasiswa = parseInt(document.getElementById('jumlah_mahasiswa').value) || 0;
    const status = document.getElementById('status').value || 'Aktif';
    const urutan = document.getElementById('urutan').value || '0';
    const jenjang = document.querySelector('input[name="jenjang"]:checked')?.value || 'S1';
    const akreditasi = document.querySelector('input[name="akreditasi"]:checked')?.value || 'Terakreditasi';
    const deskripsi = document.getElementById('deskripsi').value;
    const visi = document.getElementById('visi').value;
    const misi = document.getElementById('misi').value;

    // Title
    document.getElementById('previewTitle').textContent = nama;
    document.getElementById('previewCode').textContent = `${kode} • ${singkatan}`;

    // Badges
    document.getElementById('previewJenjangBadge').textContent = `🎓 ${jenjang}`;
    document.getElementById('previewAkreditasiBadge').textContent = `${akreditasiIcons[akreditasi]} ${akreditasi}`;

    // Banner icon (if no image)
    const bannerBox = document.getElementById('previewBannerBox');
    const bannerIconEl = document.getElementById('previewBannerIcon');
    if (!bannerBox.querySelector('img') && bannerIconEl) {
        bannerIconEl.textContent = '🎓';
    }

    // Kaprodi
    const kaprodiItem = document.getElementById('previewKaprodiItem');
    if (kaprodi) {
        kaprodiItem.style.display = 'flex';
        document.getElementById('previewKaprodi').textContent = kaprodi;
    } else {
        kaprodiItem.style.display = 'none';
    }

    // Stats
    document.getElementById('previewMahasiswa').textContent = mahasiswa + ' Mahasiswa';
    document.getElementById('previewDosen').textContent = dosen + ' Dosen';
    document.getElementById('previewStatusStat').textContent = status === 'Aktif' ? '✅' : '❌';
    document.getElementById('previewUrutan').textContent = '#' + urutan;

    // Ratio
    if (dosen > 0 && mahasiswa > 0) {
        document.getElementById('previewRatioStat').textContent = Math.round(mahasiswa / dosen) + ':1';
    } else {
        document.getElementById('previewRatioStat').textContent = '-';
    }

    // Deskripsi
    if (deskripsi) {
        document.getElementById('previewDesc').innerHTML = deskripsi.replace(/\n/g, '<br>');
        document.getElementById('previewDesc').style.color = 'var(--text-secondary)';
    } else {
        document.getElementById('previewDesc').innerHTML = '<span class="preview-empty">Deskripsi program studi akan muncul di sini...</span>';
    }

    // Visi
    const visiBox = document.getElementById('previewVisi');
    if (visi) {
        visiBox.innerHTML = visi.replace(/\n/g, '<br>');
    } else {
        visiBox.innerHTML = '<span class="preview-empty">Visi program studi akan muncul di sini...</span>';
    }

    // Misi
    const misiList = document.getElementById('previewMisi');
    if (misi) {
        const misiArr = misi.split('\n').filter(m => m.trim());
        if (misiArr.length > 0) {
            misiList.innerHTML = misiArr.map(m => `<li>${escapeHtml(m)}</li>`).join('');
        } else {
            misiList.innerHTML = '<li><span class="preview-empty">Misi akan muncul di sini...</span></li>';
        }
    } else {
        misiList.innerHTML = '<li><span class="preview-empty">Misi akan muncul di sini...</span></li>';
    }

    // QR Code
    const qrData = `PRODI:${nama}|${kode}|${jenjang}|${akreditasi}`;
    document.getElementById('qrCode').src = `https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=${encodeURIComponent(qrData)}`;
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ===== COUNTERS =====
function updateAllCounters() {
    const fields = ['nama', 'deskripsi', 'visi', 'misi', 'kurikulum', 'prospek_kerja'];
    fields.forEach(id => {
        const el = document.getElementById(id);
        const counter = document.getElementById(id + 'Counter');
        if (el && counter) {
            counter.textContent = el.value.length;
        }
    });

    const deskripsi = document.getElementById('deskripsi');
    const deskripsiWords = document.getElementById('deskripsiWords');
    if (deskripsi && deskripsiWords) {
        deskripsiWords.textContent = deskripsi.value.trim().split(/\s+/).filter(w => w).length;
    }

    const misi = document.getElementById('misi');
    const misiLines = document.getElementById('misiLines');
    if (misi && misiLines) {
        misiLines.textContent = misi.value.split('\n').filter(l => l.trim()).length;
    }
}

// ===== PROGRESS =====
function updateProgress() {
    const fields = [
        { el: 'kode', weight: 10 },
        { el: 'nama', weight: 15 },
        { el: 'singkatan', weight: 10 },
        { check: () => document.querySelector('input[name="jenjang"]:checked'), weight: 10 },
        { check: () => document.querySelector('input[name="akreditasi"]:checked'), weight: 10 },
        { el: 'ketua_prodi', weight: 5 },
        { el: 'deskripsi', weight: 10 },
        { el: 'visi', weight: 10 },
        { el: 'misi', weight: 10 },
        { check: () => parseInt(document.getElementById('jumlah_dosen').value) > 0, weight: 5 },
        { check: () => parseInt(document.getElementById('jumlah_mahasiswa').value) > 0, weight: 5 }
    ];

    let score = 0;
    let total = 0;

    fields.forEach(f => {
        total += f.weight;
        if (f.check) {
            if (f.check()) score += f.weight;
        } else {
            const el = document.getElementById(f.el);
            if (el && el.value && el.value.trim()) score += f.weight;
        }
    });

    const percent = Math.min(100, Math.round((score / total) * 100));
    document.getElementById('progressBar').style.width = percent + '%';
    document.getElementById('progressPercent').textContent = percent + '%';
}

// ===== AUTOSAVE =====
const storageKey = 'fkip_prodi_draft_<?= $id ?: "new" ?>';
let autosaveTimer;

function triggerAutosave() {
    clearTimeout(autosaveTimer);
    const indicator = document.getElementById('autosaveIndicator');
    indicator.classList.add('show', 'saving');
    indicator.classList.remove('saved');
    document.getElementById('autosaveIcon').textContent = '⏳';
    document.getElementById('autosaveText').textContent = 'Menyimpan draft...';

    autosaveTimer = setTimeout(() => {
        const data = {
            kode: document.getElementById('kode').value,
            nama: document.getElementById('nama').value,
            nama_en: document.getElementById('nama_en').value,
            singkatan: document.getElementById('singkatan').value,
            jenjang: document.querySelector('input[name="jenjang"]:checked')?.value || 'S1',
            akreditasi: document.querySelector('input[name="akreditasi"]:checked')?.value || 'Terakreditasi',
            ketua_prodi: document.getElementById('ketua_prodi').value,
            nidn_kaprodi: document.getElementById('nidn_kaprodi').value,
            deskripsi: document.getElementById('deskripsi').value,
            visi: document.getElementById('visi').value,
            misi: document.getElementById('misi').value,
            kurikulum: document.getElementById('kurikulum').value,
            prospek_kerja: document.getElementById('prospek_kerja').value,
            jumlah_dosen: document.getElementById('jumlah_dosen').value,
            jumlah_mahasiswa: document.getElementById('jumlah_mahasiswa').value,
            status: document.getElementById('status').value,
            urutan: document.getElementById('urutan').value,
            saved_at: new Date().toISOString()
        };
        try {
            localStorage.setItem(storageKey, JSON.stringify(data));
            indicator.classList.remove('saving');
            indicator.classList.add('saved');
            document.getElementById('autosaveIcon').textContent = '✅';
            document.getElementById('autosaveText').textContent = 'Draft tersimpan';
            setTimeout(() => indicator.classList.remove('show'), 2000);
        } catch (e) {}
    }, 1000);
}

// Load draft
<?php if (!$edit): ?>
(function() {
    try {
        const saved = localStorage.getItem(storageKey);
        if (saved) {
            const data = JSON.parse(saved);
            const savedTime = new Date(data.saved_at).toLocaleString('id-ID');
            if (confirm(`Ada draft tersimpan dari ${savedTime}. Muat draft tersebut?`)) {
                document.getElementById('kode').value = data.kode || '';
                document.getElementById('nama').value = data.nama || '';
                document.getElementById('nama_en').value = data.nama_en || '';
                document.getElementById('singkatan').value = data.singkatan || '';
                document.getElementById('ketua_prodi').value = data.ketua_prodi || '';
                document.getElementById('nidn_kaprodi').value = data.nidn_kaprodi || '';
                document.getElementById('deskripsi').value = data.deskripsi || '';
                document.getElementById('visi').value = data.visi || '';
                document.getElementById('misi').value = data.misi || '';
                document.getElementById('kurikulum').value = data.kurikulum || '';
                document.getElementById('prospek_kerja').value = data.prospek_kerja || '';
                document.getElementById('jumlah_dosen').value = data.jumlah_dosen || 0;
                document.getElementById('jumlah_mahasiswa').value = data.jumlah_mahasiswa || 0;
                document.getElementById('status').value = data.status || 'Aktif';
                document.getElementById('urutan').value = data.urutan || 0;

                // Set jenjang
                document.querySelectorAll('#jenjangSelector .jenjang-option').forEach(el => {
                    el.classList.toggle('selected', el.dataset.value === (data.jenjang || 'S1'));
                    el.querySelector('input').checked = (el.dataset.value === (data.jenjang || 'S1'));
                });

                // Set akreditasi
                document.querySelectorAll('#akreditasiSelector .akreditasi-option').forEach(el => {
                    el.classList.toggle('selected', el.dataset.value === (data.akreditasi || 'Terakreditasi'));
                    el.querySelector('input').checked = (el.dataset.value === (data.akreditasi || 'Terakreditasi'));
                });

                updateAllCounters();
                updateStatsBadges();
                updateRatioVisual();
                updatePreview();
                updateProgress();
                showToast('Draft Dimuat', 'Data draft berhasil dimuat', 'success');
            } else {
                localStorage.removeItem(storageKey);
            }
        }
    } catch (e) {}
})();
<?php endif; ?>

// ===== EVENT LISTENERS =====
['kode', 'nama', 'nama_en', 'singkatan', 'ketua_prodi', 'nidn_kaprodi',
 'deskripsi', 'visi', 'misi', 'kurikulum', 'prospek_kerja',
 'jumlah_dosen', 'jumlah_mahasiswa', 'status', 'urutan'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input', () => {
        updateAllCounters();
        if (id === 'jumlah_dosen' || id === 'jumlah_mahasiswa') {
            updateStatsBadges();
            updateRatioVisual();
        }
        updatePreview();
        triggerAutosave();
        updateProgress();
    });
});

// ===== FORM SUBMIT =====
document.getElementById('programForm').addEventListener('submit', function(e) {
    const kode = document.getElementById('kode').value.trim();
    const nama = document.getElementById('nama').value.trim();
    const singkatan = document.getElementById('singkatan').value.trim();

    if (!kode) {
        e.preventDefault();
        document.getElementById('kode').classList.add('error');
        showToast('Validasi Error', 'Kode Prodi wajib diisi!', 'error');
        return;
    }
    if (!nama) {
        e.preventDefault();
        document.getElementById('nama').classList.add('error');
        showToast('Validasi Error', 'Nama Prodi wajib diisi!', 'error');
        return;
    }
    if (!singkatan) {
        e.preventDefault();
        document.getElementById('singkatan').classList.add('error');
        showToast('Validasi Error', 'Singkatan wajib diisi!', 'error');
        return;
    }

    const btn = document.getElementById('submitBtn');
    btn.classList.add('loading');

    setTimeout(() => {
        try { localStorage.removeItem(storageKey); } catch(e) {}
    }, 500);
});

// ===== TOAST HELPER =====
function showToast(title, message, type = 'info') {
    if (window.AdminPanel?.Toast) {
        window.AdminPanel.Toast.show(message, type, title);
    } else if (window.showToast) {
        window.showToast(title, message, type);
    } else {
        console.log(`[${type.toUpperCase()}] ${title}: ${message}`);
    }
}

// ===== TOGGLE PREVIEW MODE =====
function togglePreviewMode() {
    const card = document.querySelector('.preview-card-extreme');
    card.style.transform = card.style.transform === 'scale(0.95)' ? 'scale(1)' : 'scale(0.95)';
    card.style.transition = 'transform 0.3s';
}

// ===== SHARE PREVIEW =====
function sharePreview() {
    const nama = document.getElementById('nama').value || 'Program Studi';
    const kode = document.getElementById('kode').value || '-';
    const jenjang = document.querySelector('input[name="jenjang"]:checked')?.value || 'S1';
    const akreditasi = document.querySelector('input[name="akreditasi"]:checked')?.value || 'Terakreditasi';
    const mahasiswa = document.getElementById('jumlah_mahasiswa').value || 0;

    let text = `🎓 ${nama}\n🔑 ${kode} • ${jenjang}\n🏆 ${akreditasiIcons[akreditasi]} ${akreditasi}\n👨‍🎓 ${mahasiswa} Mahasiswa\n\nProgram Studi FKIP UNIMOF`;

    if (navigator.share) {
        navigator.share({ title: nama, text: text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        showToast('Disalin', 'Info program studi disalin ke clipboard', 'success');
    }
}

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('programForm').dispatchEvent(new Event('submit'));
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.querySelector('.template-section')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        showToast('Template', 'Pilih template di atas', 'info');
    }
    if (e.key === 'Escape') {
        if (confirm('Batalkan perubahan dan kembali?')) {
            window.location.href = 'program.php';
        }
    }
});

// ===== INITIAL =====
updateAllCounters();
updateStatsBadges();
updateRatioVisual();
updatePreview();
updateProgress();

console.log('%c🎓 Form Program Studi FKIP UNIMOF - Super Extreme', 'color: #6366f1; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: Ctrl+S (Simpan), Ctrl+K (Template), ESC (Batal)', 'color: #64748b;');
console.log('%cFitur: 8 Templates, Jenjang/Akreditasi Selector, Ratio Calculator, Visi/Misi Builder, Live Preview', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>