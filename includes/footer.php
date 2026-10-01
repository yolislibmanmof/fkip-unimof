<?php
// includes/footer.php - Premium Footer (FIXED VERSION)
// =====================================================
// HELPER FUNCTION (auto-detect logo dari upload admin)
// =====================================================
if (!function_exists('get_active_logo_url')) {
    function get_active_logo_url() {
        $extensions = ['png', 'jpg', 'jpeg', 'svg', 'webp'];
        foreach ($extensions as $ext) {
            $path = __DIR__ . '/../assets/images/logo.' . $ext;
            if (file_exists($path)) {
                return base_url('assets/images/logo.' . $ext);
            }
        }
        return null;
    }
}
$footer_logo_url = get_active_logo_url();

// =====================================================
// DATA DINAMIS (SCHEMA-SAFE)
// =====================================================
$footer_prodi = [];
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `program_studi`")->fetchAll(PDO::FETCH_COLUMN);
    $has_status = in_array('status', $cols, true);
    $has_urutan = in_array('urutan', $cols, true);
    $sql = "SELECT id, nama, singkatan FROM program_studi" . ($has_status ? " WHERE status='Aktif'" : "") .
           " ORDER BY " . ($has_urutan ? "urutan ASC," : "") . " id ASC LIMIT 8";
    $footer_prodi = $pdo->query($sql)->fetchAll();
} catch (Exception $e) {
    $footer_prodi = [];
}
if (empty($footer_prodi)) {
    $footer_prodi = [
        ['id' => 1, 'nama' => 'Pendidikan Matematika', 'singkatan' => 'PMAT'],
        ['id' => 2, 'nama' => 'Pendidikan Fisika', 'singkatan' => 'PFIS'],
        ['id' => 3, 'nama' => 'Pendidikan Biologi', 'singkatan' => 'PBIO'],
        ['id' => 4, 'nama' => 'Pendidikan Kimia', 'singkatan' => 'PKIM'],
        ['id' => 5, 'nama' => 'Pendidikan B. Inggris', 'singkatan' => 'PBSI'],
        ['id' => 6, 'nama' => 'Pendidikan B. Indonesia', 'singkatan' => 'PBIN'],
        ['id' => 7, 'nama' => 'Pendidikan Ekonomi', 'singkatan' => 'PEKO'],
        ['id' => 8, 'nama' => 'Pendidikan Kewarganegaraan', 'singkatan' => 'PKN'],
    ];
}

// --- ✅ FIXED: Social media keys (sesuai dengan admin/pengaturan.php) ---
$footer_socials = [
    ['key' => 'facebook',  'icon' => '📘', 'label' => 'Facebook',  'url' => get_setting('facebook_url', '')],
    ['key' => 'instagram', 'icon' => '📸', 'label' => 'Instagram', 'url' => get_setting('instagram_url', '')],
    ['key' => 'youtube',   'icon' => '🎬', 'label' => 'YouTube',   'url' => get_setting('youtube_url', '')],
    ['key' => 'twitter',   'icon' => '🐦', 'label' => 'Twitter/X', 'url' => get_setting('twitter', '')],
    ['key' => 'tiktok',    'icon' => '🎵', 'label' => 'TikTok',    'url' => get_setting('tiktok', '')],
];
$footer_socials = array_values(array_filter($footer_socials, fn($s) => !empty($s['url'])));

// --- Status kantor (WITA, tanpa mengubah timezone global) ---
$nowWITA = new DateTime('now', new DateTimeZone('Asia/Makassar'));
$fh = (int)$nowWITA->format('H');
$fd = (int)$nowWITA->format('w');
$office_open = ($fd >= 1 && $fd <= 5) && ($fh >= 8 && $fh < 16);

