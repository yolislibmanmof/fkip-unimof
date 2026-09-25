<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Agenda & Kalender Akademik';
$page_description = 'Jadwal kegiatan, ujian, seminar, wisuda, dan libur FKIP UNIMOF.';

$filter = $_GET['filter'] ?? 'all';
$where = "WHERE status = 'Aktif'";
$params = [];
if ($filter !== 'all') {
    $where .= " AND jenis = ?";
    $params[] = $filter;
}

$stmt = $pdo->prepare("SELECT * FROM agenda $where ORDER BY tanggal_mulai ASC");
$stmt->execute($params);
$agenda = $stmt->fetchAll();

// Spotlight: Agenda terdekat
$next_event = $pdo->query("SELECT * FROM agenda WHERE tanggal_mulai >= CURDATE() AND status = 'Aktif' ORDER BY tanggal_mulai ASC LIMIT 1")->fetch();

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ===== HERO EXTREME ===== */
.agenda-hero-extreme {
    position: relative; background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 50%, #4c1d95 100%);
    color: white; padding: 8rem 0 6rem; text-align: center; overflow: hidden;
}
.agenda-hero-extreme::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(251,191,36,0.2) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(255,255,255,0.1) 0%, transparent 50%);
    animation: heroAurora 20s ease-in-out infinite;
}
@keyframes heroAurora { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(-20px, 20px); } }

/* ===== SPOTLIGHT CARD ===== */
.spotlight-card {
    max-width: 800px; margin: -4rem auto 3rem; position: relative; z-index: 10;
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-xl);
    display: flex; gap: 2rem; align-items: center;
}
.spotlight-badge {
    background: linear-gradient(135deg, #f59e0b, #d97706); color: white;
    padding: 0.5rem 1rem; border-radius: 999px; font-size: 0.8rem; font-weight: 700;
    display: inline-flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;
}
.spotlight-title { font-family: var(--font-display); font-size: 1.75rem; font-weight: 800; margin-bottom: 0.5rem; }
.spotlight-meta { display: flex; gap: 1.5rem; color: var(--text-muted); font-size: 0.9rem; flex-wrap: wrap; }
.spotlight-meta span { display: flex; align-items: center; gap: 0.4rem; }
.spotlight-visual {
    width: 120px; height: 120px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: white;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    box-shadow: 0 10px 30px rgba(139,92,246,0.3);
}
.spotlight-day { font-size: 2.5rem; font-weight: 900; line-height: 1; }
.spotlight-month { font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.1em; }

/* ===== FILTER TABS ===== */
.filter-tabs-extreme {
    display: flex; gap: 0.5rem; justify-content: center; flex-wrap: wrap; margin-bottom: 3rem;
}
.filter-tab-extreme {
    padding: 0.6rem 1.25rem; border-radius: 999px; border: 2px solid var(--border);
    background: var(--bg-primary); color: var(--text-secondary); font-size: 0.85rem;
    font-weight: 600; cursor: pointer; transition: all 0.3s; text-decoration: none;
    display: inline-flex; align-items: center; gap: 0.4rem;
}
.filter-tab-extreme:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); }
.filter-tab-extreme.active {
    background: var(--primary); color: white; border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(10,104,71,0.25);
}

