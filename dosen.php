<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Dosen & Tenaga Pengajar';
$page_description = 'Profil lengkap dosen dan tenaga pengajar berkualitas FKIP UNIMOF yang aktif dalam penelitian dan pengabdian masyarakat.';

// Ambil data dosen beserta nama prodi
$stmt = $pdo->query("SELECT d.*, p.nama as prodi_nama, p.singkatan as prodi_singkatan FROM dosen d LEFT JOIN program_studi p ON d.program_studi_id = p.id WHERE d.status = 'Aktif' ORDER BY d.nama ASC");
$dosen_list = $stmt->fetchAll();

// Ambil daftar prodi untuk filter
$prodi_list = $pdo->query("SELECT id, nama, singkatan FROM program_studi WHERE status = 'Aktif' ORDER BY nama ASC")->fetchAll();

// Statistik Dosen
$total_dosen = count($dosen_list);
$dosen_s3 = (int)$pdo->query("SELECT COUNT(*) FROM dosen WHERE (pendidikan_terakhir LIKE '%S3%' OR pendidikan_terakhir LIKE '%Doctor%') AND status = 'Aktif'")->fetchColumn();
$dosen_s2 = (int)$pdo->query("SELECT COUNT(*) FROM dosen WHERE (pendidikan_terakhir LIKE '%S2%' OR pendidikan_terakhir LIKE '%Master%') AND status = 'Aktif'")->fetchColumn();

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ===== HERO EXTREME ===== */
.dosen-hero-extreme {
    position: relative; background: linear-gradient(135deg, #16213e 0%, #0a6847 50%, #064e34 100%);
    color: white; padding: 8rem 0 6rem; text-align: center; overflow: hidden;
}
.dosen-hero-extreme::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(245,166,35,0.2) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(59,130,246,0.15) 0%, transparent 50%);
    animation: heroAurora 20s ease-in-out infinite;
}
@keyframes heroAurora { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(-20px, 20px); } }

/* ===== STATS BAR ===== */
.dosen-stats-bar {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem; margin: -4rem auto 3rem; max-width: 900px; position: relative; z-index: 10;
}
.dosen-stat-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.5rem; text-align: center;
    box-shadow: var(--shadow-lg); transition: all 0.3s;
}
.dosen-stat-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-xl); }
.dosen-stat-num { font-family: var(--font-display); font-size: 2.5rem; font-weight: 900; color: var(--primary); line-height: 1; margin-bottom: 0.5rem; }
.dosen-stat-label { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; }

/* ===== TOOLBAR ===== */
.dosen-toolbar {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.25rem; margin-bottom: 2.5rem;
    box-shadow: var(--shadow-sm); display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;
}
.dosen-search { flex: 1; min-width: 250px; position: relative; }
.dosen-search input {
    width: 100%; padding: 0.85rem 1rem 0.85rem 3rem; border: 2px solid var(--border);
    border-radius: var(--radius-md); font-family: inherit; font-size: 0.95rem; transition: all 0.3s;
}
.dosen-search input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.dosen-search .icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }

.dosen-select {
    padding: 0.85rem 1rem; border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.95rem; background: var(--bg-secondary); cursor: pointer; min-width: 220px;
}
.dosen-select:focus { outline: none; border-color: var(--primary); }

/* ===== GRID & CARDS ===== */
.dosen-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; }
.dosen-card-pro {
    background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl);
    padding: 2rem; text-align: center; transition: all 0.4s; position: relative; overflow: hidden;
    display: flex; flex-direction: column; align-items: center;
}
.dosen-card-pro::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
    transform: scaleX(0); transition: transform 0.4s;
}
.dosen-card-pro:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--primary); }
.dosen-card-pro:hover::before { transform: scaleX(1); }

