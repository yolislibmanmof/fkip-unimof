<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Penerimaan Mahasiswa Baru (PMB)';
$page_description = 'Informasi lengkap pendaftaran mahasiswa baru FKIP UNIMOF: alur, persyaratan, biaya, jalur beasiswa, dan kontak admin.';

// =====================================================
// KONFIGURASI PMB (pusat data — mudah diubah)
// =====================================================
$pmb_config = [
    'wa_number'           => '6281234567890',
    'biaya_pendaftaran'   => 300000,
    'uang_pangkal'        => 3500000,
    'spp_per_semester'    => 2500000,
    'kuota_total'         => 450,
    'kuota_terisi'        => 312,
    'tahun_akademik'      => '2026/2027',
];

// ===== GELOMBANG PENDAFTARAN =====
$gelombang_list = [
    ['name' => 'Gelombang 1', 'start' => '2025-10-01', 'end' => '2026-01-31', 'discount' => 20, 'note' => 'Diskon 20% uang pangkal + prioritas asrama'],
    ['name' => 'Gelombang 2', 'start' => '2026-02-01', 'end' => '2026-05-31', 'discount' => 10, 'note' => 'Diskon 10% uang pangkal'],
    ['name' => 'Gelombang 3', 'start' => '2026-06-01', 'end' => '2026-08-31', 'discount' => 0,  'note' => 'Kuota terakhir — selama kursi tersedia'],
];

// Deteksi gelombang aktif berdasarkan tanggal server
$now = time();
$active_gelombang = null;
$deadline_iso = null;
foreach ($gelombang_list as $g) {
    $start = strtotime($g['start'] . ' 00:00:00');
    $end   = strtotime($g['end'] . ' 23:59:59');
    if ($now >= $start && $now <= $end) {
        $active_gelombang = $g;
        $active_gelombang['status'] = 'open';
        $deadline_iso = date('c', $end);
        break;
    }
}
if (!$active_gelombang) {
    foreach ($gelombang_list as $g) {
        if (strtotime($g['start'] . ' 00:00:00') > $now) {
            $active_gelombang = $g;
            $active_gelombang['status'] = 'upcoming';
            $deadline_iso = date('c', strtotime($g['end'] . ' 23:59:59'));
            break;
        }
    }
}
if (!$active_gelombang) {
    $active_gelombang = $gelombang_list[count($gelombang_list) - 1];
    $active_gelombang['status'] = 'closed';
    $deadline_iso = date('c', strtotime($active_gelombang['end'] . ' 23:59:59'));
}

// ===== JALUR PENDAFTARAN =====
$jalur_pmb = [
    'reguler' => [
        'title' => 'Jalur Reguler',
        'icon' => '🎓',
        'color' => '#0a6847',
        'diskon_pangkal' => 0,
        'diskon_spp' => 0,
        'kuota' => 250,
        'desc' => 'Jalur masuk standar melalui seleksi administrasi dan tes potensi akademik. Terbuka untuk seluruh lulusan SMA/SMK/MA sederajat tanpa batasan prestasi.',
        'syarat' => ['Lulusan SMA/SMK/MA sederajat', 'Usia maksimal 21 tahun', 'Lulus tes potensi akademik', 'Membayar biaya pendaftaran Rp 300.000'],
        'benefit' => ['Bebas pilih seluruh program studi', 'Jadwal tes fleksibel (online/offline)', 'Bimbingan persiapan TPA gratis'],
    ],
    'prestasi' => [
        'title' => 'Jalur Prestasi',
        'icon' => '🏆',
        'color' => '#f59e0b',
        'diskon_pangkal' => 50,
        'diskon_spp' => 0,
        'kuota' => 100,
        'desc' => 'Khusus bagi calon mahasiswa yang memiliki prestasi akademik atau non-akademik tingkat kabupaten/provinsi/nasional. Tes diganti wawancara portofolio.',
        'syarat' => ['Memiliki sertifikat juara minimal tingkat Kabupaten', 'Rata-rata nilai rapor minimal 80', 'Lolos wawancara portofolio', 'Potongan uang pangkal hingga 50%'],
        'benefit' => ['Potongan uang pangkal 50%', 'Tanpa TPA — cukup wawancara', 'Prioritas beasiswa prestasi semester 1'],
    ],
    'beasiswa' => [
        'title' => 'Jalur Beasiswa',
        'icon' => '💰',
        'color' => '#8b5cf6',
        'diskon_pangkal' => 100,
        'diskon_spp' => 50,
        'kuota' => 100,
        'desc' => 'Program beasiswa untuk siswa berprestasi namun memiliki keterbatasan ekonomi (KIP Kuliah, Beasiswa Muhammadiyah, Beasiswa Yayasan UNIMOF).',
        'syarat' => ['Surat Keterangan Tidak Mampu (SKTM)', 'Rapor dengan nilai baik', 'Rekomendasi dari sekolah asal', 'Lolos seleksi berkas dan wawancara'],
        'benefit' => ['Bebas 100% uang pangkal', 'Potongan SPP hingga 50%', 'Pendampingan mentor selama studi'],
    ],
];

// ===== FAQ PMB (dengan kategori) =====
$faq_pmb = [
    ['kat' => 'Pendaftaran', 'q' => 'Kapan batas waktu pendaftaran Gelombang 1?', 'a' => 'Pendaftaran Gelombang 1 dibuka mulai 1 Oktober 2025 hingga 31 Januari 2026. Segera daftar sebelum kuota terpenuhi!'],
    ['kat' => 'Pendaftaran', 'q' => 'Bagaimana cara mendaftar secara online?', 'a' => 'Klik tombol "Daftar Sekarang", isi formulir multi-langkah, unggah berkas yang diperlukan, dan lakukan pembayaran melalui virtual account bank mitra. Admin kami akan memandu Anda via WhatsApp.'],
    ['kat' => 'Pendaftaran', 'q' => 'Apakah ada tes masuk? Materinya apa saja?', 'a' => 'Ya, ada Tes Potensi Akademik (TPA) yang meliputi: Tes Verbal, Tes Numerik, dan Tes Logika. Untuk jalur prestasi, tes bisa diganti dengan wawancara portofolio.'],
    ['kat' => 'Biaya', 'q' => 'Bisakah membayar uang kuliah secara dicicil?', 'a' => 'Tentu! FKIP UNIMOF bekerja sama dengan beberapa lembaga keuangan untuk memberikan fasilitas cicilan uang pangkal dan SPP dengan bunga 0% hingga 6 kali pembayaran.'],
    ['kat' => 'Biaya', 'q' => 'Apakah biaya pendaftaran dapat dikembalikan?', 'a' => 'Biaya pendaftaran tidak dapat dikembalikan karena telah mencakup proses verifikasi berkas, pelaksanaan tes, dan administrasi panitia.'],
    ['kat' => 'Biaya', 'q' => 'Beasiswa apa saja yang tersedia?', 'a' => 'Tersedia KIP Kuliah, Beasiswa Muhammadiyah, Beasiswa Yayasan UNIMOF, dan beasiswa prestasi akademik/non-akademik. Detail dapat dilihat pada bagian Jalur Beasiswa.'],
    ['kat' => 'Akademik', 'q' => 'Apakah kelas karyawan / malam tersedia?', 'a' => 'Saat ini perkuliahan dilaksanakan pada kelas reguler pagi-siang. Kelas khusus bagi guru/tenaga kependidikan dibuka bila memenuhi kuota minimal 20 mahasiswa.'],
    ['kat' => 'Asrama', 'q' => 'Apakah asrama tersedia untuk mahasiswa luar daerah?', 'a' => 'Ya, kami memiliki asrama putra dan putri yang terjangkau dengan fasilitas lengkap (WiFi, dapur bersama, keamanan 24 jam). Prioritas diberikan untuk mahasiswa luar Maumere dan pendaftar Gelombang 1.'],
];

// ===== TESTIMONI MAHASISWA BARU =====
$testimonials = [
    ['nama' => 'Maria K. Bata', 'prodi' => 'Pendidikan Matematika', 'angkatan' => '2025', 'rating' => 5, 'quote' => 'Proses pendaftarannya cepat dan transparan. Admin WhatsApp sangat responsif, semua pertanyaan saya dijawab dalam hitungan menit.'],
    ['nama' => 'Yohanes S. Laga', 'prodi' => 'PGSD', 'angkatan' => '2025', 'rating' => 5, 'quote' => 'Saya masuk lewat jalur prestasi dan mendapat potongan uang pangkal 50%. Wawancara portofolionya santai, lebih seperti diskusi motivasi.'],
    ['nama' => 'Fatimah A. Rahman', 'prodi' => 'Pendidikan Bahasa Indonesia', 'angkatan' => '2024', 'rating' => 5, 'quote' => 'Beasiswa Muhammadiyah mengubah hidup saya. Dari keluarga sederhana, kini saya bisa kuliah tanpa membebani orang tua.'],
    ['nama' => 'Petrus M. Werang', 'prodi' => 'Pendidikan Fisika', 'angkatan' => '2025', 'rating' => 4, 'quote' => 'Asramanya nyaman dan aman, cocok untuk saya yang berasal dari luar pulau. Teman-teman satu asrama sudah seperti keluarga.'],
];

// ===== CHECKLIST BERKAS =====
$checklist_items = [
    ['id' => 'skl',      'label' => 'Scan Ijazah / Surat Keterangan Lulus (SKL)', 'wajib' => true],
    ['id' => 'rapor',    'label' => 'Scan Rapor Semester 1-5', 'wajib' => true],
    ['id' => 'kk',       'label' => 'Scan Kartu Keluarga (KK)', 'wajib' => true],
    ['id' => 'ktp',      'label' => 'Scan KTP / Kartu Siswa', 'wajib' => true],
    ['id' => 'foto',     'label' => 'Pas Foto 3x4 latar merah (digital)', 'wajib' => true],
    ['id' => 'sktm',     'label' => 'Surat Keterangan Tidak Mampu (khusus jalur beasiswa)', 'wajib' => false],
    ['id' => 'sertifikat', 'label' => 'Sertifikat prestasi (khusus jalur prestasi)', 'wajib' => false],
    ['id' => 'rek',      'label' => 'Rekomendasi sekolah asal (khusus jalur beasiswa)', 'wajib' => false],
];

// ===== SCHEMA-SAFE: opsi prodi untuk form & kalkulator =====
$prodi_options = [];
try {
    $prodi_options = $pdo->query("SELECT nama, singkatan FROM program_studi WHERE status = 'Aktif' ORDER BY urutan ASC, id ASC")->fetchAll();
} catch (Exception $e) {
    $prodi_options = [];
}
if (empty($prodi_options)) {
    $prodi_options = [
        ['nama' => 'Pendidikan Matematika', 'singkatan' => 'PMAT'],
        ['nama' => 'Pendidikan Guru Sekolah Dasar', 'singkatan' => 'PGSD'],
        ['nama' => 'Pendidikan Bahasa Indonesia', 'singkatan' => 'PBI'],
        ['nama' => 'Pendidikan Fisika', 'singkatan' => 'PFIS'],
    ];
}

// ===== STATISTIK HERO =====
$kuota_sisa = max(0, $pmb_config['kuota_total'] - $pmb_config['kuota_terisi']);
$persen_terisi = $pmb_config['kuota_total'] > 0 ? round(($pmb_config['kuota_terisi'] / $pmb_config['kuota_total']) * 100) : 0;

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ===== HERO ===== */
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
.pmb-hero-extreme::after {
    content: ''; position: absolute; inset: 0;
    background-image: linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                      linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 50px 50px; pointer-events: none;
}
.hero-particles { position: absolute; inset: 0; overflow: hidden; pointer-events: none; }
.hero-particle {
    position: absolute; width: 3px; height: 3px;
    background: rgba(255,255,255,0.6); border-radius: 50%;
    animation: floatParticle 30s infinite linear;
}
@keyframes floatParticle {
    0% { transform: translateY(100vh) translateX(0); opacity: 0; }
    10% { opacity: 0.8; } 90% { opacity: 0.8; }
    100% { transform: translateY(-10vh) translateX(50px); opacity: 0; }
}
.pmb-hero-content { position: relative; z-index: 2; max-width: 850px; margin: 0 auto; }

