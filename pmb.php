<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Penerimaan Mahasiswa Baru (PMB)';
$page_description = 'Informasi lengkap pendaftaran mahasiswa baru FKIP UNIMOF: alur, persyaratan, biaya, jalur beasiswa, dan kontak admin.';

// Data dinamis untuk FAQ dan Jalur
$jalur_pmb = [
    'reguler' => [
        'title' => 'Jalur Reguler',
        'icon' => '🎓',
        'desc' => 'Jalur masuk standar melalui seleksi administrasi dan tes potensi akademik.',
        'syarat' => ['Lulusan SMA/SMK/MA sederajat', 'Usia maksimal 21 tahun', 'Lulus tes potensi akademik', 'Membayar biaya pendaftaran Rp 300.000']
    ],
    'prestasi' => [
        'title' => 'Jalur Prestasi',
        'icon' => '🏆',
        'desc' => 'Khusus bagi calon mahasiswa yang memiliki prestasi akademik或非-akademik tingkat kabupaten/provinsi/nasional.',
        'syarat' => ['Memiliki sertifikat juara minimal tingkat Kabupaten', 'Rata-rata nilai rapor minimal 80', 'Lolos wawancara', 'Potongan uang pangkal hingga 50%']
    ],
    'beasiswa' => [
        'title' => 'Jalur Beasiswa',
        'icon' => '💰',
        'desc' => 'Program beasiswa untuk siswa berprestasi namun memiliki keterbatasan ekonomi (KIP Kuliah, Beasiswa Muhammadiyah, dll).',
        'syarat' => ['Surat Keterangan Tidak Mampu (SKTM)', 'Rapor dengan nilai baik', 'Rekomendasi dari sekolah/asal', 'Lolos seleksi berkas dan wawancara']
    ]
];

$faq_pmb = [
    ['q' => 'Kapan batas waktu pendaftaran Gelombang 1?', 'a' => 'Pendaftaran Gelombang 1 dibuka mulai 1 Oktober 2025 hingga 31 Januari 2026. Segera daftar sebelum kuota terpenuhi!'],
    ['q' => 'Apakah ada tes masuk? Materinya apa saja?', 'a' => 'Ya, ada Tes Potensi Akademik (TPA) yang meliputi: Tes Verbal, Tes Numerik, dan Tes Logika. Untuk jalur prestasi tertentu, tes bisa diganti dengan wawancara.'],
    ['q' => 'Bisakah membayar uang kuliah secara dicicil?', 'a' => 'Tentu! FKIP UNIMOF bekerja sama dengan beberapa lembaga keuangan untuk memberikan fasilitas cicilan uang pangkal dan SPP dengan bunga 0%.'],
    ['q' => 'Apakah asrama tersedia untuk mahasiswa luar daerah?', 'a' => 'Ya, kami memiliki asrama putra dan putri yang terjangkau dengan fasilitas lengkap (WiFi, dapur bersama, keamanan 24 jam). Prioritas diberikan untuk mahasiswa luar Maumere.'],
    ['q' => 'Bagaimana cara mendaftar secara online?', 'a' => 'Anda dapat mengisi formulir pendaftaran di website ini, mengupload berkas yang diperlukan, dan melakukan pembayaran melalui virtual account bank mitra. Admin kami akan memandu Anda via WhatsApp.']
];

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ===== HERO EXTREME ===== */
.pmb-hero-extreme {
    position: relative; background: linear-gradient(135deg, #0a6847 0%, #084d35 40%, #16213e 100%);
    color: white; padding: 10rem 0 6rem; text-align: center; overflow: hidden;
}
.pmb-hero-extreme::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(245,166,35,0.25) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(59,130,246,0.2) 0%, transparent 50%);
    animation: heroAurora 20s ease-in-out infinite;
}
@keyframes heroAurora { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(-20px, 20px); } }
.pmb-hero-content { position: relative; z-index: 2; max-width: 800px; margin: 0 auto; }

/* Countdown Timer */
.countdown-wrapper {
    display: flex; justify-content: center; gap: 1rem; margin: 2rem 0; flex-wrap: wrap;
}
.countdown-box {
    background: rgba(255,255,255,0.1); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2);
    border-radius: var(--radius-md); padding: 1rem 1.5rem; min-width: 80px; text-align: center;
}
.countdown-num { font-family: var(--font-display); font-size: 2.5rem; font-weight: 900; line-height: 1; color: #fbbf24; }
.countdown-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.8; margin-top: 0.25rem; }

