<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== PROSES POST (SAVE / DELETE / TOGGLE) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'save_faq') {
        $pertanyaan = trim($_POST['pertanyaan'] ?? '');
        $jawaban    = trim($_POST['jawaban'] ?? '');
        $kategori   = trim($_POST['kategori'] ?? 'Umum') ?: 'Umum';
        $urutan     = (int)($_POST['urutan'] ?? 0);
        $status     = ($_POST['status'] ?? 'Aktif') === 'Nonaktif' ? 'Nonaktif' : 'Aktif';

        if ($pertanyaan === '' || $jawaban === '') {
            flash_message('error', '❌ Pertanyaan dan jawaban wajib diisi.');
        } else {
            try {
                if ($id > 0) {
                    $pdo->prepare("UPDATE faq SET kategori=?, pertanyaan=?, jawaban=?, urutan=?, status=? WHERE id=?")
                        ->execute([$kategori, $pertanyaan, $jawaban, $urutan, $status, $id]);
                    flash_message('success', '✅ FAQ berhasil diperbarui.');
                } else {
                    $pdo->prepare("INSERT INTO faq (kategori, pertanyaan, jawaban, urutan, status) VALUES (?,?,?,?,?)")
                        ->execute([$kategori, $pertanyaan, $jawaban, $urutan, $status]);
                    flash_message('success', '✅ FAQ baru berhasil ditambahkan.');
                }
            } catch (PDOException $e) {
                flash_message('error', '❌ Gagal menyimpan: ' . $e->getMessage());
            }
        }
    }
    elseif ($action === 'delete_faq' && $id) {
        $pdo->prepare("DELETE FROM faq WHERE id=?")->execute([$id]);
        flash_message('success', '🗑️ FAQ dihapus.');
    }
    elseif ($action === 'toggle_faq' && $id) {
        $pdo->prepare("UPDATE faq SET status=IF(status='Aktif','Nonaktif','Aktif') WHERE id=?")->execute([$id]);
        flash_message('success', '✅ Status FAQ diubah.');
    }

    header('Location: faq.php?' . http_build_query($_GET));
    exit;
}

// ===== FILTER & DATA =====
$q = trim($_GET['q'] ?? '');
$kat_filter = trim($_GET['kategori'] ?? '');

