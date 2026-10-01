<?php
require_once __DIR__ . '/includes/config.php';

// ===== FALLBACK AMAN: definisikan jika belum ada di functions.php =====
if (!function_exists('check_rate_limit')) {
    function check_rate_limit(string $identifier, int $max_attempts = 5, int $window_seconds = 3600): bool {
        $key = 'rl_' . md5($identifier);
        $now = time();
        $data = $_SESSION[$key] ?? ['count' => 0, 'start' => $now];
        if ($now - $data['start'] >= $window_seconds) $data = ['count' => 0, 'start' => $now];
        if ($data['count'] >= $max_attempts) { $_SESSION[$key] = $data; return false; }
        $data['count']++;
        $_SESSION[$key] = $data;
        return true;
    }
}
if (!function_exists('rate_limit_remaining')) {
    function rate_limit_remaining(string $identifier, int $max_attempts = 5, int $window_seconds = 3600): int {
        $key = 'rl_' . md5($identifier);
        $now = time();
        $data = $_SESSION[$key] ?? ['count' => 0, 'start' => $now];
        if ($now - $data['start'] >= $window_seconds) return $max_attempts;
        return max(0, $max_attempts - $data['count']);
    }
}

$page_title = 'Kontak Kami';
$page_description = 'Hubungi FKIP UNIMOF untuk pertanyaan seputar akademik, PMB, beasiswa, dan kerjasama institusi. Respon 1x24 jam kerja.';

// ===== DEPARTMENTS ROUTING =====
$departments = [
    'pmb'       => ['icon' => '🎓', 'label' => 'Pendaftaran Mahasiswa Baru', 'subjek' => 'Informasi PMB', 'eta' => '1x24 jam kerja', 'color' => '#3b82f6'],
    'akademik'  => ['icon' => '📚', 'label' => 'Layanan Akademik',           'subjek' => 'Akademik',       'eta' => '1x24 jam kerja', 'color' => '#8b5cf6'],
    'beasiswa'  => ['icon' => '💰', 'label' => 'Beasiswa & Keuangan',        'subjek' => 'Beasiswa',       'eta' => '2x24 jam kerja', 'color' => '#10b981'],
    'kerjasama' => ['icon' => '🤝', 'label' => 'Kerjasama Institusi',        'subjek' => 'Kerjasama',      'eta' => '3-5 hari kerja', 'color' => '#f59e0b'],
    'komplain'  => ['icon' => '🛠️', 'label' => 'Pengaduan & Komplain',       'subjek' => 'Pengaduan',      'eta' => '1x24 jam kerja', 'color' => '#ef4444'],
    'lainnya'   => ['icon' => '💬', 'label' => 'Pertanyaan Lainnya',         'subjek' => 'Lainnya',        'eta' => '1x24 jam kerja', 'color' => '#64748b'],
];

// ===== MESSAGE TEMPLATES per department =====
$msg_templates = [
    'pmb'       => "Halo, saya ingin bertanya mengenai pendaftaran mahasiswa baru FKIP UNIMOF:\n1. Kapan pendaftaran gelombang berikutnya dibuka?\n2. Apa saja berkas yang diperlukan?\n\nTerima kasih.",
    'akademik'  => "Halo, saya mahasiswa FKIP UNIMOF dan ingin bertanya mengenai layanan akademik:\n\n[Tuliskan pertanyaan Anda]\n\nTerima kasih.",
    'beasiswa'  => "Halo, saya ingin mengetahui informasi program beasiswa yang tersedia di FKIP UNIMOF:\n1. Jenis beasiswa apa saja yang dibuka tahun ini?\n2. Bagaimana syarat dan cara mendaftar?\n\nTerima kasih.",
    'kerjasama' => "Yth. Pimpinan FKIP UNIMOF,\n\nKami dari [Nama Institusi] ingin menjajaki kemungkinan kerjasama dalam bidang [pendidikan/penelitian/pengabdian]. Berikut ringkasan proposal kami:\n\n[Uraian singkat]\n\nHormat kami.",
    'komplain'  => "Halo, saya ingin menyampaikan pengaduan mengenai:\n\n[Uraikan masalah beserta waktu kejadian]\n\nMohon tindak lanjutnya. Terima kasih.",
    'lainnya'   => "Halo tim FKIP UNIMOF,\n\n[Tuliskan pertanyaan Anda di sini]\n\nTerima kasih.",
];

$success_sent = false;
$rate_remaining = rate_limit_remaining($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 5, 3600);

