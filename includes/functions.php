<?php
// includes/functions.php - Helper functions (EXTREME MULTIMATE VERSION)

/**
 * Generate Base URL yang Aman
 */
function base_url(string $path = ''): string {
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

/**
 * Generate Asset URL
 */
function asset(string $path): string {
    return base_url('assets/' . ltrim($path, '/'));
}

/**
 * Format Tanggal Indonesia Lengkap (e.g., 25 September 2026)
 */
function format_tanggal(string $date, string $format = 'd F Y'): string {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $timestamp = strtotime($date);
    if ($format === 'd F Y') {
        return date('d', $timestamp) . ' ' . $bulan[(int)date('n', $timestamp)] . ' ' . date('Y', $timestamp);
    }
    return date($format, $timestamp);
}

/**
 * Format Tanggal Singkat (e.g., 25 Sep 2026)
 */
function format_tanggal_singkat(string $date): string {
    $bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $timestamp = strtotime($date);
    return date('d', $timestamp) . ' ' . $bulan[(int)date('n', $timestamp) - 1] . ' ' . date('Y', $timestamp);
}

/**
 * Format Rentang Tanggal Indonesia (e.g., 12 - 14 Oktober 2026)
 * Sangat berguna untuk modul Agenda!
 */
function format_tanggal_range(string $start_date, ?string $end_date): string {
    if (!$end_date || $start_date === $end_date) {
        return format_tanggal($start_date);
    }
    $start_ts = strtotime($start_date);
    $end_ts = strtotime($end_date);
    $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    
    if (date('m Y', $start_ts) === date('m Y', $end_ts)) {
        return date('d', $start_ts) . ' - ' . date('d', $end_ts) . ' ' . $bulan[(int)date('n', $start_ts)] . ' ' . date('Y', $start_ts);
    }
    return format_tanggal($start_date) . ' s.d. ' . format_tanggal($end_date);
}

/**
 * Excerpt Text (Word-based, tidak memotong kata di tengah)
 */
function excerpt(string $text, int $length = 150, string $end = '...'): string {
    $text = strip_tags($text);
    $text = trim(preg_replace('/\s+/', ' ', $text));
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, mb_strrpos(mb_substr($text, 0, $length), ' ')) . $end;
}

/**
 * Truncate by Words (e.g., maksimal 20 kata)
 */
function truncate_words(string $text, int $word_limit = 20, string $end = '...'): string {
    $words = explode(' ', strip_tags($text));
    if (count($words) <= $word_limit) return implode(' ', $words);
    return implode(' ', array_slice($words, 0, $word_limit)) . $end;
}

/**
 * Format File Size menjadi Human Readable (e.g., "2.5 MB")
 */
function format_file_size(int $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Get Website Setting dengan IN-MEMORY CACHING (Blazing Fast ⚡)
 * Mencegah query database berulang-ulang untuk setting yang sama.
 */
function get_setting(string $key, $default = null) {
    global $pdo;
    static $cache = [];
    
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT nilai FROM pengaturan WHERE nama_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        $cache[$key] = $result ?: $default;
        return $cache[$key];
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Get Statistics dengan Caching
 */
function get_statistik(): array {
    global $pdo;
    static $cache = null;
    
    if ($cache !== null) return $cache;
    
    try {
        $stmt = $pdo->query("SELECT * FROM statistik ORDER BY id DESC LIMIT 1");
        $data = $stmt->fetch();
        $cache = $data ?: [
            'total_mahasiswa' => 1250, 'total_dosen' => 68, 
            'total_prodi' => 8, 'total_penelitian' => 45, 'total_alumni' => 3200
        ];
        return $cache;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get Featured News
 */
function get_featured_berita(int $limit = 3): array {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM berita WHERE status = 'Published' ORDER BY is_featured DESC, published_at DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get Active Study Programs
 */
function get_prodi(): array {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT * FROM program_studi WHERE status = 'Aktif' ORDER BY urutan ASC");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get Upcoming Agenda (Hanya yang aktif dan masa depan)
 */
function get_agenda_mendatang(int $limit = 5): array {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM agenda WHERE tanggal_mulai >= CURDATE() AND status = 'Aktif' ORDER BY tanggal_mulai ASC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Flash Message Setter
 */
function flash_message(string $type, string $message): void {
    $_SESSION['flash'][$type] = $message;
}

/**
 * Flash Message Display dengan Ikon & Auto-dismiss Attribute
 */
function display_flash(): void {
    if (empty($_SESSION['flash'])) return;
    foreach ($_SESSION['flash'] as $type => $message) {
        $icon = match($type) {
            'success' => '✅',
            'error'   => '❌',
            'warning' => '⚠️',
            default   => 'ℹ️'
        };
        echo "<div class='alert alert-{$type}' role='alert' data-auto-dismiss='5000'>
                <span style='margin-right:8px'>{$icon}</span>
                <span>{$message}</span>
              </div>";
    }
    unset($_SESSION['flash']);
}

/**
 * Upload Gambar Aman (Validasi MIME Server-Side, Ukuran, Nama Acak)
 */
function upload_image(array $file, string $subdir = 'uploads', int $max_bytes = 2097152): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'name' => null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload gagal (Kode error: ' . $file['error'] . ').'];
    }
    if ($file['size'] > $max_bytes) {
        return ['ok' => false, 'error' => 'Ukuran file maksimal ' . format_file_size($max_bytes) . '.'];
    }
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    
    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'error' => 'Format file tidak diizinkan. Hanya JPG, PNG, WEBP, atau GIF.'];
    }
    if (@getimagesize($file['tmp_name']) === false) {
        return ['ok' => false, 'error' => 'File terdeteksi bukan gambar valid (korup atau palsu).'];
    }
    
    $dir = APP_DIR . '/assets/' . $subdir;
    if (!is_dir($dir)) { mkdir($dir, 0755, true); }
    
    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return ['ok' => false, 'error' => 'Gagal menyimpan file ke server. Periksa izin folder.'];
    }
    return ['ok' => true, 'name' => $name];
}

/**
 * Upload File Umum (PDF, DOC, ZIP) untuk Download Center
 */
function upload_file(array $file, string $subdir = 'downloads', array $allowed_mimes = [], int $max_bytes = 10485760): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'Tidak ada file yang dipilih.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload gagal (Kode error: ' . $file['error'] . ').'];
    }
    if ($file['size'] > $max_bytes) {
        return ['ok' => false, 'error' => 'Ukuran file maksimal ' . format_file_size($max_bytes) . '.'];
    }
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    
    if (empty($allowed_mimes)) {
        $allowed_mimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/x-rar-compressed'
        ];
    }
    
    if (!in_array($mime, $allowed_mimes, true)) {
        return ['ok' => false, 'error' => 'Format file tidak diizinkan.'];
    }
    
    $dir = APP_DIR . '/assets/' . $subdir;
    if (!is_dir($dir)) { mkdir($dir, 0755, true); }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safe_ext = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($ext));
    $name = bin2hex(random_bytes(16)) . ($safe_ext ? '.' . $safe_ext : '');
    
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return ['ok' => false, 'error' => 'Gagal menyimpan file ke server.'];
    }
    
    return ['ok' => true, 'name' => $name, 'size' => format_file_size($file['size'])];
}

