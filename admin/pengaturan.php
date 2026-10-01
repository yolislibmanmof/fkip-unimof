<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== PROSES UPDATE PENGATURAN =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $errors = [];
    $section = $_POST['section'] ?? 'umum';

    // Handle section-specific updates
    if ($section === 'umum') {
        $settings_to_update = [
            'nama_fakultas' => trim($_POST['nama_fakultas'] ?? ''),
            'nama_universitas' => trim($_POST['nama_universitas'] ?? ''),
            'singkatan' => trim($_POST['singkatan'] ?? ''),
            'alamat' => trim($_POST['alamat'] ?? ''),
            'telepon' => trim($_POST['telepon'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'website' => trim($_POST['website'] ?? ''),
            'deskripsi' => trim($_POST['deskripsi'] ?? '')
        ];
    } elseif ($section === 'seo') {
        $settings_to_update = [
            'meta_title' => trim($_POST['meta_title'] ?? ''),
            'meta_description' => trim($_POST['meta_description'] ?? ''),
            'meta_keywords' => trim($_POST['meta_keywords'] ?? ''),
            'google_analytics' => trim($_POST['google_analytics'] ?? ''),
            'google_search_console' => trim($_POST['google_search_console'] ?? '')
        ];
    } elseif ($section === 'sosial') {
        $settings_to_update = [
            'facebook' => trim($_POST['facebook'] ?? ''),
            'instagram' => trim($_POST['instagram'] ?? ''),
            'twitter' => trim($_POST['twitter'] ?? ''),
            'youtube' => trim($_POST['youtube'] ?? ''),
            'linkedin' => trim($_POST['linkedin'] ?? ''),
            'tiktok' => trim($_POST['tiktok'] ?? ''),
            'whatsapp' => trim($_POST['whatsapp'] ?? '')
        ];
    } elseif ($section === 'akademik') {
        $settings_to_update = [
            'tahun_ajaran_aktif' => trim($_POST['tahun_ajaran_aktif'] ?? ''),
            'semester_aktif' => trim($_POST['semester_aktif'] ?? ''),
            'kalender_akademik' => trim($_POST['kalender_akademik'] ?? ''),
            'visi_fakultas' => trim($_POST['visi_fakultas'] ?? ''),
            'misi_fakultas' => trim($_POST['misi_fakultas'] ?? '')
        ];
    } elseif ($section === 'tampilan') {
        $settings_to_update = [
            'primary_color' => trim($_POST['primary_color'] ?? '#0a6847'),
            'secondary_color' => trim($_POST['secondary_color'] ?? '#16a34a'),
            'theme_mode' => trim($_POST['theme_mode'] ?? 'light'),
            'font_family' => trim($_POST['font_family'] ?? 'Inter'),
            'copyright' => trim($_POST['copyright'] ?? '')
        ];
    } elseif ($section === 'sistem') {
        $settings_to_update = [
            'maintenance_mode' => ($_POST['maintenance_mode'] ?? '') === '1' ? '1' : '0',
            'maintenance_message' => trim($_POST['maintenance_message'] ?? ''),
            'enable_registration' => ($_POST['enable_registration'] ?? '') === '1' ? '1' : '0',
            'timezone' => trim($_POST['timezone'] ?? 'Asia/Makassar'),
            'date_format' => trim($_POST['date_format'] ?? 'd M Y')
        ];
    } elseif ($section === 'branding') {
        $settings_to_update = []; // Branding hanya upload file, jangan sentuh setting teks
    } elseif ($section === 'hero') {
        $settings_to_update = []; // Hero video ditangani terpisah di bawah
    }
    if (!isset($settings_to_update)) $settings_to_update = [];

    foreach ($settings_to_update as $key => $value) {
        try {
            $stmt = $pdo->prepare("INSERT INTO pengaturan (nama_key, nilai, updated_at) VALUES (?, ?, NOW())
                                   ON DUPLICATE KEY UPDATE nilai = VALUES(nilai), updated_at = NOW()");
            $stmt->execute([$key, $value]);
        } catch (PDOException $e) {
            // Try update if insert fails (backward compat)
            $pdo->prepare("UPDATE pengaturan SET nilai = ?, updated_at = NOW() WHERE nama_key = ?")
                ->execute([$value, $key]);
        }
    }

    // Handle Logo Upload
    if (!empty($_FILES['logo']['name'])) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['logo']['tmp_name']);
        $allowed_types = ['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'];
        
        if (in_array($mime, $allowed_types) && $_FILES['logo']['size'] <= 2 * 1024 * 1024) {
            $upload_dir = __DIR__ . '/../assets/images/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $new_filename = 'logo.' . $ext;
            $upload_path = $upload_dir . $new_filename;
            
            foreach (['png', 'jpg', 'jpeg', 'svg', 'webp'] as $old_ext) {
                if (file_exists($upload_dir . 'logo.' . $old_ext)) {
                    @unlink($upload_dir . 'logo.' . $old_ext);
                }
            }
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
                flash_message('success', '✅ Logo berhasil diperbarui.');
            } else {
                $errors[] = '❌ Gagal memindahkan file logo.';
            }
        } else {
            $errors[] = '❌ Format/ukuran logo tidak valid. Max 2MB (JPG, PNG, SVG, WEBP).';
        }
    }

    // Handle Favicon Upload
    if (!empty($_FILES['favicon']['name'])) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['favicon']['tmp_name']);
        $allowed_types = ['image/x-icon', 'image/vnd.microsoft.icon', 'image/png', 'image/svg+xml'];
        
        if (in_array($mime, $allowed_types) && $_FILES['favicon']['size'] <= 1 * 1024 * 1024) {
            $upload_dir = __DIR__ . '/../assets/images/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            $ext = pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION);
            $new_filename = 'favicon.' . ($ext === 'ico' ? 'ico' : 'png');
            $upload_path = $upload_dir . $new_filename;
            
            foreach (['ico', 'png', 'svg'] as $old_ext) {
                if (file_exists($upload_dir . 'favicon.' . $old_ext)) {
                    @unlink($upload_dir . 'favicon.' . $old_ext);
                }
            }
            
            if (move_uploaded_file($_FILES['favicon']['tmp_name'], $upload_path)) {
                flash_message('success', '✅ Favicon berhasil diperbarui.');
            } else {
                $errors[] = '❌ Gagal memindahkan file favicon.';
            }
        } else {
            $errors[] = '❌ Format/ukuran favicon tidak valid. Max 1MB (ICO, PNG, SVG).';
        }
    }

    // ========== Handle Hero Video Upload (Background Beranda) ==========
    if (!empty($_FILES['hero_video']['name']) || !empty($_FILES['hero_poster']['name']) || isset($_POST['save_hero_settings'])) {
        $video_dir = __DIR__ . '/../assets/video/';
        if (!is_dir($video_dir)) @mkdir($video_dir, 0755, true);

        // Toggle aktif/nonaktif
        update_setting('hero_video_active', isset($_POST['hero_video_active']) ? '1' : '0');

        // Upload VIDEO (MP4/WEBM, maks 50MB)
        if (!empty($_FILES['hero_video']['name'])) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['hero_video']['tmp_name']);
            $allowed = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];

            if (isset($allowed[$mime]) && $_FILES['hero_video']['size'] <= 50 * 1024 * 1024) {
                // Hapus SEMUA file hero lama (mp4, webm, poster)
                $old_files = glob($video_dir . 'hero.*');
                if ($old_files) {
                    foreach ($old_files as $old) {
                        if (is_file($old)) {
                            // Coba hapus, jika gagal rename dulu
                            if (!@unlink($old)) {
                                $backup = $old . '.old.' . time();
                                @rename($old, $backup);
                                // Jadwalkan hapus backup nanti
                                register_shutdown_function(function() use ($backup) {
                                    if (is_file($backup)) @unlink($backup);
                                });
                            }
                        }
                    }
                }
                
                // Generate nama file unik dengan timestamp
                $file = 'hero_' . time() . '.' . $allowed[$mime];
                $upload_path = $video_dir . $file;
                
                if (move_uploaded_file($_FILES['hero_video']['tmp_name'], $upload_path)) {
                    // Update setting dengan path baru
                    update_setting('hero_video', 'assets/video/' . $file);
                    flash_message('success', '✅ Video hero berhasil diunggah (' . round($_FILES['hero_video']['size'] / 1024 / 1024, 2) . ' MB).');
                } else {
                    $errors[] = '❌ Gagal menyimpan file video. Periksa permission folder.';
                }
            } else {
                $mime_text = $finfo->file($_FILES['hero_video']['tmp_name']);
                $size_mb = round($_FILES['hero_video']['size'] / 1024 / 1024, 2);
                $errors[] = '❌ Video tidak valid. MIME: ' . $mime_text . ', Size: ' . $size_mb . ' MB (harus MP4/WebM, maks 50MB).';
            }
        }

        // Upload POSTER (JPG/PNG/WEBP, maks 5MB)
        if (!empty($_FILES['hero_poster']['name'])) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['hero_poster']['tmp_name']);
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

            if (isset($allowed[$mime]) && $_FILES['hero_poster']['size'] <= 5 * 1024 * 1024) {
                foreach (glob($video_dir . 'hero-poster.*') as $old) @unlink($old);
                $file = 'hero-poster.' . $allowed[$mime];
                if (move_uploaded_file($_FILES['hero_poster']['tmp_name'], $video_dir . $file)) {
                    update_setting('hero_video_poster', 'assets/video/' . $file);
                    flash_message('success', '✅ Poster hero berhasil diunggah.');
                } else {
                    $errors[] = '❌ Gagal menyimpan poster.';
                }
            } else {
                $errors[] = '❌ Poster harus JPG/PNG/WEBP, maksimal 5MB.';
            }
        }
    }

    // Delete hero video
    if (($_POST['action'] ?? '') === 'delete_hero_video') {
        $video_dir = __DIR__ . '/../assets/video/';
        foreach (glob($video_dir . 'hero.*') as $old) @unlink($old);
        update_setting('hero_video', '');
        flash_message('success', '🗑️ Video hero dihapus.');
    }

    if (empty($errors)) {
        flash_message('success', '✅ Pengaturan berhasil disimpan.');
    } else {
        foreach ($errors as $err) flash_message('error', $err);
    }
    
    header('Location: pengaturan.php?tab=' . $section);
    exit;
}

