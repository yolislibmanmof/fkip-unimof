<?php
require_once __DIR__ . '/includes/config.php';

$page_title = 'Galeri Kegiatan FKIP UNIMOF';
$page_description = 'Dokumentasi foto kegiatan akademik, kemahasiswaan, dan event FKIP UNIMOF.';

// ===== AMBIL DATA GALERI =====
$galeri = [];
$kat_list = [];
try {
    $galeri = $pdo->query("SELECT * FROM galeri WHERE status='Published' ORDER BY tanggal DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($galeri as $g) {
        $k = trim($g['kategori'] ?? '') ?: 'Umum';
        if (!isset($kat_list[$k])) $kat_list[$k] = 0;
        $kat_list[$k]++;
    }
} catch (Exception $e) { $galeri = []; }

$kat_icons = ['Akademik'=>'','Kegiatan'=>'🎉','Prestasi'=>'🏆','Wisuda'=>'','Riset'=>'🔬','Kerjasama'=>'🤝','Umum'=>''];

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ===== GALERI PAGE ===== */
.gal-hero {
    background: linear-gradient(135deg, var(--primary-dark,#064e34) 0%, var(--primary) 55%, var(--primary-light,#16a34a) 100%);
    color: #fff; padding: 5rem 0 4rem; position: relative; overflow: hidden; text-align: center;
}
.gal-hero::after {
    content: '📸'; position: absolute; right: 4%; bottom: -2rem; font-size: 12rem;
    opacity: .07; pointer-events: none;
}
.gal-hero h1 {
    font-family: var(--font-display); font-size: clamp(2rem, 4.5vw, 3rem);
    font-weight: 900; margin-bottom: .75rem; letter-spacing: -0.02em;
}
.gal-hero p { max-width: 600px; margin: 0 auto; opacity: .92; font-size: 1.02rem; line-height: 1.7; }

/* Pills kategori */
.gal-pills { display: flex; gap: .5rem; flex-wrap: wrap; justify-content: center; margin: -1.5rem auto 2.5rem; position: relative; z-index: 5; padding: 0 1rem; }
.gal-pill {
    padding: .55rem 1.1rem; border-radius: 999px; border: 1px solid var(--border);
    background: var(--bg-primary); color: var(--text-secondary); font-weight: 600; font-size: .85rem;
    cursor: pointer; transition: all .2s; font-family: inherit;
    box-shadow: var(--shadow-md); display: inline-flex; align-items: center; gap: .4rem;
}
.gal-pill:hover { transform: translateY(-2px); border-color: var(--primary); color: var(--primary); }
.gal-pill.active { background: var(--primary); border-color: var(--primary); color: #fff; }
.gal-pill .n { background: rgba(0,0,0,.12); padding: .05rem .5rem; border-radius: 999px; font-size: .7rem; font-weight: 800; }
.gal-pill.active .n { background: rgba(255,255,255,.25); }

/* Grid masonry */
.gal-section { padding: 1rem 0 5rem; }
.gal-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
}
.gal-item {
    position: relative; border-radius: var(--radius-lg); overflow: hidden;
    cursor: pointer; background: var(--bg-secondary); aspect-ratio: 4/3;
    transition: all .3s; box-shadow: var(--shadow-sm);
}
.gal-item:hover { transform: translateY(-4px); box-shadow: var(--shadow-xl); }
.gal-item img { width: 100%; height: 100%; object-fit: cover; transition: transform .5s; }
.gal-item:hover img { transform: scale(1.08); }
.gal-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,.85) 0%, rgba(0,0,0,0) 55%);
    display: flex; flex-direction: column; justify-content: flex-end; padding: 1rem;
    opacity: 0; transition: opacity .3s;
}
.gal-item:hover .gal-overlay { opacity: 1; }
.gal-cat {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .25rem .6rem; background: rgba(255,255,255,.2); backdrop-filter: blur(6px);
    border-radius: 999px; font-size: .7rem; font-weight: 700; color: #fff;
    margin-bottom: .5rem; width: fit-content;
}
.gal-title { color: #fff; font-weight: 700; font-size: .95rem; line-height: 1.3; }
.gal-date { color: rgba(255,255,255,.8); font-size: .75rem; margin-top: .25rem; }
.gal-zoom {
    position: absolute; top: .75rem; right: .75rem;
    width: 36px; height: 36px; border-radius: 50%;
    background: rgba(255,255,255,.9); color: var(--primary);
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; opacity: 0; transform: scale(.8); transition: all .25s;
}
.gal-item:hover .gal-zoom { opacity: 1; transform: scale(1); }

.gal-empty { text-align: center; padding: 4rem 2rem; background: var(--bg-secondary); border-radius: var(--radius-xl); border: 2px dashed var(--border); }
.gal-empty .ei { font-size: 4rem; margin-bottom: .75rem; opacity: .5; }

/* Lightbox */
.gal-lightbox {
    position: fixed; inset: 0; background: rgba(0,0,0,.92); z-index: 10000;
    display: none; align-items: center; justify-content: center; padding: 2rem;
    animation: fadeIn .25s ease;
}
.gal-lightbox.show { display: flex; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.gal-lightbox img {
    max-width: 90vw; max-height: 85vh; object-fit: contain; border-radius: var(--radius-md);
    box-shadow: 0 20px 60px rgba(0,0,0,.5);
}
.gal-lb-close {
    position: absolute; top: 1.5rem; right: 1.5rem;
    width: 44px; height: 44px; border-radius: 50%;
    background: rgba(255,255,255,.15); border: none; color: #fff;
    font-size: 1.25rem; cursor: pointer; transition: all .2s;
    display: flex; align-items: center; justify-content: center;
}
.gal-lb-close:hover { background: rgba(255,255,255,.3); transform: rotate(90deg); }
.gal-lb-info {
    position: absolute; bottom: 1.5rem; left: 50%; transform: translateX(-50%);
    background: rgba(0,0,0,.6); backdrop-filter: blur(10px);
    padding: .75rem 1.25rem; border-radius: 999px; color: #fff;
    font-size: .88rem; font-weight: 600; max-width: 80vw; text-align: center;
    border: 1px solid rgba(255,255,255,.1);
}
.gal-lb-nav {
    position: absolute; top: 50%; transform: translateY(-50%);
    width: 44px; height: 44px; border-radius: 50%;
    background: rgba(255,255,255,.15); border: none; color: #fff;
    font-size: 1.2rem; cursor: pointer; transition: all .2s;
    display: flex; align-items: center; justify-content: center;
}
.gal-lb-nav:hover { background: rgba(255,255,255,.3); }
.gal-lb-prev { left: 1.5rem; }
.gal-lb-next { right: 1.5rem; }

@media (max-width: 640px) {
    .gal-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: .6rem; }
    .gal-lb-nav { display: none; }
}
</style>

<!-- ===== HERO ===== -->
<section class="gal-hero">
    <div class="container">
        <h1>Galeri Kegiatan</h1>
        <p>Dokumentasi momen akademik, kemahasiswaan, dan event penting FKIP UNIMOF.</p>
    </div>
</section>

<!-- ===== PILLS ===== -->
<div class="container">
    <div class="gal-pills" id="galPills">
        <button class="gal-pill active" data-cat="">📸 Semua <span class="n"><?= count($galeri) ?></span></button>
        <?php foreach ($kat_list as $k => $n): ?>
        <button class="gal-pill" data-cat="<?= sanitize($k) ?>"><?= $kat_icons[$k] ?? '📁' ?> <?= sanitize($k) ?> <span class="n"><?= $n ?></span></button>
        <?php endforeach; ?>
    </div>
</div>

<!-- ===== GRID ===== -->
<section class="gal-section">
    <div class="container">
        <?php if (empty($galeri)): ?>
        <div class="gal-empty">
            <div class="ei">📭</div>
            <h3 style="font-family:var(--font-display); margin-bottom:.5rem;">Belum ada foto</h3>
            <p style="color:var(--text-muted)">Dokumentasi kegiatan akan tampil di sini setelah diunggah admin.</p>
        </div>
        <?php else: ?>
        <div class="gal-grid" id="galGrid">
            <?php foreach ($galeri as $i => $g):
                $img = !empty($g['gambar']) ? asset('uploads/galeri/' . basename($g['gambar'])) : '';
                $kat = trim($g['kategori'] ?? '') ?: 'Umum';
            ?>
            <div class="gal-item" data-cat="<?= sanitize($kat) ?>" data-idx="<?= $i ?>"
                 onclick="openLightbox(<?= $i ?>)">
                <?php if ($img): ?>
                    <img src="<?= $img ?>" alt="<?= sanitize($g['judul'] ?? '') ?>" loading="lazy">
                <?php else: ?>
                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:3rem;background:var(--bg-tertiary);">📷</div>
                <?php endif; ?>
                <div class="gal-overlay">
                    <span class="gal-cat"><?= $kat_icons[$kat] ?? '' ?> <?= sanitize($kat) ?></span>
                    <div class="gal-title"><?= sanitize($g['judul'] ?? 'Kegiatan FKIP') ?></div>
                    <?php if (!empty($g['tanggal'])): ?>
                    <div class="gal-date">📅 <?= date('d M Y', strtotime($g['tanggal'])) ?></div>
                    <?php endif; ?>
                </div>
                <div class="gal-zoom">🔍</div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ===== LIGHTBOX ===== -->
<div class="gal-lightbox" id="galLightbox" onclick="if(event.target===this)closeLightbox()">
    <button class="gal-lb-close" onclick="closeLightbox()">✕</button>
    <button class="gal-lb-nav gal-lb-prev" onclick="navLightbox(-1)">‹</button>
    <img id="galLbImg" src="" alt="">
    <button class="gal-lb-nav gal-lb-next" onclick="navLightbox(1)">›</button>
    <div class="gal-lb-info" id="galLbInfo">-</div>
</div>

<script>
const galData = <?= json_encode(array_map(function($g){
    return [
        'img' => !empty($g['gambar']) ? asset('uploads/galeri/' . basename($g['gambar'])) : '',
        'title' => $g['judul'] ?? '',
        'kat' => trim($g['kategori'] ?? '') ?: 'Umum',
        'date' => $g['tanggal'] ?? '',
    ];
}, $galeri), JSON_UNESCAPED_UNICODE) ?>;

let curIdx = 0;
function openLightbox(i){
    curIdx = i; renderLightbox();
    document.getElementById('galLightbox').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeLightbox(){
    document.getElementById('galLightbox').classList.remove('show');
    document.body.style.overflow = '';
}
function navLightbox(d){
    curIdx = (curIdx + d + galData.length) % galData.length;
    renderLightbox();
}
function renderLightbox(){
    const g = galData[curIdx];
    document.getElementById('galLbImg').src = g.img;
    document.getElementById('galLbInfo').textContent = (g.title || 'Foto') + (g.date ? ' • ' + g.date : '');
}

// Filter kategori
document.querySelectorAll('.gal-pill').forEach(p=>{
    p.addEventListener('click', ()=>{
        document.querySelectorAll('.gal-pill').forEach(x=>x.classList.remove('active'));
        p.classList.add('active');
        const cat = p.dataset.cat;
        document.querySelectorAll('.gal-item').forEach(it=>{
            it.style.display = (!cat || it.dataset.cat === cat) ? '' : 'none';
        });
    });
});

// Keyboard nav
document.addEventListener('keydown', e=>{
    if (!document.getElementById('galLightbox').classList.contains('show')) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') navLightbox(-1);
    if (e.key === 'ArrowRight') navLightbox(1);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>