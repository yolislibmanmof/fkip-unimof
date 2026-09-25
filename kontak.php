<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Kontak Kami';
$page_description = 'Hubungi FKIP UNIMOF untuk pertanyaan seputar akademik, PMB, dan kerjasama institusi';

$success_sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('error', 'Token keamanan tidak valid. Silakan coba lagi.');
    } elseif (!check_rate_limit($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 5, 3600)) {
        flash_message('error', 'Terlalu banyak pesan. Coba lagi dalam 1 jam.');
    } else {
        $nama   = trim($_POST['nama'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $subjek = trim($_POST['subjek'] ?? '');
        $pesan  = trim($_POST['pesan'] ?? '');
        $telepon = trim($_POST['telepon'] ?? '');

        if ($nama === '' || $pesan === '' || !validate_email($email)) {
            flash_message('error', 'Mohon lengkapi nama, email valid, dan pesan.');
        } elseif (strlen($pesan) < 10) {
            flash_message('error', 'Pesan terlalu singkat. Minimal 10 karakter.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO kontak (nama, email, telepon, subjek, pesan) VALUES (?,?,?,?,?)");
                $stmt->execute([$nama, $email, $telepon, $subjek, $pesan]);
                $success_sent = true;
                flash_message('success', 'Pesan terkirim! Tim kami akan segera menghubungi Anda.');
            } catch (Exception $e) {
                error_log($e->getMessage());
                flash_message('error', 'Gagal mengirim pesan. Silakan coba lagi.');
            }
        }
    }
    header('Location: ' . base_url('kontak.php?sent=' . ($success_sent ? '1' : '0')));
    exit;
}

$show_success_modal = ($_GET['sent'] ?? '') === '1';
$csrf = generate_csrf_token();

// Cek jam operasional (WITA = UTC+8)
date_default_timezone_set('Asia/Makassar');
$hour = (int)date('H');
$day_of_week = (int)date('w'); // 0=Minggu, 6=Sabtu
$is_open = ($day_of_week >= 1 && $day_of_week <= 5) && ($hour >= 8 && $hour < 16);

require_once __DIR__ . '/includes/header.php';
?>

<!-- ===== SCOPED STYLES ===== -->
<style>
/* Hero Enhancement */
.contact-hero {
    position: relative; overflow: hidden;
    background: linear-gradient(135deg, #0a6847 0%, #084d35 50%, #16213e 100%);
    color: white; padding: 7rem 0 4rem;
}
.contact-hero::before {
    content: ''; position: absolute; inset: 0;
    background:
        radial-gradient(circle at 20% 30%, rgba(245,166,35,0.2) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(59,130,246,0.15) 0%, transparent 50%);
    animation: heroShift 15s ease-in-out infinite;
}
@keyframes heroShift {
    0%, 100% { transform: translate(0, 0); }
    50% { transform: translate(-20px, 20px); }
}
.contact-hero::after {
    content: ''; position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 40px 40px;
}
.contact-hero .container { position: relative; z-index: 2; }
.contact-hero .breadcrumb a, .contact-hero .breadcrumb span { color: rgba(255,255,255,0.75); }
.contact-hero .breadcrumb a:hover { color: white; }
.contact-hero .page-title {
    font-family: var(--font-display); font-size: clamp(2rem, 5vw, 3.5rem);
    font-weight: 900; margin-bottom: 1rem; letter-spacing: -0.02em;
}
.contact-hero .page-subtitle {
    font-size: 1.15rem; opacity: 0.9; max-width: 600px; line-height: 1.7;
}

/* Contact Methods Grid */
.contact-methods {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.5rem; margin: -3rem 0 3rem; position: relative; z-index: 3;
}
.method-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.75rem; text-align: center;
    transition: all 0.4s var(--ease); position: relative; overflow: hidden;
    text-decoration: none; color: inherit; display: block;
}
.method-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: var(--method-color, var(--primary));
    transform: scaleX(0); transition: transform 0.4s;
    transform-origin: left;
}
.method-card:hover {
    transform: translateY(-8px); box-shadow: var(--shadow-xl);
    border-color: var(--method-color, var(--primary));
}
.method-card:hover::before { transform: scaleX(1); }
.method-icon {
    width: 64px; height: 64px; margin: 0 auto 1rem;
    background: linear-gradient(135deg, var(--method-color, var(--primary)), var(--method-color-light, var(--primary-light)));
    border-radius: 16px; display: flex; align-items: center; justify-content: center;
    font-size: 1.75rem; color: white; box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    transition: transform 0.3s;
}
.method-card:hover .method-icon { transform: scale(1.1) rotate(-5deg); }
.method-card h3 { font-size: 1.1rem; margin-bottom: 0.5rem; color: var(--text-primary); }
.method-card p { font-size: 0.9rem; color: var(--text-secondary); line-height: 1.5; margin-bottom: 0.75rem; }
.method-card .method-value {
    font-size: 0.85rem; color: var(--primary); font-weight: 600;
    display: inline-flex; align-items: center; gap: 0.4rem;
}

