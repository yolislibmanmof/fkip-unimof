<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

$page_title = 'FAQ — Pertanyaan yang Sering Diajukan';
$page_description = 'Temukan jawaban cepat seputar PMB, akademik, beasiswa, dan kehidupan kampus FKIP UNIMOF.';

try {
    $stmt = $pdo->query(
        "SELECT id, kategori, pertanyaan, jawaban
         FROM faq
         WHERE status = 'Aktif'
         ORDER BY urutan ASC, id ASC"
    );
    $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('Public FAQ query failed: ' . $exception->getMessage());

    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    exit('Informasi FAQ sementara tidak tersedia. Silakan coba kembali.');
}

$cat_icons = [
    'PMB' => '📝',
    'Akademik' => '🎓',
    'Beasiswa' => '💰',
    'Umum' => '💬',
    'Kemahasiswaan' => '🎉',
    'Fasilitas' => '🏢',
    'Alumni' => '🎓',
];

$kat_counts = [];

foreach ($faqs as &$faq) {
    $category = trim((string) ($faq['kategori'] ?? ''));

    if ($category === '') {
        $category = 'Umum';
    }

    $faq['kategori'] = $category;
    $kat_counts[$category] = ($kat_counts[$category] ?? 0) + 1;
}
unset($faq);

$wa_number = preg_replace(
    '/[^0-9]/',
    '',
    (string) get_setting(
        'whatsapp_number',
        get_setting('whatsapp', '6281234567890')
    )
);

if (!is_string($wa_number) || $wa_number === '') {
    $wa_number = '6281234567890';
}

$faqSchemaJson = null;

