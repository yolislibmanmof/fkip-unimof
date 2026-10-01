<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

if (!function_exists('js_attr')) {
    function js_attr($s) {
        return htmlspecialchars(json_encode((string)$s, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('testi_del_foto')) {
    function testi_del_foto($f) {
        if ($f) {
            $p = (defined('APP_DIR') ? APP_DIR : dirname(__DIR__)) . '/' . ltrim($f, '/');
            if (is_file($p)) @unlink($p);
        }
    }
}

// ===== PROSES AKSI POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('error', '❌ Token keamanan tidak valid.');
    } else {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        $ids = array_map('intval', (array)($_POST['ids'] ?? []));

        if ($action === 'bulk_delete' && !empty($ids)) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT foto FROM testimoni WHERE id IN ($ph) AND foto IS NOT NULL AND foto != ''");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $f) {
                testi_del_foto($f);  // ✅ PATCH 3
            }
            $pdo->prepare("DELETE FROM testimoni WHERE id IN ($ph)")->execute($ids);
            flash_message('success', '✅ ' . count($ids) . ' testimoni dihapus.');
        }
        elseif ($action === 'bulk_publish' && !empty($ids)) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE testimoni SET status='Published' WHERE id IN ($ph)")->execute($ids);
            flash_message('success', '✅ ' . count($ids) . ' testimoni dipublikasikan.');
        }
        elseif ($action === 'bulk_draft' && !empty($ids)) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE testimoni SET status='Draft' WHERE id IN ($ph)")->execute($ids);
            flash_message('success', '✅ ' . count($ids) . ' testimoni diubah ke draft.');
        }
        elseif ($action === 'delete' && $id) {
            $stmt = $pdo->prepare("SELECT foto FROM testimoni WHERE id = ?");
            $stmt->execute([$id]);
            $foto = $stmt->fetchColumn();
            if ($foto) testi_del_foto($foto);  // ✅ PATCH 3
            $pdo->prepare("DELETE FROM testimoni WHERE id = ?")->execute([$id]);
            flash_message('success', '✅ Testimoni dihapus.');
        }
        elseif ($action === 'toggle' && $id) {
            $pdo->prepare("UPDATE testimoni SET status = IF(status='Published','Draft','Published') WHERE id = ?")->execute([$id]);
            flash_message('success', '✅ Status testimoni diubah.');
        }
        elseif ($action === 'save' && $id) {
            $nama = trim($_POST['nama'] ?? '');
            $jabatan = trim($_POST['jabatan'] ?? '');
            $pesan = trim($_POST['pesan'] ?? '');
            $rating = min(5, max(1, (int)($_POST['rating'] ?? 5)));
            $status = in_array($_POST['status'] ?? '', ['Published','Draft'], true) ? $_POST['status'] : 'Draft';

            if ($nama === '' || $pesan === '') {
                flash_message('error', '❌ Nama dan pesan wajib diisi.');
            } else {
                // Handle foto upload
                $foto_lama = $_POST['foto_lama'] ?? '';
                $foto_baru = $foto_lama;
                if (!empty($_FILES['foto']['name']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
                        flash_message('error', '❌ Format foto harus JPG/PNG/WEBP.');
                    } else {
                        $folder = (defined('APP_DIR') ? APP_DIR : dirname(__DIR__)) . '/uploads/testimoni/';
                        if (!is_dir($folder)) @mkdir($folder, 0755, true);
                        $fname = 'testi_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                        if (move_uploaded_file($_FILES['foto']['tmp_name'], $folder . $fname)) {
                            if ($foto_lama) testi_del_foto($foto_lama);  // ✅ PATCH 3
                            $foto_baru = 'uploads/testimoni/' . $fname;
                        }
                    }
                }

                try {
                    $pdo->prepare("UPDATE testimoni SET nama=?, jabatan=?, foto=?, pesan=?, rating=?, status=? WHERE id=?")
                        ->execute([$nama, $jabatan, $foto_baru, $pesan, $rating, $status, $id]);
                    flash_message('success', '✅ Testimoni diperbarui.');
                } catch (Exception $e) {
                    flash_message('error', '❌ Gagal: ' . $e->getMessage());
                }
            }
        }
        elseif ($action === 'create') {
            $nama = trim($_POST['nama'] ?? '');
            $jabatan = trim($_POST['jabatan'] ?? '');
            $pesan = trim($_POST['pesan'] ?? '');
            $rating = min(5, max(1, (int)($_POST['rating'] ?? 5)));
            $status = in_array($_POST['status'] ?? '', ['Published','Draft'], true) ? $_POST['status'] : 'Draft';

            if ($nama === '' || $pesan === '') {
                flash_message('error', '❌ Nama dan pesan wajib diisi.');
            } else {
                $foto_baru = null;
                if (!empty($_FILES['foto']['name']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                        $folder = (defined('APP_DIR') ? APP_DIR : dirname(__DIR__)) . '/uploads/testimoni/';
                        if (!is_dir($folder)) @mkdir($folder, 0755, true);
                        $fname = 'testi_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                        if (move_uploaded_file($_FILES['foto']['tmp_name'], $folder . $fname)) {
                            $foto_baru = 'uploads/testimoni/' . $fname;
                        }
                    }
                }
                try {
                    $pdo->prepare("INSERT INTO testimoni (nama, jabatan, foto, pesan, rating, status) VALUES (?,?,?,?,?,?)")
                        ->execute([$nama, $jabatan, $foto_baru, $pesan, $rating, $status]);
                    flash_message('success', '✅ Testimoni baru ditambahkan.');
                } catch (Exception $e) {
                    flash_message('error', '❌ Gagal: ' . $e->getMessage());
                }
            }
        }
    }
    header('Location: testimoni.php');
    exit;
}

