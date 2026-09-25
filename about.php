<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Tentang Kami';
$page_description = 'Profil, Visi, Misi, Sejarah, dan Struktur Organisasi FKIP UNIMOF';
require_once __DIR__ . '/includes/header.php';
?>

<style>
.about-hero { background: linear-gradient(135deg, #0a6847 0%, #16213e 100%); color: white; padding: 8rem 0 5rem; position: relative; overflow: hidden; }
.about-hero::before { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at 80% 20%, rgba(245,166,35,0.2) 0%, transparent 50%); }
.about-hero .container { position: relative; z-index: 2; }

.about-tabs { display: flex; gap: 0.5rem; margin-bottom: 2rem; border-bottom: 2px solid var(--border); overflow-x: auto; padding-bottom: 2px; }
.about-tab { padding: 1rem 1.5rem; background: none; border: none; font-family: inherit; font-size: 1rem; font-weight: 600; color: var(--text-muted); cursor: pointer; position: relative; transition: all 0.3s; white-space: nowrap; }
.about-tab:hover { color: var(--primary); }
.about-tab.active { color: var(--primary); }
.about-tab.active::after { content: ''; position: absolute; bottom: -2px; left: 0; right: 0; height: 3px; background: var(--primary); border-radius: 3px 3px 0 0; }

.tab-panel { display: none; animation: fadeIn 0.4s ease; }
.tab-panel.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }

.vision-mission-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 3rem; }
.vm-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2.5rem; position: relative; overflow: hidden; }
.vm-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--primary); }
.vm-card h3 { font-family: var(--font-display); font-size: 1.5rem; margin-bottom: 1rem; color: var(--primary); display: flex; align-items: center; gap: 0.5rem; }
.vm-card p, .vm-card li { color: var(--text-secondary); line-height: 1.8; font-size: 1.05rem; }
.vm-card ul { padding-left: 1.5rem; }

.history-timeline { position: relative; padding-left: 2rem; border-left: 3px solid var(--border); margin-left: 1rem; }
.timeline-item { position: relative; margin-bottom: 2.5rem; }
.timeline-item::before { content: ''; position: absolute; left: -2.6rem; top: 0.5rem; width: 16px; height: 16px; background: var(--primary); border-radius: 50%; border: 3px solid var(--bg-primary); box-shadow: 0 0 0 3px rgba(10,104,71,0.2); }
.timeline-year { font-family: var(--font-display); font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-bottom: 0.5rem; }
.timeline-content { background: var(--bg-secondary); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border); }

.org-chart { text-align: center; padding: 2rem; background: var(--bg-secondary); border-radius: var(--radius-xl); border: 1px dashed var(--border); }
.org-level { display: flex; justify-content: center; gap: 2rem; margin-bottom: 2rem; flex-wrap: wrap; }
.org-box { background: var(--bg-primary); border: 2px solid var(--primary); padding: 1rem 2rem; border-radius: var(--radius-md); font-weight: 700; color: var(--primary); box-shadow: var(--shadow-sm); }
.org-line { width: 2px; height: 40px; background: var(--border); margin: 0 auto; }

@media (max-width: 768px) {
    .vision-mission-grid { grid-template-columns: 1fr; }
    .about-hero { padding: 6rem 0 3rem; }
}
</style>

