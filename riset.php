<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Pusat Riset & Publikasi';
$page_description = 'Penelitian dan publikasi ilmiah dosen serta mahasiswa FKIP UNIMOF';

// Ambil data riset
$stmt = $pdo->query("SELECT r.*, p.nama as prodi_nama, d.nama as ketua_nama 
                     FROM riset r 
                     LEFT JOIN program_studi p ON r.program_studi_id = p.id 
                     LEFT JOIN dosen d ON r.ketua_id = d.id 
                     WHERE r.status = 'Published' 
                     ORDER BY r.tahun DESC, r.created_at DESC");
$riset_list = $stmt->fetchAll();

// Statistik riset
$stat_total = count($riset_list);
$stat_publikasi = (int)$pdo->query("SELECT COUNT(*) FROM riset WHERE status='Published' AND jenis='Publikasi'")->fetchColumn();
$stat_hibah = (int)$pdo->query("SELECT COUNT(*) FROM riset WHERE status='Published' AND jenis='Hibah'")->fetchColumn();
$stat_pengabdian = (int)$pdo->query("SELECT COUNT(*) FROM riset WHERE status='Published' AND jenis='Pengabdian'")->fetchColumn();

// Kategori unik
$kategoris = [];
foreach ($riset_list as $r) {
    if (!empty($r['kategori']) && !in_array($r['kategori'], $kategoris)) {
        $kategoris[] = $r['kategori'];
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.riset-hero {
    background: linear-gradient(135deg, #1e3a8a 0%, #4c1d95 100%);
    color: white; padding: 8rem 0 5rem; position: relative; overflow: hidden;
}
.riset-hero::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(251,191,36,0.2) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(59,130,246,0.25) 0%, transparent 50%);
    animation: auroraShift 20s ease-in-out infinite;
}
@keyframes auroraShift {
    0%, 100% { transform: translate(0, 0); }
    50% { transform: translate(-20px, 20px); }
}
.riset-hero .container { position: relative; z-index: 2; }

/* Stats Bar */
.riset-stats {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem;
    margin: -3rem 0 3rem; position: relative; z-index: 3;
}
.riset-stat-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.5rem; text-align: center;
    box-shadow: var(--shadow-md); transition: all 0.3s;
}
.riset-stat-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
.riset-stat-num {
    font-family: var(--font-display); font-size: 2.5rem; font-weight: 900;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text; line-height: 1; margin-bottom: 0.5rem;
}
.riset-stat-label { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; }

/* Toolbar */
.riset-toolbar {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.5rem; margin-bottom: 2.5rem;
    box-shadow: var(--shadow-sm); display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;
}
.riset-search { flex: 1; min-width: 250px; position: relative; }
.riset-search input {
    width: 100%; padding: 0.85rem 1rem 0.85rem 2.75rem;
    border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.95rem; transition: all 0.3s;
}
.riset-search input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.riset-search .icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); }

.riset-filter {
    padding: 0.85rem 1rem; border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.95rem; background: var(--bg-secondary); cursor: pointer;
}
.riset-filter:focus { outline: none; border-color: var(--primary); }

/* Riset Grid */
.riset-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.5rem;
}
.riset-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.75rem; position: relative;
    overflow: hidden; transition: all 0.4s; display: flex; flex-direction: column;
}
.riset-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, #4c1d95, #1e3a8a);
    transform: scaleX(0); transition: transform 0.4s; transform-origin: left;
}
.riset-card:hover {
    transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: #4c1d95;
}
.riset-card:hover::before { transform: scaleX(1); }