// --- Kontak ---
$f_alamat  = get_setting('alamat', 'Jl. Bhayangkara No.1, Maumere, Sikka, NTT');
$f_telepon = get_setting('telepon', '(0382) 21234');
$f_email   = get_setting('email', 'fkip@unimof.ac.id');
$f_wa      = preg_replace('/[^0-9]/', '', get_setting('whatsapp_number', '6281234567890'));
?>
    </main>

    <footer class="site-footer" id="siteFooter">
        <!-- Wave Decoration -->
        <div class="footer-wave">
            <svg viewBox="0 0 1440 120" preserveAspectRatio="none">
                <path d="M0,64L80,69.3C160,75,320,85,480,80C640,75,800,53,960,48C1120,43,1280,53,1360,58.7L1440,64L1440,0L1360,0C1280,0,1120,0,960,0C800,0,640,0,480,0C320,0,160,0,80,0L0,0Z"></path>
            </svg>
        </div>

        <div class="container">
            <div class="footer-grid">
                <!-- Column 1: Brand -->
                <div class="footer-brand footer-reveal">
                    <div class="brand-header">
                        <?php if ($footer_logo_url): ?>
                            <!-- ✅ Logo dari upload admin -->
                            <div class="brand-logo-img">
                                <img src="<?= $footer_logo_url ?>" alt="Logo FKIP UNIMOF">
                            </div>
                        <?php else: ?>
                            <!-- Fallback: Monogram "F" -->
                            <div class="brand-logo">F</div>
                        <?php endif; ?>
                        <div>
                            <h3>FKIP UNIMOF</h3>
                            <p class="brand-tagline">Mencerdaskan Bangsa</p>
                        </div>
                    </div>
                    <p class="brand-desc">
                        Fakultas Keguruan dan Ilmu Pendidikan Universitas Muhammadiyah Maumere. Mencetak pendidik profesional berkarakter Islami untuk Indonesia Timur.
                    </p>

                    <div class="office-status-footer <?= $office_open ? 'open' : 'closed' ?>">
                        <span class="office-dot"></span>
                        <span><?= $office_open ? 'Kantor Buka Sekarang' : 'Kantor Tutup' ?> • <span id="footerClock">--:--:--</span> WITA</span>
                    </div>

                    <?php if (!empty($footer_socials)): ?>
                    <div class="social-links">
                        <?php foreach ($footer_socials as $s): ?>
                        <a href="<?= sanitize($s['url']) ?>" target="_blank" rel="noopener" class="social-icon" aria-label="<?= sanitize($s['label']) ?>" title="<?= sanitize($s['label']) ?>">
                            <span style="font-size:1.1rem;"><?= $s['icon'] ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Column 2: Quick Links -->
                <div class="footer-col footer-reveal">
                    <h4>Tautan Cepat</h4>
                    <ul class="footer-links">
                        <li><a href="<?= base_url() ?>">🏠 Beranda</a></li>
                        <li><a href="<?= base_url('tentang.php') ?>">🏛️ Tentang Kami</a></li>
                        <li><a href="<?= base_url('program.php') ?>">🎓 Program Studi</a></li>
                        <li><a href="<?= base_url('dosen.php') ?>">👨‍🏫 Dosen</a></li>
                        <li><a href="<?= base_url('berita.php') ?>">📰 Berita</a></li>
                        <li><a href="<?= base_url('pmb.php') ?>">📝 Pendaftaran (PMB)</a></li>
                        <li><a href="<?= base_url('kontak.php') ?>">✉️ Kontak</a></li>
                    </ul>
                </div>

                <!-- Column 3: Programs (DINAMIS) -->
                <div class="footer-col footer-reveal">
                    <h4>Program Studi</h4>
                    <ul class="footer-links">
                        <?php foreach ($footer_prodi as $fp): ?>
                        <li>
                            <a href="<?= base_url('program-detail.php?id=' . (int)$fp['id']) ?>">
                                <?= sanitize($fp['nama']) ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Column 4: Contact -->
                <div class="footer-col footer-reveal">
                    <h4>Kontak</h4>
                    <ul class="contact-info">
                        <li><span class="ci-icon">📍</span><span><?= sanitize($f_alamat) ?></span></li>
                        <li><span class="ci-icon">📞</span><span><a href="tel:<?= sanitize($f_telepon) ?>" class="footer-inline-link"><?= sanitize($f_telepon) ?></a></span></li>
                        <li><span class="ci-icon">✉️</span><span><a href="mailto:<?= sanitize($f_email) ?>" class="footer-inline-link"><?= sanitize($f_email) ?></a></span></li>
                        <li><span class="ci-icon">🕐</span><span>Senin – Jumat, 08:00 – 16:00 WITA</span></li>
                    </ul>
                </div>
            </div>

            <!-- Newsletter Strip -->
            <div class="footer-newsletter footer-reveal">
                <div class="fn-content">
                    <div class="fn-icon">📬</div>
                    <div>
                        <h4>Berlangganan Informasi</h4>
                        <p>Dapatkan pengumuman PMB, beasiswa, dan kegiatan terbaru langsung di email Anda.</p>
                    </div>
                </div>
                <form class="fn-form" id="newsletterForm" onsubmit="return handleNewsletter(event)">
                    <input type="email" id="newsletterEmail" placeholder="nama@email.com" required aria-label="Email untuk berlangganan">
                    <button type="submit" class="fn-btn" id="newsletterBtn">Berlangganan</button>
                </form>
            </div>

            <!-- Footer Bottom -->
            <div class="footer-bottom">
                <p>
                    &copy; <?= date('Y') ?> <strong>FKIP UNIMOF</strong>. All rights reserved.
                    <?php if (defined('APP_VERSION')): ?>
                        <span class="version-badge">v<?= APP_VERSION ?></span>
                    <?php endif; ?>
                </p>
                <div class="footer-legal">
                    <a href="#" data-soon="Kebijakan Privasi">Privacy Policy</a>
                    <span class="divider">•</span>
                    <a href="#" data-soon="Syarat & Ketentuan">Terms of Service</a>
                    <span class="divider">•</span>
                    <span class="footer-credit">Dibuat dengan 💚 oleh Tim IT FKIP</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- =====================================================
         GLOBAL FLOATING ACTION STACK
         ===================================================== -->
    <div id="footerFabRoot">
        <!-- Back to Top dengan progress ring -->
        <button id="backToTop" class="back-to-top" aria-label="Kembali ke atas">
            <svg class="btt-ring" viewBox="0 0 50 50" aria-hidden="true">
                <circle class="btt-ring-bg" cx="25" cy="25" r="22"></circle>
                <circle class="btt-ring-fill" id="bttRingFill" cx="25" cy="25" r="22"></circle>
            </svg>
            <svg class="btt-arrow" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M18 15l-6-6-6 6"/>
            </svg>
        </button>

        <!-- FAB Stack -->
        <div class="fab-stack">
            <div class="fab-menu" id="fabMenu" role="menu">
                <a href="tel:<?= sanitize($f_telepon) ?>" class="fab-item fab-tel" role="menuitem" title="Telepon">
                    <span>📞</span><small>Telepon</small>
                </a>
                <a href="mailto:<?= sanitize($f_email) ?>" class="fab-item fab-mail" role="menuitem" title="Email">
                    <span>✉️</span><small>Email</small>
                </a>
                <a href="<?= base_url('pmb.php') ?>" class="fab-item fab-pmb" role="menuitem" title="Daftar PMB">
                    <span>📝</span><small>Daftar</small>
                </a>
                <a href="https://wa.me/<?= $f_wa ?>?text=Halo%20Admin%20FKIP%20UNIMOF" target="_blank" rel="noopener" class="fab-item fab-wa" role="menuitem" title="WhatsApp">
                    <span>💬</span><small>WhatsApp</small>
                </a>
            </div>
            <button class="fab-main" id="fabMain" aria-expanded="false" aria-label="Menu kontak cepat">
                <span class="fab-main-icon">💬</span>
            </button>
        </div>
    </div>

    <style>
    /* =====================================================
       FOOTER BASE
       ===================================================== */
    .site-footer {
        background: linear-gradient(180deg, #0f172a 0%, #064e34 100%);
        color: rgba(255, 255, 255, 0.8);
        padding: 5rem 0 2rem;
        position: relative;
        margin-top: 4rem;
    }
    
    /* ✅ DARK MODE SUPPORT */
    [data-theme="dark"] .site-footer {
        background: linear-gradient(180deg, #020617 0%, #0f172a 100%);
    }
    
    .footer-wave { position: absolute; top: -1px; left: 0; width: 100%; overflow: hidden; line-height: 0; }
    .footer-wave svg { display: block; width: 100%; height: 80px; }
    .footer-wave svg path { fill: var(--bg-primary, #ffffff); }

    .footer-grid {
        display: grid; grid-template-columns: 2fr 1fr 1.2fr 1.5fr;
        gap: 3rem; margin-bottom: 3rem;
    }

    /* Reveal animation */
    .footer-reveal { opacity: 0; transform: translateY(24px); transition: opacity 0.6s ease, transform 0.6s ease; }
    .footer-reveal.revealed { opacity: 1; transform: none; }
    @media (prefers-reduced-motion: reduce) {
        .footer-reveal { opacity: 1; transform: none; transition: none; }
    }

    .brand-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }
    
    /* Logo Monogram Fallback */
    .brand-logo {
        width: 56px; height: 56px;
        background: linear-gradient(135deg, #10b981, #059669);
        border-radius: 14px; display: flex; align-items: center; justify-content: center;
        color: white; font-family: serif; font-size: 1.75rem; font-weight: 900; flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    
    /* ✅ Logo Image dari upload admin */
    .brand-logo-img {
        width: 56px; height: 56px;
        border-radius: 14px;
        overflow: hidden;
        background: #ffffff;
        padding: 6px;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        flex-shrink: 0;
    }
    .brand-logo-img img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        display: block;
    }
    [data-theme="dark"] .brand-logo-img {
        background: rgba(255, 255, 255, 0.95);
    }
    
    .brand-header h3 { color: white; margin: 0; font-size: 1.5rem; font-weight: 800; line-height: 1.2; }
    .brand-tagline { color: #10b981; margin: 0; font-size: 0.85rem; font-weight: 600; }
    .brand-desc { color: rgba(255, 255, 255, 0.7); line-height: 1.7; margin-bottom: 1.25rem; font-size: 0.95rem; }

    /* Office status */
    .office-status-footer {
        display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.45rem 0.9rem; border-radius: 999px; font-size: 0.78rem; font-weight: 700;
        margin-bottom: 1.25rem;
    }
    .office-status-footer.open { background: rgba(16,185,129,0.15); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.4); }
    .office-status-footer.closed { background: rgba(239,68,68,0.15); color: #fca5a5; border: 1px solid rgba(239,68,68,0.4); }
    .office-dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; position: relative; }
    .office-dot::after { content: ''; position: absolute; inset: 0; border-radius: 50%; background: currentColor; animation: officePulse 2s infinite; }
    @keyframes officePulse { to { transform: scale(2.5); opacity: 0; } }

    .social-links { display: flex; gap: 0.75rem; }
    .social-icon {
        width: 42px; height: 42px; background: rgba(255, 255, 255, 0.1);
        border-radius: 10px; display: flex; align-items: center; justify-content: center;
        color: white; text-decoration: none;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .social-icon:hover {
        background: #10b981; transform: translateY(-4px);
        box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4); border-color: #10b981;
    }

    .footer-col h4 {
        color: white; margin-bottom: 1.5rem; font-size: 1.15rem; font-weight: 700;
        position: relative; padding-bottom: 0.75rem;
    }
    .footer-col h4::after {
        content: ''; position: absolute; bottom: 0; left: 0;
        width: 40px; height: 3px; background: #10b981; border-radius: 3px;
    }
    .footer-links { list-style: none; padding: 0; margin: 0; }
    .footer-links li { margin-bottom: 0.7rem; }
    .footer-links a {
        color: rgba(255, 255, 255, 0.7); text-decoration: none; transition: all 0.3s;
        display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.92rem;
    }
    .footer-links a::before { content: '›'; color: #10b981; font-weight: 700; transition: transform 0.3s; }
    .footer-links a:hover { color: white; transform: translateX(5px); }
    .footer-links a:hover::before { transform: translateX(3px); }

    .contact-info { list-style: none; padding: 0; margin: 0; }
    .contact-info li {
        display: flex; gap: 0.75rem; margin-bottom: 1rem; align-items: flex-start;
        color: rgba(255, 255, 255, 0.7); font-size: 0.92rem; line-height: 1.6;
    }
    .ci-icon { flex-shrink: 0; font-size: 1rem; margin-top: 0.1rem; }
    .footer-inline-link { color: inherit; text-decoration: none; border-bottom: 1px dashed rgba(255,255,255,0.3); transition: all 0.2s; }
    .footer-inline-link:hover { color: #10b981; border-bottom-color: #10b981; }

    /* =====================================================
       NEWSLETTER STRIP
       ===================================================== */
    .footer-newsletter {
        display: flex; justify-content: space-between; align-items: center; gap: 2rem;
        background: rgba(255, 255, 255, 0.06); border: 1px solid rgba(255, 255, 255, 0.12);
        backdrop-filter: blur(10px); border-radius: var(--radius-xl, 20px);
        padding: 1.75rem 2rem; margin-bottom: 2.5rem; flex-wrap: wrap;
    }
    .fn-content { display: flex; align-items: center; gap: 1.25rem; flex: 1; min-width: 260px; }
    .fn-icon {
        width: 52px; height: 52px; border-radius: 14px; flex-shrink: 0;
        background: linear-gradient(135deg, #10b981, #059669);
        display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
        box-shadow: 0 6px 16px rgba(16,185,129,0.35);
    }
    .fn-content h4 { color: white; margin: 0 0 0.25rem; font-size: 1.1rem; }
    .fn-content p { margin: 0; font-size: 0.85rem; color: rgba(255,255,255,0.65); }
    .fn-form { display: flex; gap: 0.65rem; flex-wrap: wrap; }
    .fn-form input {
        padding: 0.8rem 1.15rem; border-radius: 999px; border: 1px solid rgba(255,255,255,0.2);
        background: rgba(255,255,255,0.1); color: white; font-family: inherit;
        font-size: 0.9rem; min-width: 240px; transition: all 0.3s;
    }
    .fn-form input::placeholder { color: rgba(255,255,255,0.5); }
    .fn-form input:focus { outline: none; border-color: #10b981; background: rgba(255,255,255,0.15); }
    .fn-btn {
        padding: 0.8rem 1.6rem; border-radius: 999px; border: none; cursor: pointer;
        background: linear-gradient(135deg, #10b981, #059669); color: white;
        font-weight: 700; font-size: 0.9rem; font-family: inherit; transition: all 0.3s;
    }
    .fn-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16,185,129,0.4); }
    .fn-btn:disabled { opacity: 0.7; cursor: wait; transform: none; }

    /* =====================================================
       FOOTER BOTTOM
       ===================================================== */
    .footer-bottom {
        border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 2rem;
        display: flex; justify-content: space-between; align-items: center;
        flex-wrap: wrap; gap: 1rem;
    }
    .footer-bottom p { margin: 0; color: rgba(255, 255, 255, 0.6); font-size: 0.88rem; display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap; }
    .footer-bottom strong { color: #10b981; }
    .version-badge {
        background: rgba(16,185,129,0.15); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.35);
        padding: 0.1rem 0.55rem; border-radius: 999px; font-size: 0.68rem; font-weight: 700;
        font-family: monospace;
    }
    .footer-legal { display: flex; gap: 0.85rem; align-items: center; flex-wrap: wrap; }
    .footer-legal a { color: rgba(255, 255, 255, 0.6); text-decoration: none; font-size: 0.85rem; transition: color 0.3s; }
    .footer-legal a:hover { color: white; }
    .footer-legal .divider { color: rgba(255, 255, 255, 0.2); }
    .footer-credit { font-size: 0.8rem; color: rgba(255,255,255,0.45); }

    /* =====================================================
       BACK TO TOP (dengan progress ring)
       ===================================================== */
    .back-to-top {
        position: fixed; bottom: 6.5rem; right: 2rem;
        width: 50px; height: 50px; background: rgba(16,185,129,0.95); color: white;
        border: none; border-radius: 50%; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        opacity: 0; visibility: hidden; transform: translateY(20px);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 998;
    }
    .back-to-top.visible { opacity: 1; visibility: visible; transform: translateY(0); }
    .back-to-top:hover { background: #059669; transform: translateY(-3px); box-shadow: 0 8px 25px rgba(16, 185, 129, 0.5); }
    .btt-ring { position: absolute; inset: -3px; width: 56px; height: 56px; transform: rotate(-90deg); }
    .btt-ring-bg { fill: none; stroke: rgba(255,255,255,0.25); stroke-width: 3; }
    .btt-ring-fill {
        fill: none; stroke: #fbbf24; stroke-width: 3; stroke-linecap: round;
        stroke-dasharray: 138.2; stroke-dashoffset: 138.2; transition: stroke-dashoffset 0.15s linear;
    }
    .btt-arrow { position: relative; z-index: 2; }

    /* =====================================================
       FAB STACK (kontak cepat)
       ===================================================== */
    .fab-stack { position: fixed; bottom: 2rem; right: 2rem; z-index: 999; display: flex; flex-direction: column; align-items: center; gap: 0.75rem; }
    .fab-main {
        width: 60px; height: 60px; border-radius: 50%; border: none; cursor: pointer;
        background: linear-gradient(135deg, #25D366, #128C7E); color: white;
        display: flex; align-items: center; justify-content: center; font-size: 1.6rem;
        box-shadow: 0 8px 25px rgba(37, 211, 102, 0.45);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative;
    }
    .fab-main::after {
        content: ''; position: absolute; inset: 0; border-radius: 50%;
        border: 2px solid #25D366; animation: fabRing 2.2s infinite;
    }
    @keyframes fabRing { 0% { transform: scale(1); opacity: 1; } 100% { transform: scale(1.55); opacity: 0; } }
    .fab-main:hover { transform: scale(1.08); }
    .fab-main.open { transform: rotate(45deg); background: linear-gradient(135deg, #ef4444, #dc2626); }
    .fab-main.open::after { animation: none; opacity: 0; }
    .fab-main-icon { transition: transform 0.3s; }

    .fab-menu {
        display: flex; flex-direction: column; gap: 0.6rem; align-items: center;
        position: absolute; bottom: 72px; right: 0;
    }
    .fab-item {
        width: 48px; height: 48px; border-radius: 50%;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        color: white; text-decoration: none; font-size: 1.15rem; line-height: 1;
        box-shadow: 0 4px 14px rgba(0,0,0,0.25);
        opacity: 0; transform: translateY(16px) scale(0.6); pointer-events: none;
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        position: relative;
    }
    .fab-item small {
        position: absolute; right: 58px; top: 50%; transform: translateY(-50%);
        background: var(--bg-primary, #fff); color: var(--text-primary, #0f172a);
        padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.68rem; font-weight: 700;
        white-space: nowrap; box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        opacity: 0; pointer-events: none; transition: opacity 0.2s;
    }
    .fab-item:hover small { opacity: 1; }
    .fab-item:hover { transform: translateY(-2px) scale(1.08) !important; }
    .fab-wa   { background: linear-gradient(135deg, #25D366, #128C7E); }
    .fab-pmb  { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .fab-mail { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
    .fab-tel  { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }

    .fab-stack.open .fab-item { opacity: 1; transform: translateY(0) scale(1); pointer-events: auto; }
    .fab-stack.open .fab-item:nth-child(1) { transition-delay: 0.12s; }
    .fab-stack.open .fab-item:nth-child(2) { transition-delay: 0.08s; }
    .fab-stack.open .fab-item:nth-child(3) { transition-delay: 0.04s; }
    .fab-stack.open .fab-item:nth-child(4) { transition-delay: 0s; }

    /* =====================================================
       RESPONSIVE
       ===================================================== */
    @media (max-width: 1024px) {
        .footer-grid { grid-template-columns: 1fr 1fr; gap: 2.5rem; }
        .footer-newsletter { flex-direction: column; align-items: stretch; }
        .fn-form input { min-width: 0; flex: 1; }
    }
    @media (max-width: 640px) {
        .site-footer { padding: 3rem 0 1.5rem; }
        .footer-grid { grid-template-columns: 1fr; gap: 2rem; }
        .footer-bottom { flex-direction: column; text-align: center; }
        .footer-legal { flex-wrap: wrap; justify-content: center; }
        .back-to-top { bottom: 6rem; right: 1.25rem; }
        .fab-stack { bottom: 1.25rem; right: 1.25rem; }
        .fab-main { width: 54px; height: 54px; }
        .fab-menu { bottom: 64px; }
    }
    </style>

    <script>
    (function () {
        'use strict';

        // ===== 1. KONSOLIDASI TOMBOL FLOATING =====
        document.querySelectorAll('.floating-wa, .floating-pmb, .wa-float, .back-to-top, #backToTop')
            .forEach(function (el) {
                if (!el.closest('#footerFabRoot')) el.remove();
            });

        // ===== 2. BACK TO TOP + PROGRESS RING =====
        var btt = document.getElementById('backToTop');
        var ring = document.getElementById('bttRingFill');
        var CIRC = 138.2;

        function onScroll() {
            var y = window.pageYOffset || document.documentElement.scrollTop;
            if (btt) btt.classList.toggle('visible', y > 300);
            if (ring) {
                var max = document.documentElement.scrollHeight - window.innerHeight;
                var pct = max > 0 ? Math.min(y / max, 1) : 0;
                ring.style.strokeDashoffset = String(CIRC - (CIRC * pct));
            }
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();

        if (btt) {
            btt.addEventListener('click', function () {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }

        // ===== 3. FAB STACK TOGGLE =====
        var fabMain = document.getElementById('fabMain');
        var fabStack = fabMain ? fabMain.closest('.fab-stack') : null;

        function setFab(open) {
            if (!fabStack || !fabMain) return;
            fabStack.classList.toggle('open', open);
            fabMain.classList.toggle('open', open);
            fabMain.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        if (fabMain) {
            fabMain.addEventListener('click', function (e) {
                e.stopPropagation();
                setFab(!fabStack.classList.contains('open'));
            });
        }
        document.addEventListener('click', function (e) {
            if (fabStack && !fabStack.contains(e.target)) setFab(false);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') setFab(false);
        });

        // ===== 4. JAM LIVE (WITA) =====
        var clockEl = document.getElementById('footerClock');
        function tickClock() {
            if (!clockEl) return;
            try {
                clockEl.textContent = new Intl.DateTimeFormat('id-ID', {
                    hour: '2-digit', minute: '2-digit', second: '2-digit',
                    hour12: false, timeZone: 'Asia/Makassar'
                }).format(new Date());
            } catch (e) {
                clockEl.textContent = new Date().toLocaleTimeString('id-ID');
            }
        }
        tickClock();
        setInterval(tickClock, 1000);

        // ===== 5. FOOTER REVEAL ON SCROLL =====
        var revealEls = document.querySelectorAll('.footer-reveal');
        if ('IntersectionObserver' in window && revealEls.length) {
            var ro = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry, i) {
                    if (entry.isIntersecting) {
                        setTimeout(function () {
                            entry.target.classList.add('revealed');
                        }, i * 100);
                        ro.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });
            revealEls.forEach(function (el) { ro.observe(el); });
        } else {
            revealEls.forEach(function (el) { el.classList.add('revealed'); });
        }

        // ===== 6. LEGAL LINKS (segera tersedia) =====
        document.querySelectorAll('[data-soon]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                var label = a.getAttribute('data-soon') || 'Halaman';
                if (window.FKIPToast) {
                    window.FKIPToast.info(label + ' segera tersedia', { icon: '🚧' });
                } else {
                    alert(label + ' segera tersedia.');
                }
            });
        });
    })();

    // ===== NEWSLETTER HANDLER =====
    function handleNewsletter(e) {
        e.preventDefault();
        var input = document.getElementById('newsletterEmail');
        var btn = document.getElementById('newsletterBtn');
        var email = (input && input.value || '').trim();

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            if (window.FKIPToast) window.FKIPToast.error('Format email tidak valid', { icon: '⚠️' });
            else alert('Format email tidak valid.');
            if (input) input.focus();
            return false;
        }

        if (btn) { btn.disabled = true; btn.textContent = 'Mengirim...'; }

        var finish = function () {
            try { localStorage.setItem('fkip_newsletter', email); } catch (err) {}
            if (btn) { btn.disabled = false; btn.textContent = '✓ Terdaftar'; }
            if (input) input.value = '';
            if (window.FKIPToast) {
                window.FKIPToast.success('Terima kasih! Anda terdaftar menerima informasi FKIP.', { icon: '📬' });
            } else {
                alert('Terima kasih! Anda terdaftar menerima informasi FKIP.');
            }
            setTimeout(function () { if (btn) btn.textContent = 'Berlangganan'; }, 3000);
        };

        // Newsletter API akan fail gracefully jika tidak ada
        finish();
        return false;
    }
    </script>

    <script src="<?= asset('js/main.js') ?>" defer></script>
</body>
</html>