<section class="about-hero">
    <div class="container">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem;">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Tentang Kami</span>
        </nav>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 5vw, 4rem); font-weight: 900; margin-bottom: 1rem;">Tentang FKIP UNIMOF</h1>
        <p class="page-subtitle" style="font-size: 1.2rem; opacity: 0.9; max-width: 700px;">Membangun peradaban melalui pendidikan yang berkualitas, berkarakter Islami, dan berdaya saing global di Indonesia Timur.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="about-tabs" data-aos="fade-up">
            <button class="about-tab active" onclick="switchAboutTab(event, 'tab-vm')">🎯 Visi & Misi</button>
            <button class="about-tab" onclick="switchAboutTab(event, 'tab-sejarah')">📜 Sejarah</button>
            <button class="about-tab" onclick="switchAboutTab(event, 'tab-struktur')">🏛️ Struktur Organisasi</button>
        </div>

        <!-- Tab: Visi & Misi -->
        <div id="tab-vm" class="tab-panel active" data-aos="fade-up">
            <div class="vision-mission-grid">
                <div class="vm-card">
                    <h3>👁️ Visi</h3>
                    <p>"Menjadi Fakultas Keguruan dan Ilmu Pendidikan yang unggul, inovatif, dan berkarakter Islami dalam menghasilkan pendidik profesional berdaya saing global pada tahun 2030."</p>
                </div>
                <div class="vm-card">
                    <h3>🚀 Misi</h3>
                    <ul>
                        <li>Menyelenggarakan pendidikan tinggi yang berkualitas dan relevan dengan perkembangan ilmu pengetahuan.</li>
                        <li>Melaksanakan penelitian dan pengabdian kepada masyarakat yang berdampak positif.</li>
                        <li>Menanamkan nilai-nilai keislaman, kemuhammadiyahan, dan kearifan lokal dalam setiap aktivitas akademik.</li>
                        <li>Membangun jejaring kerjasama dengan institusi pendidikan dalam dan luar negeri.</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Tab: Sejarah -->
        <div id="tab-sejarah" class="tab-panel" data-aos="fade-up">
            <div class="history-timeline">
                <div class="timeline-item">
                    <div class="timeline-year">2000</div>
                    <div class="timeline-content">
                        <h4>Pendirian Awal</h4>
                        <p>Fakultas Keguruan dan Ilmu Pendidikan resmi didirikan sebagai bagian dari Universitas Muhammadiyah Maumere, dengan 3 program studi perdana.</p>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-year">2010</div>
                    <div class="timeline-content">
                        <h4>Ekspansi Program Studi</h4>
                        <p>Penambahan program studi baru untuk menjawab kebutuhan tenaga pendidik di wilayah Nusa Tenggara Timur, mencapai total 8 program studi.</p>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-year">2020 - Sekarang</div>
                    <div class="timeline-content">
                        <h4>Era Digital & Akreditasi Unggul</h4>
                        <p>Transformasi digital dalam pembelajaran dan keberhasilan meraih akreditasi "Unggul" untuk beberapa program studi unggulan dari BAN-PT.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: Struktur -->
        <div id="tab-struktur" class="tab-panel" data-aos="fade-up">
            <div class="org-chart">
                <div class="org-level">
                    <div class="org-box">Dekan FKIP UNIMOF</div>
                </div>
                <div class="org-line"></div>
                <div class="org-level">
                    <div class="org-box">Kaprodi PMAT</div>
                    <div class="org-box">Kaprodi PFIS</div>
                    <div class="org-box">Kaprodi PBIO</div>
                    <div class="org-box">Kaprodi PKIM</div>
                </div>
                <div class="org-level" style="margin-top: 1rem;">
                    <div class="org-box">Kaprodi PBSI</div>
                    <div class="org-box">Kaprodi BSIND</div>
                    <div class="org-box">Kaprodi PEKO</div>
                    <div class="org-box">Kaprodi PKN</div>
                </div>
                <p style="margin-top: 2rem; color: var(--text-muted); font-size: 0.9rem;">*Struktur organisasi disederhanakan untuk tampilan web. Untuk struktur lengkap, silakan unduh dokumen resmi.</p>
                <a href="#" class="btn btn-secondary" style="margin-top: 1rem;">📥 Unduh SK Struktur Organisasi</a>
            </div>
        </div>
    </div>
</section>

<script>
function switchAboutTab(event, tabId) {
    document.querySelectorAll('.about-tab').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));
    event.currentTarget.classList.add('active');
    document.getElementById(tabId).classList.add('active');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>