// ===== AMBIL DATA PENGATURAN =====
$settings = [];
$stmt = $pdo->query("SELECT * FROM pengaturan");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['nama_key']] = $row['nilai'];
}

// Cek file yang ada
$current_logo = '';
foreach (['png', 'jpg', 'jpeg', 'svg', 'webp'] as $ext) {
    if (file_exists(__DIR__ . '/../assets/images/logo.' . $ext)) {
        $current_logo = 'logo.' . $ext;
        break;
    }
}

$current_favicon = '';
foreach (['ico', 'png', 'svg'] as $ext) {
    if (file_exists(__DIR__ . '/../assets/images/favicon.' . $ext)) {
        $current_favicon = 'favicon.' . $ext;
        break;
    }
}

// ===== Hero Video Data =====
$hero_video_path  = $settings['hero_video'] ?? '';
$hero_poster_path = $settings['hero_video_poster'] ?? '';
$hero_active      = ($settings['hero_video_active'] ?? '1') === '1';

// Cek file fisik untuk memastikan
$current_hero_video = '';
$current_hero_mime = 'video/mp4'; // Default
if (!empty($hero_video_path) && file_exists(__DIR__ . '/../' . $hero_video_path)) {
    $current_hero_video = $hero_video_path;
    $ext = strtolower(pathinfo($hero_video_path, PATHINFO_EXTENSION));
    $current_hero_mime = ($ext === 'webm') ? 'video/webm' : 'video/mp4';
} else {
    foreach (['mp4', 'webm'] as $ext) {
        if (file_exists(__DIR__ . '/../assets/video/hero.' . $ext)) {
            $current_hero_video = 'assets/video/hero.' . $ext;
            $current_hero_mime = ($ext === 'webm') ? 'video/webm' : 'video/mp4';
            break;
        }
    }
}

$current_hero_poster = '';
if (!empty($hero_poster_path) && file_exists(__DIR__ . '/../' . $hero_poster_path)) {
    $current_hero_poster = $hero_poster_path;
} else {
    foreach (['jpg', 'png', 'webp'] as $ext) {
        if (file_exists(__DIR__ . '/../assets/video/hero-poster.' . $ext)) {
            $current_hero_poster = 'assets/video/hero-poster.' . $ext;
            break;
        }
    }
}

// System info
$php_version = PHP_VERSION;
$server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$db_size = 'N/A';
try {
    $db_size_result = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.TABLES WHERE table_schema = DATABASE()")->fetch();
    $db_size = $db_size_result['size_mb'] . ' MB';
} catch (Exception $e) {}

// ===== HITUNG TOTAL USERS (tabel: admins) =====
$total_users = 0;
try {
    $total_users = (int)$pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
} catch (Exception $e) {
    $total_users = 0;
}