if ($faqs !== []) {
    $faqSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(
            static function (array $faq): array {
                return [
                    '@type' => 'Question',
                    'name' => (string) $faq['pertanyaan'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => (string) $faq['jawaban'],
                    ],
                ];
            },
            $faqs
        ),
    ];

    try {
        $faqSchemaJson = json_encode(
            $faqSchema,
            JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRETTY_PRINT
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
                | JSON_INVALID_UTF8_SUBSTITUTE
        );
    } catch (JsonException $exception) {
        error_log('Public FAQ structured data encoding failed: ' . $exception->getMessage());
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ===== FAQ PAGE ===== */
.faq-hero {
    background: linear-gradient(135deg, var(--primary-dark, #064e34) 0%, var(--primary) 55%, var(--primary-light, #16a34a) 100%);
    color: #fff; padding: 6rem 0 5rem; position: relative; overflow: hidden; text-align: center;
}
.faq-hero::after {
    content: '❓'; position: absolute; right: 4%; bottom: -3rem; font-size: 14rem;
    opacity: .07; pointer-events: none; line-height: 1;
}
.faq-hero h1 {
    font-family: var(--font-display); font-size: clamp(2.2rem, 5vw, 3.4rem);
    font-weight: 900; margin-bottom: .75rem; letter-spacing: -0.02em;
}
.faq-hero p { max-width: 620px; margin: 0 auto 2rem; opacity: .92; font-size: 1.05rem; line-height: 1.7; }
.faq-search-wrap { max-width: 560px; margin: 0 auto; position: relative; }
.faq-search-wrap .icon {
    position: absolute; left: 1.1rem; top: 50%; transform: translateY(-50%);
    font-size: 1.1rem; opacity: .6; pointer-events: none;
}
#faqSearch {
    width: 100%; padding: 1rem 1rem 1rem 3rem; border-radius: 999px; border: 2px solid rgba(255,255,255,.35);
    background: rgba(255,255,255,.14); backdrop-filter: blur(10px); color: #fff;
    font-family: inherit; font-size: 1rem; transition: all .25s;
}
#faqSearch::placeholder { color: rgba(255,255,255,.7); }
#faqSearch:focus { outline: none; background: rgba(255,255,255,.22); border-color: #fff; }
.faq-hero-stats { display: flex; gap: 2rem; justify-content: center; margin-top: 2rem; flex-wrap: wrap; }
.faq-hero-stat b { font-size: 1.6rem; font-family: var(--font-display); display: block; }
.faq-hero-stat span { font-size: .78rem; text-transform: uppercase; letter-spacing: .08em; opacity: .85; }

/* Pills kategori */
.faq-pills { display: flex; gap: .5rem; flex-wrap: wrap; justify-content: center; margin: -1.6rem auto 2.5rem; position: relative; z-index: 5; padding: 0 1rem; }
.faq-pill {
    padding: .55rem 1.1rem; border-radius: 999px; border: 1px solid var(--border);
    background: var(--bg-primary); color: var(--text-secondary); font-weight: 600; font-size: .85rem;
    cursor: pointer; transition: all .2s; font-family: inherit;
    box-shadow: var(--shadow-md); display: inline-flex; align-items: center; gap: .4rem;
}
.faq-pill:hover { transform: translateY(-2px); border-color: var(--primary); color: var(--primary); }
.faq-pill.active { background: var(--primary); border-color: var(--primary); color: #fff; }
.faq-pill .n { background: rgba(0,0,0,.12); padding: .05rem .5rem; border-radius: 999px; font-size: .7rem; font-weight: 800; }
.faq-pill.active .n { background: rgba(255,255,255,.25); }

/* Grup & item */
.faq-section { padding: 1rem 0 5rem; }
.faq-group { margin-bottom: 3rem; }
.faq-group-head {
    display: flex; align-items: center; gap: .75rem; margin-bottom: 1.25rem;
    padding-bottom: .75rem; border-bottom: 2px solid var(--border);
}
.faq-group-head .gi {
    width: 44px; height: 44px; border-radius: 12px; background: rgba(10,104,71,.1);
    display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0;
}
.faq-group-head h2 { font-family: var(--font-display); font-size: 1.4rem; font-weight: 800; }
.faq-group-head .gc { margin-left: auto; font-size: .78rem; color: var(--text-muted); font-weight: 600; }

.faq-item {
    background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-lg);
    margin-bottom: .75rem; overflow: hidden; transition: all .25s;
}
.faq-item:hover { border-color: var(--primary); box-shadow: var(--shadow-md); }
.faq-item.open { border-color: var(--primary); box-shadow: var(--shadow-lg); }
.faq-q {
    width: 100%; display: flex; align-items: center; gap: 1rem; padding: 1.15rem 1.35rem;
    background: none; border: none; cursor: pointer; text-align: left;
    font-family: inherit; font-size: 1rem; font-weight: 700; color: var(--text-primary);
}
.faq-q .qi { font-size: 1.15rem; flex-shrink: 0; }
.faq-q .qt { flex: 1; line-height: 1.5; }
.faq-q .qc {
    width: 30px; height: 30px; border-radius: 50%; background: var(--bg-tertiary);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    font-size: .9rem; color: var(--text-secondary); transition: transform .3s, background .3s, color .3s;
}
.faq-item.open .qc { transform: rotate(180deg); background: var(--primary); color: #fff; }
.faq-a { max-height: 0; overflow: hidden; transition: max-height .35s ease; }
.faq-a-inner {
    padding: 0 1.35rem 1.35rem 3.5rem; color: var(--text-secondary);
    font-size: .95rem; line-height: 1.8; white-space: pre-line;
}
.faq-a-inner::before { content: '💡'; margin-right: .5rem; }

/* Empty & CTA */
.faq-empty { text-align: center; padding: 3.5rem 1.5rem; background: var(--bg-secondary); border: 2px dashed var(--border); border-radius: var(--radius-xl); display: none; }
.faq-empty .ei { font-size: 3.5rem; margin-bottom: .75rem; opacity: .5; }
.faq-cta {
    margin: 0 auto 5rem; max-width: 860px; text-align: center;
    background: linear-gradient(135deg, var(--primary), var(--primary-light, #16a34a));
    color: #fff; border-radius: var(--radius-xl); padding: 3rem 2rem; position: relative; overflow: hidden;
}
.faq-cta::before { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at 80% 20%, rgba(255,255,255,.18), transparent 55%); }
.faq-cta h2 { font-family: var(--font-display); font-size: 1.8rem; margin-bottom: .5rem; position: relative; }
.faq-cta p { opacity: .92; margin-bottom: 1.5rem; position: relative; }
.faq-cta .btns { display: flex; gap: .75rem; justify-content: center; flex-wrap: wrap; position: relative; }
.faq-cta a {
    padding: .8rem 1.6rem; border-radius: 999px; font-weight: 700; text-decoration: none; font-size: .92rem;
    transition: all .25s; display: inline-flex; align-items: center; gap: .5rem;
}
.faq-cta a.solid { background: #fff; color: var(--primary); }
.faq-cta a.ghost { border: 2px solid rgba(255,255,255,.5); color: #fff; }
.faq-cta a:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,.25); }
@media (max-width: 640px) { .faq-a-inner { padding-left: 1.35rem; } }
@media (prefers-reduced-motion: reduce) {
    .faq-item, .faq-q .qc, .faq-cta a { transition: none; }
}
</style>

<section class="faq-hero">
    <div class="container">
        <h1>Pusat Bantuan &amp; FAQ</h1>
        <p>Jawaban cepat untuk pertanyaan yang paling sering diajukan calon mahasiswa, mahasiswa aktif, dan mitra FKIP UNIMOF.</p>
        <div class="faq-search-wrap">
            <span class="icon" aria-hidden="true">🔍</span>
            <input
                type="search"
                id="faqSearch"
                placeholder="Ketik kata kunci… mis. beasiswa, tes masuk, lokasi"
                autocomplete="off"
                aria-label="Cari pertanyaan"
            >
        </div>
        <div class="faq-hero-stats">
            <div class="faq-hero-stat"><b><?= count($faqs) ?></b><span>Pertanyaan</span></div>
            <div class="faq-hero-stat"><b><?= count($kat_counts) ?></b><span>Kategori</span></div>
            <div class="faq-hero-stat"><b>24/7</b><span>Akses Online</span></div>
        </div>
    </div>
</section>

<div class="container">
    <div class="faq-pills" id="faqPills" role="group" aria-label="Filter kategori FAQ">
        <button class="faq-pill active" type="button" data-cat="" aria-pressed="true">
            📚 Semua <span class="n"><?= count($faqs) ?></span>
        </button>

        <?php foreach ($kat_counts as $kat => $cnt): ?>
            <button
                class="faq-pill"
                type="button"
                data-cat="<?= sanitize((string) $kat) ?>"
                aria-pressed="false"
            >
                <?= $cat_icons[$kat] ?? '💬' ?>
                <?= sanitize((string) $kat) ?>
                <span class="n"><?= (int) $cnt ?></span>
            </button>
        <?php endforeach; ?>
    </div>
</div>

<section class="faq-section">
    <div class="container">
        <?php if ($faqs === []): ?>
            <div class="faq-empty" style="display:block">
                <div class="ei">📭</div>
                <h3>Belum ada FAQ</h3>
                <p style="color:var(--text-muted)">Pertanyaan yang sering diajukan akan tampil di sini.</p>
            </div>
        <?php else: ?>
            <?php foreach ($kat_counts as $kat => $cnt): ?>
                <div class="faq-group" data-group="<?= sanitize((string) $kat) ?>">
                    <div class="faq-group-head">
                        <div class="gi" aria-hidden="true"><?= $cat_icons[$kat] ?? '💬' ?></div>
                        <h2><?= sanitize((string) $kat) ?></h2>
                        <span class="gc"><?= (int) $cnt ?> pertanyaan</span>
                    </div>

                    <?php foreach ($faqs as $faq): ?>
                        <?php if ($faq['kategori'] !== (string) $kat) { continue; } ?>
                        <?php $faqId = (int) ($faq['id'] ?? 0); ?>

                        <div
                            class="faq-item"
                            data-cat="<?= sanitize((string) $kat) ?>"
                            data-search="<?= sanitize(strtolower((string) $faq['pertanyaan'] . ' ' . (string) $faq['jawaban'])) ?>"
                        >
                            <button
                                class="faq-q"
                                type="button"
                                aria-expanded="false"
                                aria-controls="faq-answer-<?= $faqId ?>"
                            >
                                <span class="qi" aria-hidden="true"><?= $cat_icons[$kat] ?? '💬' ?></span>
                                <span class="qt"><?= sanitize((string) $faq['pertanyaan']) ?></span>
                                <span class="qc" aria-hidden="true">▾</span>
                            </button>
                            <div class="faq-a" id="faq-answer-<?= $faqId ?>" aria-hidden="true">
                                <div class="faq-a-inner"><?= sanitize((string) $faq['jawaban']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="faq-empty" id="faqEmpty" role="status" aria-live="polite">
            <div class="ei">🔎</div>
            <h3>Tidak ada hasil cocok</h3>
            <p style="color:var(--text-muted); margin-bottom:1rem">Coba kata kunci lain, atau tanyakan langsung kepada kami.</p>
            <a href="<?= base_url('kontak.php') ?>" class="btn btn-primary">✉️ Hubungi Kami</a>
        </div>
    </div>
</section>

<section class="container">
    <div class="faq-cta">
        <h2>Belum menemukan jawaban?</h2>
        <p>Tim admisi kami siap membantu Anda pada jam kerja (Senin–Jumat, 08.00–16.00 WITA).</p>
        <div class="btns">
            <a href="<?= base_url('kontak.php') ?>" class="solid">✉️ Kirim Pertanyaan</a>
            <a
                href="https://wa.me/<?= sanitize($wa_number) ?>"
                target="_blank"
                rel="noopener noreferrer"
                class="ghost"
            >💬 Chat WhatsApp</a>
        </div>
    </div>
</section>

<?php if (is_string($faqSchemaJson)): ?>
<script type="application/ld+json"><?= $faqSchemaJson ?></script>
<?php endif; ?>

<script>
(() => {
    const searchInput = document.getElementById('faqSearch');
    const pills = Array.from(document.querySelectorAll('.faq-pill'));
    const emptyState = document.getElementById('faqEmpty');

    function openItem(item) {
        const answer = item.querySelector('.faq-a');
        const button = item.querySelector('.faq-q');

        item.classList.add('open');
        answer.style.maxHeight = answer.scrollHeight + 'px';
        answer.setAttribute('aria-hidden', 'false');
        button.setAttribute('aria-expanded', 'true');
    }

    function closeItem(item) {
        const answer = item.querySelector('.faq-a');
        const button = item.querySelector('.faq-q');

        item.classList.remove('open');
        answer.style.maxHeight = '0';
        answer.setAttribute('aria-hidden', 'true');
        button.setAttribute('aria-expanded', 'false');
    }

    function applyFaqFilter() {
        const query = searchInput.value.toLowerCase().trim();
        const activePill = pills.find((pill) => pill.classList.contains('active'));
        const category = activePill ? activePill.dataset.cat : '';
        let visibleCount = 0;

        document.querySelectorAll('.faq-item').forEach((item) => {
            const matchesQuery = !query || item.dataset.search.includes(query);
            const matchesCategory = !category || item.dataset.cat === category;
            const visible = matchesQuery && matchesCategory;

            item.hidden = !visible;

            if (!visible && item.classList.contains('open')) {
                closeItem(item);
            }

            if (visible) {
                visibleCount += 1;
            }
        });

        document.querySelectorAll('.faq-group').forEach((group) => {
            const hasVisibleItem = Array.from(group.querySelectorAll('.faq-item'))
                .some((item) => !item.hidden);

            group.hidden = !hasVisibleItem;
        });

        if (emptyState) {
            emptyState.style.display = visibleCount > 0 ? 'none' : 'block';
        }
    }

    document.querySelectorAll('.faq-q').forEach((button) => {
        button.addEventListener('click', () => {
            const item = button.closest('.faq-item');
            const wasOpen = item.classList.contains('open');

            document.querySelectorAll('.faq-item.open').forEach((openItemElement) => {
                if (openItemElement !== item) {
                    closeItem(openItemElement);
                }
            });

            if (wasOpen) {
                closeItem(item);
            } else {
                openItem(item);
            }
        });
    });

    searchInput.addEventListener('input', applyFaqFilter);

    pills.forEach((pill) => {
        pill.addEventListener('click', () => {
            pills.forEach((item) => {
                const active = item === pill;
                item.classList.toggle('active', active);
                item.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            applyFaqFilter();
        });
    });

    window.addEventListener('resize', () => {
        document.querySelectorAll('.faq-item.open .faq-a').forEach((answer) => {
            answer.style.maxHeight = answer.scrollHeight + 'px';
        });
    });

    const firstItem = document.querySelector('.faq-item');

    if (firstItem) {
        openItem(firstItem);
    }

    applyFaqFilter();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>