/**
 * Hapus File Upload
 */
function delete_upload(?string $name, string $subdir = 'uploads'): void {
    if ($name) {
        $path = APP_DIR . '/assets/' . $subdir . '/' . basename($name);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

/**
 * Format Time Ago (e.g., "2 jam lalu")
 */
function time_ago(int $timestamp): string {
    $diff = time() - $timestamp;
    if ($diff < 60) return 'Baru saja';
    if ($diff < 3600) return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
    if ($diff < 604800) return floor($diff / 86400) . ' hari lalu';
    if ($diff < 2592000) return floor($diff / 604800) . ' minggu lalu';
    if ($diff < 31536000) return floor($diff / 2592000) . ' bulan lalu';
    return floor($diff / 31536000) . ' tahun lalu';
}

/**
 * Highlight Search Query dalam Teks
 */
function highlight_search(string $text, string $q): string {
    $q = trim($q);
    if ($q === '') return $text;
    $pattern = '/(' . preg_quote($q, '/') . ')/iu';
    $result = preg_replace($pattern, '<mark>$1</mark>', $text);
    return $result ?? $text;
}

/**
 * Generate Pagination HTML (Smart & Responsive)
 * Otomatis menangani logika "1 ... 4 5 6 ... 10"
 */
function generate_pagination(int $current_page, int $total_pages, string $base_url): string {
    if ($total_pages <= 1) return '';
    
    $html = '<nav class="pagination-pro"><div class="page-info">Halaman ' . $current_page . ' dari ' . $total_pages . '</div><div class="page-buttons">';
    
    if ($current_page > 1) {
        $html .= '<a href="' . $base_url . ($current_page - 1) . '" class="page-btn" aria-label="Previous">←</a>';
    } else {
        $html .= '<span class="page-btn disabled">←</span>';
    }
    
    $range = 2;
    $start = max(1, $current_page - $range);
    $end = min($total_pages, $current_page + $range);
    
    if ($start > 1) {
        $html .= '<a href="' . $base_url . '1" class="page-btn">1</a>';
        if ($start > 2) $html .= '<span class="page-dots">…</span>';
    }
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i === $current_page) {
            $html .= '<span class="page-btn current">' . $i . '</span>';
        } else {
            $html .= '<a href="' . $base_url . $i . '" class="page-btn">' . $i . '</a>';
        }
    }
    
    if ($end < $total_pages) {
        if ($end < $total_pages - 1) $html .= '<span class="page-dots">…</span>';
        $html .= '<a href="' . $base_url . $total_pages . '" class="page-btn">' . $total_pages . '</a>';
    }
    
    if ($current_page < $total_pages) {
        $html .= '<a href="' . $base_url . ($current_page + 1) . '" class="page-btn" aria-label="Next">→</a>';
    } else {
        $html .= '<span class="page-btn disabled">→</span>';
    }
    
    $html .= '</div></nav>';
    return $html;
}

/**
 * Clean Filename (Menghapus karakter aneh, spasi jadi underscore)
 */
function clean_filename(string $filename): string {
    $filename = preg_replace('/[^a-zA-Z0-9_\.\-]/', '_', $filename);
    return preg_replace('/_+/', '_', $filename);
}