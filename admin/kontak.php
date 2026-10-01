<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== Helper: deteksi pesan prioritas =====
if (!function_exists('is_priority_msg')) {
    function is_priority_msg(string $text): bool {
        return (bool)preg_match('/mendesak|urgent|segera|penting|darurat|deadline|asesmen|cepat|tolong|help/i', $text);
    }
}
if (!function_exists('detect_sentiment')) {
    function detect_sentiment(string $text): string {
        $positive = preg_match('/terima kasih|bagus|senang|puas|baik|excellent|great|amazing/i', $text);
        $negative = preg_match('/kecewa|buruk|jelek|marah|komplain|lambat|tidak puas|bad|angry/i', $text);
        if ($negative) return 'negative';
        if ($positive) return 'positive';
        return 'neutral';
    }
}
if (!function_exists('auto_tag')) {
    function auto_tag(string $text): array {
        $tags = [];
        if (preg_match('/pmb|pendaftaran|mahasiswa baru|daftar|registrasi/i', $text)) $tags[] = 'PMB';
        if (preg_match('/beasiswa|bantuan|biaya|kuliah gratis/i', $text)) $tags[] = 'Beasiswa';
        if (preg_match('/kerjasama|mo[au]|partnership|kolaborasi/i', $text)) $tags[] = 'Kerjasama';
        if (preg_match('/akreditasi|ban-pt|sertifikasi/i', $text)) $tags[] = 'Akreditasi';
        if (preg_match('/kurikulum|silabus|mata kuliah/i', $text)) $tags[] = 'Kurikulum';
        if (preg_match('/kkn|magang|ppl|praktik/i', $text)) $tags[] = 'Praktik';
        if (preg_match('/ijazah|transkrip|legalisir|skl/i', $text)) $tags[] = 'Dokumen';
        if (preg_match('/komplain|keluhan|masalah|problem/i', $text)) $tags[] = 'Pengaduan';
        return $tags;
    }
}

// ===== DETEKSI SKEMA TABEL kontak (cegah fatal error kolom tidak ada) =====
$kontak_cols = ['status', 'updated_at', 'catatan_admin'];
$kontak_schema = [];
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `kontak`")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($kontak_cols as $c) $kontak_schema[$c] = in_array($c, $cols, true);
} catch (Exception $e) {
    $kontak_schema = array_fill_keys($kontak_cols, false);
}

// Cek apakah enum status mendukung nilai baru (Arsip, Prioritas)
$extended_statuses = [];
try {
    $row = $pdo->query("SHOW COLUMNS FROM `kontak` LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
    if ($row && preg_match_all("/'([^']+)'/", $row['Type'], $m)) {
        $extended_statuses = $m[1];
    }
} catch (Exception $e) {}
$has_extended_status = in_array('Arsip', $extended_statuses, true) && in_array('Prioritas', $extended_statuses, true);

// ===== PROSES AKSI POST (SCHEMA-SAFE) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('error', '❌ Token tidak valid.');
    } else {
        $action = $_POST['action'] ?? '';
        $id  = (int)($_POST['id'] ?? 0);
        $ids = array_map('intval', (array)($_POST['ids'] ?? []));
        $ids = array_values(array_filter($ids));

        // Helper: bangun SET clause dinamis
        $buildSet = function(string $status) use ($kontak_schema, $has_extended_status): array {
            // Fallback status jika enum belum mendukung
            if (in_array($status, ['Arsip', 'Prioritas'], true) && !$has_extended_status) {
                $status = 'Dibaca'; // fallback aman
            }
            $parts  = ["status = ?"];
            $params = [$status];
            if ($kontak_schema['updated_at']) {
                $parts[]  = "updated_at = NOW()";
            }
            return ['set' => implode(', ', $parts), 'params' => $params];
        };

        try {
            // ===== SINGLE ACTIONS =====
            if (in_array($action, ['read', 'replied', 'archive', 'priority'], true) && $id > 0) {
                $statusMap = [
                    'read'     => 'Dibaca',
                    'replied'  => 'Dibalas',
                    'archive'  => 'Arsip',
                    'priority' => 'Prioritas',
                ];
                $targetStatus = $statusMap[$action];

                // Cek dukungan status
                if (in_array($targetStatus, ['Arsip', 'Prioritas'], true) && !$has_extended_status) {
                    flash_message('error', '⚠️ Fitur "' . $targetStatus . '" belum didukung skema. Jalankan patch SQL untuk mengaktifkan.');
                    $targetStatus = 'Dibaca';
                }

                $s = $buildSet($targetStatus);
                $pdo->prepare("UPDATE kontak SET {$s['set']} WHERE id = ?")
                    ->execute(array_merge($s['params'], [$id]));

                $labels = [
                    'Dibaca'    => '✅ Pesan ditandai sudah dibaca.',
                    'Dibalas'   => '✅ Pesan ditandai telah dibalas. 👍',
                    'Arsip'     => '📦 Pesan diarsipkan.',
                    'Prioritas' => '🔥 Pesan ditandai sebagai prioritas.',
                ];
                flash_message('success', $labels[$targetStatus]);

            } elseif ($action === 'delete' && $id > 0) {
                $pdo->prepare("DELETE FROM kontak WHERE id = ?")->execute([$id]);
                flash_message('success', '🗑️ Pesan dihapus.');

            // ===== BULK ACTIONS =====
            } elseif (in_array($action, ['bulk_read', 'bulk_replied', 'bulk_archive', 'bulk_priority'], true) && !empty($ids)) {
                $statusMap = [
                    'bulk_read'     => 'Dibaca',
                    'bulk_replied'  => 'Dibalas',
                    'bulk_archive'  => 'Arsip',
                    'bulk_priority' => 'Prioritas',
                ];
                $targetStatus = $statusMap[$action];

                if (in_array($targetStatus, ['Arsip', 'Prioritas'], true) && !$has_extended_status) {
                    flash_message('error', '⚠️ Fitur bulk "' . $targetStatus . '" belum didukung. Pakai Dibaca/Dibalas dulu.');
                    $targetStatus = 'Dibaca';
                }

                $ph = implode(',', array_fill(0, count($ids), '?'));
                $s = $buildSet($targetStatus);
                $pdo->prepare("UPDATE kontak SET {$s['set']} WHERE id IN ($ph)")
                    ->execute(array_merge($s['params'], $ids));

                $labels = [
                    'Dibaca'    => '✅ ' . count($ids) . ' pesan ditandai dibaca.',
                    'Dibalas'   => '✅ ' . count($ids) . ' pesan ditandai dibalas.',
                    'Arsip'     => '📦 ' . count($ids) . ' pesan diarsipkan.',
                    'Prioritas' => '🔥 ' . count($ids) . ' pesan ditandai prioritas.',
                ];
                flash_message('success', $labels[$targetStatus]);

            } elseif ($action === 'bulk_delete' && !empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $pdo->prepare("DELETE FROM kontak WHERE id IN ($ph)")->execute($ids);
                flash_message('success', '🗑️ ' . count($ids) . ' pesan dihapus.');

            // ===== ADD NOTE =====
            } elseif ($action === 'add_note' && $id > 0 && !empty($_POST['note'])) {
                if ($kontak_schema['catatan_admin']) {
                    $setParts = ["catatan_admin = CONCAT(IFNULL(catatan_admin,''), ?, '\n---\n')"];
                    $params   = [trim($_POST['note']) . ' (' . date('d/m/Y H:i') . ')'];
                    if ($kontak_schema['updated_at']) {
                        $setParts[] = "updated_at = NOW()";
                    }
                    $params[] = $id;
                    $pdo->prepare("UPDATE kontak SET " . implode(', ', $setParts) . " WHERE id = ?")
                        ->execute($params);
                    flash_message('success', '📝 Catatan internal ditambahkan.');
                } else {
                    flash_message('error', '⚠️ Kolom catatan belum tersedia. Jalankan patch SQL untuk mengaktifkan.');
                }
            }

        } catch (PDOException $e) {
            error_log('[KONTAK ACTION ERROR] ' . $e->getMessage());
            flash_message('error', '❌ Gagal memproses: ' . htmlspecialchars(substr($e->getMessage(), 0, 120)));
        }
    }
    header('Location: kontak.php?' . http_build_query($_GET));
    exit;
}

// ===== Export =====
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    $all_kontak = $pdo->query("SELECT * FROM kontak ORDER BY created_at DESC")->fetchAll();

    if ($format === 'csv' && !empty($all_kontak)) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="pesan-masuk-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['ID','Nama','Email','Telepon','Subjek','Pesan','Status','Tanggal']);
        foreach ($all_kontak as $r) {
            fputcsv($out, [$r['id'],$r['nama'],$r['email'],$r['telepon']??'',$r['subjek']??'',$r['pesan'],$r['status'],$r['created_at']]);
        }
        fclose($out);
        exit;
    }

    if ($format === 'json' && !empty($all_kontak)) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="pesan-masuk-' . date('Y-m-d') . '.json"');
        echo json_encode([
            'exported_at' => date('c'),
            'total' => count($all_kontak),
            'data' => $all_kontak
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ===== Filter, Search, Pagination =====
$filter  = $_GET['filter'] ?? 'semua';
$q       = trim($_GET['q'] ?? '');
$sort    = $_GET['sort'] ?? 'terbaru';
$view    = $_GET['view'] ?? 'list';
$date_from = $_GET['from'] ?? '';
$date_to   = $_GET['to'] ?? '';
$priority_only = isset($_GET['priority']) ? 1 : 0;

$halaman = max(1, (int)($_GET['halaman'] ?? 1));
$per_page = 15;
$offset = ($halaman - 1) * $per_page;

$where = 'WHERE 1=1';
$params = [];
if ($filter === 'baru')    { $where .= " AND status='Baru'"; }
if ($filter === 'dibaca')  { $where .= " AND status='Dibaca'"; }
if ($filter === 'dibalas') { $where .= " AND status='Dibalas'"; }
if ($filter === 'arsip')   { $where .= " AND status='Arsip'"; }
if ($filter === 'prioritas') { $where .= " AND status='Prioritas'"; }
if ($priority_only) {
    // Filter by priority detection in subject/message
    $where .= " AND (pesan REGEXP 'mendesak|urgent|segera|penting|darurat|deadline' OR subjek REGEXP 'mendesak|urgent|segera|penting|darurat|deadline')";
}
if ($date_from !== '') { $where .= ' AND created_at >= ?'; $params[] = $date_from . ' 00:00:00'; }
if ($date_to !== '') { $where .= ' AND created_at <= ?'; $params[] = $date_to . ' 23:59:59'; }
if ($q !== '') {
    $where .= ' AND (nama LIKE ? OR email LIKE ? OR subjek LIKE ? OR pesan LIKE ?)';
    $params = array_merge($params, ["%$q%", "%$q%", "%$q%", "%$q%"]);
}

// Sort order
$order_by = 'created_at DESC';
if ($sort === 'terlama') $order_by = 'created_at ASC';
if ($sort === 'prioritas') $order_by = "(status='Prioritas') DESC, (status='Baru') DESC, created_at DESC";
if ($sort === 'nama') $order_by = 'nama ASC';

$cs = $pdo->prepare("SELECT COUNT(*) FROM kontak $where");
$cs->execute($params);
$total = (int)$cs->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));