/* ===== WHY CHOOSE US ===== */
.why-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin: -4rem 0 4rem; position: relative; z-index: 10; }
.why-card {
    background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl);
    padding: 2rem; text-align: center; box-shadow: var(--shadow-lg); transition: all 0.4s;
}
.why-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--primary); }
.why-icon { font-size: 2.5rem; margin-bottom: 1rem; }
.why-title { font-family: var(--font-display); font-size: 1.25rem; font-weight: 800; margin-bottom: 0.5rem; }
.why-desc { font-size: 0.9rem; color: var(--text-secondary); line-height: 1.6; }

/* ===== ALUR PENDAFTARAN (Connected Steps) ===== */
.alur-container { position: relative; padding: 2rem 0; }
.alur-container::before {
    content: ''; position: absolute; top: 50%; left: 10%; right: 10%; height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--primary-light), var(--secondary));
    transform: translateY(-50%); border-radius: 4px; z-index: 0;
}
.alur-steps-extreme {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 2rem; position: relative; z-index: 1;
}
.step-card-extreme {
    background: var(--bg-primary); border: 2px solid var(--border); border-radius: var(--radius-xl);
    padding: 2rem 1.5rem; text-align: center; transition: all 0.4s; position: relative;
}
.step-card-extreme:hover { transform: translateY(-10px); border-color: var(--primary); box-shadow: var(--shadow-xl); }
.step-number-extreme {
    width: 60px; height: 60px; background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 1.75rem; font-weight: 900; margin: 0 auto 1.25rem; border: 4px solid var(--bg-primary);
    box-shadow: 0 4px 15px rgba(10,104,71,0.3);
}
.step-card-extreme h3 { font-size: 1.15rem; font-weight: 700; margin-bottom: 0.75rem; }
.step-card-extreme p { font-size: 0.9rem; color: var(--text-secondary); line-height: 1.6; }

/* ===== JALUR PENDAFTARAN (Tabs) ===== */
.jalur-tabs { display: flex; justify-content: center; gap: 0.5rem; margin-bottom: 2rem; flex-wrap: wrap; }
.jalur-tab {
    padding: 0.75rem 1.5rem; border-radius: 999px; border: 2px solid var(--border);
    background: var(--bg-primary); color: var(--text-secondary); font-size: 0.95rem;
    font-weight: 700; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; gap: 0.5rem;
}
.jalur-tab:hover { border-color: var(--primary); color: var(--primary); }
.jalur-tab.active { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 4px 12px rgba(10,104,71,0.25); }

.jalur-panel { display: none; animation: fadeIn 0.5s ease; }
.jalur-panel.active { display: block; }
.jalur-content {
    display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; align-items: start;
    background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2.5rem;
}
.jalur-info h3 { font-family: var(--font-display); font-size: 1.75rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.75rem; }
.jalur-info p { color: var(--text-secondary); line-height: 1.7; margin-bottom: 1.5rem; }
.jalur-syarat { background: var(--bg-secondary); padding: 1.5rem; border-radius: var(--radius-lg); border-left: 4px solid var(--primary); }
.jalur-syarat h4 { font-size: 1.1rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
.jalur-syarat ul { padding-left: 1.25rem; color: var(--text-secondary); }
.jalur-syarat li { margin-bottom: 0.75rem; line-height: 1.6; }

/* ===== BIAYA (Pricing Cards) ===== */
.biaya-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; max-width: 1000px; margin: 0 auto; }
.biaya-card {
    background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl);
    padding: 2rem; text-align: center; transition: all 0.4s; position: relative; overflow: hidden;
}
.biaya-card.featured {
    border-color: var(--primary); background: linear-gradient(180deg, rgba(10,104,71,0.03) 0%, var(--bg-primary) 100%);
    transform: scale(1.05); box-shadow: var(--shadow-xl); z-index: 2;
}
.biaya-card.featured::before {
    content: 'POPULER'; position: absolute; top: 1.5rem; right: -2rem;
    background: var(--primary); color: white; padding: 0.25rem 2.5rem; font-size: 0.7rem; font-weight: 800;
    transform: rotate(45deg);
}
.biaya-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); }
.biaya-card.featured:hover { transform: scale(1.05) translateY(-8px); }
.biaya-icon { font-size: 2.5rem; margin-bottom: 1rem; }
.biaya-title { font-family: var(--font-display); font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem; }
.biaya-price { font-size: 2.5rem; font-weight: 900; color: var(--primary); margin-bottom: 1.5rem; }
.biaya-price small { font-size: 1rem; color: var(--text-muted); font-weight: 500; }
.biaya-features { list-style: none; padding: 0; margin-bottom: 2rem; text-align: left; }
.biaya-features li { padding: 0.75rem 0; border-bottom: 1px solid var(--border); font-size: 0.9rem; color: var(--text-secondary); display: flex; align-items: center; gap: 0.5rem; }
.biaya-features li::before { content: '✅'; font-size: 0.8rem; }