// ===== PROSES FORM =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('error', '❌ Token keamanan tidak valid. Silakan coba lagi.');
    } elseif (!empty($_POST['website'])) {
        // Honeypot: bot terdeteksi, diam-diam gagal
        flash_message('error', '❌ Pesan tidak dapat diproses.');
    } elseif ($rate_remaining <= 0) {
        flash_message('error', '⏳ Terlalu banyak pesan terkirim. Coba lagi dalam 1 jam.');
    } else {
        $nama    = trim($_POST['nama'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $subjek  = trim($_POST['subjek'] ?? '');
        $pesan   = trim($_POST['pesan'] ?? '');
        $telepon = trim($_POST['telepon'] ?? '');
        $dept    = $_POST['department'] ?? 'lainnya';

        // Validasi
        if ($nama === '' || $pesan === '' || !validate_email($email)) {
            flash_message('error', '❌ Mohon lengkapi nama, email valid, dan pesan.');
        } elseif (strlen($pesan) < 10) {
            flash_message('error', '❌ Pesan terlalu singkat. Minimal 10 karakter.');
        } elseif (strlen($pesan) > 2000) {
            flash_message('error', '❌ Pesan terlalu panjang. Maksimal 2000 karakter.');
        } else {
            // Auto-subject dari department jika subjek kosong
            if ($subjek === '' && isset($departments[$dept])) {
                $subjek = $departments[$dept]['subjek'];
            }
            try {
                $stmt = $pdo->prepare("INSERT INTO kontak (nama, email, telepon, subjek, pesan) VALUES (?,?,?,?,?)");
                $stmt->execute([$nama, $email, $telepon, $subjek, $pesan]);
                $success_sent = true;
                flash_message('success', '✅ Pesan terkirim! Tim kami akan segera menghubungi Anda.');
            } catch (Exception $e) {
                error_log($e->getMessage());
                flash_message('error', '❌ Gagal mengirim pesan. Silakan coba lagi.');
            }
        }
    }
    header('Location: ' . base_url('kontak.php?sent=' . ($success_sent ? '1' : '0')));
    exit;
}

$show_success_modal = ($_GET['sent'] ?? '') === '1';
$csrf = generate_csrf_token();

// ===== JAM OPERASIONAL (WITA) =====
date_default_timezone_set('Asia/Makassar');
$hour = (int)date('H');
$day_of_week = (int)date('w');
$is_open = ($day_of_week >= 1 && $day_of_week <= 5) && ($hour >= 8 && $hour < 16);
$server_time_h = $hour;
$server_time_i = (int)date('i');
$server_time_s = (int)date('s');
$server_day = $day_of_week;

// ===== FAQ (dari DB jika ada, fallback statis) =====
$faqs = [];
try {
    $faqs = $pdo->query("SELECT kategori, pertanyaan, jawaban FROM faq WHERE status='Aktif' ORDER BY urutan ASC, id ASC LIMIT 12")->fetchAll();
} catch (Exception $e) { $faqs = []; }

if (empty($faqs)) {
    $faqs = [
        ['kategori' => 'PMB', 'pertanyaan' => 'Bagaimana cara mendaftar sebagai mahasiswa baru?', 'jawaban' => 'Pendaftaran mahasiswa baru dilakukan secara online melalui website resmi PMB UNIMOF. Siapkan ijazah, transkrip nilai, pas foto, dan kartu identitas. Informasi lengkap tersedia di laman PMB atau hubungi kami via WhatsApp.'],
        ['kategori' => 'Beasiswa', 'pertanyaan' => 'Apakah tersedia program beasiswa?', 'jawaban' => 'Ya, FKIP UNIMOF menyediakan beasiswa prestasi akademik, beasiswa kurang mampu, beasiswa Muhammadiyah, dan KIP-Kuliah. Informasi detail dapat ditanyakan langsung ke bagian kemahasiswaan.'],
        ['kategori' => 'Umum', 'pertanyaan' => 'Berapa lama waktu respon untuk pesan yang dikirim?', 'jawaban' => 'Kami berkomitmen merespon semua pesan dalam 1x24 jam kerja (Senin–Jumat). Untuk pertanyaan mendesak, hubungi kami via WhatsApp untuk respon lebih cepat.'],
        ['kategori' => 'Umum', 'pertanyaan' => 'Apakah bisa kunjungan ke kampus untuk melihat fasilitas?', 'jawaban' => 'Tentu! Kami menyambut kunjungan calon mahasiswa dan orang tua. Hubungi kami minimal 1 hari sebelumnya untuk mengatur jadwal tur kampus pada jam operasional.'],
        ['kategori' => 'Kerjasama', 'pertanyaan' => 'Bagaimana cara mengajukan kerjasama institusi?', 'jawaban' => 'Kirim proposal resmi ke email kami dengan subjek "Kerjasama". Tim kami akan meninjau dan menghubungi Anda dalam 3-5 hari kerja untuk diskusi lebih lanjut.'],
        ['kategori' => 'Akademik', 'pertanyaan' => 'Bagaimana cara mengajukan legalisir ijazah atau transkrip?', 'jawaban' => 'Datang langsung ke Bagian Akademik (Gedung FKIP Lantai 1) pada jam operasional dengan membawa dokumen asli dan fotokopi. Proses legalisir 3-5 hari kerja dengan biaya Rp 10.000/lembar.'],
    ];
}
$faq_categories = array_unique(array_column($faqs, 'kategori'));

require_once __DIR__ . '/includes/header.php';
?>

<!-- ===== SCOPED STYLES ===== -->
<style>
/* Hero */
.contact-hero { position: relative; overflow: hidden; background: linear-gradient(135deg, #0a6847 0%, #084d35 50%, #16213e 100%); color: white; padding: 7rem 0 5rem; }
.contact-hero::before { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at 20% 30%, rgba(245,166,35,0.2) 0%, transparent 50%), radial-gradient(circle at 80% 70%, rgba(59,130,246,0.15) 0%, transparent 50%); animation: heroShift 15s ease-in-out infinite; }
@keyframes heroShift { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(-20px, 20px); } }
.contact-hero::after { content: ''; position: absolute; inset: 0; background-image: linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px); background-size: 40px 40px; }
.contact-hero .container { position: relative; z-index: 2; }
.contact-hero .breadcrumb a, .contact-hero .breadcrumb span { color: rgba(255,255,255,0.75); }
.contact-hero .breadcrumb a:hover { color: white; }
.contact-hero .page-title { font-family: var(--font-display); font-size: clamp(2rem, 5vw, 3.5rem); font-weight: 900; margin-bottom: 1rem; letter-spacing: -0.02em; }
.contact-hero .page-subtitle { font-size: 1.15rem; opacity: 0.9; max-width: 600px; line-height: 1.7; }

/* Hero live status + stats */
.hero-live-row { display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 2rem; align-items: center; }
.office-status { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.55rem 1.1rem; border-radius: 999px; font-size: 0.82rem; font-weight: 700; backdrop-filter: blur(10px); }
.office-status.open { background: rgba(16,185,129,0.2); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.4); }
.office-status.closed { background: rgba(239,68,68,0.2); color: #fca5a5; border: 1px solid rgba(239,68,68,0.4); }
.status-dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; position: relative; }
.status-dot::after { content: ''; position: absolute; inset: 0; border-radius: 50%; background: currentColor; animation: statusPulse 2s infinite; }
@keyframes statusPulse { to { transform: scale(2.5); opacity: 0; } }
.hero-countdown { font-family: monospace; font-weight: 800; }
.hero-mini-stats { display: flex; gap: 1.5rem; flex-wrap: wrap; }
.hero-mini-stat { display: flex; flex-direction: column; }
.hero-mini-stat strong { font-family: var(--font-display); font-size: 1.35rem; line-height: 1; }
.hero-mini-stat span { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.8; margin-top: 0.25rem; }

/* Contact Methods Grid */
.contact-methods { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin: -3rem 0 3rem; position: relative; z-index: 3; }
.method-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 1.75rem; text-align: center; transition: all 0.4s var(--ease); position: relative; overflow: hidden; text-decoration: none; color: inherit; display: block; cursor: pointer; }
.method-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: var(--method-color, var(--primary)); transform: scaleX(0); transition: transform 0.4s; transform-origin: left; }
.method-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--method-color, var(--primary)); }
.method-card:hover::before { transform: scaleX(1); }
.method-icon { width: 64px; height: 64px; margin: 0 auto 1rem; background: linear-gradient(135deg, var(--method-color, var(--primary)), var(--method-color-light, var(--primary-light))); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; color: white; box-shadow: 0 8px 20px rgba(0,0,0,0.15); transition: transform 0.3s; }
.method-card:hover .method-icon { transform: scale(1.1) rotate(-5deg); }
.method-card h3 { font-size: 1.1rem; margin-bottom: 0.5rem; color: var(--text-primary); }
.method-card p { font-size: 0.9rem; color: var(--text-secondary); line-height: 1.5; margin-bottom: 0.75rem; }
.method-value { font-size: 0.85rem; color: var(--primary); font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; }
.copy-btn { position: absolute; top: 0.75rem; right: 0.75rem; width: 30px; height: 30px; border: 1px solid var(--border); background: var(--bg-secondary); border-radius: 8px; cursor: pointer; font-size: 0.8rem; opacity: 0; transition: all 0.2s; display: flex; align-items: center; justify-content: center; }
.method-card:hover .copy-btn { opacity: 1; }
.copy-btn:hover { background: var(--method-color, var(--primary)); color: white; border-color: var(--method-color, var(--primary)); }

