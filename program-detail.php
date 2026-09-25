<?php
// program-detail.php
require_once __DIR__ . '/includes/config.php';

$id = (int)($_GET['id'] ?? 0);
if ($id < 1) {
    header('Location: ' . base_url('program.php'));
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM program_studi WHERE id = ? AND status = 'Aktif'");
    $stmt->execute([$id]);
    $prodi = $stmt->fetch();
    
    if (!$prodi) {
        header('Location: ' . base_url('program.php'));
        exit;
    }
    
    // Ambil dosen di prodi ini
    $stmtDosen = $pdo->prepare("SELECT * FROM dosen WHERE program_studi_id = ? AND status = 'Aktif' LIMIT 6");
    $stmtDosen->execute([$id]);
    $dosen = $stmtDosen->fetchAll();
    
    // Ambil prestasi
    $stmtPrestasi = $pdo->prepare("SELECT * FROM prestasi WHERE program_studi_id = ? ORDER BY tahun DESC, tingkat DESC LIMIT 5");
    $stmtPrestasi->execute([$id]);
    $prestasi = $stmtPrestasi->fetchAll();
    
} catch (Exception $e) {
    error_log($e->getMessage());
    die('Terjadi kesalahan. Silakan coba lagi.');
}

$page_title = sanitize($prodi['nama']);
$page_description = "Program Studi " . $prodi['nama'] . " - " . $prodi['jenjang'] . " - FKIP UNIMOF";

// Warna tema dinamis berdasarkan ID prodi untuk variasi visual
$theme_colors = [
    1 => ['#3b82f6', '#1d4ed8'], // Matematika
    2 => ['#8b5cf6', '#6d28d9'], // Fisika
    3 => ['#10b981', '#059669'], // Biologi
    4 => ['#f59e0b', '#d97706'], // Kimia
    5 => ['#ec4899', '#db2777'], // Inggris
    6 => ['#ef4444', '#dc2626'], // Indonesia
    7 => ['#14b8a6', '#0d9488'], // Ekonomi
    8 => ['#f97316', '#ea580c'], // PKN
];
$colors = $theme_colors[$prodi['id'] % 9] ?? ['#0a6847', '#084d35'];

require_once __DIR__ . '/includes/header.php';
?>

<!-- ===== SCOPED STYLES ===== -->
<style>
/* Hero Enhancement */
.detail-hero {
    position: relative; overflow: hidden;
    background: linear-gradient(135deg, <?= $colors[0] ?> 0%, <?= $colors[1] ?> 100%);
    color: white; padding: 8rem 0 5rem;
}
.detail-hero::before {
    content: ''; position: absolute; inset: 0;
    background-image: radial-gradient(circle at 20% 30%, rgba(255,255,255,0.15) 0%, transparent 40%),
                      radial-gradient(circle at 80% 70%, rgba(255,255,255,0.1) 0%, transparent 40%);
    pointer-events: none;
}
.detail-hero::after {
    content: ''; position: absolute; inset: 0;
    background-image: linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                      linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
}
.detail-hero .container { position: relative; z-index: 2; }
.detail-hero .breadcrumb a, .detail-hero .breadcrumb span { color: rgba(255,255,255,0.8); }
.detail-hero .breadcrumb a:hover { color: white; }
.detail-hero .page-title {
    font-family: var(--font-display); font-size: clamp(2.5rem, 5vw, 4rem);
    font-weight: 900; margin-bottom: 1rem; letter-spacing: -0.02em;
}
.detail-hero .page-subtitle {
    font-size: 1.2rem; opacity: 0.95; max-width: 700px; line-height: 1.7;
}
.page-badge {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2); padding: 0.5rem 1rem;
    border-radius: 999px; font-size: 0.85rem; font-weight: 700;
    margin-bottom: 1.5rem; text-transform: uppercase; letter-spacing: 0.05em;
}

/* Layout */
.detail-layout {
    display: grid; grid-template-columns: 1fr 360px; gap: 3rem;
    margin-top: -3rem; position: relative; z-index: 10;
}

/* Tabs */
.detail-tabs {
    background: var(--bg-primary); border-radius: var(--radius-xl);
    border: 1px solid var(--border); overflow: hidden;
    box-shadow: var(--shadow-lg);
}
.tab-nav {
    display: flex; border-bottom: 1px solid var(--border);
    background: var(--bg-secondary); overflow-x: auto;
    scrollbar-width: none;
}
.tab-nav::-webkit-scrollbar { display: none; }
.tab-btn {
    flex: 1; padding: 1.25rem 1.5rem; background: none; border: none;
    font-family: inherit; font-size: 0.95rem; font-weight: 600;
    color: var(--text-muted); cursor: pointer; position: relative;
    transition: all 0.3s; white-space: nowrap;
}
.tab-btn:hover { color: var(--text-primary); background: rgba(0,0,0,0.02); }
.tab-btn.active {
    color: <?= $colors[0] ?>; background: var(--bg-primary);
}
.tab-btn.active::after {
    content: ''; position: absolute; bottom: 0; left: 0; right: 0;
    height: 3px; background: <?= $colors[0] ?>; border-radius: 3px 3px 0 0;
}
.tab-content { padding: 2rem; display: none; animation: fadeIn 0.4s ease; }
.tab-content.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }

.tab-content h3 {
    font-family: var(--font-display); font-size: 1.5rem; color: var(--text-primary);
    margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;
}
.tab-content p, .tab-content li {
    color: var(--text-secondary); line-height: 1.8; font-size: 1.05rem; margin-bottom: 1rem;
}
.tab-content ul { padding-left: 1.5rem; }
.tab-content li { margin-bottom: 0.5rem; position: relative; }
.tab-content li::marker { color: <?= $colors[0] ?>; }

/* Dosen Grid */
.dosen-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1.25rem;
}
.dosen-card {
    display: flex; gap: 1rem; padding: 1.25rem;
    background: var(--bg-secondary); border-radius: var(--radius-lg);
    border: 1px solid var(--border); transition: all 0.3s;
}
.dosen-card:hover {
    transform: translateY(-4px); box-shadow: var(--shadow-md);
    border-color: <?= $colors[0] ?>; background: var(--bg-primary);
}
.dosen-avatar {
    width: 56px; height: 56px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, <?= $colors[0] ?>, <?= $colors[1] ?>);
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 1.25rem; font-weight: 800; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.dosen-info h4 { font-size: 0.95rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem; }
.dosen-info p { font-size: 0.8rem; color: var(--text-muted); margin: 0; line-height: 1.4; }
.dosen-info .dosen-edu { font-size: 0.75rem; color: <?= $colors[0] ?>; font-weight: 600; margin-top: 0.25rem; }

/* Sidebar Sticky */
.detail-sidebar { position: sticky; top: 100px; height: fit-content; }
.sidebar-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.75rem; margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm); transition: all 0.3s;
}
.sidebar-card:hover { box-shadow: var(--shadow-md); }
.sidebar-card h3 {
    font-family: var(--font-display); font-size: 1.15rem; margin-bottom: 1.25rem;
    padding-bottom: 0.75rem; border-bottom: 2px solid var(--bg-tertiary);
    display: flex; align-items: center; gap: 0.5rem;
}
.info-list { list-style: none; padding: 0; margin: 0; }
.info-list li {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0.85rem 0; border-bottom: 1px dashed var(--border);
    font-size: 0.9rem;
}
.info-list li:last-child { border-bottom: none; }
.info-list li span:first-child { color: var(--text-muted); }
.info-list li strong { color: var(--text-primary); text-align: right; }

/* Prestasi List */
.prestasi-list { display: flex; flex-direction: column; gap: 1rem; }
.prestasi-item {
    display: flex; gap: 1rem; align-items: flex-start;
    padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md);
    border-left: 3px solid var(--secondary); transition: all 0.3s;
}
.prestasi-item:hover { background: var(--bg-tertiary); transform: translateX(4px); }
.prestasi-medal {
    width: 40px; height: 40px; border-radius: 50%;
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; flex-shrink: 0; box-shadow: 0 4px 10px rgba(245,166,35,0.3);
}
.prestasi-info h5 { font-size: 0.9rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem; line-height: 1.3; }
.prestasi-info small { font-size: 0.75rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.3rem; }

/* CTA Card */
.cta-card {
    background: linear-gradient(135deg, <?= $colors[0] ?>, <?= $colors[1] ?>);
    color: white; border: none; text-align: center;
}
.cta-card h3 { color: white; border-bottom-color: rgba(255,255,255,0.2); justify-content: center; }
.cta-card p { font-size: 0.9rem; opacity: 0.95; margin-bottom: 1.5rem; line-height: 1.6; }
.cta-card .btn-primary {
    background: white; color: <?= $colors[0] ?>; font-weight: 700; width: 100%; justify-content: center;
}
.cta-card .btn-primary:hover { background: var(--bg-secondary); transform: translateY(-2px); }

@media (max-width: 968px) {
    .detail-layout { grid-template-columns: 1fr; }
    .detail-sidebar { position: static; order: -1; }
    .detail-hero { padding: 6rem 0 4rem; }
    .dosen-grid { grid-template-columns: 1fr; }
}
</style>