/* ===== FAQ ===== */
.faq-pmb { max-width: 800px; margin: 0 auto; }
.faq-item-pmb {
    background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-lg);
    margin-bottom: 1rem; overflow: hidden; transition: all 0.3s;
}
.faq-item-pmb:hover { border-color: var(--primary-light); }
.faq-item-pmb.open { border-color: var(--primary); box-shadow: var(--shadow-md); }
.faq-question-pmb {
    width: 100%; padding: 1.25rem 1.5rem; background: none; border: none; text-align: left;
    font-family: inherit; font-size: 1rem; font-weight: 700; color: var(--text-primary);
    cursor: pointer; display: flex; justify-content: space-between; align-items: center; gap: 1rem;
}
.faq-icon-pmb {
    width: 28px; height: 28px; border-radius: 50%; background: var(--bg-secondary);
    display: flex; align-items: center; justify-content: center; transition: all 0.3s; flex-shrink: 0;
}
.faq-item-pmb.open .faq-icon-pmb { background: var(--primary); color: white; transform: rotate(45deg); }
.faq-answer-pmb { max-height: 0; overflow: hidden; transition: max-height 0.4s ease; }
.faq-answer-inner-pmb { padding: 0 1.5rem 1.25rem; color: var(--text-secondary); line-height: 1.7; font-size: 0.95rem; }

/* ===== CTA EXTREME ===== */
.pmb-cta-extreme {
    background: linear-gradient(135deg, var(--secondary) 0%, #d97706 100%);
    border-radius: var(--radius-xl); padding: 4rem 2rem; text-align: center;
    color: white; position: relative; overflow: hidden; margin-top: 4rem;
}
.pmb-cta-extreme::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.2) 0%, transparent 50%);
}
.pmb-cta-content { position: relative; z-index: 2; max-width: 700px; margin: 0 auto; }

@media (max-width: 968px) {
    .alur-container::before { display: none; }
    .alur-steps-extreme { grid-template-columns: 1fr 1fr; }
    .jalur-content { grid-template-columns: 1fr; }
    .biaya-card.featured { transform: none; }
    .biaya-card.featured:hover { transform: translateY(-8px); }
}
@media (max-width: 640px) {
    .alur-steps-extreme { grid-template-columns: 1fr; }
    .countdown-wrapper { gap: 0.5rem; }
    .countdown-box { min-width: 60px; padding: 0.75rem; }
    .countdown-num { font-size: 1.75rem; }
}
</style>