/* Contact Layout */
.contact-layout { display: grid; grid-template-columns: 1fr 1.2fr; gap: 3rem; margin-bottom: 4rem; align-items: start; }

/* Info Sidebar */
.contact-info-sidebar { position: sticky; top: 100px; display: flex; flex-direction: column; gap: 1.5rem; }
.info-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 1.75rem; }
.info-card h3 { font-family: var(--font-display); font-size: 1.25rem; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 2px solid var(--bg-tertiary); }
.info-list { list-style: none; padding: 0; margin: 0; }
.info-list li { display: flex; gap: 0.85rem; padding: 0.85rem 0; border-bottom: 1px dashed var(--border); font-size: 0.9rem; }
.info-list li:last-child { border-bottom: none; }
.info-icon { width: 36px; height: 36px; background: var(--bg-secondary); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
.info-text { flex: 1; }
.info-text strong { display: block; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.2rem; text-transform: uppercase; letter-spacing: 0.05em; }
.info-text span { color: var(--text-primary); font-weight: 500; }

/* Office hours visual */
.hours-visual { margin-top: 0.75rem; }
.hours-bar-row { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem; font-size: 0.75rem; }
.hours-day { width: 42px; color: var(--text-muted); font-weight: 600; }
.hours-bar { flex: 1; height: 8px; background: var(--bg-tertiary); border-radius: 999px; overflow: hidden; position: relative; }
.hours-fill { height: 100%; background: linear-gradient(90deg, #10b981, #34d399); border-radius: 999px; }
.hours-bar-row.today .hours-day { color: var(--primary); font-weight: 800; }
.hours-bar-row.today .hours-bar { box-shadow: 0 0 0 2px rgba(10,104,71,0.2); }
.hours-now-marker { position: absolute; top: -3px; width: 2px; height: 14px; background: #ef4444; border-radius: 2px; }

/* Quick Contact Buttons */
.quick-contact { display: flex; flex-direction: column; gap: 0.75rem; }
.qc-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem; border-radius: var(--radius-md); text-decoration: none; color: white; font-weight: 600; font-size: 0.9rem; transition: all 0.3s; }
.qc-btn.wa { background: linear-gradient(135deg, #25D366, #128C7E); }
.qc-btn.email { background: linear-gradient(135deg, #EA4335, #B92B27); }
.qc-btn:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.2); }
.qc-icon { font-size: 1.25rem; }

/* Trust Badges */
.trust-badges { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; }
.trust-badge { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.35rem 0.7rem; background: var(--bg-secondary); border-radius: 999px; font-size: 0.72rem; color: var(--text-muted); font-weight: 600; }

/* Form Card */
.form-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2.5rem; box-shadow: var(--shadow-lg); position: relative; overflow: hidden; }
.form-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--primary), var(--secondary), #3b82f6); }
.form-header { margin-bottom: 1.5rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--border); }
.form-header h2 { font-family: var(--font-display); font-size: 1.75rem; margin-bottom: 0.5rem; }
.form-header p { color: var(--text-secondary); font-size: 0.95rem; }

/* Department Selector */
.dept-section { margin-bottom: 1.5rem; }
.dept-label { font-size: 0.85rem; font-weight: 700; color: var(--text-secondary); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.4rem; }
.dept-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.65rem; }
.dept-card { padding: 0.85rem 0.65rem; background: var(--bg-secondary); border: 2px solid var(--border); border-radius: var(--radius-md); text-align: center; cursor: pointer; transition: all 0.2s; font-size: 0.78rem; font-weight: 600; color: var(--text-secondary); display: flex; flex-direction: column; align-items: center; gap: 0.35rem; }
.dept-card:hover { border-color: var(--dept-color, var(--primary)); transform: translateY(-2px); }
.dept-card.selected { border-color: var(--dept-color, var(--primary)); background: var(--bg-primary); color: var(--text-primary); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.dept-card .dept-icon { font-size: 1.5rem; line-height: 1; }
.dept-card input { display: none; }

/* ETA Bar */
.eta-bar { display: none; align-items: center; gap: 0.65rem; margin-top: 0.85rem; padding: 0.7rem 1rem; background: var(--bg-secondary); border: 1px solid var(--border); border-left: 4px solid var(--eta-color, var(--primary)); border-radius: var(--radius-md); font-size: 0.82rem; color: var(--text-secondary); animation: etaIn 0.3s; }
.eta-bar.show { display: flex; }
@keyframes etaIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
.eta-bar strong { color: var(--text-primary); }

/* Floating Label Form */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
.form-group { position: relative; margin-bottom: 1.25rem; }
.form-group.full { grid-column: 1 / -1; }
.input-wrap { position: relative; }
.form-input-float, .form-textarea-float { width: 100%; padding: 1rem 1rem 1rem 3rem; border: 2px solid var(--border); border-radius: var(--radius-md); font-family: inherit; font-size: 0.95rem; background: var(--bg-secondary); transition: all 0.3s; color: var(--text-primary); }
.form-textarea-float { min-height: 150px; resize: vertical; padding-top: 1.5rem; line-height: 1.6; }
.form-input-float:focus, .form-textarea-float:focus { outline: none; border-color: var(--primary); background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.form-input-float::placeholder, .form-textarea-float::placeholder { color: transparent; }
.input-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 1.1rem; pointer-events: none; transition: color 0.3s; }
.form-input-float:focus ~ .input-icon, .form-textarea-float:focus ~ .input-icon { color: var(--primary); }
.floating-label { position: absolute; left: 3rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.95rem; pointer-events: none; transition: all 0.25s; background: var(--bg-secondary); padding: 0 0.35rem; }
.form-textarea-float ~ .floating-label { top: 1.5rem; }
.form-input-float:focus ~ .floating-label, .form-input-float:not(:placeholder-shown) ~ .floating-label, .form-textarea-float:focus ~ .floating-label, .form-textarea-float:not(:placeholder-shown) ~ .floating-label { top: 0; font-size: 0.75rem; color: var(--primary); font-weight: 600; background: var(--bg-primary); }
.form-input-float.error, .form-textarea-float.error { border-color: #ef4444; }
.form-input-float.error ~ .input-icon { color: #ef4444; }
.error-msg { color: #ef4444; font-size: 0.78rem; margin-top: 0.35rem; display: none; align-items: center; gap: 0.3rem; }
.error-msg.show { display: flex; }

/* Message templates chips */
.tpl-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.65rem; }
.tpl-chip { padding: 0.35rem 0.75rem; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 999px; font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.2s; color: var(--text-secondary); }
.tpl-chip:hover { background: var(--primary); color: white; border-color: var(--primary); transform: translateY(-1px); }

/* Character Counter */
.char-counter { position: absolute; bottom: 0.75rem; right: 1rem; font-size: 0.75rem; color: var(--text-muted); font-weight: 600; background: var(--bg-primary); padding: 0.2rem 0.5rem; border-radius: 999px; }
.char-counter.warn { color: #f59e0b; }
.char-counter.danger { color: #ef4444; }

/* Rate limit indicator */
.rate-indicator { display: flex; align-items: center; gap: 0.5rem; margin-top: 1rem; font-size: 0.75rem; color: var(--text-muted); }
.rate-dots { display: flex; gap: 0.25rem; }
.rate-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--bg-tertiary); }
.rate-dot.used { background: #10b981; }

/* Submit Button */
.btn-submit { width: 100%; padding: 1.1rem; background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; border: none; border-radius: var(--radius-md); font-family: inherit; font-size: 1rem; font-weight: 700; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 0.5rem; position: relative; overflow: hidden; }
.btn-submit:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(10,104,71,0.35); }
.btn-submit:disabled { opacity: 0.7; cursor: not-allowed; }
.btn-submit .spinner { width: 20px; height: 20px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.7s linear infinite; display: none; }
.btn-submit.loading .spinner { display: inline-block; }
.btn-submit.loading .btn-text { display: none; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Map Section */
.map-section { margin-bottom: 4rem; }
.map-grid { display: grid; grid-template-columns: 1fr 1.4fr; gap: 2rem; align-items: stretch; }
.map-info { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 2rem; display: flex; flex-direction: column; justify-content: center; }
.map-info h3 { font-family: var(--font-display); font-size: 1.5rem; margin-bottom: 0.75rem; }
.map-info p { color: var(--text-secondary); line-height: 1.7; margin-bottom: 1.25rem; font-size: 0.95rem; }
.map-directions { display: flex; flex-direction: column; gap: 0.65rem; }
.map-dir-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.7rem 0.9rem; background: var(--bg-secondary); border-radius: var(--radius-md); font-size: 0.85rem; color: var(--text-secondary); }
.map-frame { border-radius: var(--radius-lg); overflow: hidden; border: 1px solid var(--border); min-height: 380px; box-shadow: var(--shadow-md); }
.map-frame iframe { width: 100%; height: 100%; min-height: 380px; border: 0; display: block; }

/* FAQ Section */
.faq-section { margin-top: 4rem; }
.faq-header { text-align: center; margin-bottom: 2rem; }
.faq-header h2 { font-family: var(--font-display); font-size: 2rem; margin-bottom: 0.5rem; }
.faq-header p { color: var(--text-secondary); }
.faq-toolbar { max-width: 800px; margin: 0 auto 1.5rem; display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; }
.faq-search { flex: 1; min-width: 220px; position: relative; }
.faq-search input { width: 100%; padding: 0.75rem 1rem 0.75rem 2.6rem; border: 2px solid var(--border); border-radius: 999px; font-family: inherit; font-size: 0.9rem; background: var(--bg-primary); color: var(--text-primary); }
.faq-search input:focus { outline: none; border-color: var(--primary); }
.faq-search .fs-icon { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); pointer-events: none; }
.faq-cat-pills { display: flex; gap: 0.4rem; flex-wrap: wrap; }
.faq-pill { padding: 0.45rem 0.9rem; border-radius: 999px; border: 1px solid var(--border); background: var(--bg-primary); font-size: 0.78rem; font-weight: 600; cursor: pointer; color: var(--text-secondary); transition: all 0.2s; }
.faq-pill.active { background: var(--primary); color: white; border-color: var(--primary); }
.faq-list { max-width: 800px; margin: 0 auto; }
.faq-item { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-md); margin-bottom: 0.75rem; overflow: hidden; transition: all 0.3s; }
.faq-item:hover { border-color: var(--primary-light); }
.faq-item.open { border-color: var(--primary); box-shadow: var(--shadow-md); }
.faq-item.hidden { display: none; }
.faq-question { width: 100%; padding: 1.25rem 1.5rem; background: none; border: none; text-align: left; font-family: inherit; font-size: 1rem; font-weight: 600; color: var(--text-primary); cursor: pointer; display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
.faq-q-text { flex: 1; }
.faq-cat { font-size: 0.68rem; padding: 0.2rem 0.6rem; background: var(--bg-secondary); border-radius: 999px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; flex-shrink: 0; }
.faq-icon { width: 28px; height: 28px; border-radius: 50%; background: var(--bg-secondary); display: flex; align-items: center; justify-content: center; transition: all 0.3s; flex-shrink: 0; font-size: 1.1rem; color: var(--text-secondary); }
.faq-item.open .faq-icon { background: var(--primary); color: white; transform: rotate(45deg); }
.faq-answer { max-height: 0; overflow: hidden; transition: max-height 0.4s ease; }
.faq-answer-inner { padding: 0 1.5rem 1.25rem; color: var(--text-secondary); line-height: 1.7; font-size: 0.95rem; }
.faq-empty { text-align: center; padding: 2.5rem; color: var(--text-muted); display: none; }
.faq-empty.show { display: block; }

/* Success Modal */
.success-modal { position: fixed; inset: 0; background: rgba(15,23,42,0.8); backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center; z-index: 10000; padding: 2rem; }
.success-modal.show { display: flex; }
.success-box { background: var(--bg-primary); border-radius: var(--radius-xl); padding: 3rem 2rem; max-width: 480px; width: 100%; text-align: center; position: relative; animation: successPop 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
@keyframes successPop { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.success-icon { width: 80px; height: 80px; margin: 0 auto 1.5rem; background: linear-gradient(135deg, #10b981, #059669); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: white; animation: successBounce 0.6s; }
@keyframes successBounce { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.2); } }
.success-box h3 { font-family: var(--font-display); font-size: 1.5rem; margin-bottom: 0.75rem; color: var(--text-primary); }
.success-box p { color: var(--text-secondary); margin-bottom: 1.25rem; line-height: 1.6; }
.success-ticket { background: var(--bg-secondary); border: 1px dashed var(--border); border-radius: var(--radius-md); padding: 0.75rem; font-family: monospace; font-size: 0.85rem; color: var(--primary); font-weight: 700; margin-bottom: 1.5rem; }
.success-close { padding: 0.75rem 2rem; background: var(--primary); color: white; border: none; border-radius: var(--radius-md); font-family: inherit; font-weight: 600; cursor: pointer; transition: all 0.3s; }
.success-close:hover { background: var(--primary-dark); transform: translateY(-2px); }
.confetti-piece { position: fixed; width: 10px; height: 10px; top: -20px; z-index: 10001; pointer-events: none; }

/* Toast */
.pub-toast { position: fixed; bottom: 2rem; right: 2rem; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 12px; padding: 0.9rem 1.25rem; box-shadow: var(--shadow-lg); display: flex; align-items: center; gap: 0.75rem; z-index: 10002; transform: translateY(150%); transition: transform 0.4s cubic-bezier(0.4,0,0.2,1); max-width: 320px; }
.pub-toast.show { transform: translateY(0); }
.pub-toast-icon { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; background: #dcfce7; color: #166534; }

@media (max-width: 968px) {
    .contact-layout { grid-template-columns: 1fr; }
    .contact-info-sidebar { position: static; }
    .form-grid { grid-template-columns: 1fr; }
    .contact-methods { margin-top: -2rem; }
    .map-grid { grid-template-columns: 1fr; }
    .dept-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
    .form-card { padding: 1.5rem; }
    .hero-mini-stats { gap: 1rem; }
}
</style>

<!-- ===== HERO SECTION ===== -->
<section class="contact-hero">
    <div class="container">
        <nav class="breadcrumb" data-aos="fade-down">
            <a href="<?= base_url() ?>">Beranda</a><span>›</span><span>Kontak</span>
        </nav>
        <div class="page-hero-content" data-aos="fade-up">
            <h1 class="page-title">Hubungi Kami</h1>
            <p class="page-subtitle">Kami siap menjawab pertanyaan Anda seputar akademik, PMB, beasiswa, dan kerjasama institusi.</p>
            <div class="hero-live-row">
                <div class="office-status <?= $is_open ? 'open' : 'closed' ?>">
                    <span class="status-dot"></span>
                    <span><?= $is_open ? 'Kantor Buka Sekarang' : 'Kantor Tutup' ?></span>
                    <span class="hero-countdown" id="officeCountdown"></span>
                </div>
                <div class="hero-mini-stats">
                    <div class="hero-mini-stat"><strong>1x24</strong><span>Jam Respon</span></div>
                    <div class="hero-mini-stat"><strong><?= count($departments) ?></strong><span>Layanan</span></div>
                    <div class="hero-mini-stat"><strong>4</strong><span>Kanal Kontak</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== CONTACT METHODS ===== -->
<div class="container">
    <div class="contact-methods" data-aos="fade-up">
        <a href="#peta" class="method-card" style="--method-color:#3b82f6; --method-color-light:#60a5fa">
            <div class="method-icon">📍</div>
            <h3>Lokasi Kampus</h3>
            <p>Kunjungi kami langsung di kampus</p>
            <span class="method-value">Lihat di Peta →</span>
        </a>
        <div class="method-card" style="--method-color:#10b981; --method-color-light:#34d399" onclick="copyText('<?= sanitize(get_setting('telepon', '(0382) 21234')) ?>', 'Nomor telepon disalin!')">
            <button type="button" class="copy-btn" title="Salin nomor">📋</button>
            <div class="method-icon">📞</div>
            <h3>Telepon</h3>
            <p>Senin–Jumat, 08:00–16:00 WITA</p>
            <span class="method-value"><?= sanitize(get_setting('telepon', '(0382) 21234')) ?></span>
        </div>
        <div class="method-card" style="--method-color:#ef4444; --method-color-light:#f87171" onclick="copyText('<?= sanitize(get_setting('email', 'fkip@unimof.ac.id')) ?>', 'Email disalin!')">
            <button type="button" class="copy-btn" title="Salin email">📋</button>
            <div class="method-icon">✉️</div>
            <h3>Email</h3>
            <p>Respon dalam 1x24 jam kerja</p>
            <span class="method-value"><?= sanitize(get_setting('email', 'fkip@unimof.ac.id')) ?></span>
        </div>
        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', get_setting('whatsapp_number', '6281234567890')) ?>" target="_blank" rel="noopener" class="method-card" style="--method-color:#25D366; --method-color-light:#4ade80">
            <div class="method-icon">💬</div>
            <h3>WhatsApp</h3>
            <p>Chat langsung dengan admin</p>
            <span class="method-value">Buka Chat →</span>
        </a>
    </div>
</div>

<!-- ===== MAIN CONTACT SECTION ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">
        <div class="contact-layout">

            <!-- Left: Info Sidebar -->
            <aside class="contact-info-sidebar" data-aos="fade-right">
                <div class="info-card">
                    <h3>📍 Informasi Kontak</h3>
                    <ul class="info-list">
                        <li>
                            <div class="info-icon">🏛️</div>
                            <div class="info-text">
                                <strong>Alamat</strong>
                                <span><?= sanitize(get_setting('alamat', 'Jl. Bhayangkara No.1, Maumere, NTT')) ?></span>
                            </div>
                        </li>
                        <li>
                            <div class="info-icon">📞</div>
                            <div class="info-text">
                                <strong>Telepon</strong>
                                <span><?= sanitize(get_setting('telepon', '(0382) 21234')) ?></span>
                            </div>
                        </li>
                        <li>
                            <div class="info-icon">✉️</div>
                            <div class="info-text">
                                <strong>Email</strong>
                                <span><?= sanitize(get_setting('email', 'fkip@unimof.ac.id')) ?></span>
                            </div>
                        </li>
                        <li>
                            <div class="info-icon">🕐</div>
                            <div class="info-text">
                                <strong>Jam Operasional</strong>
                                <span>Senin–Jumat, 08:00–16:00 WITA</span>
                                <div class="office-status <?= $is_open ? 'open' : 'closed' ?>" style="margin-top:0.5rem;">
                                    <span class="status-dot"></span>
                                    <?= $is_open ? 'Buka Sekarang' : 'Tutup' ?>
                                </div>
                            </div>
                        </li>
                    </ul>

                    <!-- Visual weekly hours -->
                    <div class="hours-visual" id="hoursVisual"></div>
                </div>

                <div class="info-card">
                    <h3>⚡ Kontak Cepat</h3>
                    <div class="quick-contact">
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', get_setting('whatsapp_number', '6281234567890')) ?>?text=Halo%20FKIP%20UNIMOF" target="_blank" rel="noopener" class="qc-btn wa">
                            <span class="qc-icon">💬</span>
                            <span>Chat via WhatsApp</span>
                        </a>
                        <a href="mailto:<?= sanitize(get_setting('email', 'fkip@unimof.ac.id')) ?>" class="qc-btn email">
                            <span class="qc-icon">📧</span>
                            <span>Kirim Email Langsung</span>
                        </a>
                    </div>
                </div>

                <div class="info-card">
                    <h3>🔒 Keamanan Terjamin</h3>
                    <p style="font-size:0.85rem; color:var(--text-secondary); line-height:1.6; margin-bottom:1rem;">
                        Pesan Anda dilindungi dan tidak akan dibagikan ke pihak ketiga.
                    </p>
                    <div class="trust-badges">
                        <span class="trust-badge">🛡️ CSRF Protected</span>
                        <span class="trust-badge">⚡ Rate Limited</span>
                        <span class="trust-badge">✓ Validated</span>
                        <span class="trust-badge">🤖 Anti-Spam</span>
                    </div>
                </div>
            </aside>

            <!-- Right: Contact Form -->
            <div class="form-card" data-aos="fade-left">
                <div class="form-header">
                    <h2>✉️ Kirim Pesan</h2>
                    <p>Pilih layanan tujuan agar pesan Anda ditangani tim yang tepat.</p>
                </div>

                <?php display_flash(); ?>

                <form method="POST" action="" id="contactForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                    <input type="text" name="website" class="honeypot" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;opacity:0;">

                    <!-- Department Selector -->
                    <div class="dept-section">
                        <div class="dept-label">🎯 Tujuan Pesan</div>
                        <div class="dept-grid" id="deptGrid">
                            <?php foreach ($departments as $key => $d): ?>
                            <label class="dept-card <?= $key === 'lainnya' ? 'selected' : '' ?>" style="--dept-color: <?= $d['color'] ?>" data-dept="<?= $key ?>">
                                <input type="radio" name="department" value="<?= $key ?>" <?= $key === 'lainnya' ? 'checked' : '' ?>>
                                <span class="dept-icon"><?= $d['icon'] ?></span>
                                <span><?= $d['label'] ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="eta-bar" id="etaBar">
                            <span>⏱️</span>
                            <span>Estimasi respon: <strong id="etaText">1x24 jam kerja</strong> • Pesan akan diteruskan ke tim <strong id="etaDept">terkait</strong></span>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <div class="input-wrap">
                                <input type="text" id="nama" name="nama" class="form-input-float" placeholder=" " required maxlength="100" value="<?= htmlspecialchars($_POST['nama'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <span class="input-icon">👤</span>
                                <label class="floating-label" for="nama">Nama Lengkap *</label>
                            </div>
                            <div class="error-msg" id="namaError">⚠️ Nama wajib diisi (min. 2 karakter)</div>
                        </div>
                        <div class="form-group">
                            <div class="input-wrap">
                                <input type="email" id="email" name="email" class="form-input-float" placeholder=" " required maxlength="100" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <span class="input-icon">📧</span>
                                <label class="floating-label" for="email">Email *</label>
                            </div>
                            <div class="error-msg" id="emailError">⚠️ Format email tidak valid</div>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <div class="input-wrap">
                                <input type="tel" id="telepon" name="telepon" class="form-input-float" placeholder=" " maxlength="20" value="<?= htmlspecialchars($_POST['telepon'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <span class="input-icon">📱</span>
                                <label class="floating-label" for="telepon">Telepon (opsional)</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="input-wrap">
                                <select id="subjek" name="subjek" class="form-input-float" style="padding-left:3rem; cursor:pointer;">
                                    <option value="">Pilih subjek...</option>
                                    <?php foreach ($departments as $d): ?>
                                    <option value="<?= sanitize($d['subjek']) ?>" <?= ($_POST['subjek'] ?? '') === $d['subjek'] ? 'selected' : '' ?>><?= $d['icon'] ?> <?= sanitize($d['subjek']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="input-icon">📋</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group full">
                        <div class="input-wrap">
                            <textarea id="pesan" name="pesan" class="form-textarea-float" placeholder=" " required maxlength="2000"><?= htmlspecialchars($_POST['pesan'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            <span class="input-icon" style="top: 1.5rem;">💬</span>
                            <label class="floating-label" for="pesan">Pesan Anda *</label>
                            <span class="char-counter" id="charCounter">0/2000</span>
                        </div>
                        <div class="tpl-chips" id="tplChips">
                            <span class="tpl-chip" data-dept-tpl="current">✍️ Gunakan template layanan terpilih</span>
                        </div>
                        <div class="error-msg" id="pesanError">⚠️ Pesan minimal 10 karakter</div>
                    </div>

                    <button type="submit" class="btn-submit" id="submitBtn">
                        <span class="btn-text">Kirim Pesan 🚀</span>
                        <span class="spinner"></span>
                    </button>

                    <div class="rate-indicator">
                        <span>⚡ Kuota kirim hari ini:</span>
                        <div class="rate-dots" id="rateDots"></div>
                        <span id="rateText"><?= $rate_remaining ?>/5</span>
                    </div>

                    <p style="text-align:center; font-size:0.78rem; color:var(--text-muted); margin-top:0.75rem;">
                        Dengan mengirim, Anda menyetujui kebijakan privasi kami. • <kbd style="font-family:monospace;">Ctrl+Enter</kbd> untuk kirim cepat
                    </p>
                </form>
            </div>
        </div>

        <!-- ===== MAP SECTION ===== -->
        <div class="map-section" id="peta" data-aos="fade-up">
            <div class="map-grid">
                <div class="map-info">
                    <h3>🗺️ Kunjungi Kampus Kami</h3>
                    <p><?= sanitize(get_setting('alamat', 'Jl. Bhayangkara No.1, Maumere, Sikka, NTT')) ?>. Kampus FKIP UNIMOF mudah dijangkau dari pusat kota Maumere.</p>
                    <div class="map-directions">
                        <div class="map-dir-item"><span>🚗</span> 10 menit dari Bandara Frans Seda</div>
                        <div class="map-dir-item"><span>🚌</span> 5 menit dari Terminal Kota Maumere</div>
                        <div class="map-dir-item"><span>⛴️</span> 15 menit dari Pelabuhan Laurensius Say</div>
                        <div class="map-dir-item"><span>🅿️</span> Area parkir luas & gratis untuk pengunjung</div>
                    </div>
                </div>
                <div class="map-frame">
                    <iframe src="https://www.google.com/maps?q=Maumere,+Sikka,+Nusa+Tenggara+Timur&output=embed" loading="lazy" title="Peta Lokasi FKIP UNIMOF" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
        </div>

        <!-- ===== FAQ SECTION ===== -->
        <div class="faq-section" data-aos="fade-up">
            <div class="faq-header">
                <h2>❓ Pertanyaan Umum</h2>
                <p>Temukan jawaban cepat sebelum mengirim pesan</p>
            </div>

            <div class="faq-toolbar">
                <div class="faq-search">
                    <span class="fs-icon">🔍</span>
                    <input type="text" id="faqSearch" placeholder="Cari pertanyaan...">
                </div>
                <div class="faq-cat-pills" id="faqPills">
                    <button class="faq-pill active" data-cat="all">Semua</button>
                    <?php foreach ($faq_categories as $cat): ?>
                    <button class="faq-pill" data-cat="<?= sanitize($cat) ?>"><?= sanitize($cat) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="faq-list" id="faqList">
                <?php foreach ($faqs as $i => $f): ?>
                <div class="faq-item" data-cat="<?= sanitize($f['kategori']) ?>" data-search="<?= strtolower(sanitize($f['pertanyaan'] . ' ' . $f['jawaban'])) ?>">
                    <button class="faq-question" type="button">
                        <span class="faq-q-text"><?= sanitize($f['pertanyaan']) ?></span>
                        <span class="faq-cat"><?= sanitize($f['kategori']) ?></span>
                        <span class="faq-icon">+</span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-inner"><?= sanitize($f['jawaban']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="faq-empty" id="faqEmpty">😕 Tidak ada pertanyaan yang cocok. Silakan kirim pesan langsung kepada kami.</div>
        </div>
    </div>
</section>

<!-- ===== SUCCESS MODAL ===== -->
<div class="success-modal" id="successModal">
    <div class="success-box">
        <div class="success-icon">✓</div>
        <h3>Pesan Terkirim!</h3>
        <p>Terima kasih telah menghubungi FKIP UNIMOF. Simpan nomor tiket berikut untuk referensi:</p>
        <div class="success-ticket" id="ticketId">FKIP-<?= date('Ymd') ?>-0000</div>
        <button class="success-close" onclick="closeSuccessModal()">Tutup</button>
    </div>
</div>

<!-- Toast -->
<div class="pub-toast" id="pubToast">
    <div class="pub-toast-icon" id="pubToastIcon">✓</div>
    <div id="pubToastMsg">Berhasil</div>
</div>

<!-- ===== INTERACTIVE SCRIPTS ===== -->
<script>
// ===== DATA dari server =====
const departments = <?= json_encode($departments) ?>;
const msgTemplates = <?= json_encode($msg_templates) ?>;
const serverTime = { h: <?= $server_time_h ?>, i: <?= $server_time_i ?>, s: <?= $server_time_s ?>, day: <?= $server_day ?> };
const rateRemaining = <?= (int)$rate_remaining ?>;

// ===== TOAST =====
function pubToast(msg, icon = '✓') {
    const t = document.getElementById('pubToast');
    document.getElementById('pubToastMsg').textContent = msg;
    document.getElementById('pubToastIcon').textContent = icon;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

// ===== COPY TO CLIPBOARD =====
function copyText(text, msg) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => pubToast(msg, '📋'));
    } else {
        pubToast('Copy tidak didukung browser ini', '⚠️');
    }
}

// ===== OFFICE COUNTDOWN =====
function updateOfficeCountdown() {
    const now = new Date();
    // sinkronkan dengan waktu server saat load, lalu jalan lokal
    const day = now.getDay();
    const h = now.getHours();
    const el = document.getElementById('officeCountdown');
    if (!el) return;

    const isWeekday = day >= 1 && day <= 5;
    const isOpenNow = isWeekday && h >= 8 && h < 16;

    if (isOpenNow) {
        const close = new Date(now); close.setHours(16, 0, 0, 0);
        const diff = close - now;
        const hh = Math.floor(diff / 3600000);
        const mm = Math.floor((diff % 3600000) / 60000);
        el.textContent = `• tutup dalam ${hh}j ${mm}m`;
    } else {
        // hitung ke pembukaan berikutnya
        let target = new Date(now);
        target.setHours(8, 0, 0, 0);
        if (h >= 16 || !isWeekday) target.setDate(target.getDate() + 1);
        while (target.getDay() === 0 || target.getDay() === 6) target.setDate(target.getDate() + 1);
        if (h >= 8 && h < 16 && !isWeekday) target.setDate(target.getDate() + ((8 - target.getDay()) % 7 || 1));
        const diff = target - now;
        const dd = Math.floor(diff / 86400000);
        const hh = Math.floor((diff % 86400000) / 3600000);
        el.textContent = dd > 0 ? `• buka dalam ${dd}h ${hh}j` : `• buka dalam ${hh}j`;
    }
}
updateOfficeCountdown();
setInterval(updateOfficeCountdown, 30000);

// ===== WEEKLY HOURS VISUAL =====
(function() {
    const wrap = document.getElementById('hoursVisual');
    if (!wrap) return;
    const days = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    const today = new Date().getDay();
    const nowH = new Date().getHours() + new Date().getMinutes() / 60;
    let html = '';
    for (let d = 1; d <= 6; d++) {
        const idx = d === 6 ? 6 : d; // Sen..Sab
        const dayIdx = d; // 1..6
        const isWork = dayIdx >= 1 && dayIdx <= 5;
        const isToday = dayIdx === today;
        html += `<div class="hours-bar-row ${isToday ? 'today' : ''}">
            <span class="hours-day">${days[dayIdx]}</span>
            <div class="hours-bar">
                ${isWork ? '<div class="hours-fill" style="margin-left:33.3%;width:33.3%"></div>' : ''}
                ${isToday && isWork && nowH >= 8 && nowH <= 16 ? `<div class="hours-now-marker" style="left:${((nowH - 0) / 24) * 100}%"></div>` : ''}
            </div>
        </div>`;
    }
    html += `<div style="font-size:0.68rem;color:var(--text-muted);margin-top:0.4rem;">▬ Jam operasional 08:00–16:00 WITA</div>`;
    wrap.innerHTML = html;
})();

// ===== DEPARTMENT SELECTOR =====
let currentDept = 'lainnya';
document.querySelectorAll('.dept-card').forEach(card => {
    card.addEventListener('click', () => {
        document.querySelectorAll('.dept-card').forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        card.querySelector('input').checked = true;
        currentDept = card.dataset.dept;

        const d = departments[currentDept];
        // Sync subject select
        const subjSel = document.getElementById('subjek');
        for (const opt of subjSel.options) {
            if (opt.value === d.subjek) { subjSel.value = d.subjek; break; }
        }
        // Show ETA
        const etaBar = document.getElementById('etaBar');
        etaBar.classList.add('show');
        etaBar.style.setProperty('--eta-color', d.color);
        document.getElementById('etaText').textContent = d.eta;
        document.getElementById('etaDept').textContent = d.label;
    });
});
// Trigger initial
document.querySelector('.dept-card.selected')?.click();

// ===== TEMPLATE CHIPS =====
document.getElementById('tplChips').addEventListener('click', (e) => {
    const chip = e.target.closest('.tpl-chip');
    if (!chip) return;
    const tpl = msgTemplates[currentDept] || msgTemplates['lainnya'];
    const pesan = document.getElementById('pesan');
    pesan.value = tpl;
    pesan.dispatchEvent(new Event('input'));
    pesan.focus();
    pubToast('Template diterapkan', '✍️');
});

// ===== CHARACTER COUNTER =====
const pesanInput = document.getElementById('pesan');
const charCounter = document.getElementById('charCounter');
pesanInput.addEventListener('input', function() {
    const len = this.value.length;
    charCounter.textContent = len + '/2000';
    charCounter.classList.toggle('warn', len > 1500 && len <= 1800);
    charCounter.classList.toggle('danger', len > 1800);
});

// ===== REAL-TIME VALIDATION =====
function validateField(input, errorId, validator) {
    const errorEl = document.getElementById(errorId);
    if (!input || !errorEl) return;
    input.addEventListener('blur', function() {
        if (!validator(this.value)) { this.classList.add('error'); errorEl.classList.add('show'); }
        else { this.classList.remove('error'); errorEl.classList.remove('show'); }
    });
    input.addEventListener('input', function() {
        if (validator(this.value)) { this.classList.remove('error'); errorEl.classList.remove('show'); }
    });
}
validateField(document.getElementById('nama'), 'namaError', v => v.trim().length >= 2);
validateField(document.getElementById('email'), 'emailError', v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v));
validateField(pesanInput, 'pesanError', v => v.trim().length >= 10);

// ===== RATE LIMIT DOTS =====
(function() {
    const dots = document.getElementById('rateDots');
    if (!dots) return;
    let html = '';
    for (let i = 0; i < 5; i++) {
        html += `<span class="rate-dot ${i < (5 - rateRemaining) ? '' : 'used'}"></span>`;
    }
    dots.innerHTML = html;
})();

// ===== FORM SUBMIT =====
const form = document.getElementById('contactForm');
const submitBtn = document.getElementById('submitBtn');
form.addEventListener('submit', function(e) {
    const nama = document.getElementById('nama').value.trim();
    const email = document.getElementById('email').value.trim();
    const pesan = pesanInput.value.trim();
    let valid = true;
    if (nama.length < 2) { document.getElementById('nama').classList.add('error'); document.getElementById('namaError').classList.add('show'); valid = false; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { document.getElementById('email').classList.add('error'); document.getElementById('emailError').classList.add('show'); valid = false; }
    if (pesan.length < 10) { pesanInput.classList.add('error'); document.getElementById('pesanError').classList.add('show'); valid = false; }
    if (!valid) { e.preventDefault(); pubToast('Periksa kembali isian formulir', '⚠️'); return; }
    submitBtn.classList.add('loading');
    submitBtn.disabled = true;
    setTimeout(() => { submitBtn.classList.remove('loading'); submitBtn.disabled = false; }, 6000);
});

// ===== FAQ ACCORDON + SEARCH + CATEGORY =====
document.querySelectorAll('.faq-question').forEach(btn => {
    btn.addEventListener('click', function() {
        const item = this.closest('.faq-item');
        const answer = item.querySelector('.faq-answer');
        const isOpen = item.classList.contains('open');
        document.querySelectorAll('.faq-item').forEach(i => {
            i.classList.remove('open');
            i.querySelector('.faq-answer').style.maxHeight = null;
        });
        if (!isOpen) {
            item.classList.add('open');
            answer.style.maxHeight = answer.scrollHeight + 'px';
        }
    });
});

let faqCat = 'all';
function filterFaq() {
    const q = (document.getElementById('faqSearch').value || '').toLowerCase();
    let visible = 0;
    document.querySelectorAll('.faq-item').forEach(item => {
        const matchCat = faqCat === 'all' || item.dataset.cat === faqCat;
        const matchQ = !q || item.dataset.search.includes(q);
        const show = matchCat && matchQ;
        item.classList.toggle('hidden', !show);
        if (show) visible++;
    });
    document.getElementById('faqEmpty').classList.toggle('show', visible === 0);
}
document.getElementById('faqSearch').addEventListener('input', filterFaq);
document.querySelectorAll('.faq-pill').forEach(pill => {
    pill.addEventListener('click', () => {
        document.querySelectorAll('.faq-pill').forEach(p => p.classList.remove('active'));
        pill.classList.add('active');
        faqCat = pill.dataset.cat;
        filterFaq();
    });
});

// ===== SUCCESS MODAL + CONFETTI =====
function closeSuccessModal() {
    document.getElementById('successModal').classList.remove('show');
    if (history.replaceState) history.replaceState(null, '', 'kontak.php');
}
function launchConfetti() {
    const colors = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
    for (let i = 0; i < 60; i++) {
        const piece = document.createElement('div');
        piece.className = 'confetti-piece';
        piece.style.left = Math.random() * 100 + 'vw';
        piece.style.background = colors[Math.floor(Math.random() * colors.length)];
        piece.style.width = (6 + Math.random() * 8) + 'px';
        piece.style.height = (6 + Math.random() * 8) + 'px';
        piece.style.borderRadius = Math.random() > 0.5 ? '50%' : '2px';
        document.body.appendChild(piece);
        const x = (Math.random() - 0.5) * 400;
        const y = window.innerHeight + 100;
        const rot = Math.random() * 720;
        const dur = 2000 + Math.random() * 1500;
        piece.animate([
            { transform: 'translateY(0) translateX(0) rotate(0)', opacity: 1 },
            { transform: `translateY(${y}px) translateX(${x}px) rotate(${rot}deg)`, opacity: 0 }
        ], { duration: dur, easing: 'cubic-bezier(.25,.46,.45,.94)' }).onfinish = () => piece.remove();
    }
}
<?php if ($show_success_modal): ?>
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('ticketId').textContent = 'FKIP-' + new Date().toISOString().slice(0,10).replace(/-/g,'') + '-' + String(Math.floor(1000 + Math.random() * 9000));
    document.getElementById('successModal').classList.add('show');
    launchConfetti();
});
<?php endif; ?>

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && document.activeElement.tagName === 'TEXTAREA') {
        e.preventDefault();
        form.requestSubmit();
    }
    if (e.key === 'Escape') closeSuccessModal();
});

console.log('%c📧 Kontak Publik FKIP UNIMOF - EXTREME MULTIMATE', 'color:#0a6847;font-size:16px;font-weight:bold');
console.log('%cFitur: Department Routing, ETA Estimator, Office Countdown, FAQ Search, Rate Limit Visual', 'color:#64748b');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>