.hero-badge-pill {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
    padding: 0.5rem 1.25rem; border-radius: 999px;
    font-size: 0.85rem; font-weight: 700; margin-bottom: 1.5rem;
}
.pulse-dot { width: 8px; height: 8px; background: #10b981; border-radius: 50%; position: relative; }
.pulse-dot::after {
    content: ''; position: absolute; inset: 0; background: #10b981;
    border-radius: 50%; animation: pulse 2s infinite;
}
@keyframes pulse { to { transform: scale(2.5); opacity: 0; } }

/* Countdown */
.countdown-wrapper {
    display: flex; justify-content: center; gap: 1rem; margin: 2rem 0; flex-wrap: wrap;
}
.countdown-box {
    background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: var(--radius-md); padding: 1rem 1.5rem; min-width: 85px; text-align: center;
    transition: all 0.3s;
}
.countdown-box:hover { transform: translateY(-4px); background: rgba(255,255,255,0.15); }
.countdown-num {
    font-family: var(--font-display); font-size: 2.5rem; font-weight: 900;
    line-height: 1; color: #fbbf24; font-variant-numeric: tabular-nums;
}
.countdown-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.8; margin-top: 0.25rem; }
.countdown-caption {
    font-size: 0.85rem; opacity: 0.85; margin-bottom: 0.75rem;
    display: flex; align-items: center; justify-content: center; gap: 0.4rem;
}

/* Quota bar di hero */
.quota-bar-wrap {
    max-width: 480px; margin: 1.5rem auto 0; text-align: left;
    background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2); border-radius: var(--radius-md);
    padding: 1rem 1.25rem;
}
.quota-bar-head {
    display: flex; justify-content: space-between; font-size: 0.82rem;
    font-weight: 700; margin-bottom: 0.5rem;
}
.quota-bar-track {
    height: 10px; background: rgba(255,255,255,0.2); border-radius: 999px; overflow: hidden;
}
.quota-bar-fill {
    height: 100%; width: 0%; border-radius: 999px;
    background: linear-gradient(90deg, #fbbf24, #f59e0b);
    transition: width 1.5s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}
.quota-bar-fill::after {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    animation: shimmer 2s infinite;
}
@keyframes shimmer { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }

/* ===== STATS BAR ===== */
.pmb-stats-bar {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.25rem; margin: -4rem auto 3rem; max-width: 1100px;
    position: relative; z-index: 10; padding: 0 1rem;
}
.pmb-stat-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.5rem; text-align: center;
    box-shadow: var(--shadow-lg); transition: all 0.4s; position: relative; overflow: hidden;
}
.pmb-stat-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--stat-color, var(--primary)), transparent);
}
.pmb-stat-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--stat-color, var(--primary)); }
.pmb-stat-icon { font-size: 2rem; margin-bottom: 0.5rem; }
.pmb-stat-num {
    font-family: var(--font-display); font-size: 2.25rem; font-weight: 900;
    color: var(--stat-color, var(--primary)); line-height: 1; margin-bottom: 0.35rem;
    font-variant-numeric: tabular-nums;
}
.pmb-stat-label { font-size: 0.72rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== STICKY SECTION NAV ===== */
.section-nav {
    position: sticky; top: 70px; z-index: 900;
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 0.5rem; margin-bottom: 3rem;
    box-shadow: var(--shadow-md); display: flex; gap: 0.35rem;
    overflow-x: auto; scrollbar-width: none;
}
.section-nav::-webkit-scrollbar { display: none; }
.section-nav-link {
    padding: 0.55rem 1rem; border-radius: 999px; white-space: nowrap;
    font-size: 0.82rem; font-weight: 600; color: var(--text-secondary);
    text-decoration: none; transition: all 0.2s;
    display: inline-flex; align-items: center; gap: 0.35rem;
}
.section-nav-link:hover { background: var(--bg-secondary); color: var(--text-primary); }
.section-nav-link.active {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; box-shadow: 0 4px 12px rgba(10,104,71,0.25);
}

/* ===== WHY CHOOSE US ===== */
.why-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem; margin-bottom: 4rem;
}
.why-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; text-align: center;
    box-shadow: var(--shadow-lg); transition: all 0.4s; position: relative; overflow: hidden;
}
.why-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--why-color, var(--primary)), transparent);
    transform: scaleX(0); transition: transform 0.4s; transform-origin: left;
}
.why-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--why-color, var(--primary)); }
.why-card:hover::before { transform: scaleX(1); }
.why-icon { font-size: 2.5rem; margin-bottom: 1rem; transition: transform 0.3s; }
.why-card:hover .why-icon { transform: scale(1.15) rotate(-8deg); }
.why-title { font-family: var(--font-display); font-size: 1.2rem; font-weight: 800; margin-bottom: 0.5rem; }
.why-desc { font-size: 0.88rem; color: var(--text-secondary); line-height: 1.6; }

/* ===== ALUR (Connected Steps) ===== */
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
    background: var(--bg-primary); border: 2px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem 1.5rem; text-align: center;
    transition: all 0.4s; position: relative; cursor: pointer;
}
.step-card-extreme:hover { transform: translateY(-10px); border-color: var(--primary); box-shadow: var(--shadow-xl); }
.step-number-extreme {
    width: 60px; height: 60px; background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 1.75rem; font-weight: 900; margin: 0 auto 1.25rem;
    border: 4px solid var(--bg-primary); box-shadow: 0 4px 15px rgba(10,104,71,0.3);
    transition: all 0.3s;
}
.step-card-extreme:hover .step-number-extreme { transform: scale(1.1) rotate(360deg); }
.step-icon-extreme { font-size: 2rem; margin-bottom: 0.75rem; }
.step-card-extreme h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 0.75rem; }
.step-card-extreme p { font-size: 0.88rem; color: var(--text-secondary); line-height: 1.6; }
.step-duration {
    display: inline-flex; align-items: center; gap: 0.3rem; margin-top: 0.85rem;
    padding: 0.3rem 0.75rem; background: var(--bg-secondary); border-radius: 999px;
    font-size: 0.72rem; font-weight: 700; color: var(--text-muted);
}

/* ===== GELOMBANG TIMELINE ===== */
.gelombang-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem; position: relative;
}
.gelombang-card {
    background: var(--bg-primary); border: 2px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; position: relative;
    transition: all 0.4s; overflow: hidden;
}
.gelombang-card.active {
    border-color: var(--primary);
    box-shadow: 0 10px 40px rgba(10,104,71,0.15);
}
.gelombang-card.active::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
}
.gelombang-card.closed { opacity: 0.6; }
.gelombang-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-xl); }
.gelombang-status {
    position: absolute; top: 1.25rem; right: 1.25rem;
    padding: 0.3rem 0.85rem; border-radius: 999px;
    font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;
}
.status-open { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
.status-upcoming { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
.status-closed { background: #f3f4f6; color: #4b5563; border: 1px solid #d1d5db; }
.gelombang-name {
    font-family: var(--font-display); font-size: 1.35rem; font-weight: 800;
    margin-bottom: 0.5rem; color: var(--text-primary);
}
.gelombang-date {
    display: flex; align-items: center; gap: 0.4rem; font-size: 0.88rem;
    color: var(--text-secondary); font-weight: 600; margin-bottom: 1rem;
}
.gelombang-discount {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.4rem 0.9rem; background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: white; border-radius: 999px; font-size: 0.78rem; font-weight: 800;
    margin-bottom: 1rem;
}
.gelombang-note { font-size: 0.85rem; color: var(--text-secondary); line-height: 1.6; }

/* ===== JALUR TABS + COMPARE ===== */
.jalur-tabs { display: flex; justify-content: center; gap: 0.5rem; margin-bottom: 2rem; flex-wrap: wrap; }
.jalur-tab {
    padding: 0.75rem 1.5rem; border-radius: 999px; border: 2px solid var(--border);
    background: var(--bg-primary); color: var(--text-secondary); font-size: 0.92rem;
    font-weight: 700; cursor: pointer; transition: all 0.3s;
    display: flex; align-items: center; gap: 0.5rem; font-family: inherit;
}
.jalur-tab:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); }
.jalur-tab.active { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 4px 12px rgba(10,104,71,0.25); }

.jalur-panel { display: none; animation: fadeIn 0.5s ease; }
.jalur-panel.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
.jalur-content {
    display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; align-items: start;
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2.5rem;
}
.jalur-info h3 {
    font-family: var(--font-display); font-size: 1.65rem; margin-bottom: 1rem;
    display: flex; align-items: center; gap: 0.75rem;
}
.jalur-info p { color: var(--text-secondary); line-height: 1.7; margin-bottom: 1.5rem; }
.jalur-benefit { margin-bottom: 1.5rem; }
.jalur-benefit h4 { font-size: 0.95rem; margin-bottom: 0.75rem; color: var(--text-primary); }
.jalur-benefit ul { list-style: none; padding: 0; }
.jalur-benefit li {
    padding: 0.5rem 0; font-size: 0.88rem; color: var(--text-secondary);
    display: flex; align-items: center; gap: 0.5rem;
}
.jalur-benefit li::before { content: '✨'; font-size: 0.85rem; }
.jalur-syarat {
    background: var(--bg-secondary); padding: 1.5rem; border-radius: var(--radius-lg);
    border-left: 4px solid var(--primary);
}
.jalur-syarat h4 { font-size: 1.05rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
.jalur-syarat ul { padding-left: 1.25rem; color: var(--text-secondary); }
.jalur-syarat li { margin-bottom: 0.75rem; line-height: 1.6; font-size: 0.92rem; }

/* Compare toggle */
.compare-toggle-wrap { text-align: center; margin-top: 1.5rem; }

/* Compare table */
.compare-table-wrap {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); overflow-x: auto; box-shadow: var(--shadow-sm);
    margin-top: 1.5rem; display: none;
}
.compare-table-wrap.show { display: block; animation: fadeIn 0.4s ease; }
.compare-table { width: 100%; border-collapse: collapse; min-width: 700px; }
.compare-table th, .compare-table td {
    padding: 1rem 1.25rem; text-align: left; vertical-align: top;
    border-bottom: 1px solid var(--border); font-size: 0.9rem;
}
.compare-table thead th {
    background: var(--bg-secondary); font-size: 0.75rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);
}
.compare-table thead th.jalur-head { text-align: center; font-size: 0.95rem; color: var(--text-primary); }
.compare-table td:first-child { font-weight: 700; color: var(--text-primary); background: var(--bg-secondary); min-width: 160px; }
.compare-table td { color: var(--text-secondary); }
.compare-table td.center { text-align: center; }
.compare-jalur-icon { font-size: 1.75rem; display: block; margin-bottom: 0.35rem; }

/* ===== BIAYA: PRICING + CALCULATOR ===== */
.biaya-layout {
    display: grid; grid-template-columns: 1.2fr 1fr; gap: 2rem; align-items: start;
}
.biaya-grid { display: grid; grid-template-columns: 1fr; gap: 1.25rem; }
.biaya-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.75rem; transition: all 0.4s;
    position: relative; overflow: hidden; display: flex; gap: 1.25rem; align-items: flex-start;
}
.biaya-card.featured {
    border-color: var(--primary);
    background: linear-gradient(135deg, rgba(10,104,71,0.04) 0%, var(--bg-primary) 100%);
    box-shadow: var(--shadow-lg);
}
.biaya-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-xl); }
.biaya-icon-box {
    width: 56px; height: 56px; border-radius: 14px; flex-shrink: 0;
    background: var(--bg-secondary); display: flex; align-items: center;
    justify-content: center; font-size: 1.75rem; border: 1px solid var(--border);
}
.biaya-card.featured .biaya-icon-box {
    background: linear-gradient(135deg, var(--primary), var(--primary-light)); border: none;
}
.biaya-info { flex: 1; }
.biaya-title { font-family: var(--font-display); font-size: 1.1rem; font-weight: 800; margin-bottom: 0.35rem; }
.biaya-price { font-size: 1.65rem; font-weight: 900; color: var(--primary); margin-bottom: 0.65rem; }
.biaya-price small { font-size: 0.85rem; color: var(--text-muted); font-weight: 500; }
.biaya-features { list-style: none; padding: 0; margin: 0; }
.biaya-features li {
    padding: 0.4rem 0; font-size: 0.85rem; color: var(--text-secondary);
    display: flex; align-items: flex-start; gap: 0.5rem; line-height: 1.5;
}
.biaya-features li::before { content: '✅'; font-size: 0.75rem; margin-top: 0.15rem; }
.biaya-popular-tag {
    position: absolute; top: 1rem; right: -2rem;
    background: var(--primary); color: white; padding: 0.2rem 2.5rem;
    font-size: 0.65rem; font-weight: 800; transform: rotate(45deg); letter-spacing: 0.1em;
}