<!-- ===== HERO EXTREME ===== -->
<section class="pmb-hero-extreme">
    <div class="container pmb-hero-content">
        <span class="page-badge" style="background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.2); display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1.25rem; border-radius: 999px; font-size: 0.85rem; font-weight: 700; margin-bottom: 1.5rem;" data-aos="fade-down">
            <span style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; animation: pulse 2s infinite;"></span>
            Gelombang 1 Tahun 2026/2027 Dibuka!
        </span>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 6vw, 4.5rem); font-weight: 900; margin-bottom: 1rem; letter-spacing: -0.02em;" data-aos="fade-up">
            Wujudkan Impian Menjadi <span style="background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Pendidik Profesional</span>
        </h1>
        <p class="page-subtitle" style="max-width: 700px; margin: 0 auto 2rem; opacity: 0.95; font-size: 1.15rem; line-height: 1.7;" data-aos="fade-up" data-aos-delay="100">
            Bergabunglah dengan FKIP UNIMOF dan jadilah bagian dari generasi yang membangun peradaban Indonesia Timur melalui pendidikan yang berkualitas dan berkarakter.
        </p>
        
        <!-- Countdown Timer -->
        <div class="countdown-wrapper" data-aos="fade-up" data-aos-delay="200">
            <div class="countdown-box">
                <div class="countdown-num" id="cd-days">00</div>
                <div class="countdown-label">Hari</div>
            </div>
            <div class="countdown-box">
                <div class="countdown-num" id="cd-hours">00</div>
                <div class="countdown-label">Jam</div>
            </div>
            <div class="countdown-box">
                <div class="countdown-num" id="cd-minutes">00</div>
                <div class="countdown-label">Menit</div>
            </div>
            <div class="countdown-box">
                <div class="countdown-num" id="cd-seconds">00</div>
                <div class="countdown-label">Detik</div>
            </div>
        </div>

        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;" data-aos="fade-up" data-aos-delay="300">
            <a href="#alur" class="btn btn-primary btn-lg" style="background: white; color: var(--primary); font-weight: 800;">
                <span>Lihat Alur Pendaftaran</span>
            </a>
            <a href="https://wa.me/6281234567890?text=Halo%20Admin%20PMB%20FKIP%20UNIMOF,%20saya%20ingin%20bertanya%20tentang%20pendaftaran." target="_blank" class="btn btn-outline btn-lg" style="border-color: white; color: white;">
                <span>💬 Tanya Admin via WhatsApp</span>
            </a>
        </div>
    </div>
</section>

<!-- ===== WHY CHOOSE US ===== -->
<div class="container">
    <div class="why-grid">
        <div class="why-card" data-aos="fade-up" data-aos-delay="0">
            <div class="why-icon">🎓</div>
            <h3 class="why-title">Akreditasi Unggul</h3>
            <p class="why-desc">Program studi kami telah terakreditasi dengan predikat terbaik dari BAN-PT.</p>
        </div>
        <div class="why-card" data-aos="fade-up" data-aos-delay="100">
            <div class="why-icon">💰</div>
            <h3 class="why-title">Biaya Terjangkau</h3>
            <p class="why-desc">Tersedia berbagai program beasiswa dan fasilitas cicilan tanpa bunga.</p>
        </div>
        <div class="why-card" data-aos="fade-up" data-aos-delay="200">
            <div class="why-icon">💼</div>
            <h3 class="why-title">Siap Kerja</h3>
            <p class="why-desc">Kurikulum berbasis MBKM dengan program magang di sekolah-sekolah mitra.</p>
        </div>
        <div class="why-card" data-aos="fade-up" data-aos-delay="300">
            <div class="why-icon">🕌</div>
            <h3 class="why-title">Lingkungan Islami</h3>
            <p class="why-desc">Pembentukan karakter berlandaskan nilai-nilai keislaman dan kemuhammadiyahan.</p>
        </div>
    </div>
</div>

<!-- ===== ALUR PENDAFTARAN ===== -->
<section class="section" id="alur">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <span class="section-tag">Langkah Mudah</span>
            <h2 class="section-title">Alur <span class="gradient-text">Pendaftaran</span></h2>
            <p class="section-desc">Proses pendaftaran yang simpel dan transparan, bisa dilakukan secara online dari mana saja.</p>
        </div>
        
        <div class="alur-container">
            <div class="alur-steps-extreme">
                <div class="step-card-extreme" data-aos="fade-up" data-aos-delay="0">
                    <div class="step-number-extreme">1</div>
                    <h3>Isi Formulir Online</h3>
                    <p>Daftar melalui website atau datang langsung ke kampus untuk mengisi formulir pendaftaran.</p>
                </div>
                <div class="step-card-extreme" data-aos="fade-up" data-aos-delay="100">
                    <div class="step-number-extreme">2</div>
                    <h3>Upload Berkas</h3>
                    <p>Unggah scan Ijazah/SKL, KK, KTP, Pas Foto, dan dokumen pendukung lainnya.</p>
                </div>
                <div class="step-card-extreme" data-aos="fade-up" data-aos-delay="200">
                    <div class="step-number-extreme">3</div>
                    <h3>Pembayaran</h3>
                    <p>Lakukan pembayaran biaya pendaftaran melalui Virtual Account bank mitra atau transfer.</p>
                </div>
                <div class="step-card-extreme" data-aos="fade-up" data-aos-delay="300">
                    <div class="step-number-extreme">4</div>
                    <h3>Tes & Pengumuman</h3>
                    <p>Ikuti tes potensi akademik (atau wawancara) dan pantau hasil kelulusan di website.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== JALUR PENDAFTARAN ===== -->