.riset-type-badge {
    display: inline-block; padding: 0.3rem 0.75rem; border-radius: 999px;
    font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.05em; margin-bottom: 1rem; width: fit-content;
}
.type-publikasi { background: #dbeafe; color: #1e40af; }
.type-hibah { background: #dcfce7; color: #166534; }
.type-pengabdian { background: #fef3c7; color: #92400e; }
.type-lainnya { background: var(--bg-secondary); color: var(--text-secondary); }

.riset-year {
    font-family: var(--font-display); font-size: 0.85rem; font-weight: 700;
    color: var(--text-muted); margin-bottom: 0.5rem;
}
.riset-title {
    font-family: var(--font-display); font-size: 1.2rem; font-weight: 800;
    color: var(--text-primary); margin-bottom: 0.75rem; line-height: 1.4;
}
.riset-desc {
    color: var(--text-secondary); font-size: 0.9rem; line-height: 1.6;
    margin-bottom: 1.25rem; flex: 1;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}
.riset-meta {
    display: flex; flex-wrap: wrap; gap: 0.5rem; padding-top: 1rem;
    border-top: 1px solid var(--border); font-size: 0.8rem; color: var(--text-muted);
}
.riset-meta span { display: inline-flex; align-items: center; gap: 0.3rem; }

.empty-riset {
    grid-column: 1 / -1; text-align: center; padding: 4rem 2rem;
    background: var(--bg-secondary); border-radius: var(--radius-xl);
    border: 2px dashed var(--border);
}

@media (max-width: 768px) {
    .riset-stats { grid-template-columns: repeat(2, 1fr); }
    .riset-hero { padding: 6rem 0 3rem; }
}
</style>

<section class="riset-hero">
    <div class="container">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem;">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Riset & Publikasi</span>
        </nav>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 5vw, 4rem); font-weight: 900; margin-bottom: 1rem; letter-spacing: -0.02em;">Pusat Riset & Publikasi</h1>
        <p class="page-subtitle" style="font-size: 1.2rem; opacity: 0.95; max-width: 700px;">Kontribusi ilmiah dosen dan mahasiswa FKIP UNIMOF dalam memajukan ilmu pengetahuan dan teknologi pendidikan.</p>
    </div>
</section>

<section class="section" style="padding-top: 0;">
    <div class="container">
        
        <!-- Stats Bar -->
        <div class="riset-stats" data-aos="fade-up">
            <div class="riset-stat-card">
                <div class="riset-stat-num" data-count="<?= $stat_total ?>">0</div>
                <div class="riset-stat-label">Total Riset</div>
            </div>
            <div class="riset-stat-card">
                <div class="riset-stat-num" data-count="<?= $stat_publikasi ?>">0</div>
                <div class="riset-stat-label">Publikasi</div>
            </div>
            <div class="riset-stat-card">
                <div class="riset-stat-num" data-count="<?= $stat_hibah ?>">0</div>
                <div class="riset-stat-label">Hibah Penelitian</div>
            </div>
            <div class="riset-stat-card">
                <div class="riset-stat-num" data-count="<?= $stat_pengabdian ?>">0</div>
                <div class="riset-stat-label">Pengabdian</div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="riset-toolbar" data-aos="fade-up">
            <div class="riset-search">
                <span class="icon">🔍</span>
                <input type="text" id="risetSearch" placeholder="Cari judul riset, ketua peneliti...">
            </div>
            <select class="riset-filter" id="risetTypeFilter">
                <option value="all">Semua Jenis</option>
                <option value="Publikasi">Publikasi</option>
                <option value="Hibah">Hibah</option>
                <option value="Pengabdian">Pengabdian</option>
            </select>
            <select class="riset-filter" id="risetKatFilter">
                <option value="all">Semua Kategori</option>
                <?php foreach ($kategoris as $k): ?>
                    <option value="<?= sanitize($k) ?>"><?= sanitize($k) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Riset Grid -->
        <div class="riset-grid" id="risetGrid" data-aos="fade-up">
            <?php if (empty($riset_list)): ?>
                <div class="empty-riset">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">🔬</div>
                    <h3>Belum ada data riset</h3>
                    <p style="color: var(--text-muted);">Publikasi dan penelitian akan segera ditampilkan di sini.</p>
                </div>
            <?php else: ?>
                <?php foreach ($riset_list as $r): 
                    $type_class = 'type-' . strtolower($r['jenis'] ?? 'lainnya');
                ?>
                <article class="riset-card" 
                    data-judul="<?= strtolower(sanitize($r['judul'])) ?>"
                    data-ketua="<?= strtolower(sanitize($r['ketua_nama'] ?? '')) ?>"
                    data-jenis="<?= sanitize($r['jenis'] ?? '') ?>"
                    data-kategori="<?= sanitize($r['kategori'] ?? '') ?>">
                    
                    <span class="riset-type-badge <?= $type_class ?>"><?= sanitize($r['jenis'] ?? 'Riset') ?></span>
                    <div class="riset-year">📅 Tahun <?= sanitize($r['tahun']) ?></div>
                    <h3 class="riset-title"><?= sanitize($r['judul']) ?></h3>
                    <p class="riset-desc"><?= excerpt($r['deskripsi'] ?? $r['abstrak'] ?? 'Tidak ada deskripsi tersedia.', 150) ?></p>
                    
                    <div class="riset-meta">
                        <span>👤 <?= sanitize($r['ketua_nama'] ?? 'Tim Peneliti') ?></span>
                        <span>🎓 <?= sanitize($r['prodi_nama'] ?? 'Umum') ?></span>
                        <?php if (!empty($r['kategori'])): ?>
                            <span>🏷️ <?= sanitize($r['kategori']) ?></span>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
// Animated counters
function animateCounter(el) {
    const target = parseInt(el.dataset.count) || 0;
    const duration = 2000;
    const steps = 60;
    const stepValue = target / steps;
    let current = 0;
    const interval = setInterval(() => {
        current += stepValue;
        if (current >= target) {
            el.textContent = target.toLocaleString('id-ID');
            clearInterval(interval);
        } else {
            el.textContent = Math.floor(current).toLocaleString('id-ID');
        }
    }, duration / steps);
}
const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            animateCounter(entry.target);
            counterObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.5 });