$stmt = $pdo->prepare("SELECT * FROM kontak $where ORDER BY (status='Baru') DESC, $order_by LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$per_page, $offset]));
$pesan = $stmt->fetchAll();

// Counts global
$count_all     = (int)$pdo->query("SELECT COUNT(*) FROM kontak")->fetchColumn();
$count_baru    = (int)$pdo->query("SELECT COUNT(*) FROM kontak WHERE status='Baru'")->fetchColumn();
$count_dibaca  = (int)$pdo->query("SELECT COUNT(*) FROM kontak WHERE status='Dibaca'")->fetchColumn();
$count_dibalas = (int)$pdo->query("SELECT COUNT(*) FROM kontak WHERE status='Dibalas'")->fetchColumn();
$count_arsip   = (int)$pdo->query("SELECT COUNT(*) FROM kontak WHERE status='Arsip'")->fetchColumn();
$count_priority= (int)$pdo->query("SELECT COUNT(*) FROM kontak WHERE status='Prioritas'")->fetchColumn();

// Advanced analytics
$stat_today = (int)$pdo->query("SELECT COUNT(*) FROM kontak WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$stat_week = (int)$pdo->query("SELECT COUNT(*) FROM kontak WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$stat_month = (int)$pdo->query("SELECT COUNT(*) FROM kontak WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

// Response rate
$response_rate = $count_all > 0 ? round(($count_dibalas / $count_all) * 100) : 0;

// Avg response time (rough estimate from updated_at - created_at for replied messages)
$avg_response = 'N/A';
try {
    $avg_result = $pdo->query("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_hours FROM kontak WHERE status='Dibalas' AND updated_at IS NOT NULL")->fetch();
    if ($avg_result && $avg_result['avg_hours'] !== null) {
        $hours = round($avg_result['avg_hours'], 1);
        $avg_response = $hours < 24 ? $hours . ' jam' : round($hours/24, 1) . ' hari';
    }
} catch (Exception $e) {}

// Daily stats (7 days)
$daily_stats = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $count = (int)$pdo->query("SELECT COUNT(*) FROM kontak WHERE DATE(created_at) = '$date'")->fetchColumn();
    $daily_stats[$date] = $count;
}

// Hourly heatmap (24 hours)
$hourly_stats = [];
for ($h = 0; $h < 24; $h++) {
    $hourly_stats[$h] = (int)$pdo->query("SELECT COUNT(*) FROM kontak WHERE HOUR(created_at) = $h")->fetchColumn();
}

// Top keywords (simple word frequency)
$all_messages = $pdo->query("SELECT pesan FROM kontak WHERE status != 'Arsip' ORDER BY created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_COLUMN);
$word_freq = [];
$stopwords = ['yang','dan','di','ke','dari','untuk','dengan','pada','ini','itu','saya','kami','ada','akan','sudah','sangat','juga','tidak','atau','oleh','karena','jika','sudah','bisa','mau','harus','saya','kami','mereka','kita','anda','bapak','ibu'];
foreach ($all_messages as $msg) {
    $words = preg_split('/\s+/', strtolower($msg));
    foreach ($words as $w) {
        $w = preg_replace('/[^\p{L}\p{N}]/u', '', $w);
        if (strlen($w) > 4 && !in_array($w, $stopwords)) {
            $word_freq[$w] = ($word_freq[$w] ?? 0) + 1;
        }
    }
}
arsort($word_freq);
$top_words = array_slice($word_freq, 0, 12, true);

// Peak hour
$peak_hour = 12;
$max_hour_count = 0;
foreach ($hourly_stats as $h => $c) {
    if ($c > $max_hour_count) {
        $max_hour_count = $c;
        $peak_hour = $h;
    }
}

// Inbox health score (response rate + unread ratio)
$unread_ratio = $count_all > 0 ? ($count_baru / $count_all) : 0;
$inbox_health = max(0, min(100, round($response_rate - ($unread_ratio * 50))));

$csrf = generate_csrf_token();
$active_menu = 'kontak';
$page_heading = 'Pesan Masuk';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Pesan Masuk', null]];
require __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== PAGE HERO (Communication Theme - Blue/Indigo) ===== */
.kontak-hero {
    background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #60a5fa 100%);
    color: white;
    padding: 2rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(30,64,175,0.3);
}
.kontak-hero::before {
    content: '';
    position: absolute;
    top: -50%; right: -15%;
    width: 450px; height: 450px;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
.kontak-hero::after {
    content: '✉️';
    position: absolute;
    bottom: -30px; right: 2rem;
    font-size: 12rem;
    color: rgba(255,255,255,0.05);
    pointer-events: none;
    line-height: 1;
}
.kontak-hero-content {
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
    flex-wrap: wrap;
    z-index: 1;
}
.kontak-hero h2 {
    font-family: 'Georgia', serif;
    font-size: 1.75rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.kontak-hero p { opacity: 0.95; font-size: 0.95rem; max-width: 500px; line-height: 1.6; }
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
.health-ring .ring-fill { fill: none; stroke: white; stroke-width: 8; stroke-linecap: round; transition: stroke-dasharray 1.5s ease; }
.health-value {
    position: absolute; inset: 0;
    display: flex; flex-direction: column; align-items: center; justify-content: center; color: white;
}
.health-value .score-num { font-size: 1.85rem; font-weight: 900; line-height: 1; font-family: 'Georgia', serif; }
.health-value .score-label { font-size: 0.65rem; opacity: 0.9; margin-top: 0.2rem; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== STATS CARDS ===== */
.stats-extreme {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}
.stat-card-extreme {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: var(--shadow-sm);
    cursor: pointer;
}
.stat-card-extreme::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--stat-color, #3b82f6), transparent);
}
.stat-card-extreme:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-xl);
    border-color: var(--stat-color, #3b82f6);
}
.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
}
.stat-icon-box {
    width: 42px; height: 42px;
    border-radius: 10px;
    background: var(--stat-color, #3b82f6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.stat-number-extreme {
    font-family: 'Georgia', serif;
    font-size: 2.25rem;
    font-weight: 900;
    color: var(--stat-color, #3b82f6);
    line-height: 1;
    margin-bottom: 0.25rem;
    font-variant-numeric: tabular-nums;
}
.stat-label-extreme {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.5rem;
}
.stat-trend {
    font-size: 0.72rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-weight: 600;
    padding: 0.25rem 0.55rem;
    border-radius: 999px;
    width: fit-content;
}
.stat-trend.up { background: rgba(16,185,129,0.1); color: #059669; }
.stat-trend.down { background: rgba(239,68,68,0.1); color: #dc2626; }
.stat-trend.neutral { background: var(--bg-tertiary); color: var(--text-muted); }
.has-pulse { position: relative; }
.has-pulse::after {
    content: '';
    position: absolute;
    top: 1rem; right: 1rem;
    width: 10px; height: 10px;
    background: #ef4444;
    border-radius: 50%;
    animation: ping 1.5s infinite;
}
@keyframes ping { to { transform: scale(2.5); opacity: 0; } }

/* ===== CHARTS ===== */
.chart-section {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}
.chart-section-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}
.chart-card {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
}
.chart-card h3 {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-family: 'Georgia', serif;
}

/* Heatmap */
.heatmap-grid {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 3px;
    margin-top: 0.5rem;
}
.heatmap-cell {
    aspect-ratio: 1;
    border-radius: 3px;
    position: relative;
    cursor: pointer;
    transition: all 0.2s;
    background: var(--bg-tertiary);
}
.heatmap-cell:hover {
    transform: scale(1.2);
    z-index: 5;
}
.heatmap-cell[data-tooltip]:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%);
    background: #1f2937;
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.7rem;
    white-space: nowrap;
    z-index: 10;
    pointer-events: none;
}
.heatmap-labels {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 3px;
    margin-top: 0.25rem;
    font-size: 0.65rem;
    color: var(--text-muted);
    text-align: center;
}

/* Word cloud */
.word-cloud {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    align-items: center;
    justify-content: center;
    padding: 1rem 0;
}
.word-tag {
    padding: 0.25rem 0.65rem;
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    color: #1e40af;
    border-radius: 999px;
    font-weight: 600;
    transition: all 0.2s;
    cursor: pointer;
    border: 1px solid #93c5fd;
}
[data-theme="dark"] .word-tag {
    background: linear-gradient(135deg, #1e3a8a22, #1e40af33);
    color: #93c5fd;
    border-color: #3b82f6;
}
.word-tag:hover {
    transform: scale(1.1) translateY(-2px);
    box-shadow: 0 4px 12px rgba(59,130,246,0.3);
}
.word-tag[data-size="xl"] { font-size: 1.1rem; padding: 0.4rem 0.9rem; }
.word-tag[data-size="lg"] { font-size: 0.95rem; padding: 0.35rem 0.8rem; }
.word-tag[data-size="md"] { font-size: 0.85rem; }
.word-tag[data-size="sm"] { font-size: 0.75rem; opacity: 0.8; }

/* ===== QUICK FILTER PILLS ===== */
.quick-filter-pills {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
    padding: 0.5rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    align-items: center;
}
.pill {
    padding: 0.5rem 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    text-decoration: none;
}
.pill:hover { background: var(--bg-tertiary); color: var(--text-primary); transform: translateY(-1px); }
.pill.active {
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white;
    border-color: #1e40af;
    box-shadow: 0 4px 12px rgba(30,64,175,0.3);
}
.pill .pill-count {
    background: rgba(255,255,255,0.25);
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 800;
    min-width: 22px;
    text-align: center;
}
.pill:not(.active) .pill-count { background: var(--bg-tertiary); color: var(--text-muted); }

/* ===== TOOLBAR ===== */
.toolbar-extreme {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 1.25rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm);
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    align-items: center;
}
.search-box { flex: 1; min-width: 250px; position: relative; }
.search-box input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.75rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    font-family: inherit;
    font-size: 0.95rem;
    transition: all 0.3s;
    background: var(--bg-secondary);
}
.search-box input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59,130,246,0.1);
    background: var(--bg-primary);
}
.search-box .search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    pointer-events: none;
}
.search-shortcut {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    background: var(--bg-tertiary);
    color: var(--text-muted);
    padding: 0.15rem 0.5rem;
    border-radius: 5px;
    font-size: 0.68rem;
    font-family: monospace;
    font-weight: 600;
    pointer-events: none;
}
.search-clear {
    position: absolute;
    right: 3rem;
    top: 50%;
    transform: translateY(-50%);
    width: 20px; height: 20px;
    background: #fee2e2;
    color: #dc2626;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    text-decoration: none;
    cursor: pointer;
    border: none;
}
.search-clear:hover { background: #dc2626; color: white; }

.filter-select, .date-input {
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    font-family: inherit;
    font-size: 0.9rem;
    background: var(--bg-secondary);
    cursor: pointer;
    transition: all 0.3s;
    color: var(--text-primary);
}
.filter-select:focus, .date-input:focus { outline: none; border-color: #3b82f6; }

.view-toggle {
    display: flex;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    padding: 0.25rem;
    border: 1px solid var(--border);
}
.view-btn {
    padding: 0.5rem 0.85rem;
    border-radius: 7px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--text-muted);
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-family: inherit;
}
.view-btn.active { background: linear-gradient(135deg, #1e40af, #3b82f6); color: white; }
.view-btn:hover:not(.active) { background: var(--bg-tertiary); color: var(--text-primary); }

.btn-action {
    padding: 0.75rem 1.25rem;
    border-radius: var(--radius-md);
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    border: none;
    font-family: inherit;
    white-space: nowrap;
}
.btn-action.primary {
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white;
    box-shadow: 0 4px 12px rgba(30,64,175,0.3);
}
.btn-action.primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(30,64,175,0.4); }
.btn-action.secondary {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border);
}
.btn-action.secondary:hover { background: var(--bg-tertiary); transform: translateY(-2px); }

/* Priority Toggle */
.priority-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.65rem 1rem;
    background: var(--bg-secondary);
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-secondary);
    transition: all 0.2s;
    user-select: none;
}
.priority-toggle:hover { border-color: #f59e0b; color: #f59e0b; }
.priority-toggle.active {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border-color: #f59e0b;
    color: #92400e;
}

/* Export Dropdown */
.export-dropdown { position: relative; }
.export-menu {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    min-width: 220px;
    display: none;
    z-index: 50;
    overflow: hidden;
}
.export-menu.show { display: block; animation: menuPop 0.2s ease; }
@keyframes menuPop {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}
.export-item {
    padding: 0.7rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.7rem;
    color: var(--text-primary);
    text-decoration: none;
    font-size: 0.85rem;
    transition: background 0.15s;
    border-bottom: 1px solid var(--border);
}
.export-item:last-child { border-bottom: none; }
.export-item:hover { background: var(--bg-secondary); }
.export-item-icon { font-size: 1.1rem; width: 22px; text-align: center; }

/* ===== BULK BAR ===== */
.bulk-bar {
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white;
    padding: 1rem 1.5rem;
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
    display: none;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    animation: slideDown 0.3s ease;
    box-shadow: 0 10px 30px rgba(30,64,175,0.3);
}
.bulk-bar.show { display: flex; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.bulk-info { font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
.bulk-count { background: white; color: #1e40af; padding: 0.25rem 0.7rem; border-radius: 999px; font-size: 0.82rem; font-weight: 800; }
.bulk-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.bulk-btn {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    font-size: 0.82rem;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.bulk-btn:hover { transform: translateY(-2px); }
.bulk-btn.primary { background: white; color: #1e40af; }
.bulk-btn.success { background: #10b981; color: white; }
.bulk-btn.warning { background: #f59e0b; color: white; }
.bulk-btn.danger { background: #dc2626; color: white; }
.bulk-btn.cancel { background: transparent; color: white; border: 1px solid rgba(255,255,255,0.3); }

/* ===== MESSAGE LIST (Enhanced) ===== */
.message-container {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}
.container-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    background: var(--bg-secondary);
}
.container-header h2 {
    font-size: 1.15rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-family: 'Georgia', serif;
}
.select-all-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 0.75rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
}
.select-all-wrap input { width: 18px; height: 18px; accent-color: #3b82f6; cursor: pointer; }

/* Message Row */
.msg-list { display: flex; flex-direction: column; }
.msg-row {
    display: flex;
    gap: 1rem;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border);
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
    animation: slideIn 0.3s;
}
@keyframes slideIn { from { opacity: 0; transform: translateX(-10px); } to { opacity: 1; transform: translateX(0); } }
.msg-row:hover { background: var(--bg-secondary); transform: translateX(3px); }
.msg-row.unread { background: rgba(59,130,246,0.03); }
.msg-row.unread::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 4px;
    background: linear-gradient(180deg, #3b82f6, #60a5fa);
    animation: pulse-bar 2s infinite;
}
@keyframes pulse-bar { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
.msg-row.priority::before {
    background: linear-gradient(180deg, #f59e0b, #ef4444) !important;
    animation: fire-bar 1.5s infinite;
}
@keyframes fire-bar {
    0%, 100% { box-shadow: 0 0 0 0 rgba(245,158,11,0.5); }
    50% { box-shadow: 0 0 8px 2px rgba(239,68,68,0.3); }
}
.msg-row.selected { background: rgba(59,130,246,0.08); }
.msg-row.selected::after {
    content: '✓';
    position: absolute;
    right: 1rem; top: 1rem;
    width: 20px; height: 20px;
    background: #3b82f6;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 800;
}
.msg-row .row-check {
    width: 18px; height: 18px;
    accent-color: #3b82f6;
    cursor: pointer;
    flex-shrink: 0;
    z-index: 2;
}
.msg-avatar {
    width: 48px; height: 48px;
    border-radius: 50%;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 1.1rem;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border: 2px solid rgba(255,255,255,0.3);
}
.msg-main { flex: 1; min-width: 0; }
.msg-top {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 0.25rem;
}
.msg-name {
    font-size: 0.95rem;
    color: var(--text-primary);
    font-weight: 600;
}
.unread .msg-name { font-weight: 800; }
.prio-flag {
    font-size: 0.85rem;
    animation: flame 1.5s infinite;
}
@keyframes flame { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.2) rotate(5deg); } }
.sentiment-flag {
    font-size: 0.82rem;
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
}
.sentiment-positive { background: #dcfce7; color: #166534; }
.sentiment-negative { background: #fee2e2; color: #991b1b; }
.sentiment-neutral { background: #f3f4f6; color: #4b5563; }
.msg-subject {
    font-size: 0.85rem;
    color: var(--text-secondary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 340px;
}
.unread .msg-subject { font-weight: 600; color: var(--text-primary); }
.msg-tags { display: flex; gap: 0.25rem; flex-wrap: wrap; margin-top: 0.25rem; }
.msg-tag {
    font-size: 0.65rem;
    padding: 0.1rem 0.45rem;
    background: #e0e7ff;
    color: #4338ca;
    border-radius: 999px;
    font-weight: 600;
}
[data-theme="dark"] .msg-tag { background: rgba(67,56,202,0.2); color: #c7d2fe; }
.msg-badge {
    padding: 0.2rem 0.65rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.b-baru { background: #dbeafe; color: #1e40af; }
.b-dibaca { background: #f3f4f6; color: #4b5563; }
.b-dibalas { background: #dcfce7; color: #166534; }
.b-arsip { background: #f3f4f6; color: #6b7280; }
.b-prioritas { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: white; }
.msg-excerpt {
    font-size: 0.82rem;
    color: var(--text-muted);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    margin-top: 0.15rem;
}
.msg-side {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.4rem;
    flex-shrink: 0;
}
.msg-time {
    font-size: 0.72rem;
    color: var(--text-muted);
    font-weight: 600;
}
.unread .msg-time { color: #3b82f6; font-weight: 800; }
.row-quick {
    display: flex;
    gap: 0.25rem;
    opacity: 0;
    transition: opacity 0.2s;
}
.msg-row:hover .row-quick { opacity: 1; }
.rq-btn {
    width: 30px; height: 30px;
    border: none;
    border-radius: 8px;
    background: var(--bg-primary);
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    cursor: pointer;
    font-size: 0.85rem;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.rq-btn:hover { transform: translateY(-2px); background: #3b82f6; color: white; }
.rq-btn.danger:hover { background: #dc2626; }
.rq-btn.success:hover { background: #10b981; }

/* ===== CARD VIEW ===== */
.msg-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1rem;
    padding: 1.5rem;
}
.msg-card {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s;
    position: relative;
    display: flex;
    flex-direction: column;
}
.msg-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
    border-color: #3b82f6;
}
.msg-card.unread { border-left: 4px solid #3b82f6; }
.msg-card.priority { border-left: 4px solid #f59e0b; }
.msg-card-header {
    padding: 1rem;
    display: flex;
    gap: 0.75rem;
    align-items: center;
    border-bottom: 1px solid var(--border);
    background: var(--bg-secondary);
}
.msg-card-avatar {
    width: 40px; height: 40px;
    border-radius: 50%;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    flex-shrink: 0;
}
.msg-card-info { flex: 1; min-width: 0; }
.msg-card-name {
    font-weight: 700;
    font-size: 0.9rem;
    margin-bottom: 0.15rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.msg-card-email {
    font-size: 0.72rem;
    color: var(--text-muted);
    font-family: monospace;
}
.msg-card-body { padding: 1rem; flex: 1; }
.msg-card-subject {
    font-weight: 700;
    font-size: 0.95rem;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.msg-card-excerpt {
    font-size: 0.82rem;
    color: var(--text-secondary);
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 0.75rem;
}
.msg-card-footer {
    padding: 0.75rem 1rem;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.75rem;
    color: var(--text-muted);
    background: var(--bg-secondary);
}

/* ===== TIMELINE VIEW ===== */
.timeline-view { padding: 1.5rem; }
.timeline-group { margin-bottom: 1.5rem; }
.timeline-group-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--border);
    position: sticky;
    top: 0;
    background: var(--bg-primary);
    z-index: 5;
    padding-top: 0.5rem;
}
.timeline-date {
    font-size: 1rem;
    font-weight: 800;
    color: #1e40af;
    font-family: 'Georgia', serif;
}
.timeline-count {
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white;
    padding: 0.2rem 0.65rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
}
.timeline-item {
    display: flex;
    gap: 0.75rem;
    padding: 0.75rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
    margin-bottom: 0.5rem;
    transition: all 0.2s;
    cursor: pointer;
    align-items: center;
}
.timeline-item:hover {
    background: var(--bg-tertiary);
    transform: translateX(3px);
    border-color: #3b82f6;
}
.timeline-item-avatar {
    width: 36px; height: 36px;
    border-radius: 50%;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.9rem;
    flex-shrink: 0;
}
.timeline-item-info { flex: 1; min-width: 0; }
.timeline-item-name {
    font-weight: 700;
    font-size: 0.85rem;
    margin-bottom: 0.15rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.timeline-item-meta {
    font-size: 0.72rem;
    color: var(--text-muted);
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* ===== PAGINATION ===== */
.inbox-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border);
    flex-wrap: wrap;
    gap: 1rem;
    background: var(--bg-secondary);
}
.ip-info { font-size: 0.82rem; color: var(--text-muted); }
.ip-buttons { display: flex; gap: 0.25rem; }
.ip-btn {
    padding: 0.45rem 0.85rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.82rem;
    text-decoration: none;
    color: var(--text-primary);
    background: var(--bg-primary);
    transition: all 0.2s;
    cursor: pointer;
    font-family: inherit;
    font-weight: 600;
}
.ip-btn:hover:not(.current) { border-color: #3b82f6; color: #3b82f6; transform: translateY(-1px); }
.ip-btn.current {
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white;
    border-color: #1e40af;
}

/* ===== EMPTY STATE ===== */
.inbox-empty {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-xl);
    border: 2px dashed var(--border);
}
.ie-illustration {
    position: relative;
    width: 130px; height: 130px;
    margin: 0 auto 1.25rem;
}
.ie-circle {
    position: absolute; inset: 0;
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    border-radius: 50%;
    animation: emptyPulse 3s infinite;
}
@keyframes emptyPulse { 0%, 100% { transform: scale(1); opacity: 0.8; } 50% { transform: scale(1.08); opacity: 0.4; } }
.ie-icon {
    position: absolute; inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3.5rem;
    animation: float 3s ease-in-out infinite;
}
@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
.inbox-empty h3 { font-size: 1.3rem; margin-bottom: 0.5rem; font-family: 'Georgia', serif; }
.inbox-empty p { color: var(--text-muted); margin-bottom: 1.25rem; }

/* ===== READING PANE MODAL (Enhanced) ===== */
.modal-overlay {
    position: fixed; inset: 0;
    background: rgba(15,23,42,0.85);
    backdrop-filter: blur(10px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    padding: 1.5rem;
}
.modal-overlay.open { display: flex; animation: fadeIn 0.3s; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.read-pane {
    background: var(--bg-primary);
    border-radius: 20px;
    width: 100%;
    max-width: 760px;
    max-height: 92vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: paneIn 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.2);
    box-shadow: 0 40px 100px rgba(0,0,0,0.4);
    border: 1px solid var(--border);
}
@keyframes paneIn { from { transform: translateY(30px) scale(0.96); opacity: 0; } to { transform: none; opacity: 1; } }

.read-header {
    display: flex;
    gap: 1rem;
    align-items: center;
    padding: 1.5rem;
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white;
    position: relative;
}
.read-avatar {
    width: 56px; height: 56px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    font-weight: 800;
    flex-shrink: 0;
    border: 2px solid rgba(255,255,255,0.4);
}
.read-headinfo { flex: 1; min-width: 0; }
.read-headinfo h3 { font-size: 1.15rem; margin-bottom: 0.2rem; font-family: 'Georgia', serif; }
.read-submeta { display: flex; gap: 0.75rem; font-size: 0.78rem; opacity: 0.9; flex-wrap: wrap; }
.read-close {
    width: 40px; height: 40px;
    border-radius: 50%;
    background: rgba(255,255,255,0.15);
    border: none;
    color: white;
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.2s;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.read-close:hover { background: #dc2626; transform: rotate(90deg); }

.read-body {
    padding: 0;
    overflow-y: auto;
    flex: 1;
    background: var(--bg-primary);
}

/* Tabs */
.read-tabs {
    display: flex;
    gap: 0.25rem;
    border-bottom: 2px solid var(--border);
    padding: 0 1.5rem;
    background: var(--bg-secondary);
}
.read-tab {
    padding: 0.85rem 1.1rem;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    color: var(--text-muted);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
    font-size: 0.88rem;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}
.read-tab.active { color: #3b82f6; border-bottom-color: #3b82f6; }
.read-tab:hover:not(.active) { color: var(--text-primary); }
.read-tab-content { display: none; padding: 1.5rem; animation: tabFade 0.3s; }
.read-tab-content.active { display: block; }
@keyframes tabFade { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

/* Message tab */
.read-subject-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}
.read-subject-row h2 {
    font-size: 1.3rem;
    color: var(--text-primary);
    font-family: 'Georgia', serif;
}
.read-message {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem;
    font-size: 0.95rem;
    line-height: 1.8;
    color: var(--text-secondary);
    white-space: pre-wrap;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    margin-bottom: 1rem;
    border-left: 4px solid #3b82f6;
}
.read-message::before {
    content: '"';
    font-size: 2rem;
    color: #3b82f6;
    line-height: 0.5;
    display: block;
    margin-bottom: 0.5rem;
    opacity: 0.3;
    font-family: 'Georgia', serif;
}

/* Metadata grid */
.metadata-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
    margin-bottom: 1rem;
}
.metadata-item {
    padding: 0.85rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
}
.metadata-label {
    font-size: 0.72rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
    margin-bottom: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.metadata-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
}

/* History timeline */
.history-timeline {
    position: relative;
    padding-left: 1.5rem;
}
.history-timeline::before {
    content: '';
    position: absolute;
    left: 0.4rem;
    top: 0; bottom: 0;
    width: 2px;
    background: var(--border);
}
.history-item {
    position: relative;
    padding: 0.75rem 0;
    padding-left: 1rem;
}
.history-item::before {
    content: '';
    position: absolute;
    left: -1.25rem;
    top: 1rem;
    width: 10px; height: 10px;
    border-radius: 50%;
    background: #3b82f6;
    border: 2px solid white;
    box-shadow: 0 0 0 2px #3b82f6;
}
.history-item.success::before { background: #10b981; box-shadow: 0 0 0 2px #10b981; }
.history-item.warning::before { background: #f59e0b; box-shadow: 0 0 0 2px #f59e0b; }
.history-title {
    font-weight: 700;
    font-size: 0.9rem;
    margin-bottom: 0.15rem;
}
.history-desc { font-size: 0.82rem; color: var(--text-muted); }
.history-time {
    font-size: 0.72rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
    font-family: monospace;
}

/* Notes */
.notes-area {
    background: #fef3c7;
    border: 1px solid #fcd34d;
    border-radius: var(--radius-md);
    padding: 1rem;
    margin-bottom: 1rem;
    min-height: 80px;
    white-space: pre-wrap;
    font-size: 0.85rem;
    line-height: 1.6;
    color: #92400e;
}
[data-theme="dark"] .notes-area {
    background: #78350f22;
    border-color: #f59e0b;
    color: #fcd34d;
}
.add-note-form {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.5rem;
}
.add-note-form input {
    flex: 1;
    padding: 0.65rem 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    font-family: inherit;
    font-size: 0.85rem;
    background: var(--bg-primary);
}
.add-note-form input:focus { outline: none; border-color: #3b82f6; }
.add-note-form button {
    padding: 0.65rem 1.25rem;
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white;
    border: none;
    border-radius: var(--radius-md);
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
}

/* Footer actions */
.read-footer {
    padding: 1.25rem 1.5rem;
    border-top: 1px solid var(--border);
    background: var(--bg-secondary);
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: center;
}
.reply-composer { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.reply-select {
    padding: 0.55rem 0.9rem;
    border: 2px solid var(--border);
    border-radius: 10px;
    font-family: inherit;
    font-size: 0.85rem;
    background: var(--bg-primary);
    cursor: pointer;
    color: var(--text-primary);
}
.reply-select:focus { outline: none; border-color: #3b82f6; }
.read-actions { display: flex; gap: 0.4rem; flex-wrap: wrap; }
.btn-sm {
    padding: 0.55rem 0.95rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    font-size: 0.82rem;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    text-decoration: none;
}
.btn-sm:hover { transform: translateY(-2px); }
.btn-sm.primary { background: linear-gradient(135deg, #1e40af, #3b82f6); color: white; }
.btn-sm.gray { background: var(--bg-tertiary); color: var(--text-primary); }
.btn-sm.amber { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
.btn-sm.red { background: #dc2626; color: white; }
.btn-sm.success { background: #10b981; color: white; }

/* ===== QUICK REPLY MODAL ===== */
.reply-modal-content {
    background: var(--bg-primary);
    border-radius: var(--radius-xl);
    width: 100%;
    max-width: 640px;
    max-height: 90vh;
    overflow-y: auto;
    padding: 2rem;
    box-shadow: 0 40px 100px rgba(0,0,0,0.4);
    border: 1px solid var(--border);
    animation: paneIn 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.2);
}
.reply-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}
.reply-modal-header h3 {
    font-family: 'Georgia', serif;
    font-size: 1.25rem;
}
.reply-templates-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}
.reply-template-card {
    padding: 1rem;
    background: var(--bg-secondary);
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all 0.2s;
    text-align: left;
}
.reply-template-card:hover {
    border-color: #3b82f6;
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}
.reply-template-card.active {
    border-color: #3b82f6;
    background: rgba(59,130,246,0.05);
}
.reply-template-icon { font-size: 1.5rem; margin-bottom: 0.5rem; }
.reply-template-title { font-weight: 700; font-size: 0.88rem; margin-bottom: 0.25rem; }
.reply-template-desc {
    font-size: 0.75rem;
    color: var(--text-muted);
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.reply-preview {
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    border-left: 4px solid #3b82f6;
    font-size: 0.85rem;
    line-height: 1.6;
    white-space: pre-wrap;
    max-height: 200px;
    overflow-y: auto;
    margin-bottom: 1rem;
    color: var(--text-secondary);
}
.reply-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

/* ===== CONFIRM MODAL ===== */
.confirm-box {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 2rem;
    max-width: 400px;
    width: 100%;
    text-align: center;
    animation: paneIn 0.3s;
    border: 1px solid var(--border);
}
.confirm-icon { font-size: 3rem; margin-bottom: 0.75rem; }
.confirm-box h3 { margin-bottom: 0.5rem; font-family: 'Georgia', serif; }
.confirm-box p { color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.9rem; }
.confirm-actions { display: flex; gap: 0.75rem; justify-content: center; }

/* ===== NEW MESSAGE NOTIFICATION ===== */
.new-message-toast {
    position: fixed;
    top: 2rem;
    right: 2rem;
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white;
    padding: 1rem 1.5rem;
    border-radius: var(--radius-lg);
    box-shadow: 0 10px 30px rgba(30,64,175,0.4);
    display: none;
    align-items: center;
    gap: 0.75rem;
    z-index: 9999;
    animation: slideInRight 0.3s;
    min-width: 280px;
    cursor: pointer;
}
.new-message-toast.show { display: flex; }
@keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
.new-message-toast .toast-icon { font-size: 1.5rem; }
.new-message-toast .toast-content { flex: 1; }
.new-message-toast .toast-title { font-weight: 800; font-size: 0.9rem; margin-bottom: 0.15rem; }
.new-message-toast .toast-desc { font-size: 0.78rem; opacity: 0.9; }

/* ===== KEYBOARD SHORTCUTS HELP ===== */
.shortcuts-help {
    position: fixed;
    bottom: 1rem;
    left: 1rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 0.5rem 1rem;
    font-size: 0.75rem;
    color: var(--text-muted);
    box-shadow: var(--shadow-sm);
    display: flex;
    gap: 0.75rem;
    align-items: center;
    z-index: 50;
    flex-wrap: wrap;
}
.shortcuts-help kbd {
    background: var(--bg-secondary);
    padding: 0.15rem 0.4rem;
    border-radius: 4px;
    font-family: monospace;
    font-size: 0.7rem;
    border: 1px solid var(--border);
    margin: 0 0.15rem;
    font-weight: 700;
}

/* ===== SEARCH HIGHLIGHT ===== */
mark {
    background: #fef08a;
    padding: 0 0.15rem;
    border-radius: 2px;
    font-weight: 700;
}

@media (max-width: 1024px) {
    .chart-section, .chart-section-3 { grid-template-columns: 1fr; }
    .stats-extreme { grid-template-columns: repeat(2, 1fr); }
    .metadata-grid, .reply-templates-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .kontak-hero-content { flex-direction: column; text-align: center; }
    .health-ring { margin: 0 auto; }
    .hero-stats { justify-content: center; }
    .toolbar-extreme { flex-direction: column; align-items: stretch; }
    .search-box { min-width: 100%; }
    .filter-select, .view-toggle, .btn-action, .priority-toggle { width: 100%; justify-content: center; }
    .msg-grid { grid-template-columns: 1fr; padding: 1rem; }
    .msg-subject { max-width: 150px; }
    .msg-excerpt { display: none; }
    .read-footer { flex-direction: column; align-items: stretch; }
    .shortcuts-help { display: none; }
}
@media (max-width: 640px) {
    .stats-extreme { grid-template-columns: 1fr; }
    .stat-number-extreme { font-size: 1.85rem; }
    .bulk-bar { flex-direction: column; align-items: stretch; }
    .bulk-actions { flex-direction: column; }
    .bulk-btn { width: 100%; }
}
</style>

<!-- ===== HERO BANNER ===== -->
<div class="kontak-hero" data-aos="fade-down">
    <div class="kontak-hero-content">
        <div>
            <h2>✉️ Communication Hub</h2>
            <p>Kelola pesan masuk dari pengunjung, calon mahasiswa, dan mitra. Pantau response time dan kualitas komunikasi.</p>
            <div class="hero-stats">
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $count_all ?></span>
                    <span class="hero-stat-label">Total</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $stat_today ?></span>
                    <span class="hero-stat-label">Hari Ini</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $response_rate ?>%</span>
                    <span class="hero-stat-label">Response</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $avg_response ?></span>
                    <span class="hero-stat-label">Avg Time</span>
                </div>
            </div>
        </div>
        <div class="health-ring" title="Inbox Health Score (Response Rate - Unread Ratio)">
            <svg viewBox="0 0 36 36">
                <circle cx="18" cy="18" r="15.915" class="ring-bg"/>
                <circle cx="18" cy="18" r="15.915" class="ring-fill" style="stroke-dasharray: <?= $inbox_health ?>, 100"/>
            </svg>
            <div class="health-value">
                <div class="score-num"><?= $inbox_health ?></div>
                <div class="score-label">Health</div>
            </div>
        </div>
    </div>
</div>

<!-- ===== STATS CARDS ===== -->
<div class="stats-extreme" data-aos="fade-up">
    <div class="stat-card-extreme" style="--stat-color: #3b82f6;">
        <div class="stat-header">
            <div class="stat-icon-box">📥</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $count_all ?>">0</div>
        <div class="stat-label-extreme">Total Pesan</div>
        <div class="stat-trend neutral">📨 All</div>
    </div>
    <div class="stat-card-extreme has-pulse" style="--stat-color: #ef4444;">
        <div class="stat-header">
            <div class="stat-icon-box">🔵</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $count_baru ?>">0</div>
        <div class="stat-label-extreme">Belum Dibaca</div>
        <div class="stat-trend down">⚠️ Perlu ditindak</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #10b981;">
        <div class="stat-header">
            <div class="stat-icon-box">✅</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $count_dibalas ?>">0</div>
        <div class="stat-label-extreme">Sudah Dibalas</div>
        <div class="stat-trend up">📈 <?= $response_rate ?>%</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #f59e0b;">
        <div class="stat-header">
            <div class="stat-icon-box">🔥</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $count_priority ?>">0</div>
        <div class="stat-label-extreme">Prioritas</div>
        <div class="stat-trend neutral">🚨 Urgent</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #8b5cf6;">
        <div class="stat-header">
            <div class="stat-icon-box">📊</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_week ?>">0</div>
        <div class="stat-label-extreme">Minggu Ini</div>
        <div class="stat-trend neutral">📅 7 hari</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #ec4899;">
        <div class="stat-header">
            <div class="stat-icon-box">⏱️</div>
        </div>
        <div class="stat-number-extreme" style="font-size: 1.5rem;"><?= $avg_response ?></div>
        <div class="stat-label-extreme">Avg Response</div>
        <div class="stat-trend neutral">⚡ Cepat</div>
    </div>
</div>

<!-- ===== CHARTS ===== -->
<?php if (!empty($daily_stats)): ?>
<div class="chart-section" data-aos="fade-up">
    <div class="chart-card">
        <h3>📊 Pesan 7 Hari Terakhir</h3>
        <div id="dailyChart"></div>
    </div>
    <div class="chart-card">
        <h3>🔥 Heatmap Jam Sibuk</h3>
        <div style="padding: 1rem 0;">
            <div class="heatmap-grid">
                <?php
                $max_hourly = max(array_values($hourly_stats)) ?: 1;
                for ($h = 0; $h < 24; $h += 2):
                    $count = ($hourly_stats[$h] ?? 0) + ($hourly_stats[$h+1] ?? 0);
                    $intensity = $count / ($max_hourly * 2);
                    $opacity = 0.1 + ($intensity * 0.9);
                    $color = $h >= 8 && $h <= 17 ? "rgba(59,130,246,$opacity)" : "rgba(139,92,246,$opacity)";
                ?>
                <div class="heatmap-cell"
                     style="background: <?= $color ?>;"
                     data-tooltip="<?= sprintf('%02d', $h) ?>:00-<?= sprintf('%02d', $h+2) ?>:00 (<?= $count ?> pesan)"></div>
                <?php endfor; ?>
            </div>
            <div class="heatmap-labels">
                <?php for ($h = 0; $h < 24; $h += 2): ?>
                <div><?= sprintf('%02d', $h) ?></div>
                <?php endfor; ?>
            </div>
            <div style="margin-top: 1rem; padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px; display: flex; align-items: center; gap: 0.5rem; font-size: 0.82rem;">
                <span>⏰</span>
                <span>Peak hour: <strong style="color: #3b82f6;"><?= sprintf('%02d', $peak_hour) ?>:00</strong> dengan <strong><?= $max_hour_count ?></strong> pesan</span>
            </div>
        </div>
    </div>
</div>

<div class="chart-section-3" data-aos="fade-up">
    <div class="chart-card">
        <h3>📊 Status Distribution</h3>
        <div id="statusChart"></div>
    </div>
    <div class="chart-card">
        <h3>💬 Top Keywords</h3>
        <div class="word-cloud">
            <?php if (empty($top_words)): ?>
                <span style="color: var(--text-muted); font-size: 0.85rem;">Belum cukup data</span>
            <?php else: ?>
                <?php
                $max_freq = max($top_words);
                foreach ($top_words as $word => $freq):
                    $size = 'sm';
                    if ($freq >= $max_freq * 0.8) $size = 'xl';
                    elseif ($freq >= $max_freq * 0.6) $size = 'lg';
                    elseif ($freq >= $max_freq * 0.4) $size = 'md';
                ?>
                <span class="word-tag" data-size="<?= $size ?>" onclick="searchWord('<?= htmlspecialchars($word, ENT_QUOTES) ?>')">
                    <?= htmlspecialchars($word) ?> <small style="opacity: 0.6;">(<?= $freq ?>)</small>
                </span>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="chart-card">
        <h3>📈 Statistik Cepat</h3>
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            <div style="display: flex; justify-content: space-between; padding: 0.65rem 0.85rem; background: var(--bg-secondary); border-radius: 8px; border: 1px solid var(--border);">
                <span style="font-size: 0.85rem;">📅 Hari Ini</span>
                <strong style="color: #3b82f6;"><?= $stat_today ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 0.65rem 0.85rem; background: var(--bg-secondary); border-radius: 8px; border: 1px solid var(--border);">
                <span style="font-size: 0.85rem;">📅 Minggu Ini</span>
                <strong style="color: #3b82f6;"><?= $stat_week ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 0.65rem 0.85rem; background: var(--bg-secondary); border-radius: 8px; border: 1px solid var(--border);">
                <span style="font-size: 0.85rem;">📅 Bulan Ini</span>
                <strong style="color: #3b82f6;"><?= $stat_month ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 0.65rem 0.85rem; background: var(--bg-secondary); border-radius: 8px; border: 1px solid var(--border);">
                <span style="font-size: 0.85rem;">📦 Arsip</span>
                <strong style="color: #6b7280;"><?= $count_arsip ?></strong>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===== QUICK FILTER PILLS ===== -->
<div class="quick-filter-pills" data-aos="fade-up">
    <a href="kontak.php" class="pill <?= $filter === 'semua' && !$priority_only ? 'active' : '' ?>">
        📥 Semua <span class="pill-count"><?= $count_all ?></span>
    </a>
    <a href="kontak.php?filter=baru" class="pill <?= $filter === 'baru' ? 'active' : '' ?>">
        🔵 Baru <span class="pill-count"><?= $count_baru ?></span>
    </a>
    <a href="kontak.php?filter=dibaca" class="pill <?= $filter === 'dibaca' ? 'active' : '' ?>">
        ⚪ Dibaca <span class="pill-count"><?= $count_dibaca ?></span>
    </a>
    <a href="kontak.php?filter=dibalas" class="pill <?= $filter === 'dibalas' ? 'active' : '' ?>">
        ✅ Dibalas <span class="pill-count"><?= $count_dibalas ?></span>
    </a>
    <a href="kontak.php?filter=prioritas" class="pill <?= $filter === 'prioritas' ? 'active' : '' ?>">
        🔥 Prioritas <span class="pill-count"><?= $count_priority ?></span>
    </a>
    <a href="kontak.php?filter=arsip" class="pill <?= $filter === 'arsip' ? 'active' : '' ?>">
        📦 Arsip <span class="pill-count"><?= $count_arsip ?></span>
    </a>
    <?php if ($count_baru === 0 && $count_all > 0): ?>
    <div style="padding: 0.4rem 0.85rem; background: linear-gradient(135deg, #10b981, #059669); color: white; border-radius: 999px; font-size: 0.78rem; font-weight: 700; box-shadow: 0 4px 12px rgba(16,185,129,0.4);">
        🎉 Inbox Zero!
    </div>
    <?php endif; ?>
</div>

<!-- ===== TOOLBAR ===== -->
<div class="toolbar-extreme" data-aos="fade-up">
    <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="inboxSearch" placeholder="Cari nama, email, subjek, atau pesan..." value="<?= sanitize($q) ?>">
        <?php if ($q !== ''): ?>
        <a href="kontak.php?filter=<?= sanitize($filter) ?>" class="search-clear">✕</a>
        <?php endif; ?>
        <span class="search-shortcut">/</span>
    </div>
    <select class="filter-select" id="sortFilter">
        <option value="terbaru" <?= $sort === 'terbaru' ? 'selected' : '' ?>>📅 Terbaru</option>
        <option value="terlama" <?= $sort === 'terlama' ? 'selected' : '' ?>>📅 Terlama</option>
        <option value="prioritas" <?= $sort === 'prioritas' ? 'selected' : '' ?>>🔥 Prioritas</option>
        <option value="nama" <?= $sort === 'nama' ? 'selected' : '' ?>>👤 Nama A-Z</option>
    </select>
    <input type="date" class="date-input" id="dateFrom" value="<?= sanitize($date_from) ?>" placeholder="Dari">
    <input type="date" class="date-input" id="dateTo" value="<?= sanitize($date_to) ?>" placeholder="Sampai">

    <label class="priority-toggle <?= $priority_only ? 'active' : '' ?>">
        <input type="checkbox" id="priorityToggle" <?= $priority_only ? 'checked' : '' ?> style="display: none;">
        🔥 Prioritas Saja
    </label>

    <div class="view-toggle">
        <button class="view-btn <?= $view === 'list' ? 'active' : '' ?>" onclick="switchView('list')">📋 List</button>
        <button class="view-btn <?= $view === 'cards' ? 'active' : '' ?>" onclick="switchView('cards')">🎴 Kartu</button>
        <button class="view-btn <?= $view === 'timeline' ? 'active' : '' ?>" onclick="switchView('timeline')">📊 Timeline</button>
    </div>

    <div class="export-dropdown">
        <button class="btn-action secondary" onclick="toggleExportMenu(event)">
            <span>📥</span>
            <span>Export</span>
            <span>▾</span>
        </button>
        <div class="export-menu" id="exportMenu">
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="export-item">
                <span class="export-item-icon">📊</span>
                <div>
                    <div style="font-weight: 600;">Export CSV</div>
                    <div style="font-size: 0.72rem; color: var(--text-muted);">Untuk Excel</div>
                </div>
            </a>
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'json'])) ?>" class="export-item">
                <span class="export-item-icon">🔧</span>
                <div>
                    <div style="font-weight: 600;">Export JSON</div>
                    <div style="font-size: 0.72rem; color: var(--text-muted);">Untuk API</div>
                </div>
            </a>
            <a href="#" onclick="window.print(); return false;" class="export-item">
                <span class="export-item-icon">🖨️</span>
                <div>
                    <div style="font-weight: 600;">Print</div>
                    <div style="font-size: 0.72rem; color: var(--text-muted);">Cetak pesan</div>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- ===== BULK ACTION BAR ===== -->
<div class="bulk-bar" id="bulkBar">
    <div class="bulk-info">
        <span class="bulk-count" id="bulkCount">0</span>
        <span>pesan dipilih</span>
    </div>
    <form method="POST" id="bulkForm" style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin: 0;">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
        <div class="bulk-actions">
            <button type="submit" name="action" value="bulk_read" class="bulk-btn primary">⚪ Tandai Dibaca</button>
            <button type="submit" name="action" value="bulk_replied" class="bulk-btn success">✅ Tandai Dibalas</button>
            <button type="submit" name="action" value="bulk_priority" class="bulk-btn warning">🔥 Prioritas</button>
            <button type="submit" name="action" value="bulk_archive" class="bulk-btn primary">📦 Arsipkan</button>
            <button type="submit" name="action" value="bulk_delete" class="bulk-btn danger" onclick="return confirm('HAPUS PERMANEN pesan terpilih?')">🗑️ Hapus</button>
        </div>
    </form>
    <button class="bulk-btn cancel" onclick="clearSel()">Batal</button>
</div>

<!-- ===== MESSAGE CONTAINER ===== -->
<div class="message-container" data-aos="fade-up">
    <div class="container-header">
        <div>
            <h2>✉️ Pesan Masuk</h2>
            <p style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.2rem;">Kelola semua pesan dari pengunjung</p>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <label class="select-all-wrap">
                <input type="checkbox" id="selAll" onchange="toggleAll(this)">
                <span>Pilih Semua</span>
            </label>
            <span style="font-size: 0.82rem; color: var(--text-muted); background: var(--bg-primary); padding: 0.35rem 0.75rem; border-radius: 999px;">
                📊 <?= $total ?> pesan
            </span>
        </div>
    </div>

    <?php if (empty($pesan)): ?>
        <div class="inbox-empty">
            <div class="ie-illustration">
                <div class="ie-circle"></div>
                <span class="ie-icon"><?= $q || $filter !== 'semua' ? '🔍' : '📭' ?></span>
            </div>
            <h3><?= $q || $filter !== 'semua' ? 'Tidak ada pesan cocok' : 'Inbox kosong' ?></h3>
            <p><?= $q || $filter !== 'semua' ? 'Coba ubah kata kunci atau filter pencarian.' : 'Semua pesan pengunjung akan muncul di sini secara real-time.' ?></p>
            <?php if ($q || $filter !== 'semua'): ?>
                <a href="kontak.php" class="btn-action secondary">🔄 Reset Filter</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php if ($view === 'list'): ?>
        <!-- LIST VIEW -->
        <div class="msg-list">
            <?php foreach ($pesan as $p):
                $hue = crc32($p['nama']) % 360;
                $text_combined = ($p['pesan'] ?? '') . ' ' . ($p['subjek'] ?? '');
                $priority = is_priority_msg($text_combined);
                $sentiment = detect_sentiment($text_combined);
                $tags = auto_tag($text_combined);
                $status_lower = strtolower($p['status']);
            ?>
            <div class="msg-row <?= $p['status']==='Baru'?'unread':'' ?> <?= $priority || $p['status']==='Prioritas'?'priority':'' ?>"
                data-id="<?= $p['id'] ?>"
                data-nama="<?= htmlspecialchars($p['nama'], ENT_QUOTES, 'UTF-8') ?>"
                data-email="<?= htmlspecialchars($p['email'], ENT_QUOTES, 'UTF-8') ?>"
                data-telepon="<?= htmlspecialchars($p['telepon'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                data-subjek="<?= htmlspecialchars($p['subjek'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                data-pesan="<?= htmlspecialchars($p['pesan'], ENT_QUOTES, 'UTF-8') ?>"
                data-status="<?= htmlspecialchars($p['status'], ENT_QUOTES, 'UTF-8') ?>"
                data-created="<?= sanitize(date('d F Y, H:i', strtotime($p['created_at']))) ?>"
                data-created-iso="<?= $p['created_at'] ?>"
                data-hue="<?= $hue ?>"
                data-sentiment="<?= $sentiment ?>"
                data-tags="<?= htmlspecialchars(implode(',', $tags), ENT_QUOTES, 'UTF-8') ?>"
                onclick="openMessage(this)">

                <input type="checkbox" class="row-check" form="bulkForm" name="ids[]" value="<?= $p['id'] ?>" onclick="event.stopPropagation()" onchange="updateBulk()">

                <div class="msg-avatar" style="background:linear-gradient(135deg,hsl(<?= $hue ?>,70%,50%),hsl(<?= ($hue+40)%360 ?>,70%,40%))">
                    <?= strtoupper(substr($p['nama'], 0, 1)) ?>
                </div>

                <div class="msg-main">
                    <div class="msg-top">
                        <strong class="msg-name"><?= sanitize($p['nama']) ?></strong>
                        <?php if ($priority || $p['status']==='Prioritas'): ?>
                        <span class="prio-flag" title="Pesan prioritas">🔥</span>
                        <?php endif; ?>
                        <?php if ($sentiment === 'positive'): ?>
                        <span class="sentiment-flag sentiment-positive" title="Sentimen positif">😊</span>
                        <?php elseif ($sentiment === 'negative'): ?>
                        <span class="sentiment-flag sentiment-negative" title="Sentimen negatif">😞</span>
                        <?php endif; ?>
                        <span class="msg-badge b-<?= $status_lower ?>"><?= $p['status'] ?></span>
                    </div>
                    <div class="msg-subject">— <?= sanitize($p['subjek'] ?: '(tanpa subjek)') ?></div>
                    <?php if (!empty($tags)): ?>
                    <div class="msg-tags">
                        <?php foreach ($tags as $tag): ?>
                        <span class="msg-tag"><?= $tag ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <div class="msg-excerpt"><?= sanitize(excerpt($p['pesan'], 110)) ?></div>
                </div>

                <div class="msg-side">
                    <time class="msg-time"><?= time_ago(strtotime($p['created_at'])) ?></time>
                    <div class="row-quick">
                        <button class="rq-btn success" title="Balas" onclick="event.stopPropagation(); openReply(<?= $p['id'] ?>, '<?= htmlspecialchars($p['email'], ENT_QUOTES) ?>', '<?= htmlspecialchars($p['nama'], ENT_QUOTES) ?>')">↩️</button>
                        <button class="rq-btn" title="Tandai Dibaca" onclick="event.stopPropagation(); doAction(<?= $p['id'] ?>, 'read')">👁️</button>
                        <button class="rq-btn danger" title="Hapus" onclick="event.stopPropagation(); askDelete(<?= $p['id'] ?>, <?= json_encode(sanitize($p['nama'])) ?>)">🗑️</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php elseif ($view === 'cards'): ?>
        <!-- CARD VIEW -->
        <div class="msg-grid">
            <?php foreach ($pesan as $p):
                $hue = crc32($p['nama']) % 360;
                $text_combined = ($p['pesan'] ?? '') . ' ' . ($p['subjek'] ?? '');
                $priority = is_priority_msg($text_combined);
                $sentiment = detect_sentiment($text_combined);
                $tags = auto_tag($text_combined);
            ?>
            <div class="msg-card <?= $p['status']==='Baru'?'unread':'' ?> <?= $priority?'priority':'' ?>"
                data-id="<?= $p['id'] ?>"
                data-nama="<?= htmlspecialchars($p['nama'], ENT_QUOTES, 'UTF-8') ?>"
                data-email="<?= htmlspecialchars($p['email'], ENT_QUOTES, 'UTF-8') ?>"
                data-telepon="<?= htmlspecialchars($p['telepon'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                data-subjek="<?= htmlspecialchars($p['subjek'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                data-pesan="<?= htmlspecialchars($p['pesan'], ENT_QUOTES, 'UTF-8') ?>"
                data-status="<?= htmlspecialchars($p['status'], ENT_QUOTES, 'UTF-8') ?>"
                data-created="<?= sanitize(date('d F Y, H:i', strtotime($p['created_at']))) ?>"
                data-hue="<?= $hue ?>"
                data-sentiment="<?= $sentiment ?>"
                data-tags="<?= htmlspecialchars(implode(',', $tags), ENT_QUOTES, 'UTF-8') ?>"
                onclick="openMessage(this)">
                <div class="msg-card-header">
                    <div class="msg-card-avatar" style="background:linear-gradient(135deg,hsl(<?= $hue ?>,70%,50%),hsl(<?= ($hue+40)%360 ?>,70%,40%))">
                        <?= strtoupper(substr($p['nama'], 0, 1)) ?>
                    </div>
                    <div class="msg-card-info">
                        <div class="msg-card-name">
                            <?= sanitize($p['nama']) ?>
                            <?php if ($priority): ?><span class="prio-flag">🔥</span><?php endif; ?>
                        </div>
                        <div class="msg-card-email"><?= sanitize($p['email']) ?></div>
                    </div>
                    <span class="msg-badge b-<?= strtolower($p['status']) ?>"><?= $p['status'] ?></span>
                </div>
                <div class="msg-card-body">
                    <div class="msg-card-subject"><?= sanitize($p['subjek'] ?: '(tanpa subjek)') ?></div>
                    <div class="msg-card-excerpt"><?= sanitize($p['pesan']) ?></div>
                    <?php if (!empty($tags)): ?>
                    <div class="msg-tags">
                        <?php foreach ($tags as $tag): ?>
                        <span class="msg-tag"><?= $tag ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="msg-card-footer">
                    <span>📅 <?= date('d M Y', strtotime($p['created_at'])) ?></span>
                    <span><?= time_ago(strtotime($p['created_at'])) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
        <!-- TIMELINE VIEW -->
        <div class="timeline-view">
            <?php
            $grouped = [];
            foreach ($pesan as $p) {
                $date = date('Y-m-d', strtotime($p['created_at']));
                $grouped[$date][] = $p;
            }
            krsort($grouped);
            foreach ($grouped as $date => $items):
            ?>
            <div class="timeline-group">
                <div class="timeline-group-header">
                    <span class="timeline-date">📅 <?= date('d F Y', strtotime($date)) ?></span>
                    <span class="timeline-count"><?= count($items) ?> pesan</span>
                </div>
                <?php foreach ($items as $p):
                    $hue = crc32($p['nama']) % 360;
                    $text_combined = ($p['pesan'] ?? '') . ' ' . ($p['subjek'] ?? '');
                    $tags = auto_tag($text_combined);
                ?>
                <div class="timeline-item"
                    data-id="<?= $p['id'] ?>"
                    data-nama="<?= htmlspecialchars($p['nama'], ENT_QUOTES, 'UTF-8') ?>"
                    data-email="<?= htmlspecialchars($p['email'], ENT_QUOTES, 'UTF-8') ?>"
                    data-telepon="<?= htmlspecialchars($p['telepon'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    data-subjek="<?= htmlspecialchars($p['subjek'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    data-pesan="<?= htmlspecialchars($p['pesan'], ENT_QUOTES, 'UTF-8') ?>"
                    data-status="<?= htmlspecialchars($p['status'], ENT_QUOTES, 'UTF-8') ?>"
                    data-created="<?= sanitize(date('d F Y, H:i', strtotime($p['created_at']))) ?>"
                    data-hue="<?= $hue ?>"
                    onclick="openMessage(this)">
                    <div class="timeline-item-avatar" style="background:linear-gradient(135deg,hsl(<?= $hue ?>,70%,50%),hsl(<?= ($hue+40)%360 ?>,70%,40%))">
                        <?= strtoupper(substr($p['nama'], 0, 1)) ?>
                    </div>
                    <div class="timeline-item-info">
                        <div class="timeline-item-name">
                            <?= sanitize($p['nama']) ?>
                            <span class="msg-badge b-<?= strtolower($p['status']) ?>" style="font-size: 0.6rem; padding: 0.1rem 0.4rem;"><?= $p['status'] ?></span>
                        </div>
                        <div class="timeline-item-meta">
                            <span><?= sanitize($p['subjek'] ?: '(tanpa subjek)') ?></span>
                            <span>🕐 <?= date('H:i', strtotime($p['created_at'])) ?></span>
                            <?php if (!empty($tags)): ?>
                            <span><?= implode(' • ', $tags) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="inbox-pagination">
            <span class="ip-info">Halaman <?= $halaman ?> dari <?= $total_pages ?> (<?= $total ?> pesan)</span>
            <div class="ip-buttons">
                <?php if ($halaman > 1): ?><a class="ip-btn" href="?<?= http_build_query(array_merge($_GET, ['halaman'=>$halaman-1])) ?>">←</a><?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?= $i === $halaman ? "<span class='ip-btn current'>$i</span>" : "<a class='ip-btn' href='?" . http_build_query(array_merge($_GET, ['halaman'=>$i])) . "'>$i</a>" ?>
                <?php endfor; ?>
                <?php if ($halaman < $total_pages): ?><a class="ip-btn" href="?<?= http_build_query(array_merge($_GET, ['halaman'=>$halaman+1])) ?>">→</a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- ===== READING PANE MODAL (Enhanced with Tabs) ===== -->
<div class="modal-overlay" id="readModal" onclick="if(event.target===this)closeRead()">
    <div class="read-pane">
        <div class="read-header">
            <div class="read-avatar" id="readAvatar">?</div>
            <div class="read-headinfo">
                <h3 id="readNama">Nama Pengirim</h3>
                <div class="read-submeta">
                    <span id="readEmail">email</span>
                    <span id="readTelepon"></span>
                    <span id="readCreated"></span>
                </div>
            </div>
            <button class="read-close" onclick="closeRead()">✕</button>
        </div>

        <div class="read-tabs">
            <button class="read-tab active" onclick="switchReadTab('message', this)">💬 Pesan</button>
            <button class="read-tab" onclick="switchReadTab('metadata', this)">ℹ️ Metadata</button>
            <button class="read-tab" onclick="switchReadTab('history', this)">📜 Riwayat</button>
            <button class="read-tab" onclick="switchReadTab('notes', this)">📝 Catatan</button>
        </div>

        <div class="read-body">
            <!-- Tab: Message -->
            <div class="read-tab-content active" id="tab-message">
                <div class="read-subject-row">
                    <h2 id="readSubjek">Subjek</h2>
                    <span class="msg-badge" id="readStatus">Status</span>
                </div>
                <div class="read-message" id="readPesan"></div>
                <div id="readTags"></div>
            </div>

            <!-- Tab: Metadata -->
            <div class="read-tab-content" id="tab-metadata">
                <div class="metadata-grid">
                    <div class="metadata-item">
                        <div class="metadata-label">👤 Nama</div>
                        <div class="metadata-value" id="metaNama">-</div>
                    </div>
                    <div class="metadata-item">
                        <div class="metadata-label">✉️ Email</div>
                        <div class="metadata-value" id="metaEmail">-</div>
                    </div>
                    <div class="metadata-item">
                        <div class="metadata-label">📞 Telepon</div>
                        <div class="metadata-value" id="metaTelepon">-</div>
                    </div>
                    <div class="metadata-item">
                        <div class="metadata-label">📅 Tanggal</div>
                        <div class="metadata-value" id="metaCreated">-</div>
                    </div>
                    <div class="metadata-item">
                        <div class="metadata-label">😊 Sentimen</div>
                        <div class="metadata-value" id="metaSentiment">-</div>
                    </div>
                    <div class="metadata-item">
                        <div class="metadata-label">🏷️ Auto Tags</div>
                        <div class="metadata-value" id="metaTags">-</div>
                    </div>
                </div>
            </div>

            <!-- Tab: History -->
            <div class="read-tab-content" id="tab-history">
                <div class="history-timeline" id="readHistory">
                    <div class="history-item">
                        <div class="history-title">📥 Pesan diterima</div>
                        <div class="history-desc">Pesan masuk ke inbox</div>
                        <div class="history-time" id="histCreated">-</div>
                    </div>
                </div>
            </div>

            <!-- Tab: Notes -->
            <div class="read-tab-content" id="tab-notes">
                <div class="notes-area" id="readNotes">
                    <em style="color: var(--text-muted);">Belum ada catatan internal.</em>
                </div>
                <form method="POST" class="add-note-form" id="addNoteForm">
                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                    <input type="hidden" name="id" id="noteId">
                    <input type="hidden" name="action" value="add_note">
                    <input type="text" name="note" placeholder="Tambah catatan internal..." required>
                    <button type="submit">💾 Simpan</button>
                </form>
            </div>
        </div>

        <div class="read-footer">
            <div class="reply-composer">
                <select id="replyTemplate" class="reply-select">
                    <option value="formal">📄 Balasan Formal</option>
                    <option value="pmb">🎓 Info PMB</option>
                    <option value="beasiswa">💰 Info Beasiswa</option>
                    <option value="followup">🔄 Follow-up</option>
                    <option value="kerjasama">🤝 Kerjasama</option>
                    <option value="custom">✏️ Custom Template...</option>
                </select>
                <button class="btn-sm primary" id="replyBtn" onclick="sendReply()">✉️ Balas via Email</button>
            </div>
            <div class="read-actions">
                <button class="btn-sm gray" onclick="copyEmail()">📋 Copy Email</button>
                <button class="btn-sm gray" id="btnRead" onclick="doAction(current.id, 'read')">👁️ Dibaca</button>
                <button class="btn-sm success" id="btnReplied" onclick="doAction(current.id, 'replied')">✅ Dibalas</button>
                <button class="btn-sm amber" id="btnPriority" onclick="doAction(current.id, 'priority')">🔥 Prioritas</button>
                <button class="btn-sm gray" onclick="doAction(current.id, 'archive')">📦 Arsip</button>
                <button class="btn-sm red" onclick="askDelete(current.id, current.nama)">🗑️ Hapus</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== QUICK REPLY MODAL ===== -->
<div class="modal-overlay" id="replyModal" onclick="if(event.target===this)closeReplyModal()">
    <div class="reply-modal-content">
        <div class="reply-modal-header">
            <h3>✉️ Quick Reply</h3>
            <button class="read-close" onclick="closeReplyModal()">✕</button>
        </div>
        <div class="reply-templates-grid">
            <div class="reply-template-card active" onclick="selectReplyTemplate(this, 'formal')">
                <div class="reply-template-icon">📄</div>
                <div class="reply-template-title">Balasan Formal</div>
                <div class="reply-template-desc">Template balasan umum untuk pertanyaan standar</div>
            </div>
            <div class="reply-template-card" onclick="selectReplyTemplate(this, 'pmb')">
                <div class="reply-template-icon">🎓</div>
                <div class="reply-template-title">Info PMB</div>
                <div class="reply-template-desc">Informasi pendaftaran mahasiswa baru</div>
            </div>
            <div class="reply-template-card" onclick="selectReplyTemplate(this, 'beasiswa')">
                <div class="reply-template-icon">💰</div>
                <div class="reply-template-title">Info Beasiswa</div>
                <div class="reply-template-desc">Informasi program beasiswa yang tersedia</div>
            </div>
            <div class="reply-template-card" onclick="selectReplyTemplate(this, 'followup')">
                <div class="reply-template-icon">🔄</div>
                <div class="reply-template-title">Follow-up</div>
                <div class="reply-template-desc">Tindak lanjut pesan sebelumnya</div>
            </div>
            <div class="reply-template-card" onclick="selectReplyTemplate(this, 'kerjasama')">
                <div class="reply-template-icon">🤝</div>
                <div class="reply-template-title">Kerjasama</div>
                <div class="reply-template-desc">Tanggapan untuk proposal kerjasama</div>
            </div>
            <div class="reply-template-card" onclick="selectReplyTemplate(this, 'dokumen')">
                <div class="reply-template-icon">📋</div>
                <div class="reply-template-title">Permohonan Dokumen</div>
                <div class="reply-template-desc">Info legalisir, transkrip, ijazah</div>
            </div>
        </div>
        <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">👁️ Preview</div>
        <div class="reply-preview" id="replyPreview">Pilih template untuk melihat preview...</div>
        <div class="reply-actions">
            <button class="btn-sm gray" onclick="closeReplyModal()">Batal</button>
            <button class="btn-sm primary" onclick="confirmReply()">📧 Kirim via Email</button>
        </div>
    </div>
</div>

<!-- ===== CONFIRM MODAL ===== -->
<div class="modal-overlay" id="confirmModal" onclick="if(event.target===this)closeConfirm()">
    <div class="confirm-box">
        <div class="confirm-icon">🗑️</div>
        <h3>Hapus Pesan?</h3>
        <p id="confirmMsg">Pesan akan dihapus permanen.</p>
        <div class="confirm-actions">
            <button class="btn-sm gray" onclick="closeConfirm()">Batal</button>
            <button class="btn-sm red" id="confirmOk">Ya, Hapus</button>
        </div>
    </div>
</div>

<!-- Hidden single-action form -->
<form id="actionForm" method="POST" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
    <input type="hidden" name="id" id="afId">
    <input type="hidden" name="action" id="afAction">
</form>

<!-- New message toast -->
<div class="new-message-toast" id="newMessageToast" onclick="window.location.reload()">
    <span class="toast-icon">✉️</span>
    <div class="toast-content">
        <div class="toast-title">Pesan Baru!</div>
        <div class="toast-desc">Klik untuk refresh inbox</div>
    </div>
</div>

<!-- Shortcuts help -->
<div class="shortcuts-help">
    <span>💡 Shortcuts:</span>
    <span><kbd>/</kbd> Search</span>
    <span><kbd>R</kbd> Reply</span>
    <span><kbd>M</kbd> Mark</span>
    <span><kbd>D</kbd> Delete</span>
    <span><kbd>Esc</kbd> Close</span>
</div>

<script>
// ===== State =====
let current = { id: 0, nama: '', email: '', subjek: '' };
let selectedTemplate = 'formal';

// ===== Reply Templates =====
const replyTemplates = {
    formal: `Yth. Bapak/Ibu [NAMA],\n\nTerima kasih telah menghubungi Fakultas Keguruan dan Ilmu Pendidikan Universitas Muhammadiyah Maumere.\n\nPesan Anda telah kami terima dan akan segera kami tindak lanjuti. Tim kami akan menghubungi Anda kembali dalam waktu 1-2 hari kerja.\n\nApabila ada pertanyaan mendesak, silakan hubungi kami di:\n📞 (0382) 21234\n✉️ fkip@unimof.ac.id\n\nHormat kami,\nTim Humas FKIP UNIMOF`,

    pmb: `Halo [NAMA],\n\nTerima kasih atas ketertarikan Anda bergabung dengan FKIP UNIMOF! 🎓\n\nBerikut informasi pendaftaran mahasiswa baru:\n\n📅 Gelombang Pendaftaran:\n- Gelombang 1: Januari - Maret\n- Gelombang 2: April - Juni\n- Gelombang 3: Juli - Agustus\n\n📋 Berkas yang diperlukan:\n1. Ijazah/SKL yang telah dilegalisir\n2. Transkrip nilai\n3. Pas foto 3x4 (4 lembar)\n4. Fotokopi KTP/KK\n5. Fotokopi Akte Kelahiran\n\n💰 Biaya pendaftaran: Rp 250.000\n\n🌐 Pendaftaran online: https://pmb.unimof.ac.id\n\nUntuk informasi lebih lanjut tentang beasiswa dan program studi, silakan kunjungi website kami.\n\nSalam hangat,\nPanitia PMB FKIP UNIMOF`,

    beasiswa: `Halo [NAMA],\n\nTerima kasih atas pertanyaan Anda mengenai program beasiswa di FKIP UNIMOF! 💰\n\nBerikut beberapa jenis beasiswa yang tersedia:\n\n1. 🏆 Beasiswa Prestasi Akademik\n   - IPK minimal 3.50\n   - Potongan UKT hingga 50%\n\n2. 🥇 Beasiswa Prestasi Non-Akademik\n   - Juara lomba tingkat nasional/internasional\n   - Potongan UKT hingga 75%\n\n3. 🙏 Beasiswa Kurang Mampu\n   - Berdasarkan kondisi ekonomi\n   - Potongan UKT hingga 100%\n\n4. 📚 Beasiswa KIP-Kuliah\n   - Program pemerintah\n   - Full biaya pendidikan\n\n📅 Pendaftaran beasiswa dibuka setiap awal semester.\n📧 Info lengkap: beasiswa@unimof.ac.id\n\nSalam,\nBagian Kemahasiswaan FKIP UNIMOF`,

    followup: `Halo [NAMA],\n\nMenindaklanjuti pesan Anda sebelumnya, berikut kami sampaikan update terbaru terkait pertanyaan Anda.\n\n[UPDATE DI SINI]\n\nApabila masih ada hal yang perlu dikonfirmasi atau ada pertanyaan tambahan, silakan balas email ini. Tim kami siap membantu Anda.\n\nTerima kasih atas perhatian Anda.\n\nHormat kami,\nTim Humas FKIP UNIMOF`,

    kerjasama: `Yth. Bapak/Ibu [NAMA],\n\nTerima kasih atas proposal kerjasama yang telah Anda sampaikan kepada FKIP UNIMOF.\n\nKami sangat mengapresiasi inisiatif kerjasama ini. Tim kami akan mempelajari proposal Anda dan menghubungi Anda dalam waktu 5-7 hari kerja untuk membahas kemungkinan kolaborasi lebih lanjut.\n\nUntuk mempercepat proses, mohon lengkapi:\n1. Profil institusi\n2. Detail ruang lingkup kerjasama\n3. Timeline implementasi\n4. Narahubung yang dapat dihubungi\n\nFKIP UNIMOF terbuka untuk berbagai bentuk kolaborasi yang saling menguntungkan dalam bidang pendidikan, penelitian, dan pengabdian masyarakat.\n\nHormat kami,\nBagian Kerjasama FKIP UNIMOF`,

    dokumen: `Halo [NAMA],\n\nTerima kasih telah menghubungi FKIP UNIMOF terkait permohonan dokumen. 📋\n\nBerikut informasi layanan dokumen:\n\n📜 Legalisir Ijazah/Transkrip:\n- Proses: 3-5 hari kerja\n- Biaya: Rp 10.000/lembar\n- Bawa dokumen asli + fotokopi\n\n📄 Surat Keterangan Lulus (SKL):\n- Proses: 2-3 hari kerja\n- Gratis untuk alumni\n- Surat pengantar dari prodi\n\n📚 Transkrip Nilai:\n- Mahasiswa aktif: via SIAKAD\n- Alumni: hubungi bagian akademik\n\n⏰ Jam layanan: Senin-Jumat, 08.00-16.00 WITA\n📍 Lokasi: Gedung FKIP, Lantai 1, Bagian Akademik\n\nUntuk permintaan khusus, silakan datang langsung atau hubungi kami via WhatsApp.\n\nSalam,\nBagian Akademik FKIP UNIMOF`
};

// ===== Charts =====
<?php if (!empty($daily_stats)): ?>
new ApexCharts(document.querySelector("#dailyChart"), {
    series: [{ name: 'Pesan', data: <?= json_encode(array_values($daily_stats)) ?> }],
    chart: { type: 'area', height: 260, toolbar: { show: false } },
    colors: ['#3b82f6'],
    dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
    xaxis: {
        categories: <?= json_encode(array_map(function($d) { return date('D', strtotime($d)); }, array_keys($daily_stats))) ?>,
        labels: { style: { fontSize: '11px' } }
    },
    yaxis: { labels: { style: { fontSize: '11px' } } },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } },
    stroke: { curve: 'smooth', width: 3 }
}).render();
<?php endif; ?>

new ApexCharts(document.querySelector("#statusChart"), {
    series: [<?= $count_baru ?>, <?= $count_dibaca ?>, <?= $count_dibalas ?>, <?= $count_priority ?>, <?= $count_arsip ?>],
    labels: ['Baru', 'Dibaca', 'Dibalas', 'Prioritas', 'Arsip'],
    chart: { type: 'donut', height: 260, animations: { enabled: true, speed: 800 } },
    colors: ['#3b82f6', '#9ca3af', '#10b981', '#f59e0b', '#6b7280'],
    plotOptions: {
        pie: {
            donut: {
                size: '70%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Total',
                        formatter: () => <?= $count_all ?>
                    }
                }
            }
        }
    },
    dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
    legend: { position: 'bottom', fontSize: '11px' }
}).render();

// ===== COUNT UP ANIMATION =====
function animateCount(el) {
    const target = parseInt(el.dataset.target) || 0;
    const duration = 1800;
    const start = performance.now();
    function step(now) {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.floor(eased * target).toLocaleString('id-ID');
        if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
}
const countObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            animateCount(entry.target);
            countObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.3 });
document.querySelectorAll('.count-up').forEach(el => countObserver.observe(el));

// ===== Open reading pane =====
function openMessage(row) {
    const d = row.dataset;
    current = { id: d.id, nama: d.nama, email: d.email, subjek: d.subjek || '(tanpa subjek)' };

    document.getElementById('readAvatar').textContent = d.nama.charAt(0).toUpperCase();
    document.getElementById('readAvatar').style.background =
        `linear-gradient(135deg,hsl(${d.hue},70%,50%),hsl(${(parseInt(d.hue)+40)%360},70%,40%))`;
    document.getElementById('readNama').textContent = d.nama;
    document.getElementById('readEmail').textContent = '📧 ' + d.email;
    document.getElementById('readTelepon').textContent = d.telepon ? '📞 ' + d.telepon : '';
    document.getElementById('readCreated').textContent = '🕐 ' + d.created;
    document.getElementById('readSubjek').textContent = d.subjek || '(tanpa subjek)';
    document.getElementById('readPesan').textContent = d.pesan;

    const badge = document.getElementById('readStatus');
    badge.textContent = d.status;
    badge.className = 'msg-badge b-' + d.status.toLowerCase();

    // Metadata tab
    document.getElementById('metaNama').textContent = d.nama;
    document.getElementById('metaEmail').textContent = d.email;
    document.getElementById('metaTelepon').textContent = d.telepon || '-';
    document.getElementById('metaCreated').textContent = d.created;
    document.getElementById('metaSentiment').textContent = d.sentiment === 'positive' ? '😊 Positif' : (d.sentiment === 'negative' ? '😞 Negatif' : '😐 Netral');

    // Tags
    const tags = (d.tags || '').split(',').filter(t => t);
    const metaTags = document.getElementById('metaTags');
    const readTags = document.getElementById('readTags');
    if (tags.length > 0) {
        metaTags.innerHTML = tags.map(t => `<span class="msg-tag">${t}</span>`).join(' ');
        readTags.innerHTML = '<div style="margin-top: 1rem;"><div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; margin-bottom: 0.35rem;">🏷️ Auto Tags</div>' + tags.map(t => `<span class="msg-tag">${t}</span>`).join(' ') + '</div>';
    } else {
        metaTags.textContent = '-';
        readTags.innerHTML = '';
    }

    // History
    document.getElementById('histCreated').textContent = d.createdIso || d.created;

    // Notes
    document.getElementById('noteId').value = d.id;
    document.getElementById('readNotes').innerHTML = '<em style="color: var(--text-muted);">Belum ada catatan internal.</em>';

    document.getElementById('readModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeRead() {
    document.getElementById('readModal').classList.remove('open');
    document.body.style.overflow = '';
}

function switchReadTab(tab, btn) {
    document.querySelectorAll('.read-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.read-tab-content').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-' + tab).classList.add('active');
}

// ===== Actions =====
function doAction(id, action) {
    document.getElementById('afId').value = id;
    document.getElementById('afAction').value = action;
    document.getElementById('actionForm').submit();
}

let deleteTarget = null;
function askDelete(id, nama) {
    deleteTarget = id;
    document.getElementById('confirmMsg').textContent = 'Pesan dari "' + nama + '" akan dihapus permanen.';
    document.getElementById('confirmModal').classList.add('open');
}
function closeConfirm() {
    document.getElementById('confirmModal').classList.remove('open');
    deleteTarget = null;
}
document.getElementById('confirmOk').addEventListener('click', () => {
    if (deleteTarget) {
        document.getElementById('afId').value = deleteTarget;
        document.getElementById('afAction').value = 'delete';
        document.getElementById('actionForm').submit();
    }
    closeConfirm();
});

// ===== Quick Reply Modal =====
function openReply(id, email, nama) {
    current = { id, email, nama, subjek: 'Re: Pesan Anda ke FKIP UNIMOF' };
    selectedTemplate = 'formal';
    document.querySelectorAll('.reply-template-card').forEach(c => c.classList.remove('active'));
    document.querySelector('.reply-template-card').classList.add('active');
    updateReplyPreview();
    document.getElementById('replyModal').classList.add('open');
}
function closeReplyModal() {
    document.getElementById('replyModal').classList.remove('open');
}
function selectReplyTemplate(card, key) {
    document.querySelectorAll('.reply-template-card').forEach(c => c.classList.remove('active'));
    card.classList.add('active');
    selectedTemplate = key;
    updateReplyPreview();
}
function updateReplyPreview() {
    const template = replyTemplates[selectedTemplate] || replyTemplates.formal;
    const preview = template.replace(/\[NAMA\]/g, current.nama || 'Bapak/Ibu');
    document.getElementById('replyPreview').textContent = preview;
}
function confirmReply() {
    const template = replyTemplates[selectedTemplate] || replyTemplates.formal;
    const body = template.replace(/\[NAMA\]/g, current.nama || 'Bapak/Ibu');
    const subject = 'Re: ' + (current.subjek || 'Pesan Anda ke FKIP UNIMOF');
    window.location.href = 'mailto:' + encodeURIComponent(current.email)
        + '?subject=' + encodeURIComponent(subject)
        + '&body=' + encodeURIComponent(body);
    setTimeout(() => {
        if (confirm('Tandai pesan ini sebagai DIBALAS?')) doAction(current.id, 'replied');
    }, 800);
    closeReplyModal();
}

// ===== Reply with template (legacy) =====
function sendReply() {
    const tpl = document.getElementById('replyTemplate').value;
    if (tpl === 'custom') {
        openReply(current.id, current.email, current.nama);
        return;
    }
    const body = (replyTemplates[tpl] || replyTemplates.formal).replace(/\[NAMA\]/g, current.nama || 'Bapak/Ibu');
    const subject = 'Re: ' + (current.subjek || 'Pesan Anda ke FKIP UNIMOF');
    window.location.href = 'mailto:' + encodeURIComponent(current.email)
        + '?subject=' + encodeURIComponent(subject)
        + '&body=' + encodeURIComponent(body);
    setTimeout(() => {
        if (confirm('Tandai pesan ini sebagai DIBALAS?')) doAction(current.id, 'replied');
    }, 800);
}

// ===== Copy email =====
function copyEmail() {
    navigator.clipboard.writeText(current.email).then(() => {
        const btn = event.target;
        const old = btn.textContent;
        btn.textContent = '✅ Tersalin!';
        setTimeout(() => btn.textContent = old, 1500);
    });
}

// ===== Search word from cloud =====
function searchWord(word) {
    const url = new URL(window.location);
    url.searchParams.set('q', word);
    window.location = url;
}

// ===== Bulk selection =====
function toggleAll(master) {
    document.querySelectorAll('.row-check').forEach(cb => {
        cb.checked = master.checked;
        cb.closest('[data-id]')?.classList.toggle('selected', master.checked);
    });
    updateBulk();
}
document.querySelectorAll('.row-check').forEach(cb => cb.addEventListener('change', function(){
    this.closest('[data-id]')?.classList.toggle('selected', this.checked);
    updateBulk();
}));
function updateBulk() {
    const n = document.querySelectorAll('.row-check:checked').length;
    document.getElementById('bulkCount').textContent = n;
    document.getElementById('bulkBar').classList.toggle('show', n > 0);
}
function clearSel() {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
    document.querySelectorAll('[data-id]').forEach(r => r.classList.remove('selected'));
    document.getElementById('selAll').checked = false;
    updateBulk();
}

// ===== Search debounce =====
let sTimer;
document.getElementById('inboxSearch')?.addEventListener('input', function(){
    clearTimeout(sTimer);
    const v = this.value;
    sTimer = setTimeout(() => {
        const url = new URL(window.location);
        if (v) url.searchParams.set('q', v); else url.searchParams.delete('q');
        url.searchParams.delete('halaman');
        window.location = url;
    }, 500);
});

// ===== Sort & filter handlers =====
document.getElementById('sortFilter')?.addEventListener('change', function() {
    const url = new URL(window.location);
    url.searchParams.set('sort', this.value);
    window.location = url;
});

document.getElementById('dateFrom')?.addEventListener('change', function() {
    const url = new URL(window.location);
    if (this.value) url.searchParams.set('from', this.value); else url.searchParams.delete('from');
    window.location = url;
});
document.getElementById('dateTo')?.addEventListener('change', function() {
    const url = new URL(window.location);
    if (this.value) url.searchParams.set('to', this.value); else url.searchParams.delete('to');
    window.location = url;
});

document.getElementById('priorityToggle')?.addEventListener('change', function() {
    const url = new URL(window.location);
    if (this.checked) url.searchParams.set('priority', '1'); else url.searchParams.delete('priority');
    window.location = url;
});

function switchView(view) {
    const url = new URL(window.location);
    url.searchParams.set('view', view);
    window.location = url;
}

// ===== Export dropdown =====
function toggleExportMenu(e) {
    e.stopPropagation();
    document.getElementById('exportMenu').classList.toggle('show');
}
document.addEventListener('click', (e) => {
    if (!e.target.closest('.export-dropdown')) {
        document.getElementById('exportMenu')?.classList.remove('show');
    }
});

// ===== Keyboard shortcuts =====
document.addEventListener('keydown', e => {
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey &&
        document.activeElement.tagName !== 'INPUT' &&
        document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        document.getElementById('inboxSearch')?.focus();
    }
    if (e.key === 'Escape') {
        closeRead();
        closeConfirm();
        closeReplyModal();
    }
    if (e.key === 'r' || e.key === 'R') {
        if (document.getElementById('readModal').classList.contains('open')) {
            document.getElementById('replyBtn').click();
        }
    }
});

// ===== Search highlighting =====
const q = <?= json_encode($q) ?>;
if (q) {
    document.querySelectorAll('.msg-name, .msg-subject, .msg-excerpt, .timeline-item-name').forEach(el => {
        if (!el.querySelector('mark')) {
            const html = el.innerHTML;
            const regex = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
            el.innerHTML = html.replace(regex, '<mark>$1</mark>');
        }
    });
}

// ===== Simulate real-time new message check (optional) =====
let lastCount = <?= $count_all ?>;
setInterval(async () => {
    try {
        // Simple check - you can enhance with fetch API
        // For now just show a reminder if count_baru > 0
        <?php if ($count_baru > 0): ?>
        // Could trigger toast here if needed
        <?php endif; ?>
    } catch (e) {}
}, 60000); // Check every 60 seconds

console.log('%c✉️ Communication Hub FKIP UNIMOF - Super Extreme', 'color: #3b82f6; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: / (Search), R (Reply), M (Mark), D (Delete), ESC (Close)', 'color: #64748b;');
console.log('%cFitur: Sentiment Detection, Auto-Tagging, Heatmap, Word Cloud, 3 View Modes, Quick Reply', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>