/* Office Status Badge */
.office-status {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.5rem 1rem; border-radius: 999px; font-size: 0.8rem; font-weight: 700;
    margin-top: 0.75rem;
}
.office-status.open { background: #dcfce7; color: #166534; }
.office-status.closed { background: #fee2e2; color: #991b1b; }
.status-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: currentColor; position: relative;
}
.status-dot::after {
    content: ''; position: absolute; inset: 0; border-radius: 50%;
    background: currentColor; animation: statusPulse 2s infinite;
}
@keyframes statusPulse {
    to { transform: scale(2.5); opacity: 0; }
}

/* Contact Layout */
.contact-layout {
    display: grid; grid-template-columns: 1fr 1.2fr; gap: 3rem;
    margin-bottom: 4rem; align-items: start;
}

/* Info Sidebar */
.contact-info-sidebar {
    position: sticky; top: 100px; display: flex; flex-direction: column; gap: 1.5rem;
}
.info-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.75rem;
}
.info-card h3 {
    font-family: var(--font-display); font-size: 1.25rem; margin-bottom: 1rem;
    padding-bottom: 0.75rem; border-bottom: 2px solid var(--bg-tertiary);
}
.info-list { list-style: none; padding: 0; margin: 0; }
.info-list li {
    display: flex; gap: 0.85rem; padding: 0.85rem 0;
    border-bottom: 1px dashed var(--border); font-size: 0.9rem;
}
.info-list li:last-child { border-bottom: none; }
.info-icon {
    width: 36px; height: 36px; background: var(--bg-secondary);
    border-radius: 10px; display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; flex-shrink: 0;
}
.info-text { flex: 1; }
.info-text strong { display: block; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.2rem; text-transform: uppercase; letter-spacing: 0.05em; }
.info-text span { color: var(--text-primary); font-weight: 500; }

/* Quick Contact Buttons */
.quick-contact {
    display: flex; flex-direction: column; gap: 0.75rem;
}
.qc-btn {
    display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem;
    border-radius: var(--radius-md); text-decoration: none; color: white;
    font-weight: 600; font-size: 0.9rem; transition: all 0.3s;
}
.qc-btn.wa { background: linear-gradient(135deg, #25D366, #128C7E); }
.qc-btn.email { background: linear-gradient(135deg, #EA4335, #B92B27); }
.qc-btn:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.2); }
.qc-icon { font-size: 1.25rem; }

/* Trust Badges */
.trust-badges {
    display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem;
}
.trust-badge {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.35rem 0.7rem; background: var(--bg-secondary);
    border-radius: 999px; font-size: 0.72rem; color: var(--text-muted);
    font-weight: 600;
}

/* Form Card */
.form-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2.5rem;
    box-shadow: var(--shadow-lg); position: relative; overflow: hidden;
}
.form-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent-light));
}
.form-header {
    margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border);
}
.form-header h2 {
    font-family: var(--font-display); font-size: 1.75rem; margin-bottom: 0.5rem;
}
.form-header p { color: var(--text-secondary); font-size: 0.95rem; }