<section class="section" style="background: var(--bg-secondary); padding: 5rem 0;">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <span class="section-tag">Pilihan Jalur</span>
            <h2 class="section-title">Jalur <span class="gradient-text">Pendaftaran</span></h2>
            <p class="section-desc">Pilih jalur yang paling sesuai dengan potensi dan kondisi Anda.</p>
        </div>

        <div class="jalur-tabs" data-aos="fade-up">
            <?php foreach ($jalur_pmb as $key => $jalur): ?>
            <button class="jalur-tab <?= $key === 'reguler' ? 'active' : '' ?>" onclick="switchJalur('<?= $key ?>')">
                <span><?= $jalur['icon'] ?></span> <?= $jalur['title'] ?>
            </button>
            <?php endforeach; ?>
        </div>

        <?php foreach ($jalur_pmb as $key => $jalur): ?>
        <div id="jalur-<?= $key ?>" class="jalur-panel <?= $key === 'reguler' ? 'active' : '' ?>" data-aos="fade-up">
            <div class="jalur-content">
                <div class="jalur-info">
                    <h3><span style="font-size: 2rem;"><?= $jalur['icon'] ?></span> <?= $jalur['title'] ?></h3>
                    <p><?= $jalur['desc'] ?></p>
                    <a href="https://wa.me/6281234567890?text=Halo,%20saya%20tertarik%20dengan%20<?= urlencode($jalur['title']) ?>%20FKIP%20UNIMOF" target="_blank" class="btn btn-primary">
                        Konsultasi Jalur Ini →
                    </a>
                </div>
                <div class="jalur-syarat">
                    <h4>📋 Persyaratan Khusus</h4>
                    <ul>
                        <?php foreach ($jalur['syarat'] as $syarat): ?>
                        <li><?= $syarat ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- ===== ESTIMASI BIAYA ===== -->
<section class="section">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <span class="section-tag">Investasi Masa Depan</span>
            <h2 class="section-title">Estimasi <span class="gradient-text">Biaya Kuliah</span></h2>
            <p class="section-desc">Transparan dan terjangkau, dengan opsi cicilan yang fleksibel.</p>
        </div>

        <div class="biaya-grid" data-aos="fade-up">
            <div class="biaya-card">
                <div class="biaya-icon">📝</div>
                <h3 class="biaya-title">Biaya Pendaftaran</h3>
                <div class="biaya-price">Rp 300.000</div>
                <ul class="biaya-features">
                    <li>Pengisian formulir online</li>
                    <li>Verifikasi berkas administrasi</li>
                    <li>Pengujian Tes Potensi Akademik</li>
                    <li>Biaya tidak dapat dikembalikan</li>
                </ul>
                <a href="#alur" class="btn btn-secondary" style="width: 100%; justify-content: center;">Mulai Daftar</a>
            </div>

            <div class="biaya-card featured">
                <div class="biaya-icon">🎓</div>
                <h3 class="biaya-title">Semester 1 (Total)</h3>
                <div class="biaya-price">Rp 6.3 Jt <small>/estimasi</small></div>
                <ul class="biaya-features">
                    <li>Uang Pangkal (Gedung): Rp 3.500.000</li>
                    <li>SPP Semester 1: Rp 2.500.000</li>
                    <li>Biaya Praktikum/Lab (sesuai prodi)</li>
                    <li>Termasuk jas almamater & KTM</li>
                    <li><strong>Bisa dicicil 3x - 6x!</strong></li>
                </ul>
                <a href="https://wa.me/6281234567890?text=Halo,%20saya%20ingin%20tanya%20tentang%20cicilan%20biaya%20kuliah" target="_blank" class="btn btn-primary" style="width: 100%; justify-content: center;">Tanya Cicilan</a>
            </div>

            <div class="biaya-card">
                <div class="biaya-icon">💰</div>
                <h3 class="biaya-title">SPP per Semester</h3>
                <div class="biaya-price">Rp 2.5 Jt <small>/semester</small></div>
                <ul class="biaya-features">
                    <li>Biaya kuliah reguler per semester</li>
                    <li>Akses perpustakaan & LMS digital</li>
                    <li>Bimbingan skripsi & wisuda</li>
                    <li>Tidak ada kenaikan signifikan</li>
                    <li>Tersedia beasiswa setiap semester</li>
                </ul>
                <a href="<?= base_url('download.php?kategori=Formulir') ?>" class="btn btn-secondary" style="width: 100%; justify-content: center;">Cek Beasiswa</a>
            </div>
        </div>
        
        <p style="text-align: center; margin-top: 2rem; font-size: 0.9rem; color: var(--text-muted);" data-aos="fade-up">
            * Biaya dapat berubah sesuai kebijakan universitas. Hubungi admin untuk informasi paling akurat.
        </p>
    </div>