document.querySelectorAll('[data-count]').forEach(el => counterObserver.observe(el));

// Filter riset
const risetSearch = document.getElementById('risetSearch');
const risetTypeFilter = document.getElementById('risetTypeFilter');
const risetKatFilter = document.getElementById('risetKatFilter');
const risetCards = document.querySelectorAll('.riset-card');

function filterRiset() {
    const query = risetSearch.value.toLowerCase();
    const type = risetTypeFilter.value;
    const kat = risetKatFilter.value;
    let visibleCount = 0;

    risetCards.forEach(card => {
        const judul = card.dataset.judul;
        const ketua = card.dataset.ketua;
        const cardType = card.dataset.jenis;
        const cardKat = card.dataset.kategori;
        
        const matchSearch = !query || judul.includes(query) || ketua.includes(query);
        const matchType = type === 'all' || cardType === type;
        const matchKat = kat === 'all' || cardKat === kat;

        if (matchSearch && matchType && matchKat) {
            card.style.display = 'flex';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    // Tampilkan empty state jika tidak ada hasil
    const grid = document.getElementById('risetGrid');
    let emptyEl = grid.querySelector('.empty-riset-dynamic');
    if (visibleCount === 0 && risetCards.length > 0) {
        if (!emptyEl) {
            emptyEl = document.createElement('div');
            emptyEl.className = 'empty-riset empty-riset-dynamic';
            emptyEl.innerHTML = '<div style="font-size:4rem;margin-bottom:1rem;">🔍</div><h3>Tidak ada riset ditemukan</h3><p style="color:var(--text-muted);">Coba ubah kata kunci atau filter pencarian.</p>';
            grid.appendChild(emptyEl);
        }
    } else if (emptyEl) {
        emptyEl.remove();
    }
}

risetSearch.addEventListener('input', filterRiset);
risetTypeFilter.addEventListener('change', filterRiset);
risetKatFilter.addEventListener('change', filterRiset);

console.log('%c Riset FKIP UNIMOF', 'color:#4c1d95;font-size:16px;font-weight:bold');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>