// ===== FILTER & DATA =====
$status_filter = trim($_GET['status'] ?? '');
$q = trim($_GET['q'] ?? '');
$view_mode = $_GET['view'] ?? 'grid';

$where = 'WHERE 1=1';
$params = [];
if ($status_filter !== '') { $where .= ' AND status = ?'; $params[] = $status_filter; }
if ($q !== '') { $where .= ' AND (nama LIKE ? OR jabatan LIKE ? OR pesan LIKE ?)'; $l="%$q%"; $params[]=$l;$params[]=$l;$params[]=$l; }

$stmt = $pdo->prepare("SELECT * FROM testimoni $where ORDER BY created_at DESC");
$stmt->execute($params);
$testimonis = $stmt->fetchAll();

// Statistik
$stat_total = count($testimonis);
$stat_published = 0; $stat_draft = 0; $sum_rating = 0;
foreach ($testimonis as $t) {
    if ($t['status'] === 'Published') $stat_published++;
    else $stat_draft++;
    $sum_rating += (int)$t['rating'];
}
$avg_rating = $stat_total > 0 ? round($sum_rating / $stat_total, 1) : 0;

$csrf = generate_csrf_token();
$active_menu = 'testimoni';
$page_heading = 'Kelola Testimoni';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Testimoni', null]];
require __DIR__ . '/includes/header.php';
?>