.dosen-avatar-lg {
    width: 100px; height: 100px; border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 2.25rem; font-weight: 800; margin-bottom: 1.25rem;
    box-shadow: 0 8px 20px rgba(10,104,71,0.25); border: 4px solid var(--bg-primary);
}
.dosen-card-pro h3 { font-size: 1.2rem; font-weight: 800; margin-bottom: 0.5rem; color: var(--text-primary); line-height: 1.3; }
.dosen-jabatan {
    display: inline-block; padding: 0.3rem 0.8rem; background: rgba(10,104,71,0.1);
    color: var(--primary); border-radius: 999px; font-size: 0.75rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;
}
.dosen-prodi-badge {
    display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 1rem;
    background: var(--bg-secondary); border-radius: 999px; font-size: 0.85rem;
    color: var(--text-secondary); font-weight: 600; margin-bottom: 1rem;
}
.dosen-pendidikan { font-size: 0.9rem; color: var(--text-muted); display: flex; align-items: center; justify-content: center; gap: 0.4rem; margin-bottom: 1.5rem; }

.btn-view-profile {
    margin-top: auto; width: 100%; padding: 0.75rem; background: var(--bg-secondary);
    color: var(--text-primary); border: 1px solid var(--border); border-radius: var(--radius-md);
    font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.3s;
    display: flex; align-items: center; justify-content: center; gap: 0.5rem;
}
.btn-view-profile:hover { background: var(--primary); color: white; border-color: var(--primary); }

/* ===== MODAL ===== */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(15,23,42,0.8); backdrop-filter: blur(8px);
    display: none; align-items: center; justify-content: center; z-index: 10000; padding: 1.5rem;
}
.modal-overlay.show { display: flex; }
.modal-content {
    background: var(--bg-primary); border-radius: var(--radius-xl); width: 100%; max-width: 600px;
    max-height: 90vh; overflow-y: auto; position: relative; animation: modalPop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    box-shadow: 0 25px 50px rgba(0,0,0,0.25);
}
@keyframes modalPop { from { transform: scale(0.9) translateY(20px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }
.modal-close {
    position: absolute; top: 1rem; right: 1rem; width: 36px; height: 36px;
    background: var(--bg-secondary); border: none; border-radius: 50%; cursor: pointer;
    font-size: 1.2rem; display: flex; align-items: center; justify-content: center;
    transition: all 0.2s; color: var(--text-muted); z-index: 10;
}
.modal-close:hover { background: #fee2e2; color: #dc2626; transform: rotate(90deg); }

.modal-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    padding: 3rem 2rem 2rem; text-align: center; color: white; position: relative;
}
.modal-avatar {
    width: 100px; height: 100px; border-radius: 50%; background: rgba(255,255,255,0.2);
    border: 4px solid white; display: flex; align-items: center; justify-content: center;
    font-size: 2.5rem; font-weight: 800; margin: 0 auto 1rem; color: white;
}
.modal-header h2 { font-family: var(--font-display); font-size: 1.75rem; margin-bottom: 0.5rem; }
.modal-header p { opacity: 0.9; font-size: 1rem; }

.modal-body { padding: 2rem; }
.modal-section { margin-bottom: 1.5rem; }
.modal-section h4 { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem; }
.modal-section p, .modal-section a { font-size: 1rem; color: var(--text-primary); line-height: 1.6; }
.modal-section a { color: var(--primary); text-decoration: none; font-weight: 600; }
.modal-section a:hover { text-decoration: underline; }

/* ===== EMPTY STATE ===== */
.empty-dosen { grid-column: 1 / -1; text-align: center; padding: 4rem 2rem; background: var(--bg-secondary); border-radius: var(--radius-xl); border: 2px dashed var(--border); }
.empty-icon-lg { font-size: 4rem; margin-bottom: 1rem; opacity: 0.5; animation: float 3s ease-in-out infinite; }
@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }

@media (max-width: 768px) {
    .dosen-stats-bar { grid-template-columns: repeat(2, 1fr); margin: -3rem 1rem 2rem; }
    .dosen-toolbar { flex-direction: column; align-items: stretch; }
    .dosen-select { width: 100%; }
    .dosen-grid { grid-template-columns: 1fr; }
}
</style>

<!-- ===== HERO ===== -->
<section class="dosen-hero-extreme">
    <div class="container" style="position: relative; z-index: 2;">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; justify-content: center;" data-aos="fade-down">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Dosen & Pengajar</span>
        </nav>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 5vw, 4rem); font-weight: 900; margin-bottom: 1rem;" data-aos="fade-up">Dosen & Tenaga Pengajar</h1>
        <p class="page-subtitle" style="font-size: 1.2rem; opacity: 0.95; max-width: 700px; margin: 0 auto;" data-aos="fade-up" data-aos-delay="100">
            Dibimbing oleh akademisi berkualifikasi S2 dan S3 dari universitas terkemuka, yang aktif dalam penelitian dan pengabdian masyarakat.
        </p>
    </div>