// ===== HITUNG TOTAL KONTEN (hanya tabel yang pasti ada) =====
$total_content = 0;
foreach (['berita', 'agenda', 'prestasi', 'riset', 'jurnal', 'kerjasama', 'alumni', 'fasilitas', 'akreditasi', 'beasiswa'] as $table) {
    try {
        $total_content += (int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    } catch (Exception $e) {
        // Tabel tidak ada → lewati dengan aman
        continue;
    }
}

$disk_free = 'N/A';
$disk_total = 'N/A';
try {
    $free = @disk_free_space(__DIR__);
    $total_space = @disk_total_space(__DIR__);
    if ($free !== false) $disk_free = round($free / 1024 / 1024 / 1024, 2) . ' GB';
    if ($total_space !== false) $disk_total = round($total_space / 1024 / 1024 / 1024, 2) . ' GB';
} catch (Exception $e) {}

$uploads_size = '0 MB';
$upload_dir = __DIR__ . '/../uploads/';
try {
    if (is_dir($upload_dir)) {
        $size_bytes = 0;
        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($upload_dir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($rii as $file) {
            if ($file->isFile()) $size_bytes += $file->getSize();
        }
        $uploads_size = round($size_bytes / 1024 / 1024, 2) . ' MB';
    }
} catch (Exception $e) {
    $uploads_size = 'N/A';
}

// System health score
$health_checks = [
    'php_version' => version_compare($php_version, '7.4.0', '>='),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'mbstring' => extension_loaded('mbstring'),
    'gd' => extension_loaded('gd'),
    'json' => extension_loaded('json'),
    'uploads_writable' => is_writable(__DIR__ . '/../uploads/'),
    'assets_writable' => is_writable(__DIR__ . '/../assets/'),
    'openssl' => extension_loaded('openssl'),
];
$health_score = round((array_sum($health_checks) / count($health_checks)) * 100);

$active_tab = $_GET['tab'] ?? 'umum';
$csrf = generate_csrf_token();
$active_menu = 'pengaturan';
$page_heading = 'Pengaturan Sistem';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Control Panel', null]];

require __DIR__ . '/includes/header.php';
?>

<style>
/* ===== PAGE HERO (Settings Theme - Emerald/Green) ===== */
.settings-hero {
    background: linear-gradient(135deg, #047857 0%, #059669 50%, #10b981 100%);
    color: white;
    padding: 2rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(4,120,87,0.3);
}
.settings-hero::before {
    content: '';
    position: absolute;
    top: -50%; right: -15%;
    width: 450px; height: 450px;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
.settings-hero::after {
    content: '⚙️';
    position: absolute;
    bottom: -30px; right: 2rem;
    font-size: 12rem;
    color: rgba(255,255,255,0.05);
    pointer-events: none;
    line-height: 1;
    animation: spin 60s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.settings-hero-content {
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
    flex-wrap: wrap;
    z-index: 1;
}
.settings-hero h2 {
    font-family: 'Georgia', serif;
    font-size: 1.75rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
}
.settings-hero p { opacity: 0.95; font-size: 0.95rem; max-width: 500px; line-height: 1.6; }
.hero-stats { display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1rem; }
.hero-stat {
    display: flex; flex-direction: column; align-items: center;
    padding: 0.5rem 1rem;
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.2);
    min-width: 90px;
}
.hero-stat-num { font-size: 1.5rem; font-weight: 900; line-height: 1; font-family: 'Georgia', serif; }
.hero-stat-label { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.9; margin-top: 0.25rem; }

.health-ring { width: 110px; height: 110px; position: relative; flex-shrink: 0; }
.health-ring svg { transform: rotate(-90deg); width: 100%; height: 100%; }
.health-ring .ring-bg { fill: none; stroke: rgba(255,255,255,0.2); stroke-width: 8; }
.health-ring .ring-fill { fill: none; stroke: white; stroke-width: 8; stroke-linecap: round; }
.health-value {
    position: absolute; inset: 0;
    display: flex; flex-direction: column; align-items: center; justify-content: center; color: white;
}
.health-value .score-num { font-size: 1.85rem; font-weight: 900; line-height: 1; font-family: 'Georgia', serif; }
.health-value .score-label { font-size: 0.65rem; opacity: 0.9; margin-top: 0.2rem; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== TABS NAVIGATION ===== */
.settings-tabs {
    display: flex;
    gap: 0.25rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 0.5rem;
    margin-bottom: 1.5rem;
    overflow-x: auto;
    scrollbar-width: none;
    box-shadow: var(--shadow-sm);
}
.settings-tabs::-webkit-scrollbar { display: none; }
.settings-tab {
    padding: 0.75rem 1.25rem;
    background: transparent;
    border: none;
    border-radius: var(--radius-md);
    font-weight: 600;
    font-size: 0.88rem;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-family: inherit;
}
.settings-tab:hover { background: var(--bg-secondary); color: var(--text-primary); }
.settings-tab.active {
    background: linear-gradient(135deg, #047857, #059669);
    color: white;
    box-shadow: 0 4px 12px rgba(4,120,87,0.3);
}
.settings-tab .tab-count {
    background: rgba(255,255,255,0.2);
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 800;
}
.settings-tab:not(.active) .tab-count { background: var(--bg-tertiary); color: var(--text-muted); }

/* ===== LAYOUT ===== */
.settings-layout {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 2rem;
    align-items: start;
}
.settings-card {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 2rem;
    box-shadow: var(--shadow-sm);
    position: relative;
    overflow: hidden;
}
.settings-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, #047857, #059669, #10b981);
}
.settings-card h3 {
    font-size: 1.2rem;
    font-weight: 800;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: var(--text-primary);
    font-family: 'Georgia', serif;
}
.tab-content { display: none; animation: tabFadeIn 0.3s; }
.tab-content.active { display: block; }
@keyframes tabFadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

/* ===== FORM ELEMENTS ===== */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
.form-grid .full-width { grid-column: 1 / -1; }
.form-group { margin-bottom: 0; }
.form-label {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}
.form-label .required { color: #ef4444; margin-left: 0.15rem; }
.form-label .label-icon {
    width: 18px; height: 18px;
    background: rgba(5,150,105,0.1);
    color: #059669;
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
    border-color: #059669;
    box-shadow: 0 0 0 4px rgba(5,150,105,0.1);
}
.form-input:focus { background: var(--bg-primary); }
.form-hint {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.35rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* Color Input */
.color-input-wrap {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}
.color-input-wrap input[type="color"] {
    width: 50px; height: 44px;
    border: 2px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    padding: 2px;
}
.color-input-wrap input[type="text"] { flex: 1; font-family: monospace; }
.color-presets { display: flex; gap: 0.4rem; margin-top: 0.5rem; }
.color-preset {
    width: 28px; height: 28px;
    border-radius: 6px;
    cursor: pointer;
    border: 2px solid transparent;
    transition: all 0.2s;
}
.color-preset:hover { transform: scale(1.1); border-color: var(--text-primary); }

/* Toggle Switch */
.toggle-switch {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    user-select: none;
}
.toggle-switch input { display: none; }
.toggle-slider {
    position: relative;
    width: 48px; height: 26px;
    background: var(--bg-tertiary);
    border-radius: 999px;
    transition: all 0.3s;
}
.toggle-slider::before {
    content: '';
    position: absolute;
    top: 3px; left: 3px;
    width: 20px; height: 20px;
    background: white;
    border-radius: 50%;
    transition: all 0.3s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.toggle-switch input:checked + .toggle-slider {
    background: linear-gradient(135deg, #059669, #10b981);
}
.toggle-switch input:checked + .toggle-slider::before {
    transform: translateX(22px);
}
.toggle-label { font-weight: 600; color: var(--text-primary); font-size: 0.9rem; }
.toggle-desc { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem; }

/* Upload Zone */
.upload-zone {
    border: 2px dashed var(--border);
    border-radius: var(--radius-lg);
    padding: 2rem;
    text-align: center;
    background: var(--bg-secondary);
    transition: all 0.3s;
    cursor: pointer;
    position: relative;
}
.upload-zone:hover, .upload-zone.dragover {
    border-color: #059669;
    background: rgba(5,150,105,0.03);
    transform: translateY(-2px);
}
.upload-zone.dragover { border-style: solid; box-shadow: 0 0 0 4px rgba(5,150,105,0.1); }
.upload-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.upload-icon {
    font-size: 3rem;
    margin-bottom: 0.75rem;
    opacity: 0.6;
    transition: all 0.3s;
}
.upload-zone:hover .upload-icon { opacity: 1; transform: scale(1.1); }
.upload-text { font-size: 1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem; }
.upload-sub { font-size: 0.8rem; color: var(--text-muted); }

.preview-box {
    margin-top: 1rem;
    padding: 1rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    gap: 1rem;
    animation: fadeIn 0.3s ease;
}
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.preview-img-container {
    width: 80px; height: 80px;
    border-radius: 12px;
    background: var(--bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 1px solid var(--border);
    flex-shrink: 0;
}
.preview-img-container img { width: 100%; height: 100%; object-fit: contain; padding: 0.5rem; background: white; }
.preview-info { flex: 1; text-align: left; }
.preview-info h4 { font-size: 0.9rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem; word-break: break-all; }
.preview-info small { font-size: 0.75rem; color: var(--text-muted); }
.btn-remove {
    background: #fee2e2;
    color: #dc2626;
    border: none;
    width: 32px; height: 32px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.btn-remove:hover { background: #dc2626; color: white; transform: rotate(90deg); }

.current-file-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 0.85rem;
    background: rgba(5,150,105,0.1);
    border: 1px solid rgba(5,150,105,0.2);
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 600;
    color: #047857;
    margin-top: 0.75rem;
}

/* Save Button */
.btn-save-extreme {
    width: 100%;
    padding: 1rem;
    background: linear-gradient(135deg, #047857, #059669);
    color: white;
    border: none;
    border-radius: var(--radius-md);
    font-family: inherit;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 1.5rem;
}
.btn-save-extreme:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(5,150,105,0.35);
}
.btn-save-extreme .spinner {
    width: 18px; height: 18px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin-fast 0.7s linear infinite;
    display: none;
}
@keyframes spin-fast { to { transform: rotate(360deg); } }
.btn-save-extreme.loading .spinner { display: inline-block; }
.btn-save-extreme.loading .btn-text { display: none; }

/* ===== RIGHT PANEL: LIVE PREVIEW ===== */
.preview-panel {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 1.5rem;
    box-shadow: var(--shadow-lg);
    position: sticky;
    top: 100px;
}
.preview-panel-header {
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.preview-panel-header h3 {
    font-family: 'Georgia', serif;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Mock Browser */
.mock-browser {
    background: #1f2937;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}
.mock-browser-bar {
    padding: 0.5rem 0.75rem;
    background: #374151;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.mock-dots { display: flex; gap: 0.25rem; }
.mock-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
}
.mock-dot.red { background: #ef4444; }
.mock-dot.yellow { background: #f59e0b; }
.mock-dot.green { background: #10b981; }
.mock-url {
    flex: 1;
    background: #1f2937;
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    font-size: 0.72rem;
    color: #9ca3af;
    font-family: monospace;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.mock-url::before { content: '🔒'; font-size: 0.65rem; }

/* Mock Header */
.mock-header {
    background: white;
    padding: 0.75rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border-bottom: 2px solid;
    border-color: var(--live-primary, #059669);
}
.mock-logo {
    width: 36px; height: 36px;
    border-radius: 8px;
    background: var(--live-primary, #059669);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 800;
    font-size: 0.85rem;
    overflow: hidden;
    flex-shrink: 0;
}
.mock-logo img { width: 100%; height: 100%; object-fit: contain; padding: 0.25rem; background: white; }
.mock-brand { flex: 1; min-width: 0; }
.mock-brand-name {
    font-size: 0.78rem;
    font-weight: 800;
    color: #1f2937;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.mock-brand-sub {
    font-size: 0.62rem;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.mock-nav { display: flex; gap: 0.5rem; }
.mock-nav-item {
    font-size: 0.65rem;
    color: #64748b;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
}
.mock-nav-item:first-child {
    background: var(--live-primary, #059669);
    color: white;
}

/* Mock Hero */
.mock-hero {
    background: linear-gradient(135deg, var(--live-primary, #059669), var(--live-secondary, #10b981));
    padding: 1.5rem 1rem;
    color: white;
    text-align: center;
}
.mock-hero-title {
    font-size: 0.95rem;
    font-weight: 800;
    margin-bottom: 0.35rem;
    font-family: 'Georgia', serif;
}
.mock-hero-sub { font-size: 0.68rem; opacity: 0.9; }
.mock-hero-btn {
    display: inline-block;
    margin-top: 0.5rem;
    padding: 0.3rem 0.85rem;
    background: white;
    color: var(--live-primary, #059669);
    border-radius: 999px;
    font-size: 0.65rem;
    font-weight: 700;
}

/* Mock Content */
.mock-content {
    background: #f8fafc;
    padding: 1rem;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
}
.mock-card {
    background: white;
    border-radius: 6px;
    padding: 0.6rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.mock-card-img {
    width: 100%;
    height: 30px;
    background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
    border-radius: 4px;
    margin-bottom: 0.35rem;
}
.mock-card-title {
    font-size: 0.6rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 0.2rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.mock-card-desc {
    font-size: 0.55rem;
    color: #64748b;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Mock Footer */
.mock-footer {
    background: #1f2937;
    color: #9ca3af;
    padding: 0.6rem 1rem;
    font-size: 0.6rem;
    text-align: center;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.mock-socials { display: flex; gap: 0.3rem; }
.mock-social {
    width: 16px; height: 16px;
    background: rgba(255,255,255,0.1);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
}
.mock-social.has-link { background: var(--live-primary, #059669); color: white; }

/* Preview info */
.preview-info {
    margin-top: 1rem;
    padding: 0.75rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    font-size: 0.78rem;
}
.preview-info-title {
    font-weight: 700;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.preview-info-list { list-style: none; padding: 0; margin: 0; }
.preview-info-list li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.3rem 0;
    border-bottom: 1px dashed var(--border);
    font-size: 0.75rem;
}
.preview-info-list li:last-child { border-bottom: none; }
.preview-info-list strong { color: var(--text-primary); }
.preview-info-list span { color: var(--text-muted); font-family: monospace; font-size: 0.72rem; }

/* System health checks */
.health-checks {
    margin-top: 1rem;
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
}
.health-checks h4 {
    font-size: 0.88rem;
    margin-bottom: 0.75rem;
    font-family: 'Georgia', serif;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.health-check-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px dashed var(--border);
    font-size: 0.85rem;
}
.health-check-item:last-child { border-bottom: none; }
.health-check-item .check-name { color: var(--text-primary); font-weight: 500; }
.health-check-item .check-status {
    padding: 0.2rem 0.65rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
}
.check-status.ok { background: #dcfce7; color: #166534; }
.check-status.warn { background: #fef3c7; color: #92400e; }
.check-status.fail { background: #fee2e2; color: #991b1b; }

/* System stats grid */
.system-stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
    margin-top: 1rem;
}
.system-stat-item {
    padding: 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    text-align: center;
}
.system-stat-icon { font-size: 1.75rem; margin-bottom: 0.35rem; }
.system-stat-value {
    font-family: 'Georgia', serif;
    font-size: 1.25rem;
    font-weight: 800;
    color: #047857;
    margin-bottom: 0.15rem;
}
.system-stat-label {
    font-size: 0.7rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 600;
}

/* ===== HERO VIDEO UPLOAD (Tab Hero) ===== */
.settings-card h3 + p {
    font-size: 0.88rem;
    color: var(--text-muted);
    line-height: 1.6;
    margin-bottom: 1.5rem;
}

@media (max-width: 1024px) {
    .settings-layout { grid-template-columns: 1fr; }
    .preview-panel { position: static; }
}
@media (max-width: 768px) {
    .form-grid { grid-template-columns: 1fr; }
    .settings-tabs { gap: 0.15rem; padding: 0.4rem; }
    .settings-tab { padding: 0.6rem 0.9rem; font-size: 0.82rem; }
    .mock-nav { display: none; }
    .mock-content { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 640px) {
    .settings-card { padding: 1.25rem; }
    .hero-stats { gap: 0.5rem; }
    .hero-stat { min-width: 75px; padding: 0.4rem 0.75rem; }
    .mock-content { grid-template-columns: 1fr; }
    .system-stats-grid { grid-template-columns: 1fr; }
}
</style>

<!-- ===== HERO BANNER ===== -->
<div class="settings-hero" data-aos="fade-down">
    <div class="settings-hero-content">
        <div>
            <h2>⚙️ Control Panel</h2>
            <p>Kelola konfigurasi sistem, identitas fakultas, dan pengaturan website FKIP UNIMOF secara terpusat.</p>
            <div class="hero-stats">
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= count($settings) ?></span>
                    <span class="hero-stat-label">Settings</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $total_users ?></span>
                    <span class="hero-stat-label">Users</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $total_content ?></span>
                    <span class="hero-stat-label">Content</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $db_size ?></span>
                    <span class="hero-stat-label">Database</span>
                </div>
            </div>
        </div>
        <div class="health-ring" title="System Health Score">
            <svg viewBox="0 0 36 36">
                <circle cx="18" cy="18" r="15.915" class="ring-bg"/>
                <circle cx="18" cy="18" r="15.915" class="ring-fill" style="stroke-dasharray: <?= $health_score ?>, 100"/>
            </svg>
            <div class="health-value">
                <div class="score-num"><?= $health_score ?></div>
                <div class="score-label">Health</div>
            </div>
        </div>
    </div>
</div>

<!-- ===== TABS NAVIGATION ===== -->
<div class="settings-tabs" data-aos="fade-up">
    <button class="settings-tab <?= $active_tab === 'umum' ? 'active' : '' ?>" onclick="switchTab('umum', this)">🏛️ Umum</button>
    <button class="settings-tab <?= $active_tab === 'seo' ? 'active' : '' ?>" onclick="switchTab('seo', this)">🔍 SEO & Meta</button>
    <button class="settings-tab <?= $active_tab === 'sosial' ? 'active' : '' ?>" onclick="switchTab('sosial', this)">🌐 Social Media</button>
    <button class="settings-tab <?= $active_tab === 'akademik' ? 'active' : '' ?>" onclick="switchTab('akademik', this)">🎓 Akademik</button>
    <button class="settings-tab <?= $active_tab === 'tampilan' ? 'active' : '' ?>" onclick="switchTab('tampilan', this)">🎨 Tampilan</button>
    <button class="settings-tab <?= $active_tab === 'branding' ? 'active' : '' ?>" onclick="switchTab('branding', this)">🎨 Branding</button>
    <button class="settings-tab <?= $active_tab === 'hero' ? 'active' : '' ?>" onclick="switchTab('hero', this)">🎬 Hero Video</button>
    <button class="settings-tab <?= $active_tab === 'sistem' ? 'active' : '' ?>" onclick="switchTab('sistem', this)">🔧 Sistem <span class="tab-count"><?= $health_score ?>%</span></button>
</div>

<!-- ===== MAIN LAYOUT ===== -->
<div class="settings-layout">
    <!-- LEFT: FORMS -->
    <div>
        <!-- Tab: Umum -->
        <form method="POST" enctype="multipart/form-data" class="tab-content <?= $active_tab === 'umum' ? 'active' : '' ?>" data-tab="umum">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="section" value="umum">
            <div class="settings-card">
                <h3>🏛️ Identitas Fakultas</h3>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label class="form-label"><span class="label-icon">🏛️</span> Nama Fakultas <span class="required">*</span></label>
                        <input type="text" name="nama_fakultas" class="form-input live-update" data-target="brandName" required value="<?= sanitize($settings['nama_fakultas'] ?? '') ?>" placeholder="Fakultas Keguruan dan Ilmu Pendidikan">
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label"><span class="label-icon">🎓</span> Nama Universitas <span class="required">*</span></label>
                        <input type="text" name="nama_universitas" class="form-input live-update" data-target="brandSub" required value="<?= sanitize($settings['nama_universitas'] ?? '') ?>" placeholder="Universitas Muhammadiyah Maumere">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon">🏷️</span> Singkatan <span class="required">*</span></label>
                        <input type="text" name="singkatan" class="form-input live-update" data-target="logoText" required value="<?= sanitize($settings['singkatan'] ?? '') ?>" placeholder="FKIP UNIMOF" maxlength="15">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon">📞</span> Telepon</label>
                        <input type="text" name="telepon" class="form-input" value="<?= sanitize($settings['telepon'] ?? '') ?>" placeholder="(0382) 21234">
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label"><span class="label-icon">✉️</span> Email Resmi</label>
                        <input type="email" name="email" class="form-input" value="<?= sanitize($settings['email'] ?? '') ?>" placeholder="fkip@unimof.ac.id">
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label"><span class="label-icon">🌐</span> Website</label>
                        <input type="url" name="website" class="form-input" value="<?= sanitize($settings['website'] ?? '') ?>" placeholder="https://fkip.unimof.ac.id">
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label"><span class="label-icon">📍</span> Alamat Lengkap</label>
                        <textarea name="alamat" class="form-textarea" rows="2" placeholder="Jl.示例 No. 123, Maumere, NTT"><?= sanitize($settings['alamat'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label"><span class="label-icon">📝</span> Deskripsi Singkat</label>
                        <textarea name="deskripsi" class="form-textarea live-update" data-target="heroSub" rows="2" placeholder="Deskripsi fakultas untuk halaman depan..."><?= sanitize($settings['deskripsi'] ?? '') ?></textarea>
                    </div>
                </div>
                <button type="submit" class="btn-save-extreme" id="saveBtnUmum">
                    <span class="btn-text">💾 Simpan Pengaturan Umum</span>
                    <span class="spinner"></span>
                </button>
            </div>
        </form>

        <!-- Tab: SEO -->
        <form method="POST" class="tab-content <?= $active_tab === 'seo' ? 'active' : '' ?>" data-tab="seo">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="section" value="seo">
            <div class="settings-card">
                <h3>🔍 SEO & Meta Tags</h3>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label class="form-label"><span class="label-icon">📑</span> Meta Title</label>
                        <input type="text" name="meta_title" class="form-input" value="<?= sanitize($settings['meta_title'] ?? '') ?>" placeholder="FKIP UNIMOF - Fakultas Keguruan dan Ilmu Pendidikan" maxlength="70">
                        <div class="form-hint" style="justify-content: space-between;">
                            <span>📏 Ideal: 50-60 karakter</span>
                            <span><span id="metaTitleCount"><?= strlen($settings['meta_title'] ?? '') ?></span>/70</span>
                        </div>
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label"><span class="label-icon">📝</span> Meta Description</label>
                        <textarea name="meta_description" class="form-textarea" rows="2" maxlength="160" placeholder="Deskripsi website untuk Google search results..."><?= sanitize($settings['meta_description'] ?? '') ?></textarea>
                        <div class="form-hint" style="justify-content: space-between;">
                            <span>📏 Ideal: 150-160 karakter</span>
                            <span><span id="metaDescCount"><?= strlen($settings['meta_description'] ?? '') ?></span>/160</span>
                        </div>
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label"><span class="label-icon">🔑</span> Meta Keywords</label>
                        <input type="text" name="meta_keywords" class="form-input" value="<?= sanitize($settings['meta_keywords'] ?? '') ?>" placeholder="fkip, unimof, pendidikan, keguruan, maumere">
                        <div class="form-hint">Pisahkan dengan koma</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon">📊</span> Google Analytics ID</label>
                        <input type="text" name="google_analytics" class="form-input" value="<?= sanitize($settings['google_analytics'] ?? '') ?>" placeholder="G-XXXXXXXXXX">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon">🔍</span> Google Search Console</label>
                        <input type="text" name="google_search_console" class="form-input" value="<?= sanitize($settings['google_search_console'] ?? '') ?>" placeholder="Verification code">
                    </div>
                </div>

                <!-- Google Preview Mock -->
                <div style="margin-top: 1.5rem; padding: 1.25rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border);">
                    <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; margin-bottom: 0.75rem;">👁️ Preview Google Search</div>
                    <div style="background: white; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <div style="font-size: 0.72rem; color: #202124; margin-bottom: 0.25rem; font-family: monospace;">
                            <?= sanitize($settings['website'] ?? 'fkip.unimof.ac.id') ?>
                        </div>
                        <div style="font-size: 1.1rem; color: #1a0dab; font-weight: 500; margin-bottom: 0.35rem; font-family: 'Georgia', serif;" id="googlePreviewTitle">
                            <?= sanitize($settings['meta_title'] ?: ($settings['nama_fakultas'] ?? 'FKIP UNIMOF')) ?>
                        </div>
                        <div style="font-size: 0.85rem; color: #4d5156; line-height: 1.5;" id="googlePreviewDesc">
                            <?= sanitize($settings['meta_description'] ?: ($settings['deskripsi'] ?? 'Deskripsi fakultas akan muncul di sini...')) ?>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-save-extreme">
                    <span class="btn-text">💾 Simpan Pengaturan SEO</span>
                    <span class="spinner"></span>
                </button>
            </div>
        </form>

        <!-- Tab: Social Media -->
        <form method="POST" class="tab-content <?= $active_tab === 'sosial' ? 'active' : '' ?>" data-tab="sosial">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="section" value="sosial">
            <div class="settings-card">
                <h3>🌐 Social Media Links</h3>
                <div class="form-grid">
                    <?php
                    $socials = [
                        'facebook_url' => ['icon' => '📘', 'label' => 'Facebook', 'placeholder' => 'https://facebook.com/fkipunimof'],
                        'instagram_url' => ['icon' => '📷', 'label' => 'Instagram', 'placeholder' => 'https://instagram.com/fkipunimof'],
                        'twitter' => ['icon' => '🐦', 'label' => 'Twitter/X', 'placeholder' => 'https://twitter.com/fkipunimof'],
                        'youtube_url' => ['icon' => '📺', 'label' => 'YouTube', 'placeholder' => 'https://youtube.com/@fkipunimof'],
                        'linkedin' => ['icon' => '💼', 'label' => 'LinkedIn', 'placeholder' => 'https://linkedin.com/school/fkipunimof'],
                        'tiktok' => ['icon' => '🎵', 'label' => 'TikTok', 'placeholder' => 'https://tiktok.com/@fkipunimof'],
                        'whatsapp_number' => ['icon' => '📱', 'label' => 'WhatsApp', 'placeholder' => '+6281234567890']
                    ];
                    foreach ($socials as $key => $info):
                    ?>
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon"><?= $info['icon'] ?></span> <?= $info['label'] ?></label>
                        <input type="text" name="<?= $key ?>" class="form-input live-social" data-social="<?= $key ?>" value="<?= sanitize($settings[$key] ?? '') ?>" placeholder="<?= $info['placeholder'] ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn-save-extreme">
                    <span class="btn-text">💾 Simpan Social Media</span>
                    <span class="spinner"></span>
                </button>
            </div>
        </form>

        <!-- Tab: Akademik -->
        <form method="POST" class="tab-content <?= $active_tab === 'akademik' ? 'active' : '' ?>" data-tab="akademik">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="section" value="akademik">
            <div class="settings-card">
                <h3>🎓 Informasi Akademik</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon">📅</span> Tahun Ajaran Aktif</label>
                        <input type="text" name="tahun_ajaran_aktif" class="form-input" value="<?= sanitize($settings['tahun_ajaran_aktif'] ?? '') ?>" placeholder="2025/2026">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon">📚</span> Semester Aktif</label>
                        <select name="semester_aktif" class="form-select">
                            <option value="Ganjil" <?= ($settings['semester_aktif'] ?? '') === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                            <option value="Genap" <?= ($settings['semester_aktif'] ?? '') === 'Genap' ? 'selected' : '' ?>>Genap</option>
                            <option value="Antara" <?= ($settings['semester_aktif'] ?? '') === 'Antara' ? 'selected' : '' ?>>Antara (Pendek)</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label"><span class="label-icon">📆</span> Link Kalender Akademik</label>
                        <input type="url" name="kalender_akademik" class="form-input" value="<?= sanitize($settings['kalender_akademik'] ?? '') ?>" placeholder="https://fkip.unimof.ac.id/kalender">
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label"><span class="label-icon">🎯</span> Visi Fakultas</label>
                        <textarea name="visi_fakultas" class="form-textarea" rows="3" placeholder="Visi fakultas..."><?= sanitize($settings['visi_fakultas'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label"><span class="label-icon">🚀</span> Misi Fakultas</label>
                        <textarea name="misi_fakultas" class="form-textarea" rows="5" placeholder="Satu misi per baris..."><?= sanitize($settings['misi_fakultas'] ?? '') ?></textarea>
                        <div class="form-hint">💡 Satu misi per baris</div>
                    </div>
                </div>
                <button type="submit" class="btn-save-extreme">
                    <span class="btn-text">💾 Simpan Informasi Akademik</span>
                    <span class="spinner"></span>
                </button>
            </div>
        </form>

        <!-- Tab: Tampilan -->
        <form method="POST" class="tab-content <?= $active_tab === 'tampilan' ? 'active' : '' ?>" data-tab="tampilan">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="section" value="tampilan">
            <div class="settings-card">
                <h3>🎨 Pengaturan Tampilan</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon">🎨</span> Primary Color</label>
                        <div class="color-input-wrap">
                            <input type="color" name="primary_color" class="live-color" data-target="primary" value="<?= sanitize($settings['primary_color'] ?? '#0a6847') ?>">
                            <input type="text" class="form-input" value="<?= sanitize($settings['primary_color'] ?? '#0a6847') ?>" readonly style="font-family: monospace;">
                        </div>
                        <div class="color-presets">
                            <div class="color-preset" style="background:#0a6847" onclick="setColor('primary','#0a6847')" title="Emerald"></div>
                            <div class="color-preset" style="background:#1e40af" onclick="setColor('primary','#1e40af')" title="Blue"></div>
                            <div class="color-preset" style="background:#7c3aed" onclick="setColor('primary','#7c3aed')" title="Purple"></div>
                            <div class="color-preset" style="background:#dc2626" onclick="setColor('primary','#dc2626')" title="Red"></div>
                            <div class="color-preset" style="background:#ea580c" onclick="setColor('primary','#ea580c')" title="Orange"></div>
                            <div class="color-preset" style="background:#0891b2" onclick="setColor('primary','#0891b2')" title="Cyan"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon">🎨</span> Secondary Color</label>
                        <div class="color-input-wrap">
                            <input type="color" name="secondary_color" class="live-color" data-target="secondary" value="<?= sanitize($settings['secondary_color'] ?? '#16a34a') ?>">
                            <input type="text" class="form-input" value="<?= sanitize($settings['secondary_color'] ?? '#16a34a') ?>" readonly style="font-family: monospace;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon">🌓</span> Theme Mode</label>
                        <select name="theme_mode" class="form-select">
                            <option value="light" <?= ($settings['theme_mode'] ?? 'light') === 'light' ? 'selected' : '' ?>>☀️ Light Mode</option>
                            <option value="dark" <?= ($settings['theme_mode'] ?? '') === 'dark' ? 'selected' : '' ?>>🌙 Dark Mode</option>
                            <option value="auto" <?= ($settings['theme_mode'] ?? '') === 'auto' ? 'selected' : '' ?>>🔄 Auto (System)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon">🔤</span> Font Family</label>
                        <select name="font_family" class="form-select">
                            <option value="Inter" <?= ($settings['font_family'] ?? 'Inter') === 'Inter' ? 'selected' : '' ?>>Inter (Modern)</option>
                            <option value="Roboto" <?= ($settings['font_family'] ?? '') === 'Roboto' ? 'selected' : '' ?>>Roboto (Clean)</option>
                            <option value="Poppins" <?= ($settings['font_family'] ?? '') === 'Poppins' ? 'selected' : '' ?>>Poppins (Friendly)</option>
                            <option value="Lora" <?= ($settings['font_family'] ?? '') === 'Lora' ? 'selected' : '' ?>>Lora (Serif)</option>
                            <option value="Montserrat" <?= ($settings['font_family'] ?? '') === 'Montserrat' ? 'selected' : '' ?>>Montserrat (Bold)</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label"><span class="label-icon">©️</span> Copyright Text</label>
                        <input type="text" name="copyright" class="form-input live-update" data-target="copyright" value="<?= sanitize($settings['copyright'] ?? '') ?>" placeholder="© 2026 FKIP UNIMOF. All rights reserved.">
                    </div>
                </div>
                <button type="submit" class="btn-save-extreme">
                    <span class="btn-text">💾 Simpan Pengaturan Tampilan</span>
                    <span class="spinner"></span>
                </button>
            </div>
        </form>

        <!-- Tab: Branding -->
        <form method="POST" enctype="multipart/form-data" class="tab-content <?= $active_tab === 'branding' ? 'active' : '' ?>" data-tab="branding">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="section" value="branding">
            <div class="settings-card">
                <h3>🎨 Logo & Favicon</h3>
                
                <!-- Logo Upload -->
                <div style="margin-bottom: 2rem;">
                    <label class="form-label"><span class="label-icon">🖼️</span> Logo Institusi</label>
                    <div class="upload-zone" id="logoZone">
                        <input type="file" id="logoInput" name="logo" accept="image/jpeg,image/png,image/svg+xml,image/webp">
                        <div class="upload-icon">📷</div>
                        <div class="upload-text">Drag & drop logo di sini</div>
                        <div class="upload-sub">PNG/SVG/WEBP, max 2MB. Kotak (1:1) ideal.</div>
                    </div>
                    
                    <?php if ($current_logo): ?>
                        <div class="current-file-badge">
                            <span>✅ Logo aktif:</span>
                            <strong><?= strtoupper(pathinfo($current_logo, PATHINFO_EXTENSION)) ?></strong>
                        </div>
                    <?php endif; ?>

                    <div class="preview-box" id="logoPreview" style="display: none;">
                        <div class="preview-img-container">
                            <img id="logoPreviewImg" src="" alt="Preview">
                        </div>
                        <div class="preview-info">
                            <h4 id="logoFileName">-</h4>
                            <small id="logoFileSize">-</small>
                        </div>
                        <button type="button" class="btn-remove" onclick="removeFile('logo')">✕</button>
                    </div>
                </div>

                <!-- Favicon Upload -->
                <div>
                    <label class="form-label"><span class="label-icon">🔖</span> Favicon</label>
                    <div class="upload-zone" id="faviconZone">
                        <input type="file" id="faviconInput" name="favicon" accept="image/x-icon,image/png,image/svg+xml">
                        <div class="upload-icon">🔖</div>
                        <div class="upload-text">Drag & drop favicon di sini</div>
                        <div class="upload-sub">ICO/PNG/SVG, max 1MB. 32x32px ideal.</div>
                    </div>

                    <?php if ($current_favicon): ?>
                        <div class="current-file-badge">
                            <span>✅ Favicon aktif:</span>
                            <strong><?= strtoupper(pathinfo($current_favicon, PATHINFO_EXTENSION)) ?></strong>
                        </div>
                    <?php endif; ?>

                    <div class="preview-box" id="faviconPreview" style="display: none;">
                        <div class="preview-img-container">
                            <img id="faviconPreviewImg" src="" alt="Preview">
                        </div>
                        <div class="preview-info">
                            <h4 id="faviconFileName">-</h4>
                            <small id="faviconFileSize">-</small>
                        </div>
                        <button type="button" class="btn-remove" onclick="removeFile('favicon')">✕</button>
                    </div>
                </div>

                <button type="submit" class="btn-save-extreme">
                    <span class="btn-text">💾 Upload & Simpan Branding</span>
                    <span class="spinner"></span>
                </button>
            </div>
        </form>

        <!-- Tab: Hero Video -->
        <form method="POST" enctype="multipart/form-data" class="tab-content <?= $active_tab === 'hero' ? 'active' : '' ?>" data-tab="hero">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="section" value="hero">
            <div class="settings-card">
                <h3>🎬 Video Hero — Background Beranda</h3>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem; line-height: 1.6;">
                    Video diputar otomatis sebagai background hero halaman utama (ala FEB UGM).
                    Partikel & teks overlay tetap tampil di atas video.
                </p>

                <!-- Toggle Aktif -->
                <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border); margin-bottom: 1.5rem;">
                    <label class="toggle-switch">
                        <input type="checkbox" name="hero_video_active" value="1" <?= $hero_active ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                        <div>
                            <div class="toggle-label">🎬 Aktifkan Video Hero</div>
                            <div class="toggle-desc">Matikan untuk kembali ke hero default (tanpa video)</div>
                        </div>
                    </label>
                </div>

                <!-- Preview video saat ini -->
                <?php if ($current_hero_video): ?>
                <div style="margin-bottom: 1.5rem;">
                    <label class="form-label"><span class="label-icon">🎥</span> Video Aktif Saat Ini</label>
                    <video src="<?= base_url($current_hero_video) ?>?v=<?= time() ?>" muted loop autoplay playsinline
                           style="width: 100%; max-width: 480px; border-radius: 12px; border: 1px solid var(--border); display: block; background: #000;">
                        <source src="<?= base_url($current_hero_video) ?>?v=<?= time() ?>" type="<?= $current_hero_mime ?>">
                        Browser Anda tidak mendukung video HTML5.
                    </video>
                    <div class="current-file-badge" style="margin-top: 0.75rem;">
                        <span>✅</span>
                        <strong><?= basename($current_hero_video) ?></strong>
                        <span style="opacity: 0.7; margin-left: auto;">
                            <?php 
                            $vid_path = __DIR__ . '/../' . $current_hero_video;
                            echo is_file($vid_path) ? round(filesize($vid_path) / 1024 / 1024, 2) . ' MB' : 'File tidak ditemukan';
                            ?>
                        </span>
                    </div>
                    <div style="margin-top: 0.5rem; font-size: 0.75rem; color: var(--text-muted);">
                        💡 Jika video tidak berubah setelah upload, tekan <kbd>Ctrl+Shift+R</kbd> untuk hard refresh.
                    </div>
                </div>
                <?php endif; ?>

                <!-- Preview poster saat ini -->
                <?php if ($current_hero_poster): ?>
                <div style="margin-bottom: 1.5rem;">
                    <label class="form-label"><span class="label-icon">🖼️</span> Poster / Fallback Image</label>
                    <img src="<?= base_url($current_hero_poster) ?>" alt="Poster hero"
                         style="width: 100%; max-width: 480px; border-radius: 12px; border: 1px solid var(--border); display: block;">
                    <div class="current-file-badge" style="margin-top: 0.75rem;">
                        <span>✅</span>
                        <strong><?= basename($current_hero_poster) ?></strong>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-grid">
                    <!-- Upload Video -->
                    <div class="form-group full-width">
                        <label class="form-label"><span class="label-icon">🎥</span> File Video (MP4/WebP)</label>
                        <div class="upload-zone" id="heroVideoZone">
                            <input type="file" id="heroVideoInput" name="hero_video" accept="video/mp4,video/webm">
                            <div class="upload-icon">🎬</div>
                            <div class="upload-text">Drag & drop video di sini</div>
                            <div class="upload-sub">Format <strong>MP4</strong> atau <strong>WebM</strong> • Maks <strong>50MB</strong> • Durasi ideal 10–30 detik (loop)</div>
                        </div>
                        <div class="preview-box" id="heroVideoPreview" style="display: none;">
                            <div class="preview-img-container" style="width: 120px; height: 68px;">
                                <video id="heroVideoPreviewVid" style="width:100%;height:100%;object-fit:cover" muted></video>
                            </div>
                            <div class="preview-info">
                                <h4 id="heroVideoName">-</h4>
                                <small id="heroVideoSize">-</small>
                            </div>
                            <button type="button" class="btn-remove" onclick="clearHeroFile('heroVideoInput','heroVideoPreview')">✕</button>
                        </div>
                    </div>

                    <!-- Upload Poster -->
                    <div class="form-group full-width">
                        <label class="form-label"><span class="label-icon">🖼️</span> Poster / Gambar Cadangan</label>
                        <div class="upload-zone" id="heroPosterZone">
                            <input type="file" id="heroPosterInput" name="hero_poster" accept="image/jpeg,image/png,image/webp">
                            <div class="upload-icon">🌄</div>
                            <div class="upload-text">Drag & drop poster di sini</div>
                            <div class="upload-sub">Muncul saat koneksi lambat, hemat data, atau di mobile • <strong>JPG/PNG/WEBP</strong> • Maks 5MB</div>
                        </div>
                        <div class="preview-box" id="heroPosterPreview" style="display: none;">
                            <div class="preview-img-container" style="width: 120px; height: 68px;">
                                <img id="heroPosterPreviewImg" src="" alt="Preview">
                            </div>
                            <div class="preview-info">
                                <h4 id="heroPosterName">-</h4>
                                <small id="heroPosterSize">-</small>
                            </div>
                            <button type="button" class="btn-remove" onclick="clearHeroFile('heroPosterInput','heroPosterPreview')">✕</button>
                        </div>
                    </div>
                </div>

                <!-- Tips -->
                <div style="margin-top: 1.5rem; padding: 1rem; background: linear-gradient(135deg, rgba(5,150,105,0.08), rgba(16,185,129,0.05)); border: 1px solid rgba(5,150,105,0.2); border-radius: var(--radius-md); font-size: 0.82rem; color: var(--text-secondary);">
                    <div style="font-weight: 700; color: #059669; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.4rem;">
                        💡 Tips Video Hero yang Baik
                    </div>
                    <ul style="margin: 0; padding-left: 1.25rem; line-height: 1.7;">
                        <li>Durasi <strong>10–30 detik</strong>, di-loop otomatis</li>
                        <li>Resolusi <strong>1920×1080 (Full HD)</strong> atau 1280×720 (HD)</li>
                        <li>Tanpa audio (muted) — video akan dimute di browser</li>
                        <li>Konten <strong>campus life, gedung, mahasiswa belajar</strong> cocok untuk FKIP</li>
                        <li>Compress dengan <a href="https://handbrake.fr" target="_blank">HandBrake</a> atau <a href="https://squoosh.app" target="_blank">Squoosh</a> untuk ukuran optimal</li>
                    </ul>
                </div>

                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                    <button type="submit" name="save_hero_settings" value="1" class="btn-save-extreme" style="flex: 1; min-width: 200px; margin-top: 1.5rem;">
                        <span class="btn-text">💾 Simpan & Unggah</span>
                        <span class="spinner"></span>
                    </button>
                    <?php if ($current_hero_video): ?>
                    <button type="submit" name="action" value="delete_hero_video" class="btn-save-extreme"
                            style="flex: 0 0 auto; min-width: auto; padding: 1rem 1.5rem; background: #fee2e2; color: #dc2626; margin-top: 1.5rem;"
                            onclick="return confirm('Hapus video hero? Hero akan kembali ke tampilan default.')">
                        🗑️ Hapus
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <!-- Tab: Sistem -->
        <form method="POST" class="tab-content <?= $active_tab === 'sistem' ? 'active' : '' ?>" data-tab="sistem">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="section" value="sistem">
            <div class="settings-card">
                <h3>🔧 Konfigurasi Sistem</h3>
                
                <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1.5rem;">
                    <!-- Maintenance Mode -->
                    <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border);">
                        <label class="toggle-switch">
                            <input type="checkbox" name="maintenance_mode" value="1" <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                            <div>
                                <div class="toggle-label">🚧 Maintenance Mode</div>
                                <div class="toggle-desc">Aktifkan untuk menampilkan halaman maintenance ke pengunjung</div>
                            </div>
                        </label>
                        <textarea name="maintenance_message" class="form-textarea" rows="2" placeholder="Pesan maintenance..." style="margin-top: 0.75rem;"><?= sanitize($settings['maintenance_message'] ?? 'Website sedang dalam pemeliharaan. Silakan kembali beberapa saat lagi.') ?></textarea>
                    </div>

                    <!-- Enable Registration -->
                    <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border);">
                        <label class="toggle-switch">
                            <input type="checkbox" name="enable_registration" value="1" <?= ($settings['enable_registration'] ?? '1') === '1' ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                            <div>
                                <div class="toggle-label">👥 Pendaftaran User Baru</div>
                                <div class="toggle-desc">Izinkan user baru mendaftar ke sistem</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon">🌏</span> Timezone</label>
                        <select name="timezone" class="form-select">
                            <option value="Asia/Makassar" <?= ($settings['timezone'] ?? 'Asia/Makassar') === 'Asia/Makassar' ? 'selected' : '' ?>>WITA (Asia/Makassar)</option>
                            <option value="Asia/Jakarta" <?= ($settings['timezone'] ?? '') === 'Asia/Jakarta' ? 'selected' : '' ?>>WIB (Asia/Jakarta)</option>
                            <option value="Asia/Jayapura" <?= ($settings['timezone'] ?? '') === 'Asia/Jayapura' ? 'selected' : '' ?>>WIT (Asia/Jayapura)</option>
                            <option value="UTC" <?= ($settings['timezone'] ?? '') === 'UTC' ? 'selected' : '' ?>>UTC</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><span class="label-icon">📅</span> Format Tanggal</label>
                        <select name="date_format" class="form-select">
                            <option value="d M Y" <?= ($settings['date_format'] ?? 'd M Y') === 'd M Y' ? 'selected' : '' ?>>27 Sep 2026</option>
                            <option value="d/m/Y" <?= ($settings['date_format'] ?? '') === 'd/m/Y' ? 'selected' : '' ?>>27/09/2026</option>
                            <option value="m/d/Y" <?= ($settings['date_format'] ?? '') === 'm/d/Y' ? 'selected' : '' ?>>09/27/2026</option>
                            <option value="Y-m-d" <?= ($settings['date_format'] ?? '') === 'Y-m-d' ? 'selected' : '' ?>>2026-09-27</option>
                            <option value="d F Y" <?= ($settings['date_format'] ?? '') === 'd F Y' ? 'selected' : '' ?>>27 September 2026</option>
                        </select>
                    </div>
                </div>

                <!-- System Health Checks -->
                <div class="health-checks">
                    <h4>🩺 System Health Checks</h4>
                    <?php
                    $check_labels = [
                        'php_version' => 'PHP Version ≥ 7.4 (' . $php_version . ')',
                        'pdo_mysql' => 'PDO MySQL Extension',
                        'mbstring' => 'Mbstring Extension',
                        'gd' => 'GD Library (Image Processing)',
                        'json' => 'JSON Extension',
                        'openssl' => 'OpenSSL Extension',
                        'uploads_writable' => 'Uploads Directory Writable',
                        'assets_writable' => 'Assets Directory Writable'
                    ];
                    foreach ($health_checks as $key => $ok):
                    ?>
                    <div class="health-check-item">
                        <span class="check-name"><?= $check_labels[$key] ?? $key ?></span>
                        <span class="check-status <?= $ok ? 'ok' : 'fail' ?>"><?= $ok ? '✓ OK' : '✗ FAIL' ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- System Stats -->
                <div class="system-stats-grid">
                    <div class="system-stat-item">
                        <div class="system-stat-icon">🐘</div>
                        <div class="system-stat-value"><?= $php_version ?></div>
                        <div class="system-stat-label">PHP Version</div>
                    </div>
                    <div class="system-stat-item">
                        <div class="system-stat-icon">💾</div>
                        <div class="system-stat-value"><?= $db_size ?></div>
                        <div class="system-stat-label">Database Size</div>
                    </div>
                    <div class="system-stat-item">
                        <div class="system-stat-icon">📁</div>
                        <div class="system-stat-value"><?= $uploads_size ?></div>
                        <div class="system-stat-label">Uploads Size</div>
                    </div>
                    <div class="system-stat-item">
                        <div class="system-stat-icon">💿</div>
                        <div class="system-stat-value"><?= $disk_free ?></div>
                        <div class="system-stat-label">Free Disk</div>
                    </div>
                </div>

                <button type="submit" class="btn-save-extreme">
                    <span class="btn-text">💾 Simpan Konfigurasi Sistem</span>
                    <span class="spinner"></span>
                </button>
            </div>
        </form>
    </div>

    <!-- RIGHT: LIVE PREVIEW -->
    <div class="preview-panel" data-aos="fade-left">
        <div class="preview-panel-header">
            <h3>👁️ Live Preview</h3>
            <span style="font-size: 0.72rem; color: var(--text-muted);">Real-time</span>
        </div>

        <!-- Mock Browser -->
        <div class="mock-browser" id="mockBrowser">
            <div class="mock-browser-bar">
                <div class="mock-dots">
                    <div class="mock-dot red"></div>
                    <div class="mock-dot yellow"></div>
                    <div class="mock-dot green"></div>
                </div>
                <div class="mock-url" id="mockUrl"><?= sanitize($settings['website'] ?? 'fkip.unimof.ac.id') ?></div>
            </div>
            
            <div class="mock-header">
                <div class="mock-logo" id="mockLogo">
                    <?php if ($current_logo): ?>
                        <img src="<?= base_url('assets/images/' . $current_logo) ?>" alt="Logo">
                    <?php else: ?>
                        <span id="logoText"><?= strtoupper(substr($settings['singkatan'] ?? 'FKIP', 0, 4)) ?></span>
                    <?php endif; ?>
                </div>
                <div class="mock-brand">
                    <div class="mock-brand-name" id="brandName"><?= sanitize($settings['nama_fakultas'] ?? 'Nama Fakultas') ?></div>
                    <div class="mock-brand-sub" id="brandSub"><?= sanitize($settings['nama_universitas'] ?? 'Nama Universitas') ?></div>
                </div>
                <div class="mock-nav">
                    <div class="mock-nav-item">Home</div>
                    <div class="mock-nav-item">Profil</div>
                    <div class="mock-nav-item">Akademik</div>
                </div>
            </div>

            <div class="mock-hero">
                <div class="mock-hero-title">Selamat Datang di FKIP</div>
                <div class="mock-hero-sub" id="heroSub"><?= sanitize($settings['deskripsi'] ?? 'Fakultas Keguruan dan Ilmu Pendidikan') ?></div>
                <div class="mock-hero-btn">Jelajahi →</div>
            </div>

            <div class="mock-content">
                <div class="mock-card">
                    <div class="mock-card-img"></div>
                    <div class="mock-card-title">Berita Terkini</div>
                    <div class="mock-card-desc">Lorem ipsum dolor sit amet...</div>
                </div>
                <div class="mock-card">
                    <div class="mock-card-img"></div>
                    <div class="mock-card-title">Pengumuman</div>
                    <div class="mock-card-desc">Consectetur adipiscing elit...</div>
                </div>
                <div class="mock-card">
                    <div class="mock-card-img"></div>
                    <div class="mock-card-title">Agenda</div>
                    <div class="mock-card-desc">Sed do eiusmod tempor...</div>
                </div>
            </div>

            <div class="mock-footer">
                <span id="copyright"><?= sanitize($settings['copyright'] ?? '© 2026 FKIP UNIMOF') ?></span>
                <div class="mock-socials">
                    <div class="mock-social <?= !empty($settings['facebook']) ? 'has-link' : '' ?>" id="socialFb">f</div>
                    <div class="mock-social <?= !empty($settings['instagram']) ? 'has-link' : '' ?>" id="socialIg">📷</div>
                    <div class="mock-social <?= !empty($settings['youtube']) ? 'has-link' : '' ?>" id="socialYt">▶</div>
                    <div class="mock-social <?= !empty($settings['twitter']) ? 'has-link' : '' ?>" id="socialTw">🐦</div>
                </div>
            </div>
        </div>

        <!-- Preview Info -->
        <div class="preview-info">
            <div class="preview-info-title">📊 Info Preview</div>
            <ul class="preview-info-list">
                <li><strong>Theme</strong> <span><?= ucfirst($settings['theme_mode'] ?? 'Light') ?></span></li>
                <li><strong>Font</strong> <span><?= $settings['font_family'] ?? 'Inter' ?></span></li>
                <li><strong>Primary</strong> <span id="previewPrimaryColor"><?= $settings['primary_color'] ?? '#0a6847' ?></span></li>
                <li><strong>Logo</strong> <span><?= $current_logo ? '✅ Active' : '❌ None' ?></span></li>
                <li><strong>Favicon</strong> <span><?= $current_favicon ? '✅ Active' : '❌ None' ?></span></li>
            </ul>
        </div>
    </div>
</div>

<script>
// ===== TAB SWITCHING =====
function switchTab(tab, btn) {
    document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    document.querySelector(`.tab-content[data-tab="${tab}"]`).classList.add('active');
    
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url);
}

// ===== LIVE UPDATE PREVIEW =====
document.querySelectorAll('.live-update').forEach(el => {
    el.addEventListener('input', function() {
        const target = this.dataset.target;
        const targetEl = document.getElementById(target);
        if (targetEl) targetEl.textContent = this.value || '-';
    });
});

// ===== LIVE SOCIAL PREVIEW =====
document.querySelectorAll('.live-social').forEach(el => {
    el.addEventListener('input', function() {
        const social = this.dataset.social;
        const map = { facebook: 'socialFb', instagram: 'socialIg', youtube: 'socialYt', twitter: 'socialTw' };
        const targetEl = document.getElementById(map[social]);
        if (targetEl) {
            if (this.value.trim()) targetEl.classList.add('has-link');
            else targetEl.classList.remove('has-link');
        }
    });
});

// ===== COLOR PICKER =====
document.querySelectorAll('.live-color').forEach(el => {
    el.addEventListener('input', function() {
        const target = this.dataset.target;
        const val = this.value;
        const browser = document.getElementById('mockBrowser');
        if (target === 'primary') {
            browser.style.setProperty('--live-primary', val);
            document.getElementById('previewPrimaryColor').textContent = val;
            // Update the readonly text input next to it
            const textInput = this.parentElement.querySelector('input[type="text"]');
            if (textInput) textInput.value = val;
        } else if (target === 'secondary') {
            browser.style.setProperty('--live-secondary', val);
            const textInput = this.parentElement.querySelector('input[type="text"]');
            if (textInput) textInput.value = val;
        }
    });
});

function setColor(target, color) {
    const input = document.querySelector(`.live-color[data-target="${target}"]`);
    if (input) {
        input.value = color;
        input.dispatchEvent(new Event('input'));
    }
}

// Set initial colors
document.addEventListener('DOMContentLoaded', () => {
    const primary = '<?= sanitize($settings['primary_color'] ?? '#0a6847') ?>';
    const secondary = '<?= sanitize($settings['secondary_color'] ?? '#16a34a') ?>';
    const browser = document.getElementById('mockBrowser');
    browser.style.setProperty('--live-primary', primary);
    browser.style.setProperty('--live-secondary', secondary);
});

// ===== UPLOAD ZONE =====
function setupUploadZone(zoneId, inputId, previewId, imgId, nameId, sizeId) {
    const zone = document.getElementById(zoneId);
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    const img = document.getElementById(imgId);
    const fileName = document.getElementById(nameId);
    const fileSize = document.getElementById(sizeId);

    ['dragenter', 'dragover'].forEach(ev => {
        zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.add('dragover'); });
    });
    ['dragleave', 'drop'].forEach(ev => {
        zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.remove('dragover'); });
    });
    
    zone.addEventListener('drop', e => {
        if (e.dataTransfer.files.length > 0) {
            input.files = e.dataTransfer.files;
            handleFile(input.files[0], preview, img, fileName, fileSize, zoneId === 'logoZone');
        }
    });
    
    input.addEventListener('change', e => {
        if (e.target.files.length > 0) {
            handleFile(e.target.files[0], preview, img, fileName, fileSize, zoneId === 'logoZone');
        }
    });
}

function handleFile(file, preview, img, fileName, fileSize, isLogo) {
    if (!file.type.startsWith('image/')) {
        alert('Hanya file gambar yang diizinkan!');
        return;
    }
    
    fileName.textContent = file.name;
    fileSize.textContent = formatFileSize(file.size);
    
    const reader = new FileReader();
    reader.onload = e => {
        img.src = e.target.result;
        preview.style.display = 'flex';
        
        // Update live preview logo if it's a logo upload
        if (isLogo) {
            const mockLogo = document.getElementById('mockLogo');
            mockLogo.innerHTML = `<img src="${e.target.result}" alt="Logo">`;
        }
    };
    reader.readAsDataURL(file);
}

function removeFile(type) {
    document.getElementById(type + 'Input').value = '';
    document.getElementById(type + 'Preview').style.display = 'none';
    if (type === 'logo') {
        const mockLogo = document.getElementById('mockLogo');
        const singkatan = document.querySelector('[data-target="logoText"]')?.value || 'FKIP';
        mockLogo.innerHTML = `<span id="logoText">${singkatan.substring(0, 4).toUpperCase()}</span>`;
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

setupUploadZone('logoZone', 'logoInput', 'logoPreview', 'logoPreviewImg', 'logoFileName', 'logoFileSize');
setupUploadZone('faviconZone', 'faviconInput', 'faviconPreview', 'faviconPreviewImg', 'faviconFileName', 'faviconFileSize');

// ===== SEO CHARACTER COUNTERS =====
const metaTitleInput = document.querySelector('[name="meta_title"]');
const metaDescInput = document.querySelector('[name="meta_description"]');
const metaTitleCount = document.getElementById('metaTitleCount');
const metaDescCount = document.getElementById('metaDescCount');
const googlePreviewTitle = document.getElementById('googlePreviewTitle');
const googlePreviewDesc = document.getElementById('googlePreviewDesc');

metaTitleInput?.addEventListener('input', function() {
    metaTitleCount.textContent = this.value.length;
    googlePreviewTitle.textContent = this.value || '<?= sanitize($settings['nama_fakultas'] ?? 'FKIP UNIMOF') ?>';
});
metaDescInput?.addEventListener('input', function() {
    metaDescCount.textContent = this.value.length;
    googlePreviewDesc.textContent = this.value || '<?= sanitize($settings['deskripsi'] ?? 'Deskripsi...') ?>';
});

// ===== FORM SUBMISSION =====
document.querySelectorAll('form[data-tab]').forEach(form => {
    form.addEventListener('submit', function(e) {
        const btn = this.querySelector('.btn-save-extreme');
        if (btn) {
            btn.classList.add('loading');
            btn.disabled = true;
        }
    });
});

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        const activeTab = document.querySelector('.tab-content.active form, .tab-content.active');
        const form = activeTab.tagName === 'FORM' ? activeTab : activeTab.querySelector('form');
        if (form) form.requestSubmit();
    }
});

// ===== HERO VIDEO UPLOAD HANDLER =====
function setupHeroUpload(zoneId, inputId, previewId, nameId, sizeId, previewMediaId, isVideo) {
    const zone = document.getElementById(zoneId);
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    const media = document.getElementById(previewMediaId);
    const fileName = document.getElementById(nameId);
    const fileSize = document.getElementById(sizeId);

    if (!zone || !input) return;

    ['dragenter', 'dragover'].forEach(ev => {
        zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.add('dragover'); });
    });
    ['dragleave', 'drop'].forEach(ev => {
        zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.remove('dragover'); });
    });

    zone.addEventListener('drop', e => {
        if (e.dataTransfer.files.length > 0) {
            input.files = e.dataTransfer.files;
            handleHeroFile(input.files[0], preview, media, fileName, fileSize, isVideo);
        }
    });

    input.addEventListener('change', e => {
        if (e.target.files.length > 0) {
            handleHeroFile(e.target.files[0], preview, media, fileName, fileSize, isVideo);
        }
    });
}

function handleHeroFile(file, preview, media, fileName, fileSize, isVideo) {
    if (!file) return;

    if (isVideo && !file.type.startsWith('video/')) {
        alert('Hanya file video (MP4/WebM) yang diizinkan!');
        return;
    }
    if (!isVideo && !file.type.startsWith('image/')) {
        alert('Hanya file gambar (JPG/PNG/WEBP) yang diizinkan!');
        return;
    }

    fileName.textContent = file.name;
    fileSize.textContent = formatFileSize(file.size);

    const url = URL.createObjectURL(file);
    if (isVideo) {
        media.src = url;
        media.play().catch(() => {});
    } else {
        media.src = url;
    }
    preview.style.display = 'flex';
}

function clearHeroFile(inputId, previewId) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    if (input) input.value = '';
    if (preview) preview.style.display = 'none';
}

// Setup hero video & poster upload zones (only run on hero tab)
if (document.querySelector('[data-tab="hero"]')) {
    setupHeroUpload('heroVideoZone', 'heroVideoInput', 'heroVideoPreview', 'heroVideoName', 'heroVideoSize', 'heroVideoPreviewVid', true);
    setupHeroUpload('heroPosterZone', 'heroPosterInput', 'heroPosterPreview', 'heroPosterName', 'heroPosterSize', 'heroPosterPreviewImg', false);
}

console.log('%c⚙️ Control Panel FKIP UNIMOF - Super Extreme', 'color: #059669; font-size: 16px; font-weight: bold;');
console.log('%c7 tabs: Umum, SEO, Social, Akademik, Tampilan, Branding, Sistem', 'color: #64748b;');
console.log('%cShortcut: Ctrl+S (Save current tab)', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>