<!-- ===== HERO SECTION ===== -->
<section class="detail-hero">
    <div class="container">
        <nav class="breadcrumb" data-aos="fade-down">
            <a href="<?= base_url() ?>">Beranda</a><span>›</span>
            <a href="<?= base_url('program.php') ?>">Program Studi</a><span>›</span>
            <span><?= sanitize($prodi['singkatan']) ?></span>
        </nav>
        
        <div class="page-hero-content" data-aos="fade-up">
            <span class="page-badge">
                <span>🎓</span> <?= sanitize($prodi['jenjang']) ?> • Akreditasi: <?= sanitize($prodi['akreditasi']) ?>
            </span>
            <h1 class="page-title"><?= sanitize($prodi['nama']) ?></h1>
            <p class="page-subtitle">Membentuk pendidik profesional yang menguasai keilmuan, inovatif, dan memiliki karakter Islami untuk membangun peradaban.</p>
        </div>
    </div>
</section>

<!-- ===== MAIN CONTENT ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">
        <div class="detail-layout">
            
            <!-- Left: Main Content -->
            <main class="detail-main">
                <div class="detail-tabs" data-aos="fade-up">
                    <div class="tab-nav">
                        <button class="tab-btn active" onclick="switchTab(event, 'tab-visi')">🎯 Visi & Misi</button>
                        <button class="tab-btn" onclick="switchTab(event, 'tab-kurikulum')">📚 Kurikulum</button>
                        <button class="tab-btn" onclick="switchTab(event, 'tab-prospek')">💼 Prospek Karir</button>
                        <button class="tab-btn" onclick="switchTab(event, 'tab-dosen')">👨‍🏫 Dosen</button>
                    </div>

                    <!-- Tab: Visi & Misi -->
                    <div id="tab-visi" class="tab-content active">
                        <h3>Visi</h3>
                        <p style="font-style: italic; font-size: 1.15rem; color: var(--text-primary); border-left: 4px solid <?= $colors[0] ?>; padding-left: 1rem; background: var(--bg-secondary); padding: 1.5rem; border-radius: 0 var(--radius-md) var(--radius-md) 0;">
                            <?= nl2br(sanitize($prodi['visi'] ?? 'Menjadi program studi unggul yang menghasilkan lulusan berkompeten, berkarakter Islami, dan berdaya saing global pada tahun 2030.')) ?>
                        </p>
                        
                        <h3 style="margin-top: 2rem;">Misi</h3>
                        <ul>
                            <?php 
                            $misi_lines = explode("\n", sanitize($prodi['misi'] ?? "1. Menyelenggarakan pendidikan yang berkualitas dan relevan dengan kebutuhan zaman.\n2. Melaksanakan penelitian yang inovatif dan bermanfaat bagi masyarakat.\n3. Mengabdi kepada masyarakat melalui pemberdayaan dan pendampingan.\n4. Menanamkan nilai-nilai Islam dan kearifan lokal dalam setiap aktivitas akademik."));
                            foreach ($misi_lines as $line): 
                                if (trim($line) !== ''):
                            ?>
                                <li><?= trim($line) ?></li>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </ul>
                    </div>

                    <!-- Tab: Kurikulum -->
                    <div id="tab-kurikulum" class="tab-content">
                        <h3>Struktur Kurikulum</h3>
                        <p><?= nl2br(sanitize($prodi['kurikulum'] ?? 'Kurikulum program studi ini dirancang berbasis KKNI dengan total 144-146 SKS yang ditempuh dalam 8 semester. Kurikulum terdiri dari Mata Kuliah Wajib Universitas, Mata Kuliah Dasar Keilmuan, Mata Kuliah Keahlian, Magang, dan Skripsi. Kurikulum ini secara berkala ditinjau untuk memastikan relevansi dengan perkembangan ilmu pengetahuan dan kebutuhan dunia pendidikan.')) ?></p>
                        <div style="margin-top: 1.5rem; padding: 1.5rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px dashed var(--border); text-align: center;">
                            <p style="margin-bottom: 1rem; font-weight: 600;">📥 Ingin melihat detail mata kuliah per semester?</p>
                            <a href="<?= base_url('kontak.php') ?>" class="btn btn-secondary btn-sm">Hubungi Prodi untuk Kurikulum Lengkap</a>
                        </div>
                    </div>

                    <!-- Tab: Prospek Karir -->
                    <div id="tab-prospek" class="tab-content">
                        <h3>Prospek Karir Lulusan</h3>
                        <p>Lulusan program studi ini memiliki kompetensi yang luas dan siap berkarir di berbagai bidang, antara lain:</p>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; margin-top: 1.5rem;">
                            <?php 
                            $prospek_list = explode("\n", sanitize($prodi['prospek_kerja'] ?? "Guru/Dosen Profesional\nPeneliti Pendidikan\nKonsultan Kurikulum\nPengembang Bahan Ajar\nEntrepreneur Pendidikan\nTenaga Pendidik Non-Formal"));
                            foreach ($prospek_list as $prospek):
                                if (trim($prospek) !== ''):
                            ?>
                                <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); display: flex; align-items: center; gap: 0.75rem; border: 1px solid var(--border);">
                                    <span style="font-size: 1.5rem;">✅</span>
                                    <span style="font-weight: 600; font-size: 0.9rem;"><?= trim($prospek) ?></span>
                                </div>
                            <?php 
                                endif;
                            endforeach;
                            ?>
                        </div>
                    </div>

                    <!-- Tab: Dosen -->
                    <div id="tab-dosen" class="tab-content">
                        <h3>Tenaga Pengajar</h3>
                        <p style="margin-bottom: 1.5rem;">Dibimbing oleh dosen-dosen berkualifikasi S2 dan S3 dari universitas terkemuka, yang aktif dalam penelitian dan pengabdian masyarakat.</p>
                        
                        <?php if (empty($dosen)): ?>
                            <div class="empty-state-pro" style="padding: 2rem;">
                                <p>👨‍🏫 Data dosen sedang dalam proses pemutakhiran. Silakan hubungi prodi untuk informasi lebih lanjut.</p>
                            </div>
                        <?php else: ?>
                            <div class="dosen-grid">
                                <?php foreach ($dosen as $d): ?>
                                <div class="dosen-card">
                                    <div class="dosen-avatar">
                                        <?= strtoupper(substr($d['nama'], 0, 1)) ?>
                                    </div>
                                    <div class="dosen-info">
                                        <h4><?= sanitize(($d['gelar_depan'] ?? '') . ' ' . $d['nama'] . ' ' . ($d['gelar_belakang'] ?? '')) ?></h4>
                                        <p><?= sanitize($d['jabatan_fungsional'] ?? 'Dosen') ?></p>
                                        <?php if (!empty($d['pendidikan_terakhir'])): ?>
                                            <div class="dosen-edu">🎓 <?= sanitize($d['pendidikan_terakhir']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
            
            <!-- Right: Sidebar -->
            <aside class="detail-sidebar" data-aos="fade-left" data-aos-delay="200">
                <div class="sidebar-card info-card">
                    <h3>📊 Informasi Program</h3>
                    <ul class="info-list">
                        <li><span>Jenjang</span> <strong><?= sanitize($prodi['jenjang']) ?></strong></li>
                        <li><span>Akreditasi</span> <strong style="color: var(--primary);"><?= sanitize($prodi['akreditasi']) ?></strong></li>
                        <li><span>Kode Prodi</span> <strong><?= sanitize($prodi['kode']) ?></strong></li>
                        <li><span>Ketua Prodi</span> <strong><?= sanitize($prodi['ketua_prodi'] ?? 'Segera diisi') ?></strong></li>
                        <li><span>Jumlah Dosen</span> <strong><?= $prodi['jumlah_dosen'] ?? 15 ?> Orang</strong></li>
                        <li><span>Mahasiswa Aktif</span> <strong><?= $prodi['jumlah_mahasiswa'] ?? 150 ?> Orang</strong></li>
                    </ul>
                    <div style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem;">
                        <a href="<?= base_url('kontak.php') ?>" class="btn btn-primary btn-block">📝 Daftar Sekarang</a>
                        <a href="https://wa.me/6281234567890?text=Halo,%20saya%20ingin%20bertanya%20tentang%20Prodi%20<?= urlencode($prodi['nama']) ?>" target="_blank" class="btn btn-secondary btn-block" style="background: #25D366; color: white; border: none;">💬 Tanya via WhatsApp</a>
                    </div>
                </div>
                
                <?php if (!empty($prestasi)): ?>
                <div class="sidebar-card">
                    <h3>🏆 Prestasi Terbaru</h3>
                    <div class="prestasi-list">
                        <?php foreach ($prestasi as $p): ?>
                        <div class="prestasi-item">
                            <div class="prestasi-medal">🥇</div>
                            <div class="prestasi-info">
                                <h5><?= sanitize($p['judul']) ?></h5>
                                <small>📅 <?= sanitize($p['tahun']) ?> • <?= sanitize($p['tingkat']) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="sidebar-card cta-card">
                    <h3>🚀 Siap Bergabung?</h3>
                    <p>Jadilah bagian dari generasi pendidik unggul bersama FKIP UNIMOF. Pendaftaran mahasiswa baru telah dibuka!</p>
                    <a href="<?= base_url('kontak.php') ?>" class="btn btn-primary">Daftar Sekarang</a>
                </div>
            </aside>

        </div>
    </div>
</section>

<script>
// Tab Switching Logic
function switchTab(event, tabId) {
    // Remove active class from all buttons and contents
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    // Add active class to clicked button and corresponding content
    event.currentTarget.classList.add('active');
    document.getElementById(tabId).classList.add('active');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>