/* Calculator */
.calc-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg);
    position: sticky; top: 140px;
}
.calc-card h3 {
    font-family: var(--font-display); font-size: 1.25rem; margin-bottom: 0.5rem;
    display: flex; align-items: center; gap: 0.5rem;
}
.calc-card > p { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem; }
.calc-field { margin-bottom: 1.15rem; }
.calc-field label {
    display: block; font-size: 0.78rem; font-weight: 700; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.4rem;
}
.calc-field select, .calc-field input {
    width: 100%; padding: 0.7rem 1rem; border: 2px solid var(--border);
    border-radius: var(--radius-md); font-family: inherit; font-size: 0.9rem;
    background: var(--bg-secondary); color: var(--text-primary); transition: all 0.3s;
}
.calc-field select:focus, .calc-field input:focus {
    outline: none; border-color: var(--primary); background: var(--bg-primary);
    box-shadow: 0 0 0 4px rgba(10,104,71,0.1);
}
.calc-radio-group { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.calc-radio {
    flex: 1; min-width: 80px; padding: 0.6rem 0.75rem; border: 2px solid var(--border);
    border-radius: var(--radius-md); background: var(--bg-secondary);
    text-align: center; cursor: pointer; transition: all 0.2s;
    font-size: 0.82rem; font-weight: 600; color: var(--text-secondary);
}
.calc-radio:hover { border-color: var(--primary); }
.calc-radio.active {
    background: var(--primary); color: white; border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(10,104,71,0.25);
}
.calc-result {
    background: var(--bg-secondary); border: 1px dashed var(--border);
    border-radius: var(--radius-lg); padding: 1.25rem; margin-top: 1.5rem;
}
.calc-result-row {
    display: flex; justify-content: space-between; padding: 0.45rem 0;
    font-size: 0.88rem; color: var(--text-secondary);
    border-bottom: 1px dashed var(--border);
}
.calc-result-row:last-of-type { border-bottom: none; }
.calc-result-row .val { font-weight: 700; color: var(--text-primary); }
.calc-result-row.discount .val { color: #16a34a; }
.calc-total-row {
    display: flex; justify-content: space-between; align-items: center;
    margin-top: 0.85rem; padding-top: 0.85rem; border-top: 2px solid var(--border);
}
.calc-total-label { font-weight: 800; font-size: 0.95rem; }
.calc-total-value {
    font-family: var(--font-display); font-size: 1.5rem; font-weight: 900; color: var(--primary);
}
.calc-cicilan-note {
    margin-top: 0.85rem; padding: 0.75rem 1rem; border-radius: var(--radius-md);
    background: linear-gradient(135deg, rgba(245,158,11,0.1), rgba(245,158,11,0.05));
    border: 1px solid rgba(245,158,11,0.3); font-size: 0.82rem;
    color: var(--text-secondary); display: flex; align-items: center; gap: 0.5rem;
}
.calc-cicilan-note strong { color: #d97706; }

/* ===== CHECKLIST ===== */
.checklist-layout {
    display: grid; grid-template-columns: 1fr 340px; gap: 2rem; align-items: start;
}
.checklist-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-sm);
}
.checklist-item {
    display: flex; align-items: flex-start; gap: 0.85rem; padding: 0.9rem 1rem;
    border: 1px solid var(--border); border-radius: var(--radius-md);
    margin-bottom: 0.65rem; cursor: pointer; transition: all 0.2s;
    background: var(--bg-secondary);
}
.checklist-item:hover { border-color: var(--primary); transform: translateX(3px); }
.checklist-item.checked {
    background: rgba(10,104,71,0.05); border-color: var(--primary);
}
.checklist-item.checked .checklist-label { text-decoration: line-through; color: var(--text-muted); }
.checklist-box {
    width: 24px; height: 24px; border-radius: 7px; border: 2px solid var(--border);
    background: var(--bg-primary); flex-shrink: 0; display: flex;
    align-items: center; justify-content: center; font-size: 0.85rem;
    color: transparent; transition: all 0.2s; margin-top: 0.1rem;
}
.checklist-item.checked .checklist-box {
    background: var(--primary); border-color: var(--primary); color: white;
}
.checklist-label { font-size: 0.92rem; color: var(--text-primary); font-weight: 600; line-height: 1.5; }
.checklist-tag {
    display: inline-block; padding: 0.1rem 0.5rem; border-radius: 999px;
    font-size: 0.65rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.05em; margin-left: 0.4rem; vertical-align: middle;
}
.tag-wajib { background: #fee2e2; color: #dc2626; }
.tag-opsional { background: #e0e7ff; color: #4338ca; }

.checklist-progress-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; text-align: center;
    box-shadow: var(--shadow-sm); position: sticky; top: 140px;
}
.progress-ring-wrap { position: relative; width: 140px; height: 140px; margin: 0 auto 1.25rem; }
.progress-ring { transform: rotate(-90deg); }
.progress-ring-bg { fill: none; stroke: var(--bg-tertiary); stroke-width: 10; }
.progress-ring-fill {
    fill: none; stroke: var(--primary); stroke-width: 10; stroke-linecap: round;
    stroke-dasharray: 408; stroke-dashoffset: 408; transition: stroke-dashoffset 0.6s ease;
}
.progress-ring-text {
    position: absolute; inset: 0; display: flex; flex-direction: column;
    align-items: center; justify-content: center;
}
.progress-ring-num { font-family: var(--font-display); font-size: 2rem; font-weight: 900; color: var(--primary); }
.progress-ring-label { font-size: 0.7rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; }
.checklist-actions { display: flex; flex-direction: column; gap: 0.65rem; margin-top: 1.25rem; }

/* ===== FAQ ===== */
.faq-toolbar {
    max-width: 800px; margin: 0 auto 1.5rem; display: flex; gap: 0.75rem;
    flex-wrap: wrap; align-items: center;
}
.faq-search { flex: 1; min-width: 220px; position: relative; }
.faq-search input {
    width: 100%; padding: 0.7rem 1rem 0.7rem 2.6rem;
    border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.9rem; background: var(--bg-primary);
    color: var(--text-primary); transition: all 0.3s;
}
.faq-search input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.faq-search .s-icon { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); pointer-events: none; }
.faq-chips { display: flex; gap: 0.4rem; flex-wrap: wrap; }
.faq-chip {
    padding: 0.45rem 0.9rem; border-radius: 999px; border: 1px solid var(--border);
    background: var(--bg-primary); color: var(--text-secondary); font-size: 0.78rem;
    font-weight: 600; cursor: pointer; transition: all 0.2s; font-family: inherit;
}
.faq-chip:hover { border-color: var(--primary); color: var(--primary); }
.faq-chip.active { background: var(--primary); color: white; border-color: var(--primary); }

.faq-pmb { max-width: 800px; margin: 0 auto; }
.faq-item-pmb {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); margin-bottom: 0.85rem; overflow: hidden; transition: all 0.3s;
}
.faq-item-pmb:hover { border-color: var(--primary-light); }
.faq-item-pmb.open { border-color: var(--primary); box-shadow: var(--shadow-md); }
.faq-question-pmb {
    width: 100%; padding: 1.15rem 1.5rem; background: none; border: none; text-align: left;
    font-family: inherit; font-size: 0.98rem; font-weight: 700; color: var(--text-primary);
    cursor: pointer; display: flex; justify-content: space-between; align-items: center; gap: 1rem;
}
.faq-q-left { display: flex; align-items: center; gap: 0.75rem; }
.faq-kat-tag {
    padding: 0.2rem 0.6rem; border-radius: 999px; background: var(--bg-secondary);
    font-size: 0.68rem; font-weight: 800; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: 0.05em; flex-shrink: 0;
}
.faq-icon-pmb {
    width: 28px; height: 28px; border-radius: 50%; background: var(--bg-secondary);
    display: flex; align-items: center; justify-content: center;
    transition: all 0.3s; flex-shrink: 0; font-size: 1rem;
}
.faq-item-pmb.open .faq-icon-pmb { background: var(--primary); color: white; transform: rotate(45deg); }
.faq-answer-pmb { max-height: 0; overflow: hidden; transition: max-height 0.4s ease; }
.faq-answer-inner-pmb { padding: 0 1.5rem 1.25rem; color: var(--text-secondary); line-height: 1.7; font-size: 0.92rem; }

