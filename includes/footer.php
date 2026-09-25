<?php
// includes/footer.php - Premium Footer (Polished Version)
?>
    </main>

    <footer class="site-footer">
        <!-- Wave Decoration -->
        <div class="footer-wave">
            <svg viewBox="0 0 1440 120" preserveAspectRatio="none">
                <path d="M0,64L80,69.3C160,75,320,85,480,80C640,75,800,53,960,48C1120,43,1280,53,1360,58.7L1440,64L1440,0L1360,0C1280,0,1120,0,960,0C800,0,640,0,480,0C320,0,160,0,80,0L0,0Z"></path>
            </svg>
        </div>

        <div class="container">
            <div class="footer-grid">
                <!-- Column 1: Brand -->
                <div class="footer-brand">
                    <div class="brand-header">
                        <div class="brand-logo">F</div>
                        <div>
                            <h3>FKIP UNIMOF</h3>
                            <p class="brand-tagline">Mencerdaskan Bangsa</p>
                        </div>
                    </div>
                    <p class="brand-desc">
                        Fakultas Keguruan dan Ilmu Pendidikan Universitas Muhammadiyah Maumere. Mencetak pendidik profesional berkarakter Islami.
                    </p>
                    <div class="social-links">
                        <a href="#" class="social-icon" aria-label="Facebook">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        </a>
                        <a href="#" class="social-icon" aria-label="Instagram">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                        </a>
                        <a href="#" class="social-icon" aria-label="YouTube">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                        </a>
                    </div>
                </div>

                <!-- Column 2: Quick Links -->
                <div class="footer-col">
                    <h4>Tautan Cepat</h4>
                    <ul class="footer-links">
                        <li><a href="<?= base_url() ?>">Beranda</a></li>
                        <li><a href="<?= base_url('about.php') ?>">Tentang Kami</a></li>
                        <li><a href="<?= base_url('program.php') ?>">Program Studi</a></li>
                        <li><a href="<?= base_url('dosen.php') ?>">Dosen</a></li>
                        <li><a href="<?= base_url('kontak.php') ?>">Kontak</a></li>
                    </ul>
                </div>

                <!-- Column 3: Programs -->
                <div class="footer-col">
                    <h4>Program Studi</h4>
                    <ul class="footer-links">
                        <li><a href="<?= base_url('program-detail.php?id=1') ?>">Pendidikan Matematika</a></li>
                        <li><a href="<?= base_url('program-detail.php?id=2') ?>">Pendidikan Fisika</a></li>
                        <li><a href="<?= base_url('program-detail.php?id=3') ?>">Pendidikan Biologi</a></li>
                        <li><a href="<?= base_url('program-detail.php?id=4') ?>">Pendidikan Kimia</a></li>
                        <li><a href="<?= base_url('program-detail.php?id=5') ?>">Pendidikan B. Inggris</a></li>
                        <li><a href="<?= base_url('program-detail.php?id=6') ?>">Pendidikan B. Indonesia</a></li>
                        <li><a href="<?= base_url('program-detail.php?id=7') ?>">Pendidikan Ekonomi</a></li>
                        <li><a href="<?= base_url('program-detail.php?id=8') ?>">Pendidikan Kewarganegaraan</a></li>
                    </ul>
                </div>

                <!-- Column 4: Contact -->
                <div class="footer-col">
                    <h4>Kontak</h4>
                    <ul class="contact-info">
                        <li>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                            <span><?= sanitize(get_setting('alamat', 'Jl. Bhayangkara No.1, Maumere, NTT')) ?></span>
                        </li>
                        <li>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
                            <span><?= sanitize(get_setting('telepon', '(0382) 21234')) ?></span>
                        </li>
                        <li>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                            <span><?= sanitize(get_setting('email', 'fkip@unimof.ac.id')) ?></span>
                        </li>
                        <li>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                            <span>Senin - Jumat, 08:00 - 16:00 WITA</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Footer Bottom -->
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> <strong>FKIP UNIMOF</strong>. All rights reserved.</p>
                <div class="footer-legal">
                    <a href="#">Privacy Policy</a>
                    <span class="divider">•</span>
                    <a href="#">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Back to Top Button -->
    <button id="backToTop" class="back-to-top" aria-label="Kembali ke atas">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 15l-6-6-6 6"/>
        </svg>
    </button>

    <!-- WhatsApp Float Button -->
    <a href="https://wa.me/6281234567890?text=Halo%20Admin%20FKIP%20UNIMOF" target="_blank" rel="noopener" class="wa-float" aria-label="Chat WhatsApp">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="white">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
        </svg>
    </a>

    <style>
    .site-footer {
        background: linear-gradient(180deg, #0f172a 0%, #064e34 100%);
        color: rgba(255, 255, 255, 0.8);
        padding: 5rem 0 2rem;
        position: relative;
        margin-top: 4rem;
    }
    
    .footer-wave {
        position: absolute;
        top: -1px;
        left: 0;
        width: 100%;
        overflow: hidden;
        line-height: 0;
    }
    
    .footer-wave svg {
        display: block;
        width: 100%;
        height: 80px;
    }
    
    .footer-wave svg path {
        fill: var(--bg-primary, #ffffff);
    }
    
    .footer-grid {
        display: grid;
        grid-template-columns: 2fr 1fr 1.2fr 1.5fr;
        gap: 3rem;
        margin-bottom: 3rem;
    }
    
    .brand-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .brand-logo {
        width: 56px;
        height: 56px;
        background: linear-gradient(135deg, #10b981, #059669);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-family: serif;
        font-size: 1.75rem;
        font-weight: 900;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    
    .brand-header h3 {
        color: white;
        margin: 0;
        font-size: 1.5rem;
        font-weight: 800;
        line-height: 1.2;
    }
    
    .brand-tagline {
        color: #10b981;
        margin: 0;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .brand-desc {
        color: rgba(255, 255, 255, 0.7);
        line-height: 1.7;
        margin-bottom: 1.5rem;
        font-size: 0.95rem;
    }
    
    .social-links {
        display: flex;
        gap: 0.75rem;
    }
    
    .social-icon {
        width: 42px;
        height: 42px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .social-icon:hover {
        background: #10b981;
        transform: translateY(-4px);
        box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        border-color: #10b981;
    }
    
    .footer-col h4 {
        color: white;
        margin-bottom: 1.5rem;
        font-size: 1.15rem;
        font-weight: 700;
        position: relative;
        padding-bottom: 0.75rem;
    }
    
    .footer-col h4::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 40px;
        height: 3px;
        background: #10b981;
        border-radius: 3px;
    }
    
    .footer-links {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .footer-links li {
        margin-bottom: 0.75rem;
    }
    
    .footer-links a {
        color: rgba(255, 255, 255, 0.7);
        text-decoration: none;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.95rem;
    }
    
    .footer-links a::before {
        content: '›';
        color: #10b981;
        font-weight: 700;
        transition: transform 0.3s;
    }
    
    .footer-links a:hover {
        color: white;
        transform: translateX(5px);
    }
    
    .footer-links a:hover::before {
        transform: translateX(3px);
    }
    
    .contact-info {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .contact-info li {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 1rem;
        align-items: flex-start;
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.95rem;
        line-height: 1.6;
    }
    
    .contact-info svg {
        flex-shrink: 0;
        color: #10b981;
        margin-top: 0.1rem;
    }
    
    .footer-bottom {
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        padding-top: 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .footer-bottom p {
        margin: 0;
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.9rem;
    }
    
    .footer-bottom strong {
        color: #10b981;
    }
    
    .footer-legal {
        display: flex;
        gap: 1rem;
        align-items: center;
    }
    
    .footer-legal a {
        color: rgba(255, 255, 255, 0.6);
        text-decoration: none;
        font-size: 0.85rem;
        transition: color 0.3s;
    }
    
    .footer-legal a:hover {
        color: white;
    }
    
    .footer-legal .divider {
        color: rgba(255, 255, 255, 0.2);
    }
    
    .back-to-top {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        width: 50px;
        height: 50px;
        background: #10b981;
        color: white;
        border: none;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        opacity: 0;
        visibility: hidden;
        transform: translateY(20px);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 999;
    }
    
    .back-to-top.visible {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    
    .back-to-top:hover {
        background: #059669;
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(16, 185, 129, 0.5);
    }
    
    .wa-float {
        position: fixed;
        bottom: 2rem;
        left: 2rem;
        width: 60px;
        height: 60px;
        background: #25D366;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 20px rgba(37, 211, 102, 0.5);
        text-decoration: none;
        z-index: 999;
        animation: waFloat 3s ease-in-out infinite;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .wa-float:hover {
        background: #128C7E;
        transform: scale(1.1);
        box-shadow: 0 8px 30px rgba(37, 211, 102, 0.6);
    }
    
    @keyframes waFloat {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-8px); }
    }
    
    @media (max-width: 1024px) {
        .footer-grid {
            grid-template-columns: 1fr 1fr;
            gap: 2.5rem;
        }
    }
    
    @media (max-width: 640px) {
        .site-footer {
            padding: 3rem 0 1.5rem;
        }
        
        .footer-grid {
            grid-template-columns: 1fr;
            gap: 2rem;
        }
        
        .footer-bottom {
            flex-direction: column;
            text-align: center;
        }
        
        .footer-legal {
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .back-to-top {
            bottom: 6rem;
            right: 1.5rem;
        }
        
        .wa-float {
            left: 1.5rem;
            bottom: 1.5rem;
            width: 56px;
            height: 56px;
        }
    }
    </style>

    <script>
    // Back to Top functionality
    const backToTopBtn = document.getElementById('backToTop');
    if (backToTopBtn) {
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                backToTopBtn.classList.add('visible');
            } else {
                backToTopBtn.classList.remove('visible');
            }
        });
        backToTopBtn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
    </script>

    <script src="<?= asset('js/main.js') ?>" defer></script>
</body>
</html>