$where = "WHERE 1=1"; $params = [];
if ($q !== '') { $where .= " AND (pertanyaan LIKE ? OR jawaban LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($kat_filter !== '') { $where .= " AND kategori = ?"; $params[] = $kat_filter; }

$stmt = $pdo->prepare("SELECT * FROM faq $where ORDER BY urutan ASC, id ASC");
$stmt->execute($params);
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stat_total   = (int)$pdo->query("SELECT COUNT(*) FROM faq")->fetchColumn();
$stat_aktif   = (int)$pdo->query("SELECT COUNT(*) FROM faq WHERE status='Aktif'")->fetchColumn();
$stat_nonaktif= $stat_total - $stat_aktif;
$kat_list     = $pdo->query("SELECT DISTINCT kategori FROM faq ORDER BY kategori")->fetchAll(PDO::FETCH_COLUMN);

$csrf = generate_csrf_token();
$active_menu = 'faq';
$page_heading = 'Kelola FAQ';
$breadcrumbs = [['Dashboard','dashboard.php'], ['FAQ', null]];
require __DIR__ . '/includes/header.php';
?>

<style>
.faq-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap: 1.25rem; margin-bottom: 2rem; }
.fq-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 1.5rem; position: relative; overflow: hidden; box-shadow: var(--shadow-sm); }
.fq-card::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background: var(--sc, var(--primary)); }
.fq-card .n { font-family: var(--font-display); font-size: 2.25rem; font-weight: 900; color: var(--sc, var(--primary)); line-height: 1; margin: .5rem 0 .25rem; }
.fq-card .l { font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: var(--text-muted); font-weight: 700; }
.fq-card .i { font-size: 1.5rem; }
.faq-toolbar { display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; margin-bottom:1.5rem; }
.faq-toolbar .search { flex:1; min-width:240px; position:relative; }
.faq-toolbar .search input { width:100%; padding:.75rem 1rem .75rem 2.6rem; border:2px solid var(--border); border-radius:var(--radius-md); background:var(--bg-secondary); font-family:inherit; font-size:.92rem; color:var(--text-primary); }
.faq-toolbar .search input:focus { outline:none; border-color:var(--primary); }
.faq-toolbar .search .si { position:absolute; left:.9rem; top:50%; transform:translateY(-50%); opacity:.55; }
.faq-toolbar select { padding:.75rem 1rem; border:2px solid var(--border); border-radius:var(--radius-md); background:var(--bg-secondary); font-family:inherit; font-size:.9rem; color:var(--text-primary); cursor:pointer; }
.faq-table-wrap { background: var(--bg-primary); border:1px solid var(--border); border-radius: var(--radius-xl); overflow:hidden; box-shadow: var(--shadow-sm); }
.faq-table { width:100%; border-collapse: collapse; }
.faq-table th { background: var(--bg-secondary); padding:.85rem 1rem; text-align:left; font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; color:var(--text-muted); border-bottom:2px solid var(--border); }
.faq-table td { padding:1rem; border-bottom:1px solid var(--border); vertical-align:top; font-size:.9rem; color:var(--text-secondary); }
.faq-table tr:last-child td { border-bottom:none; }
.faq-table tr:hover td { background: var(--bg-secondary); }
.fq-q { font-weight:700; color:var(--text-primary); margin-bottom:.25rem; }
.fq-a { font-size:.82rem; color:var(--text-muted); display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.fq-urutan { font-family: var(--font-mono, monospace); font-weight:700; color:var(--primary); }
.badge-st { padding:.25rem .7rem; border-radius:999px; font-size:.7rem; font-weight:800; text-transform:uppercase; }
.badge-st.aktif { background:#dcfce7; color:#166534; }
.badge-st.nonaktif { background:#fee2e2; color:#991b1b; }
.fq-actions { display:flex; gap:.35rem; justify-content:flex-end; }
.fq-btn { width:34px; height:34px; border-radius:9px; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:.95rem; transition:all .2s; }
.fq-btn.edit { background:#dbeafe; color:#2563eb; } .fq-btn.edit:hover { background:#2563eb; color:#fff; }
.fq-btn.tog { background:#fef3c7; color:#d97706; } .fq-btn.tog:hover { background:#d97706; color:#fff; }
.fq-btn.del { background:#fee2e2; color:#dc2626; } .fq-btn.del:hover { background:#dc2626; color:#fff; }
/* Modal */
.fq-modal { position:fixed; inset:0; background:rgba(15,23,42,.7); backdrop-filter:blur(6px); z-index:9000; display:none; align-items:center; justify-content:center; padding:1.5rem; }
.fq-modal.show { display:flex; }
.fq-modal-box { background:var(--bg-primary); border:1px solid var(--border); border-radius:var(--radius-xl); width:100%; max-width:640px; max-height:90vh; overflow-y:auto; box-shadow:var(--shadow-xl); }
.fq-modal-head { padding:1.25rem 1.5rem; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
.fq-modal-head h3 { font-family:var(--font-display); font-size:1.15rem; }
.fq-modal-body { padding:1.5rem; display:flex; flex-direction:column; gap:1rem; }
.fq-field label { display:block; font-size:.82rem; font-weight:700; margin-bottom:.4rem; color:var(--text-secondary); }
.fq-field input, .fq-field textarea, .fq-field select { width:100%; padding:.7rem .9rem; border:2px solid var(--border); border-radius:var(--radius-md); background:var(--bg-secondary); font-family:inherit; font-size:.92rem; color:var(--text-primary); }
.fq-field textarea { min-height:110px; resize:vertical; line-height:1.6; }
.fq-field input:focus, .fq-field textarea:focus, .fq-field select:focus { outline:none; border-color:var(--primary); }
.fq-row2 { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
.fq-modal-foot { padding:1rem 1.5rem; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:.6rem; }
.btn-p { padding:.7rem 1.4rem; border:none; border-radius:var(--radius-md); background:linear-gradient(135deg,var(--primary),var(--primary-light,#16a34a)); color:#fff; font-weight:700; cursor:pointer; font-family:inherit; }
.btn-s { padding:.7rem 1.4rem; border:1px solid var(--border); border-radius:var(--radius-md); background:var(--bg-secondary); color:var(--text-primary); font-weight:600; cursor:pointer; font-family:inherit; }
@media (max-width:640px){ .fq-row2 { grid-template-columns:1fr; } }
</style>

<!-- Stats -->
<div class="faq-stats">
    <div class="fq-card" style="--sc:#3b82f6"><div class="i">❓</div><div class="n"><?= $stat_total ?></div><div class="l">Total FAQ</div></div>
    <div class="fq-card" style="--sc:#10b981"><div class="i">✅</div><div class="n"><?= $stat_aktif ?></div><div class="l">Aktif</div></div>
    <div class="fq-card" style="--sc:#ef4444"><div class="i">🚫</div><div class="n"><?= $stat_nonaktif ?></div><div class="l">Nonaktif</div></div>
    <div class="fq-card" style="--sc:#8b5cf6"><div class="i">📂</div><div class="n"><?= count($kat_list) ?></div><div class="l">Kategori</div></div>
</div>

<!-- Toolbar -->
<div class="faq-toolbar">
    <div class="search">
        <span class="si">🔍</span>
        <input type="text" id="fqSearch" placeholder="Cari pertanyaan / jawaban…" value="<?= sanitize($q) ?>">
    </div>
    <select id="fqKat" onchange="filterKat(this.value)">
        <option value="">📂 Semua Kategori</option>
        <?php foreach ($kat_list as $k): ?>
        <option value="<?= sanitize($k) ?>" <?= $kat_filter === $k ? 'selected' : '' ?>><?= sanitize($k) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn-p" onclick="openFaqModal(0)">➕ Tambah FAQ</button>
</div>

<!-- Table -->
<div class="faq-table-wrap">
    <table class="faq-table">
        <thead><tr>
            <th style="width:60px">Urut</th><th>Pertanyaan &amp; Jawaban</th>
            <th style="width:130px">Kategori</th><th style="width:100px">Status</th>
            <th style="width:130px; text-align:right">Aksi</th>
        </tr></thead>
        <tbody id="fqBody">
        <?php if (empty($list)): ?>
            <tr><td colspan="5" style="text-align:center; padding:3rem; color:var(--text-muted)">
                📭 Tidak ada FAQ. Klik <b>➕ Tambah FAQ</b> untuk membuat yang pertama.
            </td></tr>
        <?php else: foreach ($list as $f): ?>
            <tr data-search="<?= sanitize(strtolower($f['pertanyaan'] . ' ' . $f['jawaban'])) ?>">
                <td class="fq-urutan">#<?= (int)$f['urutan'] ?></td>
                <td>
                    <div class="fq-q"><?= sanitize($f['pertanyaan']) ?></div>
                    <div class="fq-a"><?= sanitize($f['jawaban']) ?></div>
                </td>
                <td><span class="badge-st" style="background:var(--bg-tertiary); color:var(--text-secondary)"><?= sanitize($f['kategori']) ?></span></td>
                <td><span class="badge-st <?= strtolower($f['status']) === 'aktif' ? 'aktif' : 'nonaktif' ?>"><?= sanitize($f['status']) ?></span></td>
                <td>
                    <div class="fq-actions">
                        <button class="fq-btn edit" title="Edit" onclick='openFaqModal(<?= (int)$f['id'] ?>, <?= htmlspecialchars(json_encode($f, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)'>✏️</button>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Ubah status FAQ ini?')">
                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                            <input type="hidden" name="action" value="toggle_faq">
                            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                            <button class="fq-btn tog" title="Toggle status" type="submit">🔄</button>
                        </form>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Hapus FAQ ini?')">
                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                            <input type="hidden" name="action" value="delete_faq">
                            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                            <button class="fq-btn del" title="Hapus" type="submit">🗑️</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Form -->
<div class="fq-modal" id="fqModal" onclick="if(event.target===this)closeFaqModal()">
    <form method="POST" class="fq-modal-box">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
        <input type="hidden" name="action" value="save_faq">
        <input type="hidden" name="id" id="fqId" value="0">
        <div class="fq-modal-head">
            <h3 id="fqModalTitle">➕ Tambah FAQ</h3>
            <button type="button" class="fq-btn del" onclick="closeFaqModal()">✕</button>
        </div>
        <div class="fq-modal-body">
            <div class="fq-field">
                <label>Pertanyaan *</label>
                <textarea name="pertanyaan" id="fqQ" required placeholder="Contoh: Kapan pendaftaran PMB dibuka?"></textarea>
            </div>
            <div class="fq-field">
                <label>Jawaban *</label>
                <textarea name="jawaban" id="fqA" required placeholder="Tulis jawaban lengkap dan ramah…"></textarea>
            </div>
            <div class="fq-row2">
                <div class="fq-field">
                    <label>Kategori</label>
                    <input type="text" name="kategori" id="fqKatInput" list="katList" value="Umum">
                    <datalist id="katList">
                        <?php foreach ($kat_list as $k): ?><option value="<?= sanitize($k) ?>"><?php endforeach; ?>
                        <option value="PMB"><option value="Akademik"><option value="Beasiswa"><option value="Umum">
                    </datalist>
                </div>
                <div class="fq-field">
                    <label>Urutan (kecil = di atas)</label>
                    <input type="number" name="urutan" id="fqUrutan" value="0" min="0" max="999">
                </div>
            </div>
            <div class="fq-field">
                <label>Status</label>
                <select name="status" id="fqStatus">
                    <option value="Aktif">✅ Aktif (tampil di publik)</option>
                    <option value="Nonaktif">🚫 Nonaktif (disembunyikan)</option>
                </select>
            </div>
        </div>
        <div class="fq-modal-foot">
            <button type="button" class="btn-s" onclick="closeFaqModal()">Batal</button>
            <button type="submit" class="btn-p">💾 Simpan FAQ</button>
        </div>
    </form>
</div>

<script>
function openFaqModal(id, data) {
    document.getElementById('fqId').value = id || 0;
    document.getElementById('fqModalTitle').textContent = id ? '✏️ Edit FAQ' : '➕ Tambah FAQ';
    document.getElementById('fqQ').value = data ? data.pertanyaan : '';
    document.getElementById('fqA').value = data ? data.jawaban : '';
    document.getElementById('fqKatInput').value = data ? data.kategori : 'Umum';
    document.getElementById('fqUrutan').value = data ? data.urutan : 0;
    document.getElementById('fqStatus').value = data ? data.status : 'Aktif';
    document.getElementById('fqModal').classList.add('show');
}
function closeFaqModal(){ document.getElementById('fqModal').classList.remove('show'); }

// Search client-side
document.getElementById('fqSearch').addEventListener('input', function(){
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('#fqBody tr[data-search]').forEach(tr=>{
        tr.style.display = (!q || tr.dataset.search.includes(q)) ? '' : 'none';
    });
});
function filterKat(k){
    const u = new URL(window.location);
    if (k) u.searchParams.set('kategori', k); else u.searchParams.delete('kategori');
    window.location = u;
}
document.addEventListener('keydown', e=>{ if(e.key==='Escape') closeFaqModal(); });
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>