/* ===== TESTIMONI ===== */
.testi-carousel { position: relative; max-width: 800px; margin: 0 auto; }
.testi-track { overflow: hidden; border-radius: var(--radius-xl); }
.testi-slides { display: flex; transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1); }
.testi-slide { min-width: 100%; padding: 0.5rem; }
.testi-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2.5rem; text-align: center;
    box-shadow: var(--shadow-md); position: relative;
}
.testi-card::before {
    content: '"'; position: absolute; top: 0.5rem; left: 1.5rem;
    font-family: var(--font-display); font-size: 5rem; color: var(--bg-tertiary);
    line-height: 1; pointer-events: none;
}
.testi-avatar {
    width: 72px; height: 72px; border-radius: 50%; margin: 0 auto 1rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; font-weight: 800; border: 4px solid var(--bg-primary);
    box-shadow: 0 4px 15px rgba(10,104,71,0.25);
}
.testi-quote { font-size: 1.05rem; color: var(--text-primary); line-height: 1.8; font-style: italic; margin-bottom: 1.25rem; }
.testi-name { font-weight: 800; font-size: 1rem; color: var(--text-primary); }
.testi-meta { font-size: 0.82rem; color: var(--text-muted); margin-top: 0.25rem; }
.testi-stars { color: #f59e0b; font-size: 1rem; margin-top: 0.5rem; letter-spacing: 0.15em; }
.testi-nav {
    display: flex; justify-content: center; align-items: center; gap: 1rem; margin-top: 1.5rem;
}
.testi-arrow {
    width: 40px; height: 40px; border-radius: 50%; border: 1px solid var(--border);
    background: var(--bg-primary); color: var(--text-secondary); cursor: pointer;
    display: flex; align-items: center; justify-content: center; font-size: 1rem;
    transition: all 0.2s;
}
.testi-arrow:hover { background: var(--primary); color: white; border-color: var(--primary); }
.testi-dots { display: flex; gap: 0.4rem; }
.testi-dot {
    width: 10px; height: 10px; border-radius: 999px; background: var(--bg-tertiary);
    cursor: pointer; transition: all 0.3s; border: none; padding: 0;
}
.testi-dot.active { width: 28px; background: var(--primary); }

/* ===== CTA ===== */
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

/* ===== FLOATING BUTTONS ===== */
.floating-wa {
    position: fixed; bottom: 2rem; right: 2rem; z-index: 9000;
    width: 60px; height: 60px; border-radius: 50%;
    background: linear-gradient(135deg, #25D366, #128C7E);
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 1.75rem; text-decoration: none; box-shadow: 0 8px 25px rgba(37,211,102,0.4);
    transition: all 0.3s; animation: waFloat 3s ease-in-out infinite;
}
.floating-wa:hover { transform: scale(1.1); }
@keyframes waFloat { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
.floating-wa::after {
    content: ''; position: absolute; inset: 0; border-radius: 50%;
    border: 2px solid #25D366; animation: waRing 2s infinite;
}
@keyframes waRing { 0% { transform: scale(1); opacity: 1; } 100% { transform: scale(1.5); opacity: 0; } }

.back-to-top {
    position: fixed; bottom: 2rem; right: 6.5rem; z-index: 9000;
    width: 48px; height: 48px; border-radius: 50%;
    background: var(--bg-primary); border: 1px solid var(--border);
    color: var(--text-secondary); display: flex; align-items: center;
    justify-content: center; font-size: 1.1rem; cursor: pointer;
    box-shadow: var(--shadow-md); transition: all 0.3s;
    opacity: 0; pointer-events: none; transform: translateY(20px);
}
.back-to-top.show { opacity: 1; pointer-events: auto; transform: none; }
.back-to-top:hover { background: var(--primary); color: white; border-color: var(--primary); }

/* ===== MODAL FORM MULTI-STEP ===== */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(15,23,42,0.85);
    backdrop-filter: blur(10px); display: none; align-items: center;
    justify-content: center; z-index: 10000; padding: 1.5rem;
}
.modal-overlay.show { display: flex; }
.modal-content-form {
    background: var(--bg-primary); border-radius: var(--radius-xl);
    width: 100%; max-width: 640px; max-height: 92vh; overflow-y: auto;
    box-shadow: 0 40px 100px rgba(0,0,0,0.4); border: 1px solid var(--border);
    animation: modalPop 0.4s cubic-bezier(0.2,0.9,0.3,1.2);
}
@keyframes modalPop {
    from { transform: translateY(30px) scale(0.96); opacity: 0; }
    to { transform: none; opacity: 1; }
}
.modal-form-header {
    padding: 2rem 2rem 1.5rem; color: white; position: relative;
    background: linear-gradient(135deg, var(--primary), #064e34);
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}
.modal-form-header h2 {
    font-family: var(--font-display); font-size: 1.5rem; font-weight: 900; margin-bottom: 0.35rem;
}
.modal-form-header p { font-size: 0.88rem; opacity: 0.9; }
.modal-close {
    position: absolute; top: 1rem; right: 1rem;
    width: 38px; height: 38px; border-radius: 50%;
    background: rgba(255,255,255,0.2); border: none; color: white;
    cursor: pointer; font-size: 1.05rem; z-index: 2;
    display: flex; align-items: center; justify-content: center; transition: all 0.2s;
}
.modal-close:hover { background: #dc2626; transform: rotate(90deg); }

.form-progress {
    display: flex; gap: 0.5rem; padding: 1.5rem 2rem 0;
}
.form-progress-step {
    flex: 1; text-align: center; position: relative;
}
.form-progress-step::before {
    content: ''; position: absolute; top: 14px; left: -50%; width: 100%; height: 3px;
    background: var(--bg-tertiary); z-index: 0;
}
.form-progress-step:first-child::before { display: none; }
.form-progress-step.done::before, .form-progress-step.active::before { background: var(--primary); }
.form-progress-dot {
    width: 30px; height: 30px; border-radius: 50%; margin: 0 auto 0.4rem;
    background: var(--bg-tertiary); color: var(--text-muted);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.82rem; font-weight: 800; position: relative; z-index: 1;
    transition: all 0.3s;
}
.form-progress-step.active .form-progress-dot {
    background: var(--primary); color: white; box-shadow: 0 0 0 5px rgba(10,104,71,0.15);
}
.form-progress-step.done .form-progress-dot { background: var(--primary); color: white; }
.form-progress-label { font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.form-progress-step.active .form-progress-label { color: var(--primary); }

.form-body { padding: 1.75rem 2rem; }
.form-step { display: none; animation: fadeIn 0.4s ease; }
.form-step.active { display: block; }
.form-group { margin-bottom: 1.15rem; }
.form-group label {
    display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-primary);
    margin-bottom: 0.4rem;
}
.form-group label .req { color: #dc2626; }
.form-group input, .form-group select, .form-group textarea {
    width: 100%; padding: 0.75rem 1rem; border: 2px solid var(--border);
    border-radius: var(--radius-md); font-family: inherit; font-size: 0.92rem;
    background: var(--bg-secondary); color: var(--text-primary); transition: all 0.3s;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    outline: none; border-color: var(--primary); background: var(--bg-primary);
    box-shadow: 0 0 0 4px rgba(10,104,71,0.1);
}
.form-group input.error, .form-group select.error { border-color: #dc2626; }
.form-error-msg { font-size: 0.75rem; color: #dc2626; margin-top: 0.3rem; display: none; }
.form-group.has-error .form-error-msg { display: block; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

.form-summary {
    background: var(--bg-secondary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.25rem; margin-bottom: 1.25rem;
}
.form-summary-row {
    display: flex; justify-content: space-between; padding: 0.4rem 0;
    font-size: 0.88rem; border-bottom: 1px dashed var(--border);
}
.form-summary-row:last-child { border-bottom: none; }
.form-summary-row .lbl { color: var(--text-muted); }
.form-summary-row .val { font-weight: 700; color: var(--text-primary); text-align: right; }

.form-consent {
    display: flex; gap: 0.75rem; align-items: flex-start; padding: 1rem;
    background: var(--bg-secondary); border-radius: var(--radius-md);
    border: 1px solid var(--border); cursor: pointer;
}
.form-consent input { margin-top: 0.2rem; accent-color: var(--primary); width: 18px; height: 18px; flex-shrink: 0; }
.form-consent span { font-size: 0.85rem; color: var(--text-secondary); line-height: 1.6; }

.form-footer {
    padding: 1.25rem 2rem; border-top: 1px solid var(--border);
    display: flex; justify-content: space-between; gap: 0.75rem;
    background: var(--bg-secondary); border-radius: 0 0 var(--radius-xl) var(--radius-xl);
}
.form-btn {
    padding: 0.75rem 1.5rem; border-radius: var(--radius-md); border: none;
    font-weight: 700; font-size: 0.9rem; cursor: pointer; font-family: inherit;
    display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.2s;
}
.form-btn.primary { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; }
.form-btn.primary:hover { filter: brightness(1.1); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(10,104,71,0.3); }
.form-btn.secondary { background: var(--bg-tertiary); color: var(--text-primary); }
.form-btn.wa { background: linear-gradient(135deg, #25D366, #128C7E); color: white; }
.form-btn.wa:hover { filter: brightness(1.1); transform: translateY(-2px); }

/* ===== TOAST ===== */
.pub-toast {
    position: fixed; bottom: 2rem; left: 50%; transform: translateX(-50%) translateY(150%);
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: 999px; padding: 0.85rem 1.5rem;
    box-shadow: var(--shadow-xl); display: flex; align-items: center;
    gap: 0.65rem; z-index: 10002;
    transition: transform 0.4s cubic-bezier(0.4,0,0.2,1);
    max-width: 90%; font-size: 0.9rem; font-weight: 600;
}
.pub-toast.show { transform: translateX(-50%) translateY(0); }
.pub-toast-icon { font-size: 1.1rem; }

@media (max-width: 968px) {
    .alur-container::before { display: none; }
    .alur-steps-extreme { grid-template-columns: 1fr 1fr; }
    .jalur-content { grid-template-columns: 1fr; }
    .biaya-layout { grid-template-columns: 1fr; }
    .calc-card { position: static; }
    .checklist-layout { grid-template-columns: 1fr; }
    .checklist-progress-card { position: static; }
}
@media (max-width: 640px) {
    .alur-steps-extreme { grid-template-columns: 1fr; }
    .countdown-wrapper { gap: 0.5rem; }
    .countdown-box { min-width: 65px; padding: 0.75rem; }
    .countdown-num { font-size: 1.75rem; }
    .pmb-stats-bar { grid-template-columns: 1fr 1fr; margin: -3rem 1rem 2rem; }
    .form-row { grid-template-columns: 1fr; }
    .form-body, .form-footer { padding-left: 1.25rem; padding-right: 1.25rem; }
    .form-progress { padding-left: 1.25rem; padding-right: 1.25rem; }
    .floating-wa { bottom: 1.25rem; right: 1.25rem; width: 54px; height: 54px; }
    .back-to-top { right: auto; left: 1.25rem; bottom: 1.25rem; }
    .section-nav { top: 60px; }
}
@media (max-width: 480px) {
    .pmb-stats-bar { grid-template-columns: 1fr; }
}
</style>

<!-- ===== HERO ===== -->
<section class="pmb-hero-extreme">
    <div class="hero-particles" id="heroParticles"></div>
    <div class="container pmb-hero-content">
<span class="hero-badge-pill" data-aos="fade-down">
    <span class="pulse-dot"></span>
    <?= sanitize($active_gelombang['name']) ?> Tahun <?= sanitize($pmb_config['tahun_akademik']) ?>
    — <?= $gelombang_status_label ?>
</span>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 6vw, 4.5rem); font-weight: 900; margin-bottom: 1rem; letter-spacing: -0.02em;" data-aos="fade-up">
            Wujudkan Impian Menjadi
            <span style="background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">Pendidik Profesional</span>
        </h1>
        <p class="page-subtitle" style="max-width: 700px; margin: 0 auto 1.5rem; opacity: 0.95; font-size: 1.15rem; line-height: 1.7;" data-aos="fade-up" data-aos-delay="100">
            Bergabunglah dengan FKIP UNIMOF dan jadilah bagian dari generasi yang membangun peradaban Indonesia Timur melalui pendidikan yang berkualitas dan berkarakter.
        </p>

        <!-- Countdown -->
        <div data-aos="fade-up" data-aos-delay="200">
            <div class="countdown-caption">
                ⏰ <span id="countdownCaption">Pendaftaran <?= sanitize($active_gelombang['name']) ?> ditutup dalam:</span>
            </div>
            <div class="countdown-wrapper" style="margin: 0 0 1.5rem;">
                <div class="countdown-box"><div class="countdown-num" id="cd-days">00</div><div class="countdown-label">Hari</div></div>
                <div class="countdown-box"><div class="countdown-num" id="cd-hours">00</div><div class="countdown-label">Jam</div></div>
                <div class="countdown-box"><div class="countdown-num" id="cd-minutes">00</div><div class="countdown-label">Menit</div></div>
                <div class="countdown-box"><div class="countdown-num" id="cd-seconds">00</div><div class="countdown-label">Detik</div></div>
            </div>
        </div>

        <!-- Quota bar -->
        <div class="quota-bar-wrap" data-aos="fade-up" data-aos-delay="250">
            <div class="quota-bar-head">
                <span>🔥 Kuota Terisi</span>
                <span><?= $pmb_config['kuota_terisi'] ?> / <?= $pmb_config['kuota_total'] ?> (<?= $persen_terisi ?>%)</span>
            </div>
            <div class="quota-bar-track">
                <div class="quota-bar-fill" id="quotaBarFill" data-width="<?= $persen_terisi ?>"></div>
            </div>
            <div style="font-size: 0.78rem; opacity: 0.85; margin-top: 0.5rem; text-align: center;">
                ⚡ Sisa <strong><?= $kuota_sisa ?> kursi</strong> — segera amankan tempatmu!
            </div>
        </div>

        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; margin-top: 2rem;" data-aos="fade-up" data-aos-delay="300">
            <button class="btn btn-lg" style="background: white; color: var(--primary); font-weight: 800; border: none; cursor: pointer; font-family: inherit;" onclick="openFormModal()">
                📝 Daftar Sekarang
            </button>
            <a href="https://wa.me/<?= $pmb_config['wa_number'] ?>?text=Halo%20Admin%20PMB%20FKIP%20UNIMOF,%20saya%20ingin%20bertanya%20tentang%20pendaftaran." target="_blank" class="btn btn-outline btn-lg" style="border-color: white; color: white;">
                💬 Tanya Admin via WhatsApp
            </a>
        </div>
    </div>
</section>

<!-- ===== STATS BAR ===== -->
<div class="container">
    <div class="pmb-stats-bar" data-aos="fade-up">
        <div class="pmb-stat-card" style="--stat-color: #0a6847;">
            <div class="pmb-stat-icon">🎓</div>
            <div class="pmb-stat-num count-up" data-target="<?= count($prodi_options) ?>">0</div>
            <div class="pmb-stat-label">Program Studi</div>
        </div>
        <div class="pmb-stat-card" style="--stat-color: #f59e0b;">
            <div class="pmb-stat-icon">🔥</div>
            <div class="pmb-stat-num count-up" data-target="<?= $pmb_config['kuota_terisi'] ?>">0</div>
            <div class="pmb-stat-label">Pendaftar</div>
        </div>
        <div class="pmb-stat-card" style="--stat-color: #3b82f6;">
            <div class="pmb-stat-icon">💺</div>
            <div class="pmb-stat-num count-up" data-target="<?= $kuota_sisa ?>">0</div>
            <div class="pmb-stat-label">Sisa Kuota</div>
        </div>
        <div class="pmb-stat-card" style="--stat-color: #8b5cf6;">
            <div class="pmb-stat-icon">💰</div>
            <div class="pmb-stat-num count-up" data-target="4">0</div>
            <div class="pmb-stat-label">Jenis Beasiswa</div>
        </div>
        <div class="pmb-stat-card" style="--stat-color: #10b981;">
            <div class="pmb-stat-icon">📅</div>
            <div class="pmb-stat-num count-up" data-target="<?= count($gelombang_list) ?>">0</div>
            <div class="pmb-stat-label">Gelombang</div>
        </div>
    </div>
</div>

<!-- ===== MAIN ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">

        <!-- Sticky Section Nav -->
        <nav class="section-nav" id="sectionNav" data-aos="fade-up">
            <a href="#why" class="section-nav-link active">✨ Keunggulan</a>
            <a href="#alur" class="section-nav-link"> Alur</a>
            <a href="#gelombang" class="section-nav-link">📅 Gelombang</a>
            <a href="#jalur" class="section-nav-link"> Jalur</a>
            <a href="#biaya" class="section-nav-link">💰 Biaya</a>
            <a href="#berkas" class="section-nav-link">📋 Berkas</a>
            <a href="#faq" class="section-nav-link">❓ FAQ</a>
            <a href="#testimoni" class="section-nav-link">💬 Testimoni</a>
        </nav>

        <!-- WHY CHOOSE US -->
        <div id="why" class="scroll-section">
            <div class="section-header" data-aos="fade-up">
                <span class="section-tag">Kenapa FKIP UNIMOF?</span>
                <h2 class="section-title">Alasan <span class="gradient-text">Tepat Memilih Kami</span></h2>
            </div>
            <div class="why-grid">
                <div class="why-card" style="--why-color: #0a6847;" data-aos="fade-up">
                    <div class="why-icon">🎓</div>
                    <h3 class="why-title">Akreditasi Unggul</h3>
                    <p class="why-desc">Program studi kami telah terakreditasi dengan predikat terbaik dari BAN-PT.</p>
                </div>
                <div class="why-card" style="--why-color: #f59e0b;" data-aos="fade-up" data-aos-delay="100">
                    <div class="why-icon">💰</div>
                    <h3 class="why-title">Biaya Terjangkau</h3>
                    <p class="why-desc">Tersedia berbagai program beasiswa dan fasilitas cicilan tanpa bunga hingga 6x.</p>
                </div>
                <div class="why-card" style="--why-color: #3b82f6;" data-aos="fade-up" data-aos-delay="200">
                    <div class="why-icon">💼</div>
                    <h3 class="why-title">Siap Kerja</h3>
                    <p class="why-desc">Kurikulum berbasis MBKM dengan program magang di sekolah-sekolah mitra.</p>
                </div>
                <div class="why-card" style="--why-color: #8b5cf6;" data-aos="fade-up" data-aos-delay="300">
                    <div class="why-icon">🕌</div>
                    <h3 class="why-title">Lingkungan Islami</h3>
                    <p class="why-desc">Pembentukan karakter berlandaskan nilai-nilai keislaman dan kemuhammadiyahan.</p>
                </div>
            </div>
        </div>

        <!-- ALUR -->
        <div id="alur" class="scroll-section" style="padding: 3rem 0;">
            <div class="section-header" data-aos="fade-up">
                <span class="section-tag">Langkah Mudah</span>
                <h2 class="section-title">Alur <span class="gradient-text">Pendaftaran</span></h2>
                <p class="section-desc">Proses pendaftaran yang simpel dan transparan, bisa dilakukan secara online dari mana saja.</p>
            </div>
            <div class="alur-container">
                <div class="alur-steps-extreme">
                    <div class="step-card-extreme" data-aos="fade-up">
                        <div class="step-number-extreme">1</div>
                        <div class="step-icon-extreme">📝</div>
                        <h3>Isi Formulir Online</h3>
                        <p>Daftar melalui website atau datang langsung ke kampus untuk mengisi formulir pendaftaran.</p>
                        <span class="step-duration">⏱️ ± 10 menit</span>
                    </div>
                    <div class="step-card-extreme" data-aos="fade-up" data-aos-delay="100">
                        <div class="step-number-extreme">2</div>
                        <div class="step-icon-extreme">📤</div>
                        <h3>Upload Berkas</h3>
                        <p>Unggah scan Ijazah/SKL, KK, KTP, Pas Foto, dan dokumen pendukung lainnya.</p>
                        <span class="step-duration">⏱️ ± 15 menit</span>
                    </div>
                    <div class="step-card-extreme" data-aos="fade-up" data-aos-delay="200">
                        <div class="step-number-extreme">3</div>
                        <div class="step-icon-extreme">💳</div>
                        <h3>Pembayaran</h3>
                        <p>Lakukan pembayaran biaya pendaftaran melalui Virtual Account bank mitra atau transfer.</p>
                        <span class="step-duration">⏱️ ± 5 menit</span>
                    </div>
                    <div class="step-card-extreme" data-aos="fade-up" data-aos-delay="300">
                        <div class="step-number-extreme">4</div>
                        <div class="step-icon-extreme">🎉</div>
                        <h3>Tes & Pengumuman</h3>
                        <p>Ikuti tes potensi akademik (atau wawancara) dan pantau hasil kelulusan di website.</p>
                        <span class="step-duration">⏱️ 3-7 hari kerja</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- GELOMBANG -->
        <div id="gelombang" class="scroll-section" style="padding: 3rem 0;">
            <div class="section-header" data-aos="fade-up">
                <span class="section-tag">Jadwal Penting</span>
                <h2 class="section-title">Gelombang <span class="gradient-text">Pendaftaran</span></h2>
                <p class="section-desc">Daftar lebih awal, dapatkan keuntungan lebih besar.</p>
            </div>
            <div class="gelombang-grid">
                <?php foreach ($gelombang_list as $g):
                    $status = 'upcoming';
                    if ($now >= strtotime($g['start'] . ' 00:00:00') && $now <= strtotime($g['end'] . ' 23:59:59')) $status = 'open';
                    elseif ($now > strtotime($g['end'] . ' 23:59:59')) $status = 'closed';
                    $status_label = ['open' => '🟢 Sedang Berlangsung', 'upcoming' => '🟡 Segera Dibuka', 'closed' => '⚪ Telah Ditutup'][$status];
                ?>
                <div class="gelombang-card <?= $status === 'open' ? 'active' : '' ?> <?= $status === 'closed' ? 'closed' : '' ?>" data-aos="fade-up">
                    <span class="gelombang-status status-<?= $status ?>"><?= $status_label ?></span>
                    <h3 class="gelombang-name"><?= sanitize($g['name']) ?></h3>
                    <div class="gelombang-date">
                        📅 <?= date('d M Y', strtotime($g['start'])) ?> — <?= date('d M Y', strtotime($g['end'])) ?>
                    </div>
                    <?php if ($g['discount'] > 0): ?>
                        <div class="gelombang-discount">🎁 Diskon <?= $g['discount'] ?>% Uang Pangkal</div>
                    <?php else: ?>
                        <div class="gelombang-discount" style="background: linear-gradient(135deg, #94a3b8, #64748b);">📌 Kuota Terakhir</div>
                    <?php endif; ?>
                    <p class="gelombang-note"><?= sanitize($g['note']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- JALUR -->
        <div id="jalur" class="scroll-section" style="padding: 3rem 0;">
            <div class="section-header" data-aos="fade-up">
                <span class="section-tag">Pilihan Jalur</span>
                <h2 class="section-title">Jalur <span class="gradient-text">Pendaftaran</span></h2>
                <p class="section-desc">Pilih jalur yang paling sesuai dengan potensi dan kondisi Anda.</p>
            </div>

            <div class="jalur-tabs" data-aos="fade-up">
                <?php foreach ($jalur_pmb as $key => $jalur): ?>
                <button class="jalur-tab <?= $key === 'reguler' ? 'active' : '' ?>" onclick="switchJalur('<?= $key ?>', this)">
                    <span><?= $jalur['icon'] ?></span> <?= $jalur['title'] ?>
                </button>
                <?php endforeach; ?>
            </div>

            <?php foreach ($jalur_pmb as $key => $jalur): ?>
            <div id="jalur-<?= $key ?>" class="jalur-panel <?= $key === 'reguler' ? 'active' : '' ?>">
                <div class="jalur-content">
                    <div class="jalur-info">
                        <h3><span style="font-size: 2rem;"><?= $jalur['icon'] ?></span> <?= $jalur['title'] ?></h3>
                        <p><?= sanitize($jalur['desc']) ?></p>
                        <div class="jalur-benefit">
                            <h4>✨ Keuntungan Jalur Ini</h4>
                            <ul>
                                <?php foreach ($jalur['benefit'] as $b): ?>
                                <li><?= sanitize($b) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div style="display:flex; gap:0.65rem; flex-wrap:wrap;">
                            <button class="btn btn-primary" onclick="openFormModal('<?= $key ?>')">Daftar Jalur Ini →</button>
                            <a href="https://wa.me/<?= $pmb_config['wa_number'] ?>?text=Halo,%20saya%20tertarik%20dengan%20<?= urlencode($jalur['title']) ?>%20FKIP%20UNIMOF" target="_blank" class="btn btn-secondary">💬 Konsultasi</a>
                        </div>
                    </div>
                    <div class="jalur-syarat" style="border-left-color: <?= $jalur['color'] ?>;">
                        <h4>📋 Persyaratan Khusus</h4>
                        <ul>
                            <?php foreach ($jalur['syarat'] as $syarat): ?>
                            <li><?= sanitize($syarat) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <div style="margin-top:1.25rem; padding-top:1rem; border-top:1px dashed var(--border); display:flex; justify-content:space-between; font-size:0.85rem;">
                            <span style="color:var(--text-muted);">Kuota jalur:</span>
                            <strong style="color:<?= $jalur['color'] ?>;"><?= $jalur['kuota'] ?> mahasiswa</strong>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Compare -->
            <div class="compare-toggle-wrap" data-aos="fade-up">
                <button class="btn btn-secondary" onclick="toggleCompareJalur()" id="compareToggleBtn">
                    ⚖️ Bandingkan Semua Jalur
                </button>
            </div>
            <div class="compare-table-wrap" id="compareTableWrap">
                <table class="compare-table">
                    <thead>
                        <tr>
                            <th>Atribut</th>
                            <?php foreach ($jalur_pmb as $j): ?>
                            <th class="jalur-head">
                                <span class="compare-jalur-icon"><?= $j['icon'] ?></span>
                                <?= sanitize($j['title']) ?>
                            </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Seleksi</td>
                            <td class="center">TPA + Administrasi</td>
                            <td class="center">Wawancara Portofolio</td>
                            <td class="center">Berkas + Wawancara</td>
                        </tr>
                        <tr>
                            <td>Diskon Uang Pangkal</td>
                            <?php foreach ($jalur_pmb as $j): ?>
                            <td class="center"><strong style="color:<?= $j['diskon_pangkal'] > 0 ? '#16a34a' : 'var(--text-muted)' ?>;"><?= $j['diskon_pangkal'] > 0 ? $j['diskon_pangkal'] . '%' : '—' ?></strong></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td>Diskon SPP</td>
                            <?php foreach ($jalur_pmb as $j): ?>
                            <td class="center"><strong style="color:<?= $j['diskon_spp'] > 0 ? '#16a34a' : 'var(--text-muted)' ?>;"><?= $j['diskon_spp'] > 0 ? $j['diskon_spp'] . '%' : '—' ?></strong></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td>Kuota</td>
                            <?php foreach ($jalur_pmb as $j): ?>
                            <td class="center"><?= $j['kuota'] ?> mhs</td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td>Syarat Nilai</td>
                            <td class="center">Minimal rata-rata 70</td>
                            <td class="center">Minimal rata-rata 80</td>
                            <td class="center">Nilai baik + SKTM</td>
                        </tr>
                        <tr>
                            <td>Cocok Untuk</td>
                            <td class="center">Semua lulusan</td>
                            <td class="center">Berprestasi lomba/akademik</td>
                            <td class="center">Berprestasi & kurang mampu</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- BIAYA + CALCULATOR -->
        <div id="biaya" class="scroll-section" style="padding: 3rem 0;">
            <div class="section-header" data-aos="fade-up">
                <span class="section-tag">Investasi Masa Depan</span>
                <h2 class="section-title">Estimasi <span class="gradient-text">Biaya Kuliah</span></h2>
                <p class="section-desc">Transparan dan terjangkau, dengan opsi cicilan yang fleksibel.</p>
            </div>

            <div class="biaya-layout">
                <!-- Pricing cards -->
                <div class="biaya-grid" data-aos="fade-up">
                    <div class="biaya-card">
                        <div class="biaya-icon-box">📝</div>
                        <div class="biaya-info">
                            <h3 class="biaya-title">Biaya Pendaftaran</h3>
                            <div class="biaya-price">Rp <?= number_format($pmb_config['biaya_pendaftaran'], 0, ',', '.') ?></div>
                            <ul class="biaya-features">
                                <li>Pengisian formulir online</li>
                                <li>Verifikasi berkas administrasi</li>
                                <li>Pengujian Tes Potensi Akademik</li>
                                <li>Biaya tidak dapat dikembalikan</li>
                            </ul>
                        </div>
                    </div>
                    <div class="biaya-card featured">
                        <span class="biaya-popular-tag">POPULER</span>
                        <div class="biaya-icon-box">🎓</div>
                        <div class="biaya-info">
                            <h3 class="biaya-title">Semester 1 (Total)</h3>
                            <div class="biaya-price">Rp <?= number_format($pmb_config['biaya_pendaftaran'] + $pmb_config['uang_pangkal'] + $pmb_config['spp_per_semester'], 0, ',', '.') ?> <small>/estimasi</small></div>
                            <ul class="biaya-features">
                                <li>Uang Pangkal (Gedung): Rp <?= number_format($pmb_config['uang_pangkal'], 0, ',', '.') ?></li>
                                <li>SPP Semester 1: Rp <?= number_format($pmb_config['spp_per_semester'], 0, ',', '.') ?></li>
                                <li>Termasuk jas almamater & KTM</li>
                                <li><strong>Bisa dicicil 3x - 6x bunga 0%!</strong></li>
                            </ul>
                        </div>
                    </div>
                    <div class="biaya-card">
                        <div class="biaya-icon-box">💰</div>
                        <div class="biaya-info">
                            <h3 class="biaya-title">SPP per Semester</h3>
                            <div class="biaya-price">Rp <?= number_format($pmb_config['spp_per_semester'], 0, ',', '.') ?> <small>/semester</small></div>
                            <ul class="biaya-features">
                                <li>Akses perpustakaan & LMS digital</li>
                                <li>Bimbingan skripsi & wisuda</li>
                                <li>Tidak ada kenaikan signifikan</li>
                                <li>Tersedia beasiswa setiap semester</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Calculator -->
                <div class="calc-card" data-aos="fade-left">
                    <h3>🧮 Kalkulator Biaya</h3>
                    <p>Hitung estimasi biaya personal Anda berdasarkan jalur, gelombang, dan skema cicilan.</p>

                    <div class="calc-field">
                        <label>🎯 Jalur Pendaftaran</label>
                        <select id="calcJalur" onchange="hitungBiaya()">
                            <?php foreach ($jalur_pmb as $key => $j): ?>
                            <option value="<?= $key ?>"><?= $j['icon'] ?> <?= sanitize($j['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="calc-field">
                        <label>📅 Gelombang</label>
                        <select id="calcGelombang" onchange="hitungBiaya()">
                            <?php foreach ($gelombang_list as $i => $g): ?>
                            <option value="<?= $i ?>" <?= $g['name'] === $active_gelombang['name'] ? 'selected' : '' ?>>
                                <?= sanitize($g['name']) ?> <?= $g['discount'] > 0 ? '(−' . $g['discount'] . '%)' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="calc-field">
                        <label>💳 Skema Pembayaran</label>
                        <div class="calc-radio-group" id="calcCicilanGroup">
                            <div class="calc-radio active" data-val="1" onclick="setCicilan(1, this)">Lunas</div>
                            <div class="calc-radio" data-val="3" onclick="setCicilan(3, this)">3x Cicilan</div>
                            <div class="calc-radio" data-val="6" onclick="setCicilan(6, this)">6x Cicilan</div>
                        </div>
                    </div>

                    <div class="calc-result" id="calcResult">
                        <!-- diisi oleh JS -->
                    </div>

                    <button class="btn btn-primary btn-block" style="margin-top: 1.25rem; justify-content: center;" onclick="openFormModal(document.getElementById('calcJalur').value)">
                        📝 Daftar dengan Estimasi Ini
                    </button>
                </div>
            </div>

            <p style="text-align: center; margin-top: 2rem; font-size: 0.88rem; color: var(--text-muted);" data-aos="fade-up">
                * Biaya dapat berubah sesuai kebijakan universitas. Hubungi admin untuk informasi paling akurat.
            </p>
        </div>

        <!-- CHECKLIST BERKAS -->
        <div id="berkas" class="scroll-section" style="padding: 3rem 0;">
            <div class="section-header" data-aos="fade-up">
                <span class="section-tag">Persiapan</span>
                <h2 class="section-title">Checklist <span class="gradient-text">Berkas Pendaftaran</span></h2>
                <p class="section-desc">Centang berkas yang sudah Anda siapkan. Progres tersimpan otomatis di perangkat Anda.</p>
            </div>

            <div class="checklist-layout">
                <div class="checklist-card" data-aos="fade-up">
                    <?php foreach ($checklist_items as $item): ?>
                    <div class="checklist-item" data-id="<?= $item['id'] ?>" onclick="toggleChecklist('<?= $item['id'] ?>', this)">
                        <div class="checklist-box">✓</div>
                        <div class="checklist-label">
                            <?= sanitize($item['label']) ?>
                            <span class="checklist-tag <?= $item['wajib'] ? 'tag-wajib' : 'tag-opsional' ?>">
                                <?= $item['wajib'] ? 'Wajib' : 'Opsional' ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="checklist-progress-card" data-aos="fade-left">
                    <div class="progress-ring-wrap">
                        <svg class="progress-ring" width="140" height="140">
                            <circle class="progress-ring-bg" cx="70" cy="70" r="65"></circle>
                            <circle class="progress-ring-fill" id="progressRingFill" cx="70" cy="70" r="65"></circle>
                        </svg>
                        <div class="progress-ring-text">
                            <div class="progress-ring-num" id="checklistPercent">0%</div>
                            <div class="progress-ring-label">Siap</div>
                        </div>
                    </div>
                    <h3 style="font-size: 1.05rem; margin-bottom: 0.35rem;">Kesiapan Berkas</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">
                        <span id="checklistCount">0</span> dari <?= count($checklist_items) ?> berkas siap
                    </p>
                    <div id="checklistWajibNote" style="font-size:0.78rem; color:#dc2626; font-weight:600; margin-bottom:0.5rem;">
                        ⚠️ <?= count(array_filter($checklist_items, fn($i) => $i['wajib'])) ?> berkas wajib belum lengkap
                    </div>
                    <div class="checklist-actions">
                        <button class="btn btn-primary btn-block" style="justify-content:center;" onclick="openFormModal()" id="checklistDaftarBtn" disabled>
                            📝 Daftar Sekarang
                        </button>
                        <button class="btn btn-secondary btn-block" style="justify-content:center;" onclick="resetChecklist()">
                            🔄 Reset Checklist
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- FAQ -->
        <div id="faq" class="scroll-section" style="padding: 3rem 0;">
            <div class="section-header" data-aos="fade-up">
                <span class="section-tag">Bantuan</span>
                <h2 class="section-title">Pertanyaan <span class="gradient-text">Umum (FAQ)</span></h2>
            </div>

            <div class="faq-toolbar" data-aos="fade-up">
                <div class="faq-search">
                    <span class="s-icon">🔍</span>
                    <input type="text" id="faqSearch" placeholder="Cari pertanyaan...">
                </div>
                <div class="faq-chips" id="faqChips">
                    <button class="faq-chip active" data-kat="all" onclick="filterFaq('all', this)">Semua</button>
                    <?php
                    $faq_kats = array_unique(array_column($faq_pmb, 'kat'));
                    foreach ($faq_kats as $k):
                    ?>
                    <button class="faq-chip" data-kat="<?= sanitize($k) ?>" onclick="filterFaq('<?= sanitize($k) ?>', this)"><?= sanitize($k) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="faq-pmb" id="faqList" data-aos="fade-up">
                <?php foreach ($faq_pmb as $i => $faq): ?>
                <div class="faq-item-pmb" data-kat="<?= sanitize($faq['kat']) ?>" data-text="<?= strtolower(sanitize($faq['q'] . ' ' . $faq['a'])) ?>">
                    <button class="faq-question-pmb" onclick="toggleFaqPmb(this)">
                        <span class="faq-q-left">
                            <span class="faq-kat-tag"><?= sanitize($faq['kat']) ?></span>
                            <span><?= sanitize($faq['q']) ?></span>
                        </span>
                        <span class="faq-icon-pmb">+</span>
                    </button>
                    <div class="faq-answer-pmb">
                        <div class="faq-answer-inner-pmb"><?= sanitize($faq['a']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <div id="faqEmpty" style="display:none; text-align:center; padding:2.5rem; background:var(--bg-secondary); border-radius:var(--radius-xl); border:2px dashed var(--border);">
                    <div style="font-size:3rem; margin-bottom:0.75rem; opacity:0.5;">🔍</div>
                    <h3 style="font-size:1.1rem;">Pertanyaan tidak ditemukan</h3>
                    <p style="color:var(--text-muted); font-size:0.88rem; margin-top:0.35rem;">Coba kata kunci lain atau tanyakan langsung ke admin via WhatsApp.</p>
                </div>
            </div>
        </div>

        <!-- TESTIMONI -->
        <div id="testimoni" class="scroll-section" style="padding: 3rem 0;">
            <div class="section-header" data-aos="fade-up">
                <span class="section-tag">Kata Mereka</span>
                <h2 class="section-title">Testimoni <span class="gradient-text">Mahasiswa Baru</span></h2>
            </div>

            <div class="testi-carousel" data-aos="fade-up">
                <div class="testi-track">
                    <div class="testi-slides" id="testiSlides">
                        <?php foreach ($testimonials as $t):
                            $initials = strtoupper(substr($t['nama'], 0, 1) . (strpos($t['nama'], ' ') ? substr($t['nama'], strpos($t['nama'], ' ') + 1, 1) : ''));
                        ?>
                        <div class="testi-slide">
                            <div class="testi-card">
                                <div class="testi-avatar"><?= $initials ?></div>
                                <p class="testi-quote"><?= sanitize($t['quote']) ?></p>
                                <div class="testi-name"><?= sanitize($t['nama']) ?></div>
                                <div class="testi-meta">🎓 <?= sanitize($t['prodi']) ?> • Angkatan <?= sanitize($t['angkatan']) ?></div>
                                <div class="testi-stars"><?= str_repeat('★', $t['rating']) . str_repeat('☆', 5 - $t['rating']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="testi-nav">
                    <button class="testi-arrow" onclick="moveTesti(-1)">←</button>
                    <div class="testi-dots" id="testiDots"></div>
                    <button class="testi-arrow" onclick="moveTesti(1)">→</button>
                </div>
            </div>
        </div>

        <!-- CTA -->
        <div class="pmb-cta-extreme" data-aos="zoom-in">
            <div class="pmb-cta-content">
                <h2 style="font-family: var(--font-display); font-size: clamp(1.75rem, 3vw, 2.5rem); margin-bottom: 1rem;">Kuota Terbatas! Jangan Sampai Kehabisan.</h2>
                <p style="font-size: 1.1rem; margin-bottom: 2rem; opacity: 0.95;">
                    Bergabunglah dengan ribuan mahasiswa yang telah memilih FKIP UNIMOF sebagai rumah akademik mereka. Wujudkan mimpimu mulai dari sini.
                </p>
                <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                    <button class="btn btn-lg" style="background: white; color: #d97706; font-weight: 800; border: none; cursor: pointer; font-family: inherit;" onclick="openFormModal()">
                        📝 Daftar Sekarang →
                    </button>
                    <a href="https://wa.me/<?= $pmb_config['wa_number'] ?>?text=Halo%20Admin,%20saya%20ingin%20mendaftar%20PMB%20FKIP%20UNIMOF" target="_blank" class="btn btn-outline btn-lg" style="border-color: white; color: white;">
                        💬 Daftar via WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== MODAL FORM MULTI-STEP ===== -->
<div class="modal-overlay" id="formModal" onclick="if(event.target===this)closeFormModal()">
    <div class="modal-content-form">
        <div class="modal-form-header">
            <button class="modal-close" onclick="closeFormModal()">✕</button>
            <h2>📝 Formulir Pendaftaran PMB</h2>
            <p>FKIP UNIMOF — Tahun Akademik <?= sanitize($pmb_config['tahun_akademik']) ?> • <?= sanitize($active_gelombang['name']) ?></p>
        </div>

        <div class="form-progress">
            <div class="form-progress-step active" data-step="1">
                <div class="form-progress-dot">1</div>
                <div class="form-progress-label">Data Diri</div>
            </div>
            <div class="form-progress-step" data-step="2">
                <div class="form-progress-dot">2</div>
                <div class="form-progress-label">Pilihan</div>
            </div>
            <div class="form-progress-step" data-step="3">
                <div class="form-progress-dot">3</div>
                <div class="form-progress-label">Konfirmasi</div>
            </div>
        </div>

        <div class="form-body">
            <!-- STEP 1 -->
            <div class="form-step active" data-step="1">
                <div class="form-group">
                    <label>Nama Lengkap <span class="req">*</span></label>
                    <input type="text" id="fNama" placeholder="Sesuai ijazah/KTP">
                    <div class="form-error-msg">Nama lengkap wajib diisi.</div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>NISN</label>
                        <input type="text" id="fNisn" placeholder="10 digit NISN">
                    </div>
                    <div class="form-group">
                        <label>Asal Sekolah <span class="req">*</span></label>
                        <input type="text" id="fSekolah" placeholder="SMA/SMK/MA ...">
                        <div class="form-error-msg">Asal sekolah wajib diisi.</div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>No. WhatsApp <span class="req">*</span></label>
                        <input type="tel" id="fHp" placeholder="08xxxxxxxxxx">
                        <div class="form-error-msg">Nomor WhatsApp tidak valid.</div>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" id="fEmail" placeholder="nama@email.com">
                        <div class="form-error-msg">Format email tidak valid.</div>
                    </div>
                </div>
            </div>

            <!-- STEP 2 -->
            <div class="form-step" data-step="2">
                <div class="form-group">
                    <label>Program Studi Tujuan <span class="req">*</span></label>
                    <select id="fProdi">
                        <option value="">— Pilih Program Studi —</option>
                        <?php foreach ($prodi_options as $p): ?>
                        <option value="<?= sanitize($p['nama']) ?>"><?= sanitize($p['nama']) ?> (<?= sanitize($p['singkatan']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-error-msg">Silakan pilih program studi.</div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Jalur Pendaftaran <span class="req">*</span></label>
                        <select id="fJalur" onchange="updateFormSummary()">
                            <?php foreach ($jalur_pmb as $key => $j): ?>
                            <option value="<?= $key ?>"><?= $j['icon'] ?> <?= sanitize($j['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Gelombang</label>
                        <select id="fGelombang" disabled>
                            <option><?= sanitize($active_gelombang['name']) ?> (otomatis)</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Pesan / Pertanyaan (opsional)</label>
                    <textarea id="fPesan" rows="3" placeholder="Contoh: Saya ingin bertanya tentang asrama..."></textarea>
                </div>
            </div>

            <!-- STEP 3 -->
            <div class="form-step" data-step="3">
                <div class="form-summary" id="formSummary"></div>
                <label class="form-consent">
                    <input type="checkbox" id="fConsent">
                    <span>Saya menyatakan data yang saya isi adalah benar dan saya bersedia dihubungi oleh panitia PMB FKIP UNIMOF melalui WhatsApp/telepon/email.</span>
                </label>
                <div class="form-error-msg" id="consentError" style="margin-top:0.5rem;">Anda harus menyetujui pernyataan terlebih dahulu.</div>
            </div>
        </div>

        <div class="form-footer">
            <button class="form-btn secondary" id="formBackBtn" onclick="formStep(-1)" style="visibility:hidden;">← Kembali</button>
            <button class="form-btn primary" id="formNextBtn" onclick="formStep(1)">Lanjutkan →</button>
            <button class="form-btn wa" id="formSubmitBtn" onclick="submitFormToWA()" style="display:none;">💬 Kirim via WhatsApp</button>
        </div>
    </div>
</div>

<!-- ===== FLOATING ===== -->
<a href="https://wa.me/<?= $pmb_config['wa_number'] ?>?text=Halo%20Admin%20PMB%20FKIP%20UNIMOF!" target="_blank" class="floating-wa" title="Chat Admin PMB">💬</a>
<button class="back-to-top" id="backToTop" onclick="window.scrollTo({top:0, behavior:'smooth'})" title="Kembali ke atas">↑</button>

<!-- Toast -->
<div class="pub-toast" id="pubToast">
    <span class="pub-toast-icon" id="pubToastIcon">✓</span>
    <span id="pubToastMsg">Berhasil</span>
</div>

<script>
// ===== DATA =====
const PMB = <?= json_encode([
    'wa' => $pmb_config['wa_number'],
    'pendaftaran' => $pmb_config['biaya_pendaftaran'],
    'pangkal' => $pmb_config['uang_pangkal'],
    'spp' => $pmb_config['spp_per_semester'],
    'jalur' => array_map(fn($j) => ['dp' => $j['diskon_pangkal'], 'ds' => $j['diskon_spp'], 'title' => $j['title']], $jalur_pmb),
    'gelombang' => array_map(fn($g) => $g['discount'], $gelombang_list),
    'gelombang_name' => $active_gelombang['name'],
    'deadline' => $deadline_iso,
    'tahun' => $pmb_config['tahun_akademik'],
]) ?>;
const CHECKLIST_IDS = <?= json_encode(array_column($checklist_items, 'id')) ?>;
const CHECKLIST_WAJIB = <?= json_encode(array_column(array_filter($checklist_items, fn($i) => $i['wajib']), 'id')) ?>;

// ===== HERO PARTICLES =====
(function() {
    const container = document.getElementById('heroParticles');
    if (!container) return;
    for (let i = 0; i < 30; i++) {
        const p = document.createElement('div');
        p.className = 'hero-particle';
        p.style.left = Math.random() * 100 + '%';
        p.style.animationDelay = Math.random() * 30 + 's';
        p.style.animationDuration = (25 + Math.random() * 20) + 's';
        p.style.width = p.style.height = (2 + Math.random() * 3) + 'px';
        container.appendChild(p);
    }
})();

// ===== COUNT UP =====
function animateCount(el) {
    const target = parseInt(el.dataset.target) || 0;
    const duration = 1800; const start = performance.now();
    function step(now) {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.floor(eased * target).toLocaleString('id-ID');
        if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
}
const countObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) { animateCount(entry.target); countObserver.unobserve(entry.target); }
    });
}, { threshold: 0.3 });
document.querySelectorAll('.count-up').forEach(el => countObserver.observe(el));

// ===== QUOTA BAR =====
setTimeout(() => {
    const fill = document.getElementById('quotaBarFill');
    if (fill) fill.style.width = fill.dataset.width + '%';
}, 400);

// ===== COUNTDOWN =====
const deadline = new Date(PMB.deadline).getTime();
function updateCountdown() {
    const now = Date.now();
    const distance = deadline - now;
    const els = ['cd-days','cd-hours','cd-minutes','cd-seconds'].map(id => document.getElementById(id));
    if (distance < 0) {
        els.forEach(e => e && (e.innerText = '00'));
        const cap = document.getElementById('countdownCaption');
        if (cap) cap.textContent = '⏰ Pendaftaran gelombang ini telah ditutup. Nantikan gelombang berikutnya!';
        return;
    }
    const d = Math.floor(distance / 86400000);
    const h = Math.floor((distance % 86400000) / 3600000);
    const m = Math.floor((distance % 3600000) / 60000);
    const s = Math.floor((distance % 60000) / 1000);
    els[0].innerText = String(d).padStart(2, '0');
    els[1].innerText = String(h).padStart(2, '0');
    els[2].innerText = String(m).padStart(2, '0');
    els[3].innerText = String(s).padStart(2, '0');
}
setInterval(updateCountdown, 1000);
updateCountdown();

// ===== TOAST =====
function pubToast(msg, icon = '✓') {
    const t = document.getElementById('pubToast');
    document.getElementById('pubToastMsg').textContent = msg;
    document.getElementById('pubToastIcon').textContent = icon;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

// ===== SCROLLSPY + BACK TO TOP =====
const navLinks = document.querySelectorAll('.section-nav-link');
const sections = Array.from(navLinks).map(l => document.querySelector(l.getAttribute('href'))).filter(Boolean);
const spyObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            navLinks.forEach(l => l.classList.toggle('active', l.getAttribute('href') === '#' + entry.target.id));
        }
    });
}, { rootMargin: '-40% 0px -55% 0px' });
sections.forEach(s => spyObserver.observe(s));

navLinks.forEach(l => l.addEventListener('click', (e) => {
    e.preventDefault();
    const target = document.querySelector(l.getAttribute('href'));
    if (target) window.scrollTo({ top: target.offsetTop - 130, behavior: 'smooth' });
}));

window.addEventListener('scroll', () => {
    document.getElementById('backToTop').classList.toggle('show', window.scrollY > 600);
});

// ===== JALUR TABS =====
function switchJalur(key, btn) {
    document.querySelectorAll('.jalur-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.jalur-panel').forEach(p => p.classList.remove('active'));
    if (btn) btn.classList.add('active');
    document.getElementById('jalur-' + key).classList.add('active');
}

function toggleCompareJalur() {
    const wrap = document.getElementById('compareTableWrap');
    const btn = document.getElementById('compareToggleBtn');
    wrap.classList.toggle('show');
    btn.innerHTML = wrap.classList.contains('show') ? ' Sembunyikan Perbandingan' : '⚖️ Bandingkan Semua Jalur';
}

// ===== KALKULATOR BIAYA =====
let cicilanVal = 1;
function setCicilan(val, el) {
    cicilanVal = val;
    document.querySelectorAll('#calcCicilanGroup .calc-radio').forEach(r => r.classList.remove('active'));
    el.classList.add('active');
    hitungBiaya();
}

function rupiah(x) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(x);
}

function hitungBiaya() {
    const jalurKey = document.getElementById('calcJalur').value;
    const gelIdx = parseInt(document.getElementById('calcGelombang').value);
    const j = PMB.jalur[jalurKey] || { dp: 0, ds: 0 };
    const gDisc = PMB.gelombang[gelIdx] || 0;

    let pangkal = PMB.pangkal * (1 - j.dp / 100);
    pangkal = pangkal * (1 - gDisc / 100);
    const spp = PMB.spp * (1 - j.ds / 100);
    const total = PMB.pendaftaran + pangkal + spp;

    const diskonPangkal = PMB.pangkal - (PMB.pangkal * (1 - j.dp / 100));
    const diskonGel = (PMB.pangkal * (1 - j.dp / 100)) - pangkal;
    const diskonSpp = PMB.spp - spp;

    let rows = `
        <div class="calc-result-row"><span>Biaya Pendaftaran</span><span class="val">${rupiah(PMB.pendaftaran)}</span></div>
        <div class="calc-result-row"><span>Uang Pangkal</span><span class="val">${rupiah(pangkal)}</span></div>
        <div class="calc-result-row"><span>SPP Semester 1</span><span class="val">${rupiah(spp)}</span></div>
    `;
    if (diskonPangkal > 0) rows += `<div class="calc-result-row discount"><span>🎁 Diskon jalur (${j.dp}%)</span><span class="val">−${rupiah(diskonPangkal)}</span></div>`;
    if (diskonGel > 0) rows += `<div class="calc-result-row discount"><span>🎁 Diskon gelombang (${gDisc}%)</span><span class="val">−${rupiah(diskonGel)}</span></div>`;
    if (diskonSpp > 0) rows += `<div class="calc-result-row discount"><span>🎁 Diskon SPP (${j.ds}%)</span><span class="val">−${rupiah(diskonSpp)}</span></div>`;

    let cicilanNote = '';
    if (cicilanVal > 1) {
        const perMonth = Math.ceil(total / cicilanVal);
        cicilanNote = `<div class="calc-cicilan-note">💳 Cicilan ${cicilanVal}x bunga 0%: <strong>${rupiah(perMonth)}/bulan</strong></div>`;
    } else {
        cicilanNote = `<div class="calc-cicilan-note">✅ Pembayaran lunas sekali bayar — tanpa cicilan.</div>`;
    }

    document.getElementById('calcResult').innerHTML = `
        ${rows}
        <div class="calc-total-row">
            <span class="calc-total-label">Total Semester 1</span>
            <span class="calc-total-value">${rupiah(total)}</span>
        </div>
        ${cicilanNote}
    `;
}
hitungBiaya();

// ===== CHECKLIST (localStorage) =====
const LS_KEY = 'pmb_checklist_v1';
function getChecklistState() {
    try { return JSON.parse(localStorage.getItem(LS_KEY)) || {}; } catch (e) { return {}; }
}
function saveChecklistState(state) {
    try { localStorage.setItem(LS_KEY, JSON.stringify(state)); } catch (e) {}
}
function toggleChecklist(id, el) {
    const state = getChecklistState();
    state[id] = !state[id];
    saveChecklistState(state);
    el.classList.toggle('checked', !!state[id]);
    updateChecklistProgress();
}
function resetChecklist() {
    saveChecklistState({});
    document.querySelectorAll('.checklist-item').forEach(i => i.classList.remove('checked'));
    updateChecklistProgress();
    pubToast('Checklist direset', '🔄');
}
function updateChecklistProgress() {
    const state = getChecklistState();
    const total = CHECKLIST_IDS.length;
    const done = CHECKLIST_IDS.filter(id => state[id]).length;
    const pct = total > 0 ? Math.round((done / total) * 100) : 0;

    document.getElementById('checklistPercent').textContent = pct + '%';
    document.getElementById('checklistCount').textContent = done;

    const ring = document.getElementById('progressRingFill');
    const circumference = 2 * Math.PI * 65;
    ring.style.strokeDashoffset = circumference - (circumference * pct / 100);

    const wajibDone = CHECKLIST_WAJIB.every(id => state[id]);
    const note = document.getElementById('checklistWajibNote');
    const btn = document.getElementById('checklistDaftarBtn');
    if (wajibDone) {
        note.innerHTML = '✅ Semua berkas wajib lengkap!';
        note.style.color = '#16a34a';
        btn.disabled = false;
    } else {
        const sisa = CHECKLIST_WAJIB.filter(id => !state[id]).length;
        note.innerHTML = '⚠️ ' + sisa + ' berkas wajib belum dicentang';
        note.style.color = '#dc2626';
        btn.disabled = true;
    }
}
// init checklist UI
(function() {
    const state = getChecklistState();
    document.querySelectorAll('.checklist-item').forEach(item => {
        if (state[item.dataset.id]) item.classList.add('checked');
    });
    updateChecklistProgress();
})();

// ===== FAQ =====
let faqKat = 'all';
function filterFaq(kat, btn) {
    faqKat = kat;
    document.querySelectorAll('.faq-chip').forEach(c => c.classList.remove('active'));
    if (btn) btn.classList.add('active');
    applyFaqFilter();
}
document.getElementById('faqSearch')?.addEventListener('input', applyFaqFilter);
function applyFaqFilter() {
    const q = (document.getElementById('faqSearch')?.value || '').toLowerCase();
    let visible = 0;
    document.querySelectorAll('.faq-item-pmb').forEach(item => {
        const matchKat = faqKat === 'all' || item.dataset.kat === faqKat;
        const matchQ = !q || item.dataset.text.includes(q);
        const show = matchKat && matchQ;
        item.style.display = show ? 'block' : 'none';
        if (show) visible++;
    });
    document.getElementById('faqEmpty').style.display = visible === 0 ? 'block' : 'none';
}
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

// ===== TESTIMONI CAROUSEL =====
let testiIndex = 0;
const testiSlides = document.getElementById('testiSlides');
const testiCount = testiSlides ? testiSlides.children.length : 0;
(function initTesti() {
    const dots = document.getElementById('testiDots');
    if (!dots) return;
    for (let i = 0; i < testiCount; i++) {
        const d = document.createElement('button');
        d.className = 'testi-dot' + (i === 0 ? ' active' : '');
        d.onclick = () => goTesti(i);
        dots.appendChild(d);
    }
})();
function goTesti(i) {
    testiIndex = (i + testiCount) % testiCount;
    testiSlides.style.transform = `translateX(-${testiIndex * 100}%)`;
    document.querySelectorAll('.testi-dot').forEach((d, idx) => d.classList.toggle('active', idx === testiIndex));
}
function moveTesti(dir) { goTesti(testiIndex + dir); }
setInterval(() => moveTesti(1), 6000);

// ===== FORM MULTI-STEP =====
let formCurrentStep = 1;
function openFormModal(jalurKey) {
    document.getElementById('formModal').classList.add('show');
    document.body.style.overflow = 'hidden';
    if (jalurKey) document.getElementById('fJalur').value = jalurKey;
    goFormStep(1);
    updateFormSummary();
}
function closeFormModal() {
    document.getElementById('formModal').classList.remove('show');
    document.body.style.overflow = '';
}
function goFormStep(step) {
    formCurrentStep = step;
    document.querySelectorAll('.form-step').forEach(s => s.classList.toggle('active', parseInt(s.dataset.step) === step));
    document.querySelectorAll('.form-progress-step').forEach(s => {
        const n = parseInt(s.dataset.step);
        s.classList.toggle('active', n === step);
        s.classList.toggle('done', n < step);
    });
    document.getElementById('formBackBtn').style.visibility = step === 1 ? 'hidden' : 'visible';
    document.getElementById('formNextBtn').style.display = step === 3 ? 'none' : 'inline-flex';
    document.getElementById('formSubmitBtn').style.display = step === 3 ? 'inline-flex' : 'none';
    if (step === 3) updateFormSummary();
}
function formStep(dir) {
    if (dir === 1 && !validateStep(formCurrentStep)) return;
    goFormStep(formCurrentStep + dir);
}
function validateStep(step) {
    let ok = true;
    const check = (id, cond) => {
        const el = document.getElementById(id);
        const group = el.closest('.form-group');
        const valid = cond(el.value.trim());
        group.classList.toggle('has-error', !valid);
        el.classList.toggle('error', !valid);
        if (!valid) ok = false;
    };
    if (step === 1) {
        check('fNama', v => v.length >= 3);
        check('fSekolah', v => v.length >= 3);
        check('fHp', v => /^0\d{8,13}$|^62\d{8,13}$/.test(v.replace(/[\s-]/g, '')));
        const email = document.getElementById('fEmail').value.trim();
        const emailGroup = document.getElementById('fEmail').closest('.form-group');
        const emailOk = email === '' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        emailGroup.classList.toggle('has-error', !emailOk);
        if (!emailOk) ok = false;
    }
    if (step === 2) {
        check('fProdi', v => v !== '');
    }
    if (!ok) pubToast('Lengkapi kolom bertanda * dengan benar', '⚠️');
    return ok;
}
function updateFormSummary() {
    const jalurKey = document.getElementById('fJalur').value;
    const j = PMB.jalur[jalurKey] || { title: '-', dp: 0, ds: 0 };
    const gDisc = PMB.gelombang[0] || 0;
    let pangkal = PMB.pangkal * (1 - j.dp / 100) * (1 - gDisc / 100);
    const spp = PMB.spp * (1 - j.ds / 100);
    const total = PMB.pendaftaran + pangkal + spp;

    document.getElementById('formSummary').innerHTML = `
        <div class="form-summary-row"><span class="lbl">Nama</span><span class="val">${escapeHtml(document.getElementById('fNama').value || '-')}</span></div>
        <div class="form-summary-row"><span class="lbl">Asal Sekolah</span><span class="val">${escapeHtml(document.getElementById('fSekolah').value || '-')}</span></div>
        <div class="form-summary-row"><span class="lbl">No. WhatsApp</span><span class="val">${escapeHtml(document.getElementById('fHp').value || '-')}</span></div>
        <div class="form-summary-row"><span class="lbl">Prodi Tujuan</span><span class="val">${escapeHtml(document.getElementById('fProdi').value || '-')}</span></div>
        <div class="form-summary-row"><span class="lbl">Jalur</span><span class="val">${escapeHtml(j.title)}</span></div>
        <div class="form-summary-row"><span class="lbl">Gelombang</span><span class="val">${escapeHtml(PMB.gelombang_name)}</span></div>
        <div class="form-summary-row"><span class="lbl">Estimasi Biaya Sem. 1</span><span class="val" style="color:var(--primary);">${rupiah(total)}</span></div>
    `;
}
function submitFormToWA() {
    const consent = document.getElementById('fConsent');
    const err = document.getElementById('consentError');
    if (!consent.checked) {
        err.style.display = 'block';
        pubToast('Centang pernyataan persetujuan terlebih dahulu', '⚠️');
        return;
    }
    err.style.display = 'none';

    const jalurKey = document.getElementById('fJalur').value;
    const j = PMB.jalur[jalurKey] || { title: '-' };
    const gDisc = PMB.gelombang[0] || 0;
    const total = PMB.pendaftaran + (PMB.pangkal * (1 - j.dp / 100) * (1 - gDisc / 100)) + (PMB.spp * (1 - j.ds / 100));

    const lines = [
        '*PENDAFTARAN PMB FKIP UNIMOF*',
        'T.A. ' + PMB.tahun + ' — ' + PMB.gelombang_name,
        '─────────────────',
        '👤 Nama: ' + document.getElementById('fNama').value,
        '🔢 NISN: ' + (document.getElementById('fNisn').value || '-'),
        '🏫 Asal Sekolah: ' + document.getElementById('fSekolah').value,
        '📱 No. WA: ' + document.getElementById('fHp').value,
        '📧 Email: ' + (document.getElementById('fEmail').value || '-'),
        '🎓 Prodi: ' + document.getElementById('fProdi').value,
        '🎯 Jalur: ' + j.title,
        '💰 Estimasi Sem. 1: ' + rupiah(total),
    ];
    const pesan = document.getElementById('fPesan').value.trim();
    if (pesan) lines.push('💬 Pesan: ' + pesan);
    lines.push('─────────────────', 'Dikirim melalui website FKIP UNIMOF');

    const url = 'https://wa.me/' + PMB.wa + '?text=' + encodeURIComponent(lines.join('\n'));
    window.open(url, '_blank');
    pubToast('Membuka WhatsApp admin...', '💬');
    setTimeout(closeFormModal, 800);
}
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeFormModal();
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey &&
        document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA' && document.activeElement.tagName !== 'SELECT') {
        e.preventDefault();
        document.getElementById('faqSearch')?.focus();
    }
});

console.log('%c📝 PMB FKIP UNIMOF - EXTREME MULTIMATE', 'color:#0a6847;font-size:16px;font-weight:bold');
console.log('%cShortcuts: / (Cari FAQ) • ESC (Tutup modal)', 'color:#64748b');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>