</section>

<!-- ===== MAIN CONTENT ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">
        <!-- Stats Bar -->
        <div class="dosen-stats-bar" data-aos="fade-up">
            <div class="dosen-stat-card">
                <div class="dosen-stat-num count-up" data-target="<?= $total_dosen ?>">0</div>
                <div class="dosen-stat-label">Total Dosen Aktif</div>
            </div>
            <div class="dosen-stat-card">
                <div class="dosen-stat-num count-up" data-target="<?= $dosen_s3 ?>">0</div>
                <div class="dosen-stat-label">Bergelar Doktor (S3)</div>
            </div>
            <div class="dosen-stat-card">
                <div class="dosen-stat-num count-up" data-target="<?= $dosen_s2 ?>">0</div>
                <div class="dosen-stat-label">Bergelar Magister (S2)</div>
            </div>
            <div class="dosen-stat-card">
                <div class="dosen-stat-num count-up" data-target="<?= count($prodi_list) ?>">0</div>
                <div class="dosen-stat-label">Program Studi</div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="dosen-toolbar" data-aos="fade-up">
            <div class="dosen-search">
                <span class="icon">🔍</span>
                <input type="text" id="dosenSearch" placeholder="Cari nama dosen atau bidang keahlian...">
            </div>
            <select class="dosen-select" id="dosenFilter">
                <option value="all">Semua Program Studi</option>
                <?php foreach ($prodi_list as $p): ?>
                    <option value="<?= sanitize($p['nama']) ?>"><?= sanitize($p['nama']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Grid -->
        <div class="dosen-grid" id="dosenGrid" data-aos="fade-up">
            <?php foreach ($dosen_list as $d): 
                $initials = strtoupper(substr($d['nama'], 0, 1) . (strpos($d['nama'], ' ') ? substr($d['nama'], strpos($d['nama'], ' ') + 1, 1) : ''));
                $keahlian = $d['bidang_keahlian'] ?? 'Pendidikan & Pengajaran';
            ?>
            <div class="dosen-card-pro" 
                 data-nama="<?= strtolower(sanitize($d['nama'])) ?>" 
                 data-prodi="<?= strtolower(sanitize($d['prodi_nama'] ?? '')) ?>"
                 data-keahlian="<?= strtolower(sanitize($keahlian)) ?>"
                 onclick="openDosenModal(<?= htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8') ?>, '<?= $initials ?>')">
                
                <div class="dosen-avatar-lg"><?= $initials ?></div>
                <h3><?= sanitize(($d['gelar_depan'] ?? '') . ' ' . $d['nama'] . ' ' . ($d['gelar_belakang'] ?? '')) ?></h3>
                <div class="dosen-jabatan"><?= sanitize($d['jabatan_fungsional'] ?? 'Dosen') ?></div>
                <div class="dosen-prodi-badge">🎓 <?= sanitize($d['prodi_nama'] ?? 'Umum') ?></div>
                <div class="dosen-pendidikan">🎓 <?= sanitize($d['pendidikan_terakhir'] ?? 'S2/S3') ?></div>
                
                <button class="btn-view-profile">
                    <span>👁️</span> Lihat Profil Lengkap
                </button>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Empty State -->
        <div class="empty-dosen" id="emptyDosen" style="display: none;">
            <div class="empty-icon-lg">🔍</div>
            <h3>Dosen tidak ditemukan</h3>
            <p style="color: var(--text-muted); margin-top: 0.5rem;">Coba ubah kata kunci pencarian atau filter program studi.</p>
        </div>
    </div>
</section>

<!-- ===== MODAL PROFIL DOSEN ===== -->
<div class="modal-overlay" id="dosenModal" onclick="if(event.target===this)closeDosenModal()">
    <div class="modal-content">
        <button class="modal-close" onclick="closeDosenModal()">✕</button>
        <div class="modal-header">
            <div class="modal-avatar" id="modalAvatar"></div>
            <h2 id="modalName"></h2>
            <p id="modalJabatan"></p>
        </div>
        <div class="modal-body">
            <div class="modal-section">
                <h4>🎓 Program Studi</h4>
                <p id="modalProdi"></p>
            </div>
            <div class="modal-section">
                <h4>🎓 Pendidikan Terakhir</h4>
                <p id="modalPendidikan"></p>
            </div>
            <div class="modal-section">
                <h4>🔬 Bidang Keahlian</h4>
                <p id="modalKeahlian"></p>
            </div>
            <div class="modal-section">
                <h4>📧 Kontak Email</h4>
                <a id="modalEmail" href=""></a>
            </div>
        </div>
    </div>
</div>

<script>
// ===== COUNT UP ANIMATION =====
function animateCount(el) {
    const target = parseInt(el.dataset.target) || 0;
    const duration = 2000;
    const start = performance.now();
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
        if (entry.isIntersecting) {
            animateCount(entry.target);
            countObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.5 });
document.querySelectorAll('.count-up').forEach(el => countObserver.observe(el));

// ===== SEARCH & FILTER =====
const dosenSearch = document.getElementById('dosenSearch');
const dosenFilter = document.getElementById('dosenFilter');
const dosenCards = document.querySelectorAll('.dosen-card-pro');
const emptyDosen = document.getElementById('emptyDosen');

function filterDosen() {
    const query = dosenSearch.value.toLowerCase();
    const prodi = dosenFilter.value.toLowerCase();
    let visibleCount = 0;

    dosenCards.forEach(card => {
        const nama = card.dataset.nama;
        const cardProdi = card.dataset.prodi;
        const keahlian = card.dataset.keahlian;
        
        const matchSearch = !query || nama.includes(query) || keahlian.includes(query);
        const matchProdi = prodi === 'all' || cardProdi.includes(prodi);

        if (matchSearch && matchProdi) {
            card.style.display = 'flex';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    emptyDosen.style.display = visibleCount === 0 ? 'flex' : 'none';
}

dosenSearch.addEventListener('input', filterDosen);
dosenFilter.addEventListener('change', filterDosen);

// ===== MODAL FUNCTIONS =====
function openDosenModal(dosen, initials) {
    document.getElementById('modalAvatar').textContent = initials;
    const fullName = (dosen.gelar_depan ? dosen.gelar_depan + ' ' : '') + dosen.nama + (dosen.gelar_belakang ? ' ' + dosen.gelar_belakang : '');
    document.getElementById('modalName').textContent = fullName;
    document.getElementById('modalJabatan').textContent = dosen.jabatan_fungsional || 'Dosen';
    document.getElementById('modalProdi').textContent = dosen.prodi_nama || 'Umum';
    document.getElementById('modalPendidikan').textContent = dosen.pendidikan_terakhir || 'S2/S3';
    document.getElementById('modalKeahlian').textContent = dosen.bidang_keahlian || 'Pendidikan & Pengajaran';
    
    const emailEl = document.getElementById('modalEmail');
    if (dosen.email) {
        emailEl.textContent = dosen.email;
        emailEl.href = 'mailto:' + dosen.email;
        emailEl.style.display = 'inline';
    } else {
        emailEl.style.display = 'none';
    }
    
    document.getElementById('dosenModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeDosenModal() {
    document.getElementById('dosenModal').classList.remove('show');
    document.body.style.overflow = '';
}

// Close modal on Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeDosenModal();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>