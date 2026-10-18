<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

$page_title = 'Galeri Kegiatan FKIP UNIMOF';
$page_description = 'Dokumentasi foto kegiatan akademik, kemahasiswaan, dan event FKIP UNIMOF.';

try {
    $stmt = $pdo->query(
        "SELECT id, judul, gambar, kategori, tanggal
         FROM galeri
         WHERE status = 'Published'
         ORDER BY tanggal DESC, id DESC"
    );
    $galeri = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('Public gallery query failed: ' . $exception->getMessage());

    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    exit('Galeri sementara tidak tersedia. Silakan coba kembali.');
}

$kat_icons = [
    'Akademik' => '🎓',
    'Kegiatan' => '🎉',
    'Prestasi' => '🏆',
    'Wisuda' => '🎓',
    'Riset' => '🔬',
    'Kerjasama' => '🤝',
    'Umum' => '📁',
];

$kat_list = [];

foreach ($galeri as &$foto) {
    $kategori = trim((string) ($foto['kategori'] ?? ''));

    if ($kategori === '') {
        $kategori = 'Umum';
    }

    $foto['kategori'] = $kategori;
    $kat_list[$kategori] = ($kat_list[$kategori] ?? 0) + 1;
}
unset($foto);

$galleryData = [];

foreach ($galeri as $index => $foto) {
    $filename = basename((string) ($foto['gambar'] ?? ''));
    $imageUrl = $filename !== ''
        ? asset('uploads/galeri/' . rawurlencode($filename))
        : '';

    $rawDate = trim((string) ($foto['tanggal'] ?? ''));
    $timestamp = $rawDate !== '' ? strtotime($rawDate) : false;
    $displayDate = $timestamp !== false ? date('d M Y', $timestamp) : '';

    $galleryData[] = [
        'index' => $index,
        'img' => $imageUrl,
        'title' => (string) ($foto['judul'] ?? ''),
        'category' => (string) $foto['kategori'],
        'date' => $displayDate,
    ];
}

try {
    $galleryJson = json_encode(
        $galleryData,
        JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
            | JSON_INVALID_UTF8_SUBSTITUTE
    );
} catch (JsonException $exception) {
    error_log('Gallery data encoding failed: ' . $exception->getMessage());

    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    exit('Galeri tidak dapat ditampilkan saat ini.');
}

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

/* Grid */
.gal-section { padding: 1rem 0 5rem; }
.gal-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
}
.gal-item {
    position: relative; display: block; width: 100%; padding: 0;
    border: 0; border-radius: var(--radius-lg); overflow: hidden;
    cursor: pointer; color: inherit; text-align: left; font: inherit;
    background: var(--bg-secondary); aspect-ratio: 4/3;
    transition: transform .3s, box-shadow .3s; box-shadow: var(--shadow-sm);
}
.gal-item:hover { transform: translateY(-4px); box-shadow: var(--shadow-xl); }
.gal-item:focus-visible { outline: 3px solid var(--primary); outline-offset: 3px; }
.gal-item img { width: 100%; height: 100%; object-fit: cover; transition: transform .5s; }
.gal-item:hover img { transform: scale(1.08); }
.gal-placeholder {
    width: 100%; height: 100%; display: flex; align-items: center;
    justify-content: center; font-size: 3rem; background: var(--bg-tertiary);
}
.gal-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,.85) 0%, rgba(0,0,0,0) 55%);
    display: flex; flex-direction: column; justify-content: flex-end; padding: 1rem;
    opacity: 0; transition: opacity .3s;
}
.gal-item:hover .gal-overlay, .gal-item:focus-visible .gal-overlay { opacity: 1; }
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
.gal-item:hover .gal-zoom, .gal-item:focus-visible .gal-zoom { opacity: 1; transform: scale(1); }

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
@media (prefers-reduced-motion: reduce) {
    .gal-item, .gal-item img, .gal-overlay, .gal-zoom, .gal-lb-close, .gal-lb-nav {
        transition: none;
    }
    .gal-lightbox { animation: none; }
}
</style>

<section class="gal-hero">
    <div class="container">
        <h1>Galeri Kegiatan</h1>
        <p>Dokumentasi momen akademik, kemahasiswaan, dan event penting FKIP UNIMOF.</p>
    </div>
</section>

<div class="container">
    <div class="gal-pills" id="galPills" role="group" aria-label="Filter kategori galeri">
        <button class="gal-pill active" type="button" data-cat="" aria-pressed="true">
            📸 Semua <span class="n"><?= count($galeri) ?></span>
        </button>
        <?php foreach ($kat_list as $kategori => $count): ?>
            <button
                class="gal-pill"
                type="button"
                data-cat="<?= sanitize((string) $kategori) ?>"
                aria-pressed="false"
            >
                <?= $kat_icons[$kategori] ?? '📁' ?>
                <?= sanitize((string) $kategori) ?>
                <span class="n"><?= (int) $count ?></span>
            </button>
        <?php endforeach; ?>
    </div>
</div>