/* ===== TIMELINE EXTREME ===== */
.timeline-extreme-pub { max-width: 900px; margin: 0 auto; position: relative; padding: 2rem 0; }
.timeline-extreme-pub::before {
    content: ''; position: absolute; left: 24px; top: 0; bottom: 0; width: 4px;
    background: linear-gradient(180deg, var(--primary), var(--primary-light), transparent);
    border-radius: 4px;
}
.timeline-item-pub { display: flex; gap: 2rem; margin-bottom: 2.5rem; position: relative; }
.timeline-dot-pub {
    width: 52px; height: 52px; background: var(--bg-primary);
    border: 4px solid var(--primary); border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; flex-shrink: 0; z-index: 2;
    box-shadow: 0 4px 15px rgba(10,104,71,0.2); transition: all 0.3s;
}
.timeline-item-pub:hover .timeline-dot-pub { transform: scale(1.1); box-shadow: 0 6px 20px rgba(10,104,71,0.3); }
.timeline-card-pub {
    flex: 1; background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.75rem; transition: all 0.3s;
    position: relative;
}
.timeline-card-pub::before {
    content: ''; position: absolute; left: -10px; top: 1.5rem; width: 20px; height: 20px;
    background: var(--bg-primary); border-left: 1px solid var(--border); border-bottom: 1px solid var(--border);
    transform: rotate(45deg);
}
.timeline-item-pub:hover .timeline-card-pub {
    transform: translateX(8px); box-shadow: var(--shadow-lg); border-color: var(--primary-light);
}
.timeline-date-pub {
    font-family: var(--font-display); font-size: 0.95rem; font-weight: 700;
    color: var(--primary); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;
}
.timeline-card-pub h3 { font-size: 1.2rem; font-weight: 700; margin-bottom: 0.75rem; color: var(--text-primary); }
.timeline-card-pub p { color: var(--text-secondary); font-size: 0.95rem; line-height: 1.7; margin-bottom: 1rem; }
.badge-jenis-extreme {
    display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.35rem 0.85rem;
    border-radius: 999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
}
.badge-ujian { background: #fee2e2; color: #dc2626; }
.badge-seminar { background: #dbeafe; color: #2563eb; }
.badge-wisuda { background: #fef3c7; color: #d97706; }
.badge-libur { background: #e0e7ff; color: #4338ca; }
.badge-pmb { background: #dcfce7; color: #16a34a; }
.badge-umum { background: #f3f4f6; color: #4b5563; }

/* Empty State */
.empty-state-premium { text-align: center; padding: 4rem 2rem; background: var(--bg-secondary); border-radius: var(--radius-xl); border: 2px dashed var(--border); }
.empty-icon-lg { font-size: 5rem; margin-bottom: 1rem; opacity: 0.5; animation: float 3s ease-in-out infinite; }
@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }

@media (max-width: 768px) {
    .spotlight-card { flex-direction: column; text-align: center; margin: -3rem 1rem 2rem; }
    .spotlight-meta { justify-content: center; }
    .timeline-extreme-pub::before { left: 20px; }
    .timeline-dot-pub { width: 44px; height: 44px; font-size: 1.2rem; }
    .timeline-card-pub::before { display: none; }
}
</style>

<!-- ===== HERO ===== -->
<section class="agenda-hero-extreme">
    <div class="container" style="position: relative; z-index: 2;">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; justify-content: center;" data-aos="fade-down">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Agenda & Kalender</span>
        </nav>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 5vw, 4rem); font-weight: 900; margin-bottom: 1rem;" data-aos="fade-up">Agenda & Kalender Akademik</h1>
        <p class="page-subtitle" style="max-width: 600px; margin: 0 auto; opacity: 0.9; font-size: 1.1rem;" data-aos="fade-up" data-aos-delay="100">
            Pantau jadwal kegiatan akademik, ujian, seminar, wisuda, dan hari libur fakultas agar Anda tidak ketinggalan momen penting.
        </p>
    </div>
</section>

<!-- ===== SPOTLIGHT NEXT EVENT ===== -->
<div class="container">
    <?php if ($next_event): 
        $d = new DateTime($next_event['tanggal_mulai']);
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    ?>
    <div class="spotlight-card" data-aos="fade-up">
        <div class="spotlight-visual">
            <span class="spotlight-day"><?= $d->format('d') ?></span>
            <span class="spotlight-month"><?= $months[$d->format('n')-1] ?></span>
        </div>
        <div style="flex: 1;">
            <span class="spotlight-badge">🔥 Agenda Terdekat</span>
            <h2 class="spotlight-title"><?= sanitize($next_event['judul']) ?></h2>
            <div class="spotlight-meta">
                <span>📅 <?= format_tanggal_range($next_event['tanggal_mulai'], $next_event['tanggal_selesai']) ?></span>
                <?php if ($next_event['lokasi']): ?><span>📍 <?= sanitize($next_event['lokasi']) ?></span><?php endif; ?>
                <span class="badge-jenis-extreme badge-<?= strtolower($next_event['jenis']) ?>"><?= ucfirst($next_event['jenis']) ?></span>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ===== MAIN CONTENT ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">
        <!-- Filter Tabs -->
        <div class="filter-tabs-extreme" data-aos="fade-up">
            <a class="filter-tab-extreme <?= $filter === 'all' ? 'active' : '' ?>" href="<?= base_url('agenda.php?filter=all') ?>">📅 Semua</a>
            <a class="filter-tab-extreme <?= $filter === 'ujian' ? 'active' : '' ?>" href="<?= base_url('agenda.php?filter=ujian') ?>">📝 Ujian</a>
            <a class="filter-tab-extreme <?= $filter === 'seminar' ? 'active' : '' ?>" href="<?= base_url('agenda.php?filter=seminar') ?>">🎤 Seminar</a>
            <a class="filter-tab-extreme <?= $filter === 'wisuda' ? 'active' : '' ?>" href="<?= base_url('agenda.php?filter=wisuda') ?>">🎓 Wisuda</a>
            <a class="filter-tab-extreme <?= $filter === 'libur' ? 'active' : '' ?>" href="<?= base_url('agenda.php?filter=libur') ?>">🏖️ Libur</a>
            <a class="filter-tab-extreme <?= $filter === 'pmb' ? 'active' : '' ?>" href="<?= base_url('agenda.php?filter=pmb') ?>">📋 PMB</a>
        </div>

        <!-- Timeline -->
        <div class="timeline-extreme-pub">
            <?php if (empty($agenda)): ?>
                <div class="empty-state-premium" data-aos="fade-up">
                    <div class="empty-icon-lg">📅</div>
                    <h3>Belum ada agenda di kategori ini</h3>
                    <p style="color: var(--text-muted); margin-top: 0.5rem;">Silakan cek kembali nanti atau pilih kategori lain.</p>
                </div>
            <?php else: foreach ($agenda as $a): 
                $icon = $a['jenis'] === 'ujian' ? '📝' : ($a['jenis'] === 'libur' ? '🏖️' : ($a['jenis'] === 'wisuda' ? '🎓' : ($a['jenis'] === 'pmb' ? '📋' : '📌')));
                $date_str = format_tanggal_range($a['tanggal_mulai'], $a['tanggal_selesai']);
            ?>
            <div class="timeline-item-pub" data-aos="fade-up">
                <div class="timeline-dot-pub"><?= $icon ?></div>
                <div class="timeline-card-pub">
                    <div class="timeline-date-pub">📅 <?= $date_str ?></div>
                    <h3><?= sanitize($a['judul']) ?></h3>
                    <?php if ($a['lokasi']): ?><p style="margin-bottom: 0.5rem;"><strong>📍 Lokasi:</strong> <?= sanitize($a['lokasi']) ?></p><?php endif; ?>
                    <?php if ($a['deskripsi']): ?><p><?= nl2br(sanitize($a['deskripsi'])) ?></p><?php endif; ?>
                    <span class="badge-jenis-extreme badge-<?= strtolower($a['jenis']) ?>"><?= ucfirst($a['jenis']) ?></span>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>