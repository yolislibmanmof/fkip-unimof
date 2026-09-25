<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Dosen & Tenaga Pengajar';
$page_description = 'Profil dosen berkualitas FKIP UNIMOF';

// Ambil data dosen beserta nama prodi
$stmt = $pdo->query("SELECT d.*, p.nama as prodi_nama FROM dosen d LEFT JOIN program_studi p ON d.program_studi_id = p.id WHERE d.status = 'Aktif' ORDER BY d.nama ASC");
$dosen_list = $stmt->fetchAll();

// Ambil daftar prodi untuk filter
$prodi_list = $pdo->query("SELECT id, nama FROM program_studi WHERE status = 'Aktif' ORDER BY nama ASC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<style>
.dosen-hero { background: linear-gradient(135deg, #16213e 0%, #0a6847 100%); color: white; padding: 8rem 0 5rem; text-align: center; }
.dosen-toolbar { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 1.5rem; margin: -3rem auto 3rem; max-width: 900px; position: relative; z-index: 10; box-shadow: var(--shadow-lg); display: flex; gap: 1rem; flex-wrap: wrap; }
.dosen-search { flex: 1; min-width: 250px; position: relative; }
.dosen-search input { width: 100%; padding: 0.85rem 1rem 0.85rem 2.75rem; border: 2px solid var(--border); border-radius: var(--radius-md); font-family: inherit; font-size: 0.95rem; transition: all 0.3s; }
.dosen-search input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.dosen-search .icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); }

.dosen-select { padding: 0.85rem 1rem; border: 2px solid var(--border); border-radius: var(--radius-md); font-family: inherit; font-size: 0.95rem; background: var(--bg-secondary); cursor: pointer; min-width: 200px; }
.dosen-select:focus { outline: none; border-color: var(--primary); }

.dosen-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }
.dosen-card-pro { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; text-align: center; transition: all 0.4s; position: relative; overflow: hidden; }
.dosen-card-pro::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--primary), var(--primary-light)); transform: scaleX(0); transition: transform 0.4s; }
.dosen-card-pro:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--primary); }
.dosen-card-pro:hover::before { transform: scaleX(1); }

.dosen-avatar-lg { width: 90px; height: 90px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 800; margin: 0 auto 1.25rem; box-shadow: 0 8px 20px rgba(10,104,71,0.2); }
.dosen-card-pro h3 { font-size: 1.15rem; font-weight: 800; margin-bottom: 0.25rem; color: var(--text-primary); }
.dosen-card-pro .jabatan { color: var(--primary); font-size: 0.85rem; font-weight: 700; margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; }
.dosen-card-pro .prodi-badge { display: inline-block; padding: 0.3rem 0.8rem; background: var(--bg-secondary); border-radius: 999px; font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 1rem; }
.dosen-card-pro .pendidikan { font-size: 0.85rem; color: var(--text-muted); display: flex; align-items: center; justify-content: center; gap: 0.4rem; }

.empty-dosen { grid-column: 1 / -1; text-align: center; padding: 4rem 2rem; background: var(--bg-secondary); border-radius: var(--radius-xl); border: 2px dashed var(--border); }
</style>

<section class="dosen-hero">
    <div class="container">
        <h1 class="page-title" style="font-size: clamp(2.5rem, 5vw, 4rem); font-weight: 900; margin-bottom: 1rem;">Dosen & Tenaga Pengajar</h1>
        <p class="page-subtitle" style="font-size: 1.2rem; opacity: 0.9; max-width: 700px; margin: 0 auto;">Dibimbing oleh akademisi berkualifikasi S2 dan S3 yang aktif dalam penelitian dan pengabdian masyarakat.</p>
    </div>
</section>

<section class="section" style="padding-top: 0;">
    <div class="container">
        <div class="dosen-toolbar" data-aos="fade-up">
            <div class="dosen-search">
                <span class="icon">🔍</span>
                <input type="text" id="dosenSearch" placeholder="Cari nama dosen...">
            </div>
            <select class="dosen-select" id="dosenFilter">
                <option value="all">Semua Program Studi</option>
                <?php foreach ($prodi_list as $p): ?>
                    <option value="<?= sanitize($p['nama']) ?>"><?= sanitize($p['nama']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="dosen-grid" id="dosenGrid" data-aos="fade-up">
            <?php foreach ($dosen_list as $d): 
                $initials = strtoupper(substr($d['nama'], 0, 1) . (strpos($d['nama'], ' ') ? substr($d['nama'], strpos($d['nama'], ' ') + 1, 1) : ''));
            ?>
            <div class="dosen-card-pro" data-nama="<?= strtolower(sanitize($d['nama'])) ?>" data-prodi="<?= strtolower(sanitize($d['prodi_nama'] ?? '')) ?>">
                <div class="dosen-avatar-lg"><?= $initials ?></div>
                <h3><?= sanitize(($d['gelar_depan'] ?? '') . ' ' . $d['nama'] . ' ' . ($d['gelar_belakang'] ?? '')) ?></h3>
                <div class="jabatan"><?= sanitize($d['jabatan_fungsional'] ?? 'Dosen') ?></div>
                <div class="prodi-badge"><?= sanitize($d['prodi_nama'] ?? 'Umum') ?></div>
                <div class="pendidikan">🎓 <?= sanitize($d['pendidikan_terakhir'] ?? 'S2/S3') ?></div>
                <?php if (!empty($d['email'])): ?>
                    <a href="mailto:<?= sanitize($d['email']) ?>" class="btn btn-sm btn-secondary" style="margin-top: 1rem; width: 100%; justify-content: center;">✉️ Hubungi</a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="empty-dosen" id="emptyDosen" style="display: none;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
            <h3>Dosen tidak ditemukan</h3>
            <p style="color: var(--text-muted);">Coba ubah kata kunci pencarian atau filter program studi.</p>
        </div>
    </div>
</section>

<script>
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
        const matchSearch = !query || nama.includes(query);
        const matchProdi = prodi === 'all' || cardProdi.includes(prodi);

        if (matchSearch && matchProdi) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    emptyDosen.style.display = visibleCount === 0 ? 'block' : 'none';
}

dosenSearch.addEventListener('input', filterDosen);
dosenFilter.addEventListener('change', filterDosen);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>