<section class="gal-section">
    <div class="container">
        <?php if ($galeri === []): ?>
            <div class="gal-empty">
                <div class="ei">📭</div>
                <h3 style="font-family:var(--font-display); margin-bottom:.5rem;">Belum ada foto</h3>
                <p style="color:var(--text-muted)">Dokumentasi kegiatan akan tampil di sini setelah diunggah admin.</p>
            </div>
        <?php else: ?>
            <div class="gal-grid" id="galGrid">
                <?php foreach ($galleryData as $foto): ?>
                    <?php
                    $imageExists = $foto['img'] !== '';
                    $categoryIcon = $kat_icons[$foto['category']] ?? '📁';
                    ?>
                    <button
                        class="gal-item"
                        type="button"
                        data-index="<?= (int) $foto['index'] ?>"
                        data-cat="<?= sanitize($foto['category']) ?>"
                        aria-label="<?= sanitize('Lihat foto: ' . ($foto['title'] !== '' ? $foto['title'] : 'Kegiatan FKIP')) ?>"
                        <?= !$imageExists ? 'disabled aria-disabled="true"' : '' ?>
                    >
                        <?php if ($imageExists): ?>
                            <img
                                src="<?= sanitize($foto['img']) ?>"
                                alt="<?= sanitize($foto['title']) ?>"
                                loading="lazy"
                                decoding="async"
                            >
                        <?php else: ?>
                            <div class="gal-placeholder" aria-hidden="true">📷</div>
                        <?php endif; ?>

                        <span class="gal-overlay" aria-hidden="true">
                            <span class="gal-cat">
                                <?= $categoryIcon ?> <?= sanitize($foto['category']) ?>
                            </span>
                            <span class="gal-title">
                                <?= sanitize($foto['title'] !== '' ? $foto['title'] : 'Kegiatan FKIP') ?>
                            </span>
                            <?php if ($foto['date'] !== ''): ?>
                                <span class="gal-date">📅 <?= sanitize($foto['date']) ?></span>
                            <?php endif; ?>
                        </span>

                        <?php if ($imageExists): ?>
                            <span class="gal-zoom" aria-hidden="true">🔍</span>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<div
    class="gal-lightbox"
    id="galLightbox"
    role="dialog"
    aria-modal="true"
    aria-label="Tampilan foto galeri"
    aria-hidden="true"
>
    <button class="gal-lb-close" id="galLbClose" type="button" aria-label="Tutup foto">✕</button>
    <button class="gal-lb-nav gal-lb-prev" id="galLbPrev" type="button" aria-label="Foto sebelumnya">‹</button>
    <img id="galLbImg" src="" alt="">
    <button class="gal-lb-nav gal-lb-next" id="galLbNext" type="button" aria-label="Foto berikutnya">›</button>
    <div class="gal-lb-info" id="galLbInfo" aria-live="polite"></div>
</div>

<script>
(() => {
    const galData = <?= $galleryJson ?>;
    const lightbox = document.getElementById('galLightbox');
    const lightboxImage = document.getElementById('galLbImg');
    const lightboxInfo = document.getElementById('galLbInfo');
    const galleryButtons = Array.from(document.querySelectorAll('.gal-item:not(:disabled)'));
    const pills = Array.from(document.querySelectorAll('.gal-pill'));

    let visibleIndices = [];
    let currentPosition = 0;
    let previouslyFocused = null;

    function renderLightbox() {
        if (visibleIndices.length === 0) {
            return;
        }

        const foto = galData[visibleIndices[currentPosition]];

        if (!foto) {
            return;
        }

        lightboxImage.src = foto.img;
        lightboxImage.alt = foto.title || 'Foto kegiatan FKIP UNIMOF';
        lightboxInfo.textContent = (foto.title || 'Foto kegiatan FKIP')
            + (foto.date ? ' • ' + foto.date : '');
    }

    function openLightbox(index, trigger) {
        const position = visibleIndices.indexOf(index);

        if (position < 0) {
            return;
        }

        currentPosition = position;
        previouslyFocused = trigger;
        renderLightbox();

        lightbox.classList.add('show');
        lightbox.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        document.getElementById('galLbClose').focus();
    }

    function closeLightbox() {
        lightbox.classList.remove('show');
        lightbox.setAttribute('aria-hidden', 'true');
        lightboxImage.removeAttribute('src');
        lightboxImage.alt = '';
        document.body.style.overflow = '';

        if (previouslyFocused && document.contains(previouslyFocused)) {
            previouslyFocused.focus();
        }

        previouslyFocused = null;
    }

    function navigateLightbox(direction) {
        if (visibleIndices.length < 2) {
            return;
        }

        currentPosition = (
            currentPosition + direction + visibleIndices.length
        ) % visibleIndices.length;

        renderLightbox();
    }

    function applyCategoryFilter(category) {
        visibleIndices = [];

        galleryButtons.forEach((button) => {
            const matches = !category || button.dataset.cat === category;
            button.hidden = !matches;

            if (matches) {
                visibleIndices.push(Number.parseInt(button.dataset.index, 10));
            }
        });

        if (
            lightbox.classList.contains('show')
            && !visibleIndices.includes(galData[visibleIndices[currentPosition]]?.index)
        ) {
            closeLightbox();
        }
    }

    galleryButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const index = Number.parseInt(button.dataset.index, 10);
            openLightbox(index, button);
        });
    });

    pills.forEach((pill) => {
        pill.addEventListener('click', () => {
            pills.forEach((item) => {
                const active = item === pill;
                item.classList.toggle('active', active);
                item.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            applyCategoryFilter(pill.dataset.cat || '');
        });
    });

    document.getElementById('galLbClose').addEventListener('click', closeLightbox);
    document.getElementById('galLbPrev').addEventListener('click', () => navigateLightbox(-1));
    document.getElementById('galLbNext').addEventListener('click', () => navigateLightbox(1));

    lightbox.addEventListener('click', (event) => {
        if (event.target === lightbox) {
            closeLightbox();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (!lightbox.classList.contains('show')) {
            return;
        }

        if (event.key === 'Escape') {
            closeLightbox();
        } else if (event.key === 'ArrowLeft') {
            navigateLightbox(-1);
        } else if (event.key === 'ArrowRight') {
            navigateLightbox(1);
        }
    });

    applyCategoryFilter('');
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>