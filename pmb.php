<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Penerimaan Mahasiswa Baru (PMB)';
$page_description = 'Informasi lengkap pendaftaran mahasiswa baru FKIP UNIMOF, alur, persyaratan, dan biaya.';
require_once __DIR__ . '/includes/header.php';
?>
<style>
.pmb-hero { background: linear-gradient(135deg, #0a6847 0%, #16213e 100%); color: white; padding: 8rem 0 5rem; text-align: center; position: relative; overflow: hidden; }
.pmb-hero::before { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at 20% 30%, rgba(245,166,35,0.2) 0%, transparent 50%); }
.pmb-hero .container { position: relative; z-index: 2; }
.alur-steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin: 3rem 0; }
.step-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; text-align: center; position: relative; transition: all 0.3s; }
.step-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); border-color: var(--primary); }
.step-number { width: 50px; height: 50px; background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 800; margin: 0 auto 1rem; }
.req-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
.req-card { background: var(--bg-secondary); padding: 1.5rem; border-radius: var(--radius-lg); border-left: 4px solid var(--primary); }
.req-card h4 { margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem; }
.req-card ul { padding-left: 1.25rem; color: var(--text-secondary); }
.req-card li { margin-bottom: 0.5rem; }
.biaya-table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; background: var(--bg-primary); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm); }
.biaya-table th, .biaya-table td { padding: 1rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border); }
.biaya-table th { background: var(--bg-secondary); font-weight: 700; color: var(--primary); }
.pmb-cta { background: linear-gradient(135deg, var(--secondary), #d97706); color: white; padding: 3rem; border-radius: var(--radius-xl); text-align: center; margin-top: 4rem; }
</style>

<section class="pmb-hero">
    <div class="container">
        <span class="page-badge" style="background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.2);">🎓 Gelombang 1 Tahun 2026/2027 Dibuka!</span>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 5vw, 4rem); margin: 1rem 0;">Penerimaan Mahasiswa Baru</h1>
        <p class="page-subtitle" style="max-width: 700px; margin: 0 auto 2rem; opacity: 0.9;">Bergabunglah dengan FKIP UNIMOF dan wujudkan impianmu menjadi pendidik profesional yang berkarakter dan berdaya saing global.</p>
        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <a href="#alur" class="btn btn-primary btn-lg" style="background: white; color: var(--primary);">Lihat Alur Pendaftaran</a>
            <a href="<?= base_url('kontak.php') ?>" class="btn btn-outline btn-lg" style="border-color: white; color: white;">Tanya Admin</a>
        </div>
    </div>
</section>

<section class="section" id="alur">
    <div class="container">
        <div class="section-header"><span class="section-tag">Langkah Mudah</span><h2 class="section-title">Alur <span class="gradient-text">Pendaftaran</span></h2></div>
        <div class="alur-steps">
            <div class="step-card" data-aos="fade-up"><div class="step-number">1</div><h3>Isi Formulir</h3><p style="color:var(--text-secondary);font-size:0.9rem;">Daftar online melalui website atau datang langsung ke kampus.</p></div>
            <div class="step-card" data-aos="fade-up" data-aos-delay="100"><div class="step-number">2</div><h3>Upload Berkas</h3><p style="color:var(--text-secondary);font-size:0.9rem;">Unggah scan Ijazah, KK, KTP, dan Pas Foto terbaru.</p></div>
            <div class="step-card" data-aos="fade-up" data-aos-delay="200"><div class="step-number">3</div><h3>Pembayaran</h3><p style="color:var(--text-secondary);font-size:0.9rem;">Lakukan pembayaran biaya pendaftaran melalui bank mitra.</p></div>
            <div class="step-card" data-aos="fade-up" data-aos-delay="300"><div class="step-number">4</div><h3>Seleksi & Hasil</h3><p style="color:var(--text-secondary);font-size:0.9rem;">Ikuti tes potensi akademik (jika ada) dan cek hasil kelulusan.</p></div>
        </div>

        <div class="section-header" style="margin-top: 4rem;"><span class="section-tag">Dokumen</span><h2 class="section-title">Persyaratan <span class="gradient-text">Pendaftaran</span></h2></div>
        <div class="req-list">
            <div class="req-card" data-aos="fade-right">
                <h4>📄 Syarat Umum</h4>
                <ul><li>Lulusan SMA/SMK/MA sederajat.</li><li>Sehat jasmani dan rohani.</li><li>Berkomitmen menyelesaikan studi tepat waktu.</li><li>Bersedia mematuhi peraturan universitas.</li></ul>
            </div>
            <div class="req-card" data-aos="fade-left">
                <h4>📎 Berkas yang Diperlukan</h4>
                <ul><li>Fotokopi Ijazah & SKHU (legalisir).</li><li>Fotokopi Kartu Keluarga (KK) & KTP.</li><li>Pas Foto berwarna 3x4 (4 lembar).</li><li>Surat Keterangan Kelakuan Baik (SKCK).</li></ul>
            </div>
        </div>

        <div class="section-header" style="margin-top: 4rem;"><span class="section-tag">Investasi</span><h2 class="section-title">Estimasi <span class="gradient-text">Biaya</span></h2></div>
        <div style="max-width: 800px; margin: 0 auto;" data-aos="fade-up">
            <table class="biaya-table">
                <thead><tr><th>Komponen Biaya</th><th>Jumlah (Rp)</th><th>Keterangan</th></tr></thead>
                <tbody>
                    <tr><td><strong>Biaya Pendaftaran</strong></td><td>Rp 300.000</td><td>Tidak dapat dikembalikan</td></tr>
                    <tr><td><strong>Uang Pangkal (Gedung)</strong></td><td>Rp 3.500.000</td><td>Dibayar sekali di awal</td></tr>
                    <tr><td><strong>SPP per Semester</strong></td><td>Rp 2.500.000</td><td>Belum termasuk praktikum/KKN</td></tr>
                    <tr><td><strong>Total Estimasi Awal</strong></td><td><strong>Rp 6.300.000</strong></td><td>Belum termasuk seragam & buku</td></tr>
                </tbody>
            </table>
            <p style="text-align: center; margin-top: 1rem; font-size: 0.85rem; color: var(--text-muted);">*Tersedia program beasiswa prestasi dan beasiswa kurang mampu. Hubungi kami untuk info lebih lanjut.</p>
        </div>

        <div class="pmb-cta" data-aos="zoom-in">
            <h2 style="font-family: var(--font-display); font-size: 2rem; margin-bottom: 1rem;">Siap Menjadi Bagian dari Kami?</h2>
            <p style="margin-bottom: 2rem; opacity: 0.95;">Kuota terbatas! Segera daftarkan dirimu sebelum gelombang ditutup.</p>
            <a href="<?= base_url('kontak.php') ?>" class="btn btn-lg" style="background: white; color: #d97706; font-weight: 800;">Daftar Sekarang / Tanya PMB</a>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>