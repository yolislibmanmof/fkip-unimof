<?php
require_once __DIR__ . '/includes/config.php';

$page_title = 'FAQ — Pertanyaan yang Sering Diajukan';
$page_description = 'Temukan jawaban cepat seputar PMB, akademik, beasiswa, dan kehidupan kampus FKIP UNIMOF.';

// ===== AMBIL DATA FAQ (hanya yang Aktif) =====
$faqs = [];
$kat_counts = [];
try {
    $faqs = $pdo->query("SELECT * FROM faq WHERE status='Aktif' ORDER BY urutan ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($faqs as $f) {
        $k = trim($f['kategori'] ?? '') ?: 'Umum';
        $kat_counts[$k] = ($kat_counts[$k] ?? 0) + 1;
    }
} catch (Exception $e) { $faqs = []; }

$cat_icons = ['PMB'=>'📝','Akademik'=>'🎓','Beasiswa'=>'💰','Umum'=>'💬','Kemahasiswaan'=>'🎉','Fasilitas'=>'🏢','Alumni'=>'🎓'];
$wa_number = preg_replace('/[^0-9]/', '', get_setting('whatsapp_number', get_setting('whatsapp', '6281234567890')));

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
.faq-a-inner::before {
    content: '💡'; margin-right: .5rem;
}

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
</style>

<!-- ===== HERO ===== -->
<section class="faq-hero">
    <div class="container">
        <h1>Pusat Bantuan &amp; FAQ</h1>
        <p>Jawaban cepat untuk pertanyaan yang paling sering diajukan calon mahasiswa, mahasiswa aktif, dan mitra FKIP UNIMOF.</p>
        <div class="faq-search-wrap">
            <span class="icon">🔍</span>
            <input type="text" id="faqSearch" placeholder="Ketik kata kunci… mis. beasiswa, tes masuk, lokasi" autocomplete="off" aria-label="Cari pertanyaan">
        </div>
        <div class="faq-hero-stats">
            <div class="faq-hero-stat"><b><?= count($faqs) ?></b><span>Pertanyaan</span></div>
            <div class="faq-hero-stat"><b><?= count($kat_counts) ?></b><span>Kategori</span></div>
            <div class="faq-hero-stat"><b>24/7</b><span>Akses Online</span></div>
        </div>
    </div>
</section>

<!-- ===== PILLS KATEGORI ===== -->
<div class="container">
    <div class="faq-pills" id="faqPills">
        <button class="faq-pill active" data-cat="">📚 Semua <span class="n"><?= count($faqs) ?></span></button>
        <?php foreach ($kat_counts as $kat => $cnt): ?>
        <button class="faq-pill" data-cat="<?= sanitize($kat) ?>"><?= $cat_icons[$kat] ?? '💬' ?> <?= sanitize($kat) ?> <span class="n"><?= $cnt ?></span></button>
        <?php endforeach; ?>
    </div>
</div>

<!-- ===== LIST FAQ ===== -->
<section class="faq-section">
    <div class="container">
        <?php if (empty($faqs)): ?>
            <div class="faq-empty" style="display:block">
                <div class="ei">📭</div>
                <h3>Belum ada FAQ</h3>
                <p style="color:var(--text-muted)">Pertanyaan yang sering diajukan akan tampil di sini.</p>
            </div>
        <?php else: foreach ($kat_counts as $kat => $cnt): ?>
        <div class="faq-group" data-group="<?= sanitize($kat) ?>">
            <div class="faq-group-head">
                <div class="gi"><?= $cat_icons[$kat] ?? '💬' ?></div>
                <h2><?= sanitize($kat) ?></h2>
                <span class="gc"><?= $cnt ?> pertanyaan</span>
            </div>
            <?php foreach ($faqs as $f): if ((trim($f['kategori'] ?? '') ?: 'Umum') !== $kat) continue; ?>
            <div class="faq-item" data-cat="<?= sanitize($kat) ?>" data-search="<?= sanitize(strtolower($f['pertanyaan'] . ' ' . $f['jawaban'])) ?>">
                <button class="faq-q" aria-expanded="false">
                    <span class="qi"><?= $cat_icons[$kat] ?? '💬' ?></span>
                    <span class="qt"><?= sanitize($f['pertanyaan']) ?></span>
                    <span class="qc">▾</span>
                </button>
                <div class="faq-a"><div class="faq-a-inner"><?= sanitize($f['jawaban']) ?></div></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; endif; ?>

        <div class="faq-empty" id="faqEmpty">
            <div class="ei">🔎</div>
            <h3>Tidak ada hasil cocok</h3>
            <p style="color:var(--text-muted); margin-bottom:1rem">Coba kata kunci lain, atau tanyakan langsung kepada kami.</p>
            <a href="<?= base_url('kontak.php') ?>" class="btn btn-primary">✉️ Hubungi Kami</a>
        </div>
    </div>
