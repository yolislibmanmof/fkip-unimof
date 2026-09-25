<?php
// includes/footer.php - Premium Footer (Final Fixed Version)
?>
    </main>

    <footer class="site-footer" style="
        background: linear-gradient(180deg, #0f172a 0%, #064e34 100%);
        color: rgba(255, 255, 255, 0.8);
        padding: 4rem 0 2rem;
        position: relative;
        margin-top: 4rem;
    ">
        <!-- Wave Decoration -->
        <div style="position: absolute; top: -1px; left: 0; width: 100%; overflow: hidden; line-height: 0;">
            <svg style="display: block; width: 100%; height: 60px;" viewBox="0 0 1440 120" preserveAspectRatio="none">
                <path d="M0,64L80,69.3C160,75,320,85,480,80C640,75,800,53,960,48C1120,43,1280,53,1360,58.7L1440,64L1440,0L1360,0C1280,0,1120,0,960,0C800,0,640,0,480,0C320,0,160,0,80,0L0,0Z" fill="#0f172a"></path>
            </svg>
        </div>

        <div class="container" style="position: relative; z-index: 2;">
            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1.5fr; gap: 3rem; margin-bottom: 3rem;">
                
                <!-- Column 1: Brand -->
                <div>
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                        <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-family: serif; font-size: 1.5rem; font-weight: 900;">F</div>
                        <div>
                            <h3 style="color: white; margin: 0; font-size: 1.5rem;">FKIP UNIMOF</h3>
                            <p style="color: #10b981; margin: 0; font-size: 0.85rem; font-weight: 600;">Mencerdaskan Bangsa</p>
                        </div>
                    </div>
                    <p style="color: rgba(255,255,255,0.7); line-height: 1.7; margin-bottom: 1.5rem;">
                        Fakultas Keguruan dan Ilmu Pendidikan Universitas Muhammadiyah Maumere. Mencetak pendidik profesional berkarakter Islami.
                    </p>
                    <div style="display: flex; gap: 0.75rem;">
                        <a href="#" style="width: 40px; height: 40px; background: rgba(255,255,255,0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 1.2rem; transition: all 0.3s;" onmouseover="this.style.background='#10b981'; this.style.transform='translateY(-3px)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.transform='translateY(0)'">📘</a>
                        <a href="#" style="width: 40px; height: 40px; background: rgba(255,255,255,0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 1.2rem; transition: all 0.3s;" onmouseover="this.style.background='#10b981'; this.style.transform='translateY(-3px)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.transform='translateY(0)'">📷</a>
                        <a href="#" style="width: 40px; height: 40px; background: rgba(255,255,255,0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 1.2rem; transition: all 0.3s;" onmouseover="this.style.background='#10b981'; this.style.transform='translateY(-3px)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.transform='translateY(0)'">▶️</a>
                    </div>
                </div>

                <!-- Column 2: Quick Links -->
                <div>
                    <h4 style="color: white; margin-bottom: 1.5rem; font-size: 1.1rem; position: relative; padding-bottom: 0.75rem;">
                        Tautan Cepat
                        <span style="position: absolute; bottom: 0; left: 0; width: 40px; height: 3px; background: #10b981; border-radius: 3px;"></span>
                    </h4>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <li style="margin-bottom: 0.75rem;"><a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.7); text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.7)'">› Beranda</a></li>
                        <li style="margin-bottom: 0.75rem;"><a href="<?= base_url('about.php') ?>" style="color: rgba(255,255,255,0.7); text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.7)'">› Tentang Kami</a></li>
                        <li style="margin-bottom: 0.75rem;"><a href="<?= base_url('program.php') ?>" style="color: rgba(255,255,255,0.7); text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.7)'">› Program Studi</a></li>
                        <li style="margin-bottom: 0.75rem;"><a href="<?= base_url('dosen.php') ?>" style="color: rgba(255,255,255,0.7); text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.7)'">› Dosen</a></li>
                        <li style="margin-bottom: 0.75rem;"><a href="<?= base_url('kontak.php') ?>" style="color: rgba(255,255,255,0.7); text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.7)'">› Kontak</a></li>
                    </ul>
                </div>

                <!-- Column 3: Programs (LENGKAP 8 PRODI) -->
                <div>
                    <h4 style="color: white; margin-bottom: 1.5rem; font-size: 1.1rem; position: relative; padding-bottom: 0.75rem;">
                        Program Studi
                        <span style="position: absolute; bottom: 0; left: 0; width: 40px; height: 3px; background: #10b981; border-radius: 3px;"></span>
                    </h4>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <li style="margin-bottom: 0.75rem;"><a href="<?= base_url('program-detail.php?id=1') ?>" style="color: rgba(255,255,255,0.7); text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.7)'">› Pendidikan Matematika</a></li>
                        <li style="margin-bottom: 0.75rem;"><a href="<?= base_url('program-detail.php?id=2') ?>" style="color: rgba(255,255,255,0.7); text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.7)'">› Pendidikan Fisika</a></li>
                        <li style="margin-bottom: 0.75rem;"><a href="<?= base_url('program-detail.php?id=3') ?>" style="color: rgba(255,255,255,0.7); text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.7)'">› Pendidikan Biologi</a></li>
                        <li style="margin-bottom: 0.75rem;"><a href="<?= base_url('program-detail.php?id=4') ?>" style="color: rgba(255,255,255,0.7); text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.7)'">› Pendidikan Kimia</a></li>
                        <li style="margin-bottom: 0.75rem;"><a href="<?= base_url('program-detail.php?id=5') ?>" style="color: rgba(255,255,255,0.7); text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.7)'">› Pendidikan B. Inggris</a></li>
                        <li style="margin-bottom: 0.75rem;"><a href="<?= base_url('program-detail.php?id=6') ?>" style="color: rgba(255,255,255,0.7); text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.7)'">› Pendidikan B. Indonesia</a></li>
                        <li style="margin-bottom: 0.75rem;"><a href="<?= base_url('program-detail.php?id=7') ?>" style="color: rgba(255,255,255,0.7); text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.7)'">› Pendidikan Ekonomi</a></li>
                        <li style="margin-bottom: 0.75rem;"><a href="<?= base_url('program-detail.php?id=8') ?>" style="color: rgba(255,255,255,0.7); text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.7)'">› Pendidikan Kewarganegaraan</a></li>
                    </ul>
                </div>

                <!-- Column 4: Contact -->
                <div>
                    <h4 style="color: white; margin-bottom: 1.5rem; font-size: 1.1rem; position: relative; padding-bottom: 0.75rem;">
                        Kontak
                        <span style="position: absolute; bottom: 0; left: 0; width: 40px; height: 3px; background: #10b981; border-radius: 3px;"></span>
                    </h4>
                    <div style="margin-bottom: 1rem; display: flex; gap: 0.75rem; align-items: flex-start;">
                        <span style="font-size: 1.2rem;">📍</span>
                        <span style="color: rgba(255,255,255,0.7);"><?= sanitize(get_setting('alamat', 'Jl. Bhayangkara No.1, Maumere, NTT')) ?></span>
                    </div>
                    <div style="margin-bottom: 1rem; display: flex; gap: 0.75rem; align-items: center;">
                        <span style="font-size: 1.2rem;">📞</span>
                        <span style="color: rgba(255,255,255,0.7);"><?= sanitize(get_setting('telepon', '(0382) 21234')) ?></span>
                    </div>
                    <div style="margin-bottom: 1rem; display: flex; gap: 0.75rem; align-items: center;">
                        <span style="font-size: 1.2rem;">✉️</span>
                        <span style="color: rgba(255,255,255,0.7);"><?= sanitize(get_setting('email', 'fkip@unimof.ac.id')) ?></span>
                    </div>
                    <div style="display: flex; gap: 0.75rem; align-items: center;">
                        <span style="font-size: 1.2rem;">🕐</span>
                        <span style="color: rgba(255,255,255,0.7);">Senin - Jumat, 08:00 - 16:00 WITA</span>
                    </div>
                </div>
            </div>

            <!-- Footer Bottom -->
            <div style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <p style="margin: 0; color: rgba(255,255,255,0.5); font-size: 0.9rem;">
                    &copy; <?= date('Y') ?> <strong style="color: #10b981;">FKIP UNIMOF</strong>. All rights reserved.
                </p>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <a href="#" style="color: rgba(255,255,255,0.5); text-decoration: none; font-size: 0.85rem;" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.5)'">Privacy Policy</a>
                    <span style="color: rgba(255,255,255,0.2);">•</span>
                    <a href="#" style="color: rgba(255,255,255,0.5); text-decoration: none; font-size: 0.85rem;" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.5)'">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Back to Top Button -->
    <button id="backToTop" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" style="
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
        font-size: 1.5rem;
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        opacity: 0;
        visibility: hidden;
        transform: translateY(20px);
        transition: all 0.3s;
        z-index: 999;
    ">↑</button>

    <!-- WhatsApp Float Button (PERBAIKAN: SVG Icon yang Jelas) -->
    <a href="https://wa.me/6281234567890?text=Halo%20Admin%20FKIP%20UNIMOF" target="_blank" rel="noopener" aria-label="Chat WhatsApp" style="
        position: fixed;
        bottom: 2rem;
        left: 2rem;
        width: 56px;
        height: 56px;
        background: #25D366;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4);
        text-decoration: none;
        z-index: 999;
        animation: waFloat 3s ease-in-out infinite;
        transition: all 0.3s;
    " onmouseover="this.style.transform='scale(1.1)'; this.style.boxShadow='0 8px 25px rgba(37, 211, 102, 0.6)'" onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 4px 15px rgba(37, 211, 102, 0.4)'">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="white" style="display: block;">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
        </svg>
    </a>

    <style>
    @keyframes waFloat {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-8px); }
    }
    @media (max-width: 1024px) {
        .site-footer > .container > div:first-child {
            grid-template-columns: 1fr 1fr !important;
        }
    }
    @media (max-width: 640px) {
        .site-footer > .container > div:first-child {
            grid-template-columns: 1fr !important;
        }
    }
    </style>

    <script>
    // Show/hide back to top button
    window.addEventListener('scroll', function() {
        const btn = document.getElementById('backToTop');
        if (window.pageYOffset > 300) {
            btn.style.opacity = '1';
            btn.style.visibility = 'visible';
            btn.style.transform = 'translateY(0)';
        } else {
            btn.style.opacity = '0';
            btn.style.visibility = 'hidden';
            btn.style.transform = 'translateY(20px)';
        }
    });
    </script>

    <script src="<?= asset('js/main.js') ?>" defer></script>
</body>
</html>