</section>

<!-- ===== FAQ PMB ===== -->
<section class="section" style="background: var(--bg-secondary); padding: 5rem 0;">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <span class="section-tag">Bantuan</span>
            <h2 class="section-title">Pertanyaan <span class="gradient-text">Umum (FAQ)</span></h2>
        </div>
        
        <div class="faq-pmb" data-aos="fade-up">
            <?php foreach ($faq_pmb as $i => $faq): ?>
            <div class="faq-item-pmb">
                <button class="faq-question-pmb" onclick="toggleFaqPmb(this)">
                    <span><?= $faq['q'] ?></span>
                    <span class="faq-icon-pmb">+</span>
                </button>
                <div class="faq-answer-pmb">
                    <div class="faq-answer-inner-pmb"><?= $faq['a'] ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===== CTA EXTREME ===== -->
<div class="container">
    <div class="pmb-cta-extreme" data-aos="zoom-in">
        <div class="pmb-cta-content">
            <h2 style="font-family: var(--font-display); font-size: clamp(1.75rem, 3vw, 2.5rem); margin-bottom: 1rem;">Kuota Terbatas! Jangan Sampai Kehabisan.</h2>
            <p style="font-size: 1.1rem; margin-bottom: 2rem; opacity: 0.95;">Bergabunglah dengan ribuan mahasiswa yang telah memilih FKIP UNIMOF sebagai rumah akademik mereka. Wujudkan mimpimu mulai dari sini.</p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="https://wa.me/6281234567890?text=Halo%20Admin,%20saya%20ingin%20mendaftar%20PMB%20FKIP%20UNIMOF" target="_blank" class="btn btn-lg" style="background: white; color: #d97706; font-weight: 800;">
                    Daftar Sekarang via WhatsApp →
                </a>
                <a href="<?= base_url('kontak.php') ?>" class="btn btn-outline btn-lg" style="border-color: white; color: white;">
                    Hubungi Kami
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// ===== COUNTDOWN TIMER =====
// Set tanggal deadline (contoh: 31 Januari 2026)
const deadline = new Date('2026-01-31T23:59:59').getTime();

function updateCountdown() {
    const now = new Date().getTime();
    const distance = deadline - now;

    if (distance < 0) {
        document.getElementById('cd-days').innerText = "00";
        document.getElementById('cd-hours').innerText = "00";
        document.getElementById('cd-minutes').innerText = "00";
        document.getElementById('cd-seconds').innerText = "00";
        return;
    }

    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((distance % (1000 * 60)) / 1000);

    document.getElementById('cd-days').innerText = String(days).padStart(2, '0');
    document.getElementById('cd-hours').innerText = String(hours).padStart(2, '0');
    document.getElementById('cd-minutes').innerText = String(minutes).padStart(2, '0');
    document.getElementById('cd-seconds').innerText = String(seconds).padStart(2, '0');
}
setInterval(updateCountdown, 1000);
updateCountdown();

// ===== JALUR TABS =====
function switchJalur(key) {
    document.querySelectorAll('.jalur-tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.jalur-panel').forEach(panel => panel.classList.remove('active'));
    
    event.currentTarget.classList.add('active');
    document.getElementById('jalur-' + key).classList.add('active');
}

// ===== FAQ TOGGLE =====
function toggleFaqPmb(btn) {
    const item = btn.closest('.faq-item-pmb');
    const answer = item.querySelector('.faq-answer-pmb');
    const isOpen = item.classList.contains('open');
    
    document.querySelectorAll('.faq-item-pmb').forEach(i => {
        i.classList.remove('open');
        i.querySelector('.faq-answer-pmb').style.maxHeight = null;
    });
    
    if (!isOpen) {
        item.classList.add('open');
        answer.style.maxHeight = answer.scrollHeight + 'px';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>