<style>
.testi-hero {
    background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 50%, #4c1d95 100%);
    color: white;
    padding: 2rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(139, 92, 246, 0.3);
}
.testi-hero::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 5px;
    background: linear-gradient(90deg, #fbbf24, #f59e0b, #fbbf24);
}
.testi-hero::after {
    content: 'VOICES';
    position: absolute;
    top: 2rem; right: 2rem;
    font-family: 'Georgia', serif;
    font-size: 6rem;
    font-weight: 900;
    color: rgba(255,255,255,0.05);
    letter-spacing: 0.2em;
    pointer-events: none;
}
.testi-hero-content { position: relative; z-index: 1; }
.testi-hero h2 {
    font-family: 'Georgia', serif;
    font-size: 2rem;
    font-weight: 900;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.testi-hero p { opacity: 0.95; font-size: 0.95rem; max-width: 520px; line-height: 1.6; }

.testi-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}
.testi-stat-card {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
}
.testi-stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: var(--sc, #8b5cf6);
}
.testi-stat-icon {
    width: 42px; height: 42px;
    border-radius: 10px;
    background: var(--sc, #8b5cf6);
    color: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.25rem;
    margin-bottom: 0.75rem;
}
.testi-stat-num {
    font-family: 'Georgia', serif;
    font-size: 2.25rem;
    font-weight: 900;
    color: var(--sc, #8b5cf6);
    line-height: 1;
    margin-bottom: 0.25rem;
}
.testi-stat-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.testi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.25rem;
}
.testi-card {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 1.5rem;
    position: relative;
    transition: all 0.3s;
    display: flex;
    flex-direction: column;
}
.testi-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}
.testi-card-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}
.testi-avatar {
    width: 56px; height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, #8b5cf6, #6d28d9);
    color: white;
    display: flex; align-items: center; justify-content: center;
    font-weight: 800;
    font-size: 1.5rem;
    font-family: 'Georgia', serif;
    flex-shrink: 0;
    overflow: hidden;
    border: 3px solid var(--bg-tertiary);
}
.testi-avatar img { width: 100%; height: 100%; object-fit: cover; }
.testi-info { flex: 1; min-width: 0; }
.testi-name {
    font-weight: 800;
    font-size: 1rem;
    margin-bottom: 0.15rem;
    font-family: 'Georgia', serif;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.testi-role {
    font-size: 0.78rem;
    color: var(--text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.testi-rating {
    display: flex;
    gap: 0.15rem;
    margin-bottom: 0.75rem;
    color: #fbbf24;
    font-size: 1rem;
}
.testi-rating .empty { color: var(--border-strong); }
.testi-message {
    font-size: 0.9rem;
    line-height: 1.6;
    color: var(--text-secondary);
    margin-bottom: 1rem;
    flex: 1;
    position: relative;
    padding-left: 1rem;
    font-style: italic;
}
.testi-message::before {
    content: '"';
    position: absolute;
    left: -0.25rem;
    top: -0.5rem;
    font-family: 'Georgia', serif;
    font-size: 2.5rem;
    color: var(--primary);
    opacity: 0.3;
    line-height: 1;
}
.testi-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 0.75rem;
    border-top: 1px dashed var(--border);
    font-size: 0.75rem;
    color: var(--text-muted);
}
.testi-actions {
    display: flex;
    gap: 0.25rem;
}
.testi-actions button,
.testi-actions a {
    width: 32px; height: 32px;
    border-radius: 8px;
    border: none;
    background: var(--bg-tertiary);
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.9rem;
    color: var(--text-secondary);
    text-decoration: none;
    transition: all 0.2s;
}
.testi-actions button:hover,
.testi-actions a:hover {
    transform: translateY(-2px);
    background: var(--primary);
    color: white;
}
.testi-actions .danger:hover { background: #dc2626; }

.testi-toolbar {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    align-items: center;
}
.testi-search {
    flex: 1;
    min-width: 240px;
    position: relative;
}
.testi-search input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.75rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    font-size: 0.95rem;
    background: var(--bg-primary);
    color: var(--text-primary);
    font-family: inherit;
}
.testi-search input:focus { outline: none; border-color: var(--primary); }
.testi-search::before {
    content: '🔍';
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 1rem;
}

.btn-testi {
    padding: 0.7rem 1.15rem;
    border-radius: var(--radius-md);
    border: 2px solid var(--border);
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    font-family: inherit;
    font-size: 0.85rem;
}
.btn-testi:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); }
.btn-testi.primary {
    background: linear-gradient(135deg, #8b5cf6, #6d28d9);
    color: white;
    border-color: #8b5cf6;
}
.btn-testi.primary:hover { color: white; box-shadow: 0 8px 20px rgba(139,92,246,0.4); }

.testi-filter-pills {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}
.pill-testi {
    padding: 0.45rem 0.95rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.pill-testi:hover { border-color: var(--primary); color: var(--primary); }
.pill-testi.active {
    background: linear-gradient(135deg, #8b5cf6, #6d28d9);
    color: white;
    border-color: #8b5cf6;
}

.empty-testi {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-xl);
    border: 2px dashed var(--border);
}
.empty-testi-icon { font-size: 4rem; margin-bottom: 1rem; opacity: 0.4; }
.empty-testi h3 { font-size: 1.25rem; margin-bottom: 0.5rem; font-family: 'Georgia', serif; }
.empty-testi p { color: var(--text-muted); margin-bottom: 1.5rem; }

/* Modal form */
.testi-modal {
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,0.85);
    backdrop-filter: blur(10px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    padding: 1.5rem;
}
.testi-modal.open { display: flex; animation: fadeIn 0.3s; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.testi-modal-content {
    background: var(--bg-primary);
    border-radius: var(--radius-xl);
    width: 100%;
    max-width: 640px;
    max-height: 90vh;
    overflow-y: auto;
    animation: slideUp 0.4s;
    border: 1px solid var(--border);
}
@keyframes slideUp {
    from { transform: translateY(20px) scale(0.95); opacity: 0; }
    to { transform: translateY(0) scale(1); opacity: 1; }
}

.testi-modal-header {
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, #8b5cf6, #6d28d9);
    color: white;
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
    position: relative;
}
.testi-modal-header h2 {
    font-family: 'Georgia', serif;
    font-size: 1.35rem;
    font-weight: 800;
    margin: 0;
}
.testi-modal-close {
    position: absolute;
    top: 1rem; right: 1rem;
    width: 36px; height: 36px;
    background: rgba(255,255,255,0.2);
    border: none;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1.1rem;
    color: white;
    transition: all 0.2s;
}
.testi-modal-close:hover { background: rgba(255,255,255,0.3); transform: rotate(90deg); }

.testi-modal-body { padding: 1.75rem 2rem; }
.testi-field { margin-bottom: 1.15rem; }
.testi-field label {
    display: block;
    font-size: 0.82rem;
    font-weight: 700;
    margin-bottom: 0.4rem;
    color: var(--text-primary);
}
.testi-field input[type="text"],
.testi-field input[type="file"],
.testi-field select,
.testi-field textarea {
    width: 100%;
    padding: 0.7rem 0.9rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-family: inherit;
    font-size: 0.9rem;
    transition: all 0.2s;
}
.testi-field input:focus, .testi-field select:focus, .testi-field textarea:focus {
    outline: none;
    border-color: var(--primary);
    background: var(--bg-primary);
}
.testi-field textarea { min-height: 110px; resize: vertical; }

.testi-rating-input {
    display: flex;
    gap: 0.25rem;
    flex-direction: row-reverse;
    justify-content: flex-end;
}
.testi-rating-input input { display: none; }
.testi-rating-input label {
    font-size: 2rem;
    color: var(--border-strong);
    cursor: pointer;
    transition: color 0.15s;
}
.testi-rating-input input:checked ~ label,
.testi-rating-input label:hover,
.testi-rating-input label:hover ~ label { color: #fbbf24; }

.testi-avatar-preview {
    width: 80px; height: 80px;
    border-radius: 50%;
    background: var(--bg-tertiary);
    border: 3px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem;
    margin-bottom: 0.5rem;
    overflow: hidden;
}
.testi-avatar-preview img { width: 100%; height: 100%; object-fit: cover; }

.testi-modal-actions {
    display: flex;
    gap: 0.6rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
    padding-top: 1.25rem;
    border-top: 1px solid var(--border);
}

.bulk-bar-testi {
    background: linear-gradient(135deg, #8b5cf6, #6d28d9);
    color: white;
    padding: 1rem 1.5rem;
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
    display: none;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    box-shadow: 0 10px 30px rgba(139,92,246,0.3);
}
.bulk-bar-testi.show { display: flex; }
.bulk-count-testi {
    background: white;
    color: #6d28d9;
    padding: 0.25rem 0.7rem;
    border-radius: 999px;
    font-weight: 800;
    font-size: 0.82rem;
}
.bulk-btn-testi {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    font-size: 0.82rem;
}
.bulk-btn-testi.publish { background: #10b981; color: white; }
.bulk-btn-testi.draft { background: #f59e0b; color: white; }
.bulk-btn-testi.delete { background: #dc2626; color: white; }
.bulk-btn-testi.cancel { background: transparent; color: white; border: 1px solid rgba(255,255,255,0.3); }

.row-check-testi {
    position: absolute;
    top: 1rem; right: 1rem;
    width: 20px; height: 20px;
    accent-color: #8b5cf6;
    cursor: pointer;
    z-index: 2;
}

@media (max-width: 640px) {
    .testi-grid { grid-template-columns: 1fr; }
    .testi-stats { grid-template-columns: 1fr; }
}
</style>

<!-- HERO -->
<div class="testi-hero">
    <div class="testi-hero-content">
        <h2>💬 Testimoni & Suara</h2>
        <p>Kelola kutipan dan testimoni dari mahasiswa, alumni, dan mitra. Tampilkan di halaman beranda untuk membangun kredibilitas.</p>
    </div>
</div>

<!-- STATS -->
<div class="testi-stats">
    <div class="testi-stat-card" style="--sc: #8b5cf6;">
        <div class="testi-stat-icon">💬</div>
        <div class="testi-stat-num"><?= $stat_total ?></div>
        <div class="testi-stat-label">Total Testimoni</div>
    </div>
    <div class="testi-stat-card" style="--sc: #10b981;">
        <div class="testi-stat-icon">✅</div>
        <div class="testi-stat-num"><?= $stat_published ?></div>
        <div class="testi-stat-label">Published</div>
    </div>
    <div class="testi-stat-card" style="--sc: #f59e0b;">
        <div class="testi-stat-icon">📝</div>
        <div class="testi-stat-num"><?= $stat_draft ?></div>
        <div class="testi-stat-label">Draft</div>
    </div>
    <div class="testi-stat-card" style="--sc: #fbbf24;">
        <div class="testi-stat-icon">⭐</div>
        <div class="testi-stat-num"><?= $avg_rating ?></div>
        <div class="testi-stat-label">Rating Rata-rata</div>
    </div>
</div>

<!-- CARD -->
<div style="background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 1.5rem; box-shadow: var(--shadow-sm);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
        <div>
            <h2 style="font-family:'Georgia',serif; font-size:1.35rem; margin-bottom:0.15rem;">💬 Daftar Testimoni <span style="background:linear-gradient(135deg,#8b5cf6,#6d28d9);color:white;padding:0.2rem 0.7rem;border-radius:999px;font-size:0.82rem;font-weight:700"><?= $stat_total ?></span></h2>
            <p style="color:var(--text-muted);font-size:0.82rem;margin-top:0.15rem">Klik tombol "+" untuk menambah testimoni baru</p>
        </div>
        <div style="display:flex;gap:0.5rem">
            <label class="btn-testi" style="cursor:pointer">
                <input type="checkbox" id="selectAllTesti" onchange="toggleSelectAllTesti(this.checked)" style="accent-color:#8b5cf6">
                <span>Pilih Semua</span>
            </label>
            <button class="btn-testi primary" onclick="openTestiModal()">
                <span>➕</span><span>Tambah Testimoni</span>
            </button>
        </div>
    </div>

    <!-- FILTER PILLS -->
    <div class="testi-filter-pills">
        <a href="testimoni.php" class="pill-testi <?= $status_filter === '' ? 'active' : '' ?>">📋 Semua <span style="background:rgba(255,255,255,0.25);padding:0.1rem 0.5rem;border-radius:999px;font-size:0.7rem;font-weight:800"><?= $stat_total ?></span></a>
        <a href="testimoni.php?status=Published" class="pill-testi <?= $status_filter === 'Published' ? 'active' : '' ?>">🟢 Published <span style="background:rgba(255,255,255,0.25);padding:0.1rem 0.5rem;border-radius:999px;font-size:0.7rem;font-weight:800"><?= $stat_published ?></span></a>
        <a href="testimoni.php?status=Draft" class="pill-testi <?= $status_filter === 'Draft' ? 'active' : '' ?>">📝 Draft <span style="background:rgba(255,255,255,0.25);padding:0.1rem 0.5rem;border-radius:999px;font-size:0.7rem;font-weight:800"><?= $stat_draft ?></span></a>
    </div>

    <!-- TOOLBAR -->
    <div class="testi-toolbar">
        <form method="GET" class="testi-search" style="margin:0;flex:1">
            <input type="text" name="q" placeholder="Cari nama, jabatan, atau pesan..." value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" onchange="this.form.submit()">
        </form>
        <?php if ($q !== ''): ?>
            <a href="testimoni.php" class="btn-testi">🔄 Reset</a>
        <?php endif; ?>
    </div>

    <!-- BULK BAR -->
    <div class="bulk-bar-testi" id="bulkBarTesti">
        <div style="font-weight:700"><span class="bulk-count-testi" id="bulkCountTesti">0</span> <span>testimoni dipilih</span></div>
        <form method="POST" id="bulkFormTesti" style="display:flex;gap:0.5rem;flex-wrap:wrap;margin:0">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <button type="submit" name="action" value="bulk_publish" class="bulk-btn-testi publish" onclick="return confirm('Publikasikan terpilih?')">📤 Publish</button>
            <button type="submit" name="action" value="bulk_draft" class="bulk-btn-testi draft" onclick="return confirm('Ubah ke draft?')">📝 Draft</button>
            <button type="submit" name="action" value="bulk_delete" class="bulk-btn-testi delete" onclick="return confirm('HAPUS PERMANEN terpilih?')">🗑️ Hapus</button>
        </form>
        <button class="bulk-btn-testi cancel" onclick="clearTestiSelection()">Batal</button>
    </div>

    <!-- CONTENT -->
    <?php if (empty($testimonis)): ?>
        <div class="empty-testi">
            <div class="empty-testi-icon">💬</div>
            <h3><?= $q || $status_filter ? 'Tidak ada hasil untuk filter ini' : 'Belum ada testimoni' ?></h3>
            <p><?= $q || $status_filter ? 'Coba ubah kata kunci atau filter.' : 'Mulai tambahkan testimoni pertama dari mahasiswa, alumni, atau mitra.' ?></p>
            <button class="btn-testi primary" onclick="openTestiModal()">➕ Tambah Testimoni Pertama</button>
        </div>
    <?php else: ?>
        <div class="testi-grid">
            <?php foreach ($testimonis as $t): ?>
                <div class="testi-card" data-id="<?= $t['id'] ?>">
                    <input type="checkbox" class="row-check-testi" value="<?= $t['id'] ?>" form="bulkFormTesti" name="ids[]" onchange="updateBulkBarTesti()">
                    <div class="testi-card-header">
                        <div class="testi-avatar">
                            <?php if (!empty($t['foto'])): ?>
                                <img src="<?= base_url($t['foto']) ?>" alt="<?= sanitize($t['nama']) ?>">
                            <?php else: ?>
                                <?= strtoupper(substr($t['nama'], 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div class="testi-info">
                            <div class="testi-name"><?= sanitize($t['nama']) ?></div>
                            <div class="testi-role"><?= sanitize($t['jabatan'] ?: '—') ?></div>
                        </div>
                    </div>
                    <div class="testi-rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="<?= $i <= (int)$t['rating'] ? '' : 'empty' ?>">★</span>
                        <?php endfor; ?>
                    </div>
                    <div class="testi-message"><?= excerpt($t['pesan'], 180) ?></div>
                    <div class="testi-meta">
                        <span><?= $t['status'] === 'Published' ? '🟢 Published' : '📝 Draft' ?> • <?= date('d M Y', strtotime($t['created_at'])) ?></span>
                        <div class="testi-actions">
                            <button onclick="openTestiModal(<?= $t['id'] ?>)" title="Edit">✏️</button>
                            <form method="POST" style="display:inline;margin:0" onsubmit="return confirm('Toggle status?')">
                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <button type="submit" title="Toggle"><?= $t['status'] === 'Published' ? '📥' : '📤' ?></button>
                            </form>
                            <form method="POST" style="display:inline;margin:0" onsubmit="return confirm('Hapus testimoni <?= htmlspecialchars(addslashes($t['nama']), ENT_QUOTES) ?>?')">
                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <button type="submit" class="danger" title="Hapus">🗑️</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL FORM -->
<div class="testi-modal" id="testiModal" onclick="if(event.target===this)closeTestiModal()">
    <div class="testi-modal-content">
        <div class="testi-modal-header">
            <h2 id="testiModalTitle">➕ Testimoni Baru</h2>
            <button class="testi-modal-close" onclick="closeTestiModal()">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data" id="testiForm">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="action" value="create" id="testiAction">
            <input type="hidden" name="id" value="0" id="testiId">
            <input type="hidden" name="foto_lama" value="" id="testiFotoLama">
            <div class="testi-modal-body">
                <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem">
                    <div class="testi-avatar-preview" id="testiAvatarPreview">👤</div>
                    <div style="flex:1">
                        <div class="testi-field" style="margin:0">
                            <label>Foto (opsional)</label>
                            <input type="file" name="foto" accept="image/*" onchange="previewTestiFoto(this)">
                        </div>
                        <p style="font-size:0.72rem;color:var(--text-muted);margin:0.25rem 0 0">JPG/PNG/WEBP, maks 2MB</p>
                    </div>
                </div>
                <div class="testi-field">
                    <label>Nama *</label>
                    <input type="text" name="nama" id="testiNama" required maxlength="100" placeholder="mis. Ahmad Fauzi, S.Pd.">
                </div>
                <div class="testi-field">
                    <label>Jabatan / Status</label>
                    <input type="text" name="jabatan" id="testiJabatan" maxlength="100" placeholder="mis. Alumni 2020 - Guru SMA Negeri 1 Maumere">
                </div>
                <div class="testi-field">
                    <label>Pesan Testimoni *</label>
                    <textarea name="pesan" id="testiPesan" required maxlength="1000" placeholder="Kutipan testimoni yang akan ditampilkan di beranda..."></textarea>
                </div>
                <div class="testi-field">
                    <label>Rating</label>
                    <div class="testi-rating-input">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" name="rating" value="<?= $i ?>" id="rating<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>>
                            <label for="rating<?= $i ?>">★</label>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="testi-field">
                    <label>Status</label>
                    <select name="status" id="testiStatus">
                        <option value="Published">🟢 Published (tampil di beranda)</option>
                        <option value="Draft">📝 Draft (disembunyikan)</option>
                    </select>
                </div>
                <div class="testi-modal-actions">
                    <button type="button" class="btn-testi" onclick="closeTestiModal()">Batal</button>
                    <button type="submit" class="btn-testi primary">💾 Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
const testData = <?= json_encode(array_map(fn($t) => [
    'id' => (int)$t['id'],
    'nama' => $t['nama'],
    'jabatan' => $t['jabatan'],
    'foto' => !empty($t['foto']) ? base_url($t['foto']) : '',
    'pesan' => $t['pesan'],
    'rating' => (int)$t['rating'],
    'status' => $t['status'],
], $testimonis), JSON_UNESCAPED_UNICODE) ?>;

function openTestiModal(id) {
    const f = document.getElementById('testiForm');
    f.reset();
    document.getElementById('testiId').value = 0;
    document.getElementById('testiFotoLama').value = '';
    document.getElementById('testiAction').value = 'create';
    document.getElementById('testiAvatarPreview').innerHTML = '👤';

    if (id) {
        const t = testData.find(x => x.id == id);
        if (t) {
            document.getElementById('testiModalTitle').textContent = '✏️ Edit Testimoni';
            document.getElementById('testiAction').value = 'save';
            document.getElementById('testiId').value = t.id;
            document.getElementById('testiNama').value = t.nama;
            document.getElementById('testiJabatan').value = t.jabatan || '';
            document.getElementById('testiPesan').value = t.pesan;
            document.getElementById('testiStatus').value = t.status;
            const r = document.querySelector(`input[name="rating"][value="${t.rating}"]`);
            if (r) r.checked = true;
            if (t.foto) {
                document.getElementById('testiAvatarPreview').innerHTML = `<img src="${t.foto}" alt="">`;
                document.getElementById('testiFotoLama').value = t.foto;
            }
        }
    } else {
        document.getElementById('testiModalTitle').textContent = '➕ Testimoni Baru';
    }
    document.getElementById('testiModal').classList.add('open');
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('testiNama').focus(), 100);
}

function closeTestiModal() {
    document.getElementById('testiModal').classList.remove('open');
    document.body.style.overflow = '';
}

function previewTestiFoto(input) {
    if (input.files && input.files[0]) {
        const r = new FileReader();
        r.onload = e => {
            document.getElementById('testiAvatarPreview').innerHTML = `<img src="${e.target.result}" alt="">`;
        };
        r.readAsDataURL(input.files[0]);
    }
}

function toggleSelectAllTesti(checked) {
    document.querySelectorAll('.row-check-testi').forEach(cb => { cb.checked = checked; });
    updateBulkBarTesti();
}

function updateBulkBarTesti() {
    const c = document.querySelectorAll('.row-check-testi:checked').length;
    document.getElementById('bulkCountTesti').textContent = c;
    document.getElementById('bulkBarTesti').classList.toggle('show', c > 0);
    const all = document.querySelectorAll('.row-check-testi');
    const sa = document.getElementById('selectAllTesti');
    if (sa) sa.checked = all.length > 0 && c === all.length;
}

function clearTestiSelection() {
    document.querySelectorAll('.row-check-testi').forEach(cb => cb.checked = false);
    const sa = document.getElementById('selectAllTesti');
    if (sa) sa.checked = false;
    updateBulkBarTesti();
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeTestiModal();
    if ((e.ctrlKey || e.metaKey) && e.key === 'n' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        openTestiModal();
    }
});

console.log('%c💬 Testimoni Manager - READY','color:#8b5cf6;font-size:16px;font-weight:bold');
console.log('%cCtrl+N = Tambah baru, ESC = Tutup modal','color:#64748b');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>