</section>

<!-- ===== CTA ===== -->
<section class="container">
    <div class="faq-cta">
        <h2>Belum menemukan jawaban?</h2>
        <p>Tim admisi kami siap membantu Anda pada jam kerja (Senin–Jumat, 08.00–16.00 WITA).</p>
        <div class="btns">
            <a href="<?= base_url('kontak.php') ?>" class="solid">✉️ Kirim Pertanyaan</a>
            <a href="https://wa.me/<?= $wa_number ?>" target="_blank" rel="noopener" class="ghost">💬 Chat WhatsApp</a>
        </div>
    </div>
</section>

<!-- ===== SCHEMA.ORG FAQPage (SEO) ===== -->
<?php if (!empty($faqs)): ?>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": <?= json_encode(array_map(function($f) {
      return [
          '@type' => 'Question',
          'name' => $f['pertanyaan'],
          'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['jawaban']],
      ];
  }, $faqs), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
}
</script>
<?php endif; ?>

<script>
// ===== ACCORDION =====
function openItem(it){ it.classList.add('open'); const a=it.querySelector('.faq-a'); a.style.maxHeight=a.scrollHeight+'px'; it.querySelector('.faq-q').setAttribute('aria-expanded','true'); }
function closeItem(it){ it.classList.remove('open'); const a=it.querySelector('.faq-a'); a.style.maxHeight='0'; it.querySelector('.faq-q').setAttribute('aria-expanded','false'); }
document.querySelectorAll('.faq-q').forEach(btn=>{
    btn.addEventListener('click', ()=>{
        const it = btn.closest('.faq-item');
        const wasOpen = it.classList.contains('open');
        document.querySelectorAll('.faq-item.open').forEach(o=>{ if(o!==it) closeItem(o); });
        wasOpen ? closeItem(it) : openItem(it);
    });
});

// ===== FILTER: SEARCH + KATEGORI =====
const faqSearch = document.getElementById('faqSearch');
const pills = document.querySelectorAll('.faq-pill');
function applyFaqFilter(){
    const q = faqSearch.value.toLowerCase().trim();
    const cat = document.querySelector('.faq-pill.active')?.dataset.cat || '';
    let visible = 0;
    document.querySelectorAll('.faq-item').forEach(it=>{
        const okQ = !q || it.dataset.search.includes(q);
        const okC = !cat || it.dataset.cat === cat;
        const show = okQ && okC;
        it.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    document.querySelectorAll('.faq-group').forEach(g=>{
        const any = [...g.querySelectorAll('.faq-item')].some(i=>i.style.display !== 'none');
        g.style.display = any ? '' : 'none';
    });
    document.getElementById('faqEmpty').style.display = visible ? 'none' : 'block';
}
faqSearch.addEventListener('input', applyFaqFilter);
pills.forEach(p=>p.addEventListener('click', ()=>{
    pills.forEach(x=>x.classList.remove('active'));
    p.classList.add('active');
    applyFaqFilter();
}));

// Buka item pertama otomatis agar halaman tidak terlihat "kosong"
const first = document.querySelector('.faq-item');
if (first) openItem(first);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>