/* Floating Label Form */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
.form-group { position: relative; margin-bottom: 1.25rem; }
.form-group.full { grid-column: 1 / -1; }
.input-wrap { position: relative; }
.form-input-float, .form-textarea-float {
    width: 100%; padding: 1rem 1rem 1rem 3rem;
    border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.95rem; background: var(--bg-secondary);
    transition: all 0.3s; color: var(--text-primary);
}
.form-textarea-float { min-height: 140px; resize: vertical; padding-top: 1.5rem; }
.form-input-float:focus, .form-textarea-float:focus {
    outline: none; border-color: var(--primary); background: var(--bg-primary);
    box-shadow: 0 0 0 4px rgba(10,104,71,0.1);
}
.form-input-float::placeholder, .form-textarea-float::placeholder { color: transparent; }
.input-icon {
    position: absolute; left: 1rem; top: 50%; transform: translateY(-50%);
    color: var(--text-muted); font-size: 1.1rem; pointer-events: none;
    transition: color 0.3s;
}
.form-input-float:focus ~ .input-icon, .form-textarea-float:focus ~ .input-icon {
    color: var(--primary);
}
.floating-label {
    position: absolute; left: 3rem; top: 50%; transform: translateY(-50%);
    color: var(--text-muted); font-size: 0.95rem; pointer-events: none;
    transition: all 0.25s; background: var(--bg-secondary); padding: 0 0.35rem;
}
.form-textarea-float ~ .floating-label { top: 1.5rem; }
.form-input-float:focus ~ .floating-label,
.form-input-float:not(:placeholder-shown) ~ .floating-label,
.form-textarea-float:focus ~ .floating-label,
.form-textarea-float:not(:placeholder-shown) ~ .floating-label {
    top: 0; font-size: 0.75rem; color: var(--primary); font-weight: 600;
}
.form-input-float.error, .form-textarea-float.error {
    border-color: var(--error, #ef4444);
}
.form-input-float.error ~ .input-icon { color: var(--error, #ef4444); }
.error-msg {
    color: var(--error, #ef4444); font-size: 0.78rem; margin-top: 0.35rem;
    display: none; align-items: center; gap: 0.3rem;
}
.error-msg.show { display: flex; }

/* Subject Select Enhancement */
.subject-wrap { position: relative; }
.form-select-float {
    width: 100%; padding: 1rem 1rem 1rem 3rem;
    border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.95rem; background: var(--bg-secondary);
    cursor: pointer; appearance: none; transition: all 0.3s;
}
.form-select-float:focus {
    outline: none; border-color: var(--primary); background: var(--bg-primary);
    box-shadow: 0 0 0 4px rgba(10,104,71,0.1);
}
.select-arrow {
    position: absolute; right: 1rem; top: 50%; transform: translateY(-50%);
    pointer-events: none; color: var(--text-muted);
}

/* Character Counter */
.char-counter {
    position: absolute; bottom: 0.75rem; right: 1rem;
    font-size: 0.75rem; color: var(--text-muted); font-weight: 600;
    background: var(--bg-primary); padding: 0.2rem 0.5rem; border-radius: 999px;
}
.char-counter.warn { color: #f59e0b; }
.char-counter.danger { color: #ef4444; }

/* Submit Button */
.btn-submit {
    width: 100%; padding: 1.1rem; background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; border: none; border-radius: var(--radius-md);
    font-family: inherit; font-size: 1rem; font-weight: 700; cursor: pointer;
    transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 0.5rem;
    position: relative; overflow: hidden;
}
.btn-submit:hover:not(:disabled) {
    transform: translateY(-2px); box-shadow: 0 10px 25px rgba(10,104,71,0.35);
}
.btn-submit:disabled { opacity: 0.7; cursor: not-allowed; }
.btn-submit .spinner {
    width: 20px; height: 20px; border: 2px solid rgba(255,255,255,0.3);
    border-top-color: white; border-radius: 50%; animation: spin 0.7s linear infinite;
    display: none;
}
.btn-submit.loading .spinner { display: inline-block; }
.btn-submit.loading .btn-text { display: none; }
@keyframes spin { to { transform: rotate(360deg); } }

/* FAQ Section */
.faq-section { margin-top: 4rem; }
.faq-header { text-align: center; margin-bottom: 2.5rem; }
.faq-header h2 {
    font-family: var(--font-display); font-size: 2rem; margin-bottom: 0.5rem;
}
.faq-header p { color: var(--text-secondary); }
.faq-list { max-width: 800px; margin: 0 auto; }
.faq-item {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-md); margin-bottom: 0.75rem; overflow: hidden;
    transition: all 0.3s;
}
.faq-item:hover { border-color: var(--primary-light); }
.faq-item.open { border-color: var(--primary); box-shadow: var(--shadow-md); }
.faq-question {
    width: 100%; padding: 1.25rem 1.5rem; background: none; border: none;
    text-align: left; font-family: inherit; font-size: 1rem; font-weight: 600;
    color: var(--text-primary); cursor: pointer; display: flex;
    justify-content: space-between; align-items: center; gap: 1rem;
}
.faq-icon {
    width: 28px; height: 28px; border-radius: 50%;
    background: var(--bg-secondary); display: flex; align-items: center; justify-content: center;
    transition: all 0.3s; flex-shrink: 0; font-size: 1.1rem;
}
.faq-item.open .faq-icon { background: var(--primary); color: white; transform: rotate(45deg); }
.faq-answer {
    max-height: 0; overflow: hidden; transition: max-height 0.4s ease;
}
.faq-answer-inner {
    padding: 0 1.5rem 1.25rem; color: var(--text-secondary); line-height: 1.7; font-size: 0.95rem;
}

/* Success Modal */
.success-modal {
    position: fixed; inset: 0; background: rgba(15,23,42,0.8);
    backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center;
    z-index: 10000; padding: 2rem;
}
.success-modal.show { display: flex; }
.success-box {
    background: white; border-radius: var(--radius-xl); padding: 3rem 2rem;
    max-width: 480px; width: 100%; text-align: center; position: relative;
    animation: successPop 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
@keyframes successPop {
    from { transform: scale(0.8); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
.success-icon {
    width: 80px; height: 80px; margin: 0 auto 1.5rem;
    background: linear-gradient(135deg, #10b981, #059669);
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 2.5rem; color: white; animation: successBounce 0.6s;
}
@keyframes successBounce {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.2); }
}
.success-box h3 { font-family: var(--font-display); font-size: 1.5rem; margin-bottom: 0.75rem; }
.success-box p { color: var(--text-secondary); margin-bottom: 1.5rem; line-height: 1.6; }
.success-close {
    padding: 0.75rem 2rem; background: var(--primary); color: white;
    border: none; border-radius: var(--radius-md); font-family: inherit;
    font-weight: 600; cursor: pointer; transition: all 0.3s;
}
.success-close:hover { background: var(--primary-dark); transform: translateY(-2px); }

/* Confetti */
.confetti-piece {
    position: fixed; width: 10px; height: 10px; top: -20px;
    z-index: 10001; pointer-events: none;
}

@media (max-width: 968px) {
    .contact-layout { grid-template-columns: 1fr; }
    .contact-info-sidebar { position: static; }
    .form-grid { grid-template-columns: 1fr; }
    .contact-methods { margin-top: -2rem; }
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
            <p class="page-subtitle">Kami siap menjawab pertanyaan Anda seputar akademik, PMB, dan kerjasama institusi. Tim kami akan merespon dalam 1x24 jam kerja.</p>
        </div>
    </div>
</section>

<!-- ===== CONTACT METHODS ===== -->
<div class="container">
    <div class="contact-methods" data-aos="fade-up">
        <div class="method-card" style="--method-color:#3b82f6; --method-color-light:#60a5fa">
            <div class="method-icon"></div>
            <h3>Lokasi Kampus</h3>
            <p>Kunjungi kami langsung di kampus</p>
            <span class="method-value">Lihat di Peta →</span>
        </div>
        <div class="method-card" style="--method-color:#10b981; --method-color-light:#34d399">
            <div class="method-icon"></div>
            <h3>Telepon</h3>
            <p>Senin–Jumat, 08:00–16:00 WITA</p>
            <span class="method-value"><?= sanitize(get_setting('telepon', '(0382) 21234')) ?></span>
        </div>
        <div class="method-card" style="--method-color:#ef4444; --method-color-light:#f87171">
            <div class="method-icon">✉️</div>
            <h3>Email</h3>
            <p>Respon dalam 1x24 jam kerja</p>
            <span class="method-value"><?= sanitize(get_setting('email', 'fkip@unimof.ac.id')) ?></span>
        </div>
        <a href="https://wa.me/6281234567890" target="_blank" rel="noopener" class="method-card" style="--method-color:#25D366; --method-color-light:#4ade80">
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
                                <div class="office-status <?= $is_open ? 'open' : 'closed' ?>">
                                    <span class="status-dot"></span>
                                    <?= $is_open ? 'Buka Sekarang' : 'Tutup' ?>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="info-card">
                    <h3>⚡ Kontak Cepat</h3>
                    <div class="quick-contact">
                        <a href="https://wa.me/6281234567890?text=Halo%20FKIP%20UNIMOF" target="_blank" rel="noopener" class="qc-btn wa">
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
                        Pesan Anda dilindungi dengan enkripsi dan tidak akan dibagikan ke pihak ketiga.
                    </p>
                    <div class="trust-badges">
                        <span class="trust-badge"> CSRF Protected</span>
                        <span class="trust-badge">⚡ Rate Limited</span>
                        <span class="trust-badge">✓ Validated</span>
                    </div>
                </div>
            </aside>

            <!-- Right: Contact Form -->
            <div class="form-card" data-aos="fade-left">
                <div class="form-header">
                    <h2>✉️ Kirim Pesan</h2>
                    <p>Isi formulir di bawah ini dan tim kami akan segera menghubungi Anda.</p>
                </div>

                <?php display_flash(); ?>

                <form method="POST" action="" id="contactForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <div class="input-wrap">
                                <input type="text" id="nama" name="nama" class="form-input-float" 
                                    placeholder=" " required maxlength="100"
                                    value="<?= htmlspecialchars($_POST['nama'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <span class="input-icon">👤</span>
                                <label class="floating-label" for="nama">Nama Lengkap *</label>
                            </div>
                            <div class="error-msg" id="namaError">⚠️ Nama wajib diisi</div>
                        </div>

                        <div class="form-group">
                            <div class="input-wrap">
                                <input type="email" id="email" name="email" class="form-input-float" 
                                    placeholder=" " required maxlength="100"
                                    value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <span class="input-icon">📧</span>
                                <label class="floating-label" for="email">Email *</label>
                            </div>
                            <div class="error-msg" id="emailError">⚠️ Email tidak valid</div>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <div class="input-wrap">
                                <input type="tel" id="telepon" name="telepon" class="form-input-float" 
                                    placeholder=" " maxlength="20"
                                    value="<?= htmlspecialchars($_POST['telepon'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <span class="input-icon">📱</span>
                                <label class="floating-label" for="telepon">Telepon (opsional)</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="input-wrap subject-wrap">
                                <select id="subjek" name="subjek" class="form-select-float">
                                    <option value="">Pilih subjek...</option>
                                    <option value="Informasi PMB" <?= ($_POST['subjek'] ?? '') === 'Informasi PMB' ? 'selected' : '' ?>>Informasi PMB</option>
                                    <option value="Akademik" <?= ($_POST['subjek'] ?? '') === 'Akademik' ? 'selected' : '' ?>>Pertanyaan Akademik</option>
                                    <option value="Beasiswa" <?= ($_POST['subjek'] ?? '') === 'Beasiswa' ? 'selected' : '' ?>>Beasiswa</option>
                                    <option value="Kerjasama" <?= ($_POST['subjek'] ?? '') === 'Kerjasama' ? 'selected' : '' ?>>Kerjasama Institusi</option>
                                    <option value="Lainnya" <?= ($_POST['subjek'] ?? '') === 'Lainnya' ? 'selected' : '' ?>>Lainnya</option>
                                </select>
                                <span class="input-icon">📋</span>
                                <span class="select-arrow">▾</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group full">
                        <div class="input-wrap">
                            <textarea id="pesan" name="pesan" class="form-textarea-float" 
                                placeholder=" " required maxlength="2000"><?= htmlspecialchars($_POST['pesan'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            <span class="input-icon" style="top: 1.5rem;">💬</span>
                            <label class="floating-label" for="pesan">Pesan Anda *</label>
                            <span class="char-counter" id="charCounter">0/2000</span>
                        </div>
                        <div class="error-msg" id="pesanError">⚠️ Pesan minimal 10 karakter</div>
                    </div>

                    <button type="submit" class="btn-submit" id="submitBtn">
                        <span class="btn-text">Kirim Pesan 🚀</span>
                        <span class="spinner"></span>
                    </button>

                    <p style="text-align:center; font-size:0.78rem; color:var(--text-muted); margin-top:1rem;">
                        Dengan mengirim, Anda menyetujui kebijakan privasi kami.
                    </p>
                </form>
            </div>
        </div>

        <!-- ===== FAQ SECTION ===== -->
        <div class="faq-section" data-aos="fade-up">
            <div class="faq-header">
                <h2>❓ Pertanyaan Umum</h2>
                <p>Temukan jawaban untuk pertanyaan yang sering diajukan</p>
            </div>
            <div class="faq-list">
                <div class="faq-item">
                    <button class="faq-question">
                        <span>Bagaimana cara mendaftar sebagai mahasiswa baru?</span>
                        <span class="faq-icon">+</span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-inner">
                            Pendaftaran mahasiswa baru dilakukan secara online melalui website resmi PMB UNIMOF. Anda perlu menyiapkan ijazah, transkrip nilai, pas foto, dan kartu identitas. Informasi lengkap tersedia di laman PMB atau hubungi kami via WhatsApp.
                        </div>
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question">
                        <span>Apakah tersedia program beasiswa?</span>
                        <span class="faq-icon">+</span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-inner">
                            Ya, FKIP UNIMOF menyediakan berbagai program beasiswa termasuk beasiswa prestasi akademik, beasiswa kurang mampu, dan beasiswa dari Muhammadiyah. Informasi detail dapat ditanyakan langsung ke bagian kemahasiswaan.
                        </div>
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question">
                        <span>Berapa lama waktu respon untuk pesan yang dikirim?</span>
                        <span class="faq-icon">+</span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-inner">
                            Kami berkomitmen untuk merespon semua pesan dalam waktu 1x24 jam kerja (Senin–Jumat). Untuk pertanyaan mendesak, silakan hubungi kami via WhatsApp untuk respon lebih cepat.
                        </div>
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question">
                        <span>Apakah bisa kunjungan ke kampus untuk melihat fasilitas?</span>
                        <span class="faq-icon">+</span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-inner">
                            Tentu! Kami menyambut baik kunjungan calon mahasiswa dan orang tua. Silakan hubungi kami minimal 1 hari sebelumnya untuk mengatur jadwal kunjungan dan tur kampus. Kunjungan dapat dilakukan pada jam operasional.
                        </div>
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question">
                        <span>Bagaimana cara mengajukan kerjasama institusi?</span>
                        <span class="faq-icon">+</span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-inner">
                            Untuk kerjasama institusi (MoU, magang, penelitian bersama), silakan kirim proposal resmi ke email kami dengan subjek "Kerjasama". Tim kami akan meninjau dan menghubungi Anda dalam 3-5 hari kerja untuk diskusi lebih lanjut.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== SUCCESS MODAL ===== -->
<div class="success-modal" id="successModal">
    <div class="success-box">
        <div class="success-icon">✓</div>
        <h3>Pesan Terkirim!</h3>
        <p>Terima kasih telah menghubungi FKIP UNIMOF. Tim kami akan merespon pesan Anda dalam 1x24 jam kerja.</p>
        <button class="success-close" onclick="closeSuccessModal()">Tutup</button>
    </div>
</div>

<!-- ===== INTERACTIVE SCRIPTS ===== -->
<script>
// Character Counter
const pesanInput = document.getElementById('pesan');
const charCounter = document.getElementById('charCounter');
pesanInput.addEventListener('input', function() {
    const len = this.value.length;
    charCounter.textContent = len + '/2000';
    charCounter.classList.toggle('warn', len > 1500 && len <= 1800);
    charCounter.classList.toggle('danger', len > 1800);
});

// Real-time Validation
function validateField(input, errorId, validator) {
    const errorEl = document.getElementById(errorId);
    input.addEventListener('blur', function() {
        if (!validator(this.value)) {
            this.classList.add('error');
            errorEl.classList.add('show');
        } else {
            this.classList.remove('error');
            errorEl.classList.remove('show');
        }
    });
    input.addEventListener('input', function() {
        if (validator(this.value)) {
            this.classList.remove('error');
            errorEl.classList.remove('show');
        }
    });
}

validateField(document.getElementById('nama'), 'namaError', v => v.trim().length >= 2);
validateField(document.getElementById('email'), 'emailError', v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v));
validateField(document.getElementById('pesan'), 'pesanError', v => v.trim().length >= 10);

// Form Submit with Loading
const form = document.getElementById('contactForm');
const submitBtn = document.getElementById('submitBtn');

form.addEventListener('submit', function(e) {
    const nama = document.getElementById('nama').value.trim();
    const email = document.getElementById('email').value.trim();
    const pesan = document.getElementById('pesan').value.trim();
    
    let valid = true;
    if (nama.length < 2) { document.getElementById('nama').classList.add('error'); document.getElementById('namaError').classList.add('show'); valid = false; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { document.getElementById('email').classList.add('error'); document.getElementById('emailError').classList.add('show'); valid = false; }
    if (pesan.length < 10) { document.getElementById('pesan').classList.add('error'); document.getElementById('pesanError').classList.add('show'); valid = false; }
    
    if (!valid) {
        e.preventDefault();
        return;
    }
    
    submitBtn.classList.add('loading');
    submitBtn.disabled = true;
});

// FAQ Accordion
document.querySelectorAll('.faq-question').forEach(btn => {
    btn.addEventListener('click', function() {
        const item = this.closest('.faq-item');
        const answer = item.querySelector('.faq-answer');
        const isOpen = item.classList.contains('open');
        
        // Close all
        document.querySelectorAll('.faq-item').forEach(i => {
            i.classList.remove('open');
            i.querySelector('.faq-answer').style.maxHeight = null;
        });
        
        // Open clicked if wasn't open
        if (!isOpen) {
            item.classList.add('open');
            answer.style.maxHeight = answer.scrollHeight + 'px';
        }
    });
});

// Success Modal
function closeSuccessModal() {
    document.getElementById('successModal').classList.remove('show');
}

// Show success modal if sent=1
<?php if ($show_success_modal): ?>
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('successModal').classList.add('show');
    launchConfetti();
});
<?php endif; ?>

// Confetti Animation
function launchConfetti() {
    const colors = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
    for (let i = 0; i < 50; i++) {
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

// Keyboard Shortcut: Ctrl+Enter to submit
document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && document.activeElement.tagName === 'TEXTAREA') {
        e.preventDefault();
        form.submit();
    }
});

// Close modal on Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeSuccessModal();
});

console.log('%c📧 Kontak FKIP UNIMOF', 'color:#0a6847;font-size:16px;font-weight:bold');
console.log('%cShortcut: Ctrl+Enter untuk submit pesan', 'color:#64748b');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>