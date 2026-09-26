<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== Helper: deteksi pesan prioritas =====
if (!function_exists('is_priority_msg')) {
    function is_priority_msg(string $text): bool {
        return (bool)preg_match('/mendesak|urgent|segera|penting|darurat|deadline|asesmen/i', $text);
    }
}

// ===== Proses AKSI POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('error', 'Token tidak valid.');
    } else {
        $action = $_POST['action'] ?? '';
        $id  = (int)($_POST['id'] ?? 0);
        $ids = array_map('intval', (array)($_POST['ids'] ?? []));
        $ids = array_values(array_filter($ids));

        if ($action === 'read' && $id) {
            $pdo->prepare("UPDATE kontak SET status='Dibaca' WHERE id=?")->execute([$id]);
            flash_message('success', 'Pesan ditandai sudah dibaca.');
        } elseif ($action === 'replied' && $id) {
            $pdo->prepare("UPDATE kontak SET status='Dibalas' WHERE id=?")->execute([$id]);
            flash_message('success', 'Pesan ditandai telah dibalas. 👍');
        } elseif ($action === 'delete' && $id) {
            $pdo->prepare("DELETE FROM kontak WHERE id=?")->execute([$id]);
            flash_message('success', 'Pesan dihapus.');
        } elseif ($action === 'bulk_read' && $ids) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE kontak SET status='Dibaca' WHERE id IN ($ph)")->execute($ids);
            flash_message('success', count($ids) . ' pesan ditandai dibaca.');
        } elseif ($action === 'bulk_delete' && $ids) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM kontak WHERE id IN ($ph)")->execute($ids);
            flash_message('success', count($ids) . ' pesan dihapus.');
        }
    }
    header('Location: kontak.php?' . http_build_query($_GET));
    exit;
}

// ===== Export CSV =====
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pesan-masuk-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['ID','Nama','Email','Telepon','Subjek','Pesan','Status','Tanggal']);
    foreach ($pdo->query("SELECT * FROM kontak ORDER BY created_at DESC") as $r) {
        fputcsv($out, [$r['id'],$r['nama'],$r['email'],$r['telepon'],$r['subjek'],$r['pesan'],$r['status'],$r['created_at']]);
    }
    exit;
}

// ===== Filter, Search, Pagination =====
$filter  = $_GET['filter'] ?? 'semua';
$q       = trim($_GET['q'] ?? '');
$halaman = max(1, (int)($_GET['halaman'] ?? 1));
$per_page = 15;
$offset = ($halaman - 1) * $per_page;

$where = 'WHERE 1=1';
$params = [];
if ($filter === 'baru')    { $where .= " AND status='Baru'"; }
if ($filter === 'dibaca')  { $where .= " AND status='Dibaca'"; }
if ($filter === 'dibalas') { $where .= " AND status='Dibalas'"; }
if ($q !== '') {
    $where .= ' AND (nama LIKE ? OR email LIKE ? OR subjek LIKE ? OR pesan LIKE ?)';
    $params = array_merge($params, ["%$q%", "%$q%", "%$q%", "%$q%"]);
}

$cs = $pdo->prepare("SELECT COUNT(*) FROM kontak $where");
$cs->execute($params);
$total = (int)$cs->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));

$stmt = $pdo->prepare("SELECT * FROM kontak $where ORDER BY (status='Baru') DESC, created_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$per_page, $offset]));
$pesan = $stmt->fetchAll();

// Counts global
$count_all    = (int)$pdo->query("SELECT COUNT(*) FROM kontak")->fetchColumn();
$count_baru   = (int)$pdo->query("SELECT COUNT(*) FROM kontak WHERE status='Baru'")->fetchColumn();
$count_dibaca = (int)$pdo->query("SELECT COUNT(*) FROM kontak WHERE status='Dibaca'")->fetchColumn();
$count_dibalas= (int)$pdo->query("SELECT COUNT(*) FROM kontak WHERE status='Dibalas'")->fetchColumn();

$csrf = generate_csrf_token();
$active_menu = 'kontak';
$page_heading = 'Pesan Masuk';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Pesan Masuk', null]];
require __DIR__ . '/includes/header.php';
?>

<!-- ===== INBOX STATS ===== -->
<div class="inbox-stats" data-aos="fade-down">
    <a href="?filter=semua" class="inbox-stat <?= $filter==='semua'?'active':'' ?>" style="--is-color:#3b82f6">
        <span class="is-num"><?= $count_all ?></span>
        <span class="is-label">📥 Total Pesan</span>
    </a>
    <a href="?filter=baru" class="inbox-stat <?= $filter==='baru'?'active':'' ?> <?= $count_baru>0?'has-new':'' ?>" style="--is-color:#ef4444">
        <span class="is-num"><?= $count_baru ?></span>
        <span class="is-label">🔵 Belum Dibaca</span>
        <?php if ($count_baru > 0): ?><span class="is-pulse"></span><?php endif; ?>
    </a>
    <a href="?filter=dibaca" class="inbox-stat <?= $filter==='dibaca'?'active':'' ?>" style="--is-color:#10b981">
        <span class="is-num"><?= $count_dibaca ?></span>
        <span class="is-label">⚪ Dibaca</span>
    </a>
    <a href="?filter=dibalas" class="inbox-stat <?= $filter==='dibalas'?'active':'' ?>" style="--is-color:#8b5cf6">
        <span class="is-num"><?= $count_dibalas ?></span>
        <span class="is-label">✅ Dibalas</span>
    </a>
    <?php if ($count_baru === 0 && $count_all > 0): ?>
    <div class="inbox-zero">🎉 Inbox Zero!</div>
    <?php endif; ?>
</div>

<div class="card inbox-card">
    <!-- ===== TOOLBAR ===== -->
    <div class="inbox-toolbar">
        <div class="inbox-search">
            <span class="is-icon">🔍</span>
            <input type="text" id="inboxSearch" class="inbox-search-input" placeholder="Cari nama, email, subjek, atau isi pesan... (Ctrl+/)" value="<?= sanitize($q) ?>">
            <?php if ($q !== ''): ?><a href="?filter=<?= sanitize($filter) ?>" class="is-clear">✕</a><?php endif; ?>
        </div>
        <div class="inbox-tools">
            <label class="select-all-wrap"><input type="checkbox" id="selAll" onchange="toggleAll(this)"></label>
            <a href="?export=csv" class="btn-sm gray">📥 Export</a>
        </div>
    </div>

    <!-- ===== BULK BAR ===== -->
    <div class="bulk-bar" id="bulkBar" style="display:none">
        <span class="bulk-info"><span id="bulkCount">0</span> pesan dipilih</span>
        <form method="POST" id="bulkForm" style="display:flex;gap:.5rem;flex-wrap:wrap">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <button type="submit" name="action" value="bulk_read" class="bulk-btn read">⚪ Tandai Dibaca</button>
            <button type="submit" name="action" value="bulk_delete" class="bulk-btn delete" onclick="return confirm('Hapus pesan terpilih?')">🗑️ Hapus</button>
        </form>
        <button class="bulk-btn cancel" onclick="clearSel()">Batal</button>
    </div>

    <!-- ===== MESSAGE LIST ===== -->
    <?php if (empty($pesan)): ?>
        <div class="inbox-empty">
            <div class="ie-illustration">
                <div class="ie-circle"></div>
                <span class="ie-icon"><?= $q || $filter !== 'semua' ? '🔍' : '📭' ?></span>
            </div>
            <h3><?= $q || $filter !== 'semua' ? 'Tidak ada pesan cocok' : 'Inbox kosong' ?></h3>
            <p><?= $q || $filter !== 'semua' ? 'Coba ubah kata kunci atau filter pencarian.' : 'Semua pesan pengunjung akan muncul di sini secara real-time.' ?></p>
            <?php if ($q || $filter !== 'semua'): ?>
                <a href="kontak.php" class="btn-sm gray">🔄 Reset Filter</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="msg-list">
            <?php foreach ($pesan as $p):
                $hue = crc32($p['nama']) % 360;
                $priority = is_priority_msg($p['pesan'] . ' ' . $p['subjek']);
            ?>
            <div class="msg-row status-<?= strtolower($p['status']) ?> <?= $p['status']==='Baru'?'unread':'' ?> <?= $priority?'priority':'' ?>"
                data-id="<?= $p['id'] ?>"
                data-nama="<?= htmlspecialchars($p['nama'], ENT_QUOTES, 'UTF-8') ?>"
                data-email="<?= htmlspecialchars($p['email'], ENT_QUOTES, 'UTF-8') ?>"
                data-telepon="<?= htmlspecialchars($p['telepon'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                data-subjek="<?= htmlspecialchars($p['subjek'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                data-pesan="<?= htmlspecialchars($p['pesan'], ENT_QUOTES, 'UTF-8') ?>"
                data-status="<?= htmlspecialchars($p['status'], ENT_QUOTES, 'UTF-8') ?>"
                data-created="<?= sanitize(date('d F Y, H:i', strtotime($p['created_at']))) ?>"
                data-hue="<?= $hue ?>"
                onclick="openMessage(this)">
                
                <input type="checkbox" class="row-check" form="bulkForm" name="ids[]" value="<?= $p['id'] ?>" onclick="event.stopPropagation()" onchange="updateBulk()">
                
                <div class="msg-avatar" style="background:linear-gradient(135deg,hsl(<?= $hue ?>,70%,50%),hsl(<?= ($hue+40)%360 ?>,70%,40%))">
                    <?= strtoupper(substr($p['nama'], 0, 1)) ?>
                </div>
                
                <div class="msg-main">
                    <div class="msg-top">
                        <strong class="msg-name"><?= sanitize($p['nama']) ?></strong>
                        <?php if ($priority): ?><span class="prio-flag" title="Pesan prioritas">🔥</span><?php endif; ?>
                        <span class="msg-subject">— <?= sanitize($p['subjek'] ?: '(tanpa subjek)') ?></span>
                        <span class="msg-badge b-<?= strtolower($p['status']) ?>"><?= $p['status'] ?></span>
                    </div>
                    <div class="msg-excerpt"><?= sanitize(excerpt($p['pesan'], 110)) ?></div>
                </div>
                
                <div class="msg-side">
                    <time class="msg-time"><?= time_ago(strtotime($p['created_at'])) ?></time>
                    <div class="row-quick">
                    <button class="rq-btn" title="Balas" onclick="event.stopPropagation(); openMessage(this.closest('.msg-row')); setTimeout(()=>document.getElementById('replyBtn').click(),100)">↩️</button>
                    <button class="rq-btn danger" title="Hapus" onclick="event.stopPropagation(); askDelete(<?= $p['id'] ?>, <?= json_encode(sanitize($p['nama'])) ?>)">🗑️</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav class="inbox-pagination">
            <span class="ip-info">Halaman <?= $halaman ?> dari <?= $total_pages ?> (<?= $total ?> pesan)</span>
            <div class="ip-buttons">
                <?php if ($halaman > 1): ?><a class="ip-btn" href="?<?= http_build_query(array_merge($_GET, ['halaman'=>$halaman-1])) ?>">←</a><?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?= $i === $halaman ? "<span class='ip-btn current'>$i</span>" : "<a class='ip-btn' href='?" . http_build_query(array_merge($_GET, ['halaman'=>$i])) . "'>$i</a>" ?>
                <?php endfor; ?>
                <?php if ($halaman < $total_pages): ?><a class="ip-btn" href="?<?= http_build_query(array_merge($_GET, ['halaman'=>$halaman+1])) ?>">→</a><?php endif; ?>
            </div>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- ===== READING PANE MODAL ===== -->
<div class="modal-overlay" id="readModal" onclick="if(event.target===this)closeRead()">
    <div class="read-pane">
        <div class="read-header">
            <div class="read-avatar" id="readAvatar">?</div>
            <div class="read-headinfo">
                <h3 id="readNama">Nama Pengirim</h3>
                <div class="read-submeta">
                    <span id="readEmail">email</span>
                    <span id="readTelepon"></span>
                    <span id="readCreated"></span>
                </div>
            </div>
            <button class="read-close" onclick="closeRead()">✕</button>
        </div>
        
        <div class="read-body">
            <div class="read-subject-row">
                <h2 id="readSubjek">Subjek</h2>
                <span class="msg-badge" id="readStatus">Status</span>
            </div>
            <div class="read-message" id="readPesan"></div>
        </div>
        
        <div class="read-footer">
            <div class="reply-composer">
                <select id="replyTemplate" class="reply-select">
                    <option value="formal">📄 Balasan Formal</option>
                    <option value="pmb">🎓 Info PMB</option>
                    <option value="followup">🔄 Follow-up</option>
                </select>
                <button class="btn-sm" id="replyBtn" onclick="sendReply()">✉️ Balas via Email</button>
            </div>
            <div class="read-actions">
                <button class="btn-sm gray" onclick="copyEmail()">📋 Copy Email</button>
                <button class="btn-sm gray" id="btnRead" onclick="doAction('read')">⚪ Tandai Dibaca</button>
                <button class="btn-sm amber" id="btnReplied" onclick="doAction('replied')">✅ Tandai Dibalas</button>
                <button class="btn-sm red" id="btnDelete" onclick="askDelete(current.id, current.nama)">🗑️ Hapus</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== CONFIRM MODAL ===== -->
<div class="modal-overlay" id="confirmModal" onclick="if(event.target===this)closeConfirm()">
    <div class="confirm-box">
        <div class="confirm-icon">🗑️</div>
        <h3>Hapus Pesan?</h3>
        <p id="confirmMsg">Pesan akan dihapus permanen.</p>
        <div class="confirm-actions">
            <button class="btn-sm gray" onclick="closeConfirm()">Batal</button>
            <button class="btn-sm red" id="confirmOk">Ya, Hapus</button>
        </div>
    </div>
</div>

<!-- Hidden single-action form -->
<form id="actionForm" method="POST" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
    <input type="hidden" name="id" id="afId">
    <input type="hidden" name="action" id="afAction">
</form>

<!-- ===== SCOPED STYLES ===== -->
<style>
/* Stats */
.inbox-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;position:relative}
.inbox-stat{background:#fff;border-radius:16px;padding:1.25rem;text-align:center;text-decoration:none;border:2px solid #f1f5f9;transition:all .3s;position:relative;overflow:hidden}
.inbox-stat::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:var(--is-color)}
.inbox-stat:hover{transform:translateY(-4px);box-shadow:0 10px 25px rgba(0,0,0,.08)}
.inbox-stat.active{border-color:var(--is-color);background:color-mix(in srgb,var(--is-color),#fff 94%)}
.is-num{display:block;font-size:2rem;font-weight:800;color:var(--is-color);line-height:1}
.is-label{font-size:.8rem;color:#64748b;font-weight:600}
.inbox-stat.has-new{animation:newGlow 2s infinite}
@keyframes newGlow{0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,.3)}50%{box-shadow:0 0 0 10px rgba(239,68,68,0)}}
.is-pulse{position:absolute;top:.75rem;right:.75rem;width:10px;height:10px;background:#ef4444;border-radius:50%}
.is-pulse::after{content:'';position:absolute;inset:0;background:#ef4444;border-radius:50%;animation:ping 1.5s infinite}
@keyframes ping{to{transform:scale(2.5);opacity:0}}
.inbox-zero{position:absolute;right:0;top:-.75rem;background:linear-gradient(135deg,#10b981,#059669);color:#fff;padding:.35rem .9rem;border-radius:999px;font-size:.75rem;font-weight:700;box-shadow:0 4px 12px rgba(16,185,129,.4);animation:zeroPop .5s}
@keyframes zeroPop{from{transform:scale(0)}to{transform:scale(1)}}

/* Toolbar */
.inbox-card{padding:0!important;overflow:hidden}
.inbox-toolbar{display:flex;gap:1rem;align-items:center;padding:1.25rem 1.5rem;border-bottom:1px solid #f1f5f9;background:#fafbfc;flex-wrap:wrap}
.inbox-search{flex:1;min-width:250px;position:relative;display:flex;align-items:center}
.is-icon{position:absolute;left:1rem;pointer-events:none}
.inbox-search-input{width:100%;padding:.7rem 2.5rem .7rem 2.75rem;border:2px solid #e2e8f0;border-radius:999px;font-size:.9rem;font-family:inherit;background:#fff;transition:all .2s}
.inbox-search-input:focus{outline:none;border-color:#0a6847;box-shadow:0 0 0 4px rgba(10,104,71,.1)}
.is-clear{position:absolute;right:.9rem;color:#94a3b8;text-decoration:none;width:22px;height:22px;display:flex;align-items:center;justify-content:center;border-radius:50%}
.is-clear:hover{background:#fee2e2;color:#dc2626}
.inbox-tools{display:flex;align-items:center;gap:.75rem}
.select-all-wrap input{width:18px;height:18px;cursor:pointer;accent-color:#0a6847}

/* Bulk bar */
.bulk-bar{background:linear-gradient(135deg,#0a6847,#16a34a);color:#fff;padding:.85rem 1.5rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;animation:slideDown .3s}
@keyframes slideDown{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:none}}
.bulk-info{font-weight:600;font-size:.9rem}
#bulkCount{background:#fff;color:#0a6847;border-radius:999px;padding:.1rem .55rem;font-weight:800}
.bulk-btn{padding:.45rem .9rem;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-family:inherit;font-size:.82rem;transition:all .2s}
.bulk-btn.read{background:#fff;color:#0a6847}
.bulk-btn.delete{background:#dc2626;color:#fff}
.bulk-btn.cancel{background:transparent;color:#fff;border:1px solid rgba(255,255,255,.4)}
.bulk-btn:hover{filter:brightness(1.08);transform:translateY(-1px)}

/* Message list */
.msg-list{display:flex;flex-direction:column}
.msg-row{display:flex;gap:1rem;align-items:center;padding:1rem 1.5rem;border-bottom:1px solid #f1f5f9;cursor:pointer;transition:all .15s;position:relative}
.msg-row:hover{background:#f8fafc}
.msg-row.unread{background:#f0f9ff}
.msg-row.unread:hover{background:#e0f2fe}
.msg-row.unread::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:#3b82f6}
.msg-row.priority::after{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:linear-gradient(180deg,#f59e0b,#ef4444)}
.msg-row.unread.priority::before{background:linear-gradient(180deg,#f59e0b,#ef4444)}
.msg-row.selected{background:#f0fdf4}
.msg-row .row-check{width:16px;height:16px;accent-color:#0a6847;cursor:pointer;flex-shrink:0}
.msg-avatar{width:44px;height:44px;border-radius:50%;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.05rem;flex-shrink:0;box-shadow:0 4px 10px rgba(0,0,0,.15)}
.msg-main{flex:1;min-width:0}
.msg-top{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.2rem}
.msg-name{font-size:.92rem;color:#0f172a}
.unread .msg-name{font-weight:800}
.prio-flag{font-size:.85rem;animation:flame 1.5s infinite}
@keyframes flame{0%,100%{transform:scale(1)}50%{transform:scale(1.2)}}
.msg-subject{font-size:.85rem;color:#475569;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:340px}
.unread .msg-subject{font-weight:600;color:#0f172a}
.msg-badge{padding:.15rem .55rem;border-radius:999px;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em}
.b-baru{background:#dbeafe;color:#1e40af}
.b-dibaca{background:#f1f5f9;color:#64748b}
.b-dibalas{background:#dcfce7;color:#166534}
.msg-excerpt{font-size:.82rem;color:#64748b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.msg-side{display:flex;flex-direction:column;align-items:flex-end;gap:.4rem;flex-shrink:0}
.msg-time{font-size:.72rem;color:#94a3b8;font-weight:600}
.unread .msg-time{color:#3b82f6;font-weight:800}
.row-quick{display:flex;gap:.25rem;opacity:0;transition:opacity .2s}
.msg-row:hover .row-quick{opacity:1}
.rq-btn{width:30px;height:30px;border:none;border-radius:8px;background:#fff;box-shadow:0 2px 6px rgba(0,0,0,.08);cursor:pointer;font-size:.85rem;transition:all .2s}
.rq-btn:hover{transform:translateY(-2px)}
.rq-btn.danger:hover{background:#fee2e2}

/* Empty */
.inbox-empty{text-align:center;padding:4rem 2rem}
.ie-illustration{position:relative;width:130px;height:130px;margin:0 auto 1.25rem}
.ie-circle{position:absolute;inset:0;background:linear-gradient(135deg,#f1f5f9,#e2e8f0);border-radius:50%;animation:emptyPulse 3s infinite}
@keyframes emptyPulse{0%,100%{transform:scale(1);opacity:.8}50%{transform:scale(1.08);opacity:.4}}
.ie-icon{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:3.5rem;animation:float 3s ease-in-out infinite}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.inbox-empty h3{font-size:1.3rem;margin-bottom:.5rem}
.inbox-empty p{color:#64748b;margin-bottom:1.25rem}

/* Pagination */
.inbox-pagination{display:flex;justify-content:space-between;align-items:center;padding:1rem 1.5rem;border-top:1px solid #f1f5f9;flex-wrap:wrap;gap:1rem;background:#fafbfc}
.ip-info{font-size:.82rem;color:#64748b}
.ip-buttons{display:flex;gap:.25rem}
.ip-btn{padding:.4rem .75rem;border:1px solid #e2e8f0;border-radius:8px;font-size:.82rem;text-decoration:none;color:#475569;background:#fff;transition:all .2s}
.ip-btn:hover:not(.current){border-color:#0a6847;color:#0a6847}
.ip-btn.current{background:#0a6847;color:#fff;border-color:#0a6847;font-weight:700}

/* Reading pane modal */
.modal-overlay{position:fixed;inset:0;background:rgba(15,23,42,.75);backdrop-filter:blur(6px);display:none;align-items:center;justify-content:center;z-index:9999;padding:1.5rem}
.modal-overlay.open{display:flex}
.read-pane{background:#fff;border-radius:20px;width:100%;max-width:760px;max-height:92vh;display:flex;flex-direction:column;overflow:hidden;animation:paneIn .35s cubic-bezier(.2,.9,.3,1.2);box-shadow:0 40px 100px rgba(0,0,0,.4)}
@keyframes paneIn{from{transform:translateY(30px) scale(.96);opacity:0}to{transform:none;opacity:1}}
.read-header{display:flex;gap:1rem;align-items:center;padding:1.5rem;background:linear-gradient(135deg,#0a6847,#084d35);color:#fff}
.read-avatar{width:52px;height:52px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:800;flex-shrink:0;border:2px solid rgba(255,255,255,.4)}
.read-headinfo{flex:1;min-width:0}
.read-headinfo h3{font-size:1.15rem;margin-bottom:.2rem}
.read-submeta{display:flex;gap:1rem;font-size:.78rem;opacity:.85;flex-wrap:wrap}
.read-close{width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.15);border:none;color:#fff;font-size:1rem;cursor:pointer;transition:all .2s;flex-shrink:0}
.read-close:hover{background:#dc2626;transform:rotate(90deg)}
.read-body{padding:1.75rem;overflow-y:auto;flex:1;background:#fafbfc}
.read-subject-row{display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap}
.read-subject-row h2{font-size:1.3rem;color:#0f172a}
.read-message{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1.5rem;font-size:.95rem;line-height:1.8;color:#334155;white-space:pre-wrap;box-shadow:0 2px 8px rgba(0,0,0,.04)}
.read-footer{padding:1.25rem 1.75rem;border-top:1px solid #e2e8f0;background:#fff;display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;align-items:center}
.reply-composer{display:flex;gap:.5rem;flex-wrap:wrap}
.reply-select{padding:.5rem .75rem;border:2px solid #e2e8f0;border-radius:10px;font-family:inherit;font-size:.82rem;background:#f8fafc;cursor:pointer}
.reply-select:focus{outline:none;border-color:#0a6847}
.read-actions{display:flex;gap:.5rem;flex-wrap:wrap}
.btn-sm.amber{background:#d97706}
.btn-sm.amber:hover{background:#b45309}

/* Confirm */
.confirm-box{background:#fff;border-radius:16px;padding:2rem;max-width:400px;width:100%;text-align:center;animation:paneIn .3s}
.confirm-icon{font-size:3rem;margin-bottom:.75rem}
.confirm-box h3{margin-bottom:.5rem}
.confirm-box p{color:#64748b;margin-bottom:1.5rem;font-size:.9rem}
.confirm-actions{display:flex;gap:.75rem;justify-content:center}

@media(max-width:968px){
    .inbox-stats{grid-template-columns:repeat(2,1fr)}
    .msg-subject{max-width:150px}
    .msg-excerpt{display:none}
    .read-footer{flex-direction:column;align-items:stretch}
}
</style>

<script>
// ===== State =====
let current = { id: 0, nama: '', email: '' };

// ===== Open reading pane =====
function openMessage(row) {
    const d = row.dataset;
    current = { id: d.id, nama: d.nama, email: d.email };
    document.getElementById('readAvatar').textContent = d.nama.charAt(0).toUpperCase();
    document.getElementById('readAvatar').style.background =
        `linear-gradient(135deg,hsl(${d.hue},70%,50%),hsl(${(parseInt(d.hue)+40)%360},70%,40%))`;
    document.getElementById('readNama').textContent = d.nama;
    document.getElementById('readEmail').textContent = '📧 ' + d.email;
    document.getElementById('readTelepon').textContent = d.telepon ? '📞 ' + d.telepon : '';
    document.getElementById('readCreated').textContent = '🕐 ' + d.created;
    document.getElementById('readSubjek').textContent = d.subjek || '(tanpa subjek)';
    document.getElementById('readPesan').textContent = d.pesan;
    const badge = document.getElementById('readStatus');
    badge.textContent = d.status;
    badge.className = 'msg-badge b-' + d.status.toLowerCase();
    document.getElementById('readModal').classList.add('open');
}
function closeRead() { document.getElementById('readModal').classList.remove('open'); }

// ===== Actions =====
function doAction(action) {
    document.getElementById('afId').value = current.id;
    document.getElementById('afAction').value = action;
    document.getElementById('actionForm').submit();
}

let deleteTarget = null;
function askDelete(id, nama) {
    deleteTarget = id;
    document.getElementById('confirmMsg').textContent = 'Pesan dari "' + nama + '" akan dihapus permanen.';
    document.getElementById('confirmModal').classList.add('open');
}
function closeConfirm() { document.getElementById('confirmModal').classList.remove('open'); deleteTarget = null; }
document.getElementById('confirmOk').addEventListener('click', () => {
    if (deleteTarget) {
        document.getElementById('afId').value = deleteTarget;
        document.getElementById('afAction').value = 'delete';
        document.getElementById('actionForm').submit();
    }
    closeConfirm();
});

// ===== Reply with template =====
function sendReply() {
    const tpl = document.getElementById('replyTemplate').value;
    const subj = 'Re: ' + (document.getElementById('readSubjek').textContent || 'Pesan Anda ke FKIP UNIMOF');
    const bodies = {
        formal: `Yth. Bapak/Ibu ${current.nama},\n\nTerima kasih telah menghubungi Fakultas Keguruan dan Ilmu Pendidikan Universitas Muhammadiyah Maumere. Pesan Anda telah kami terima dan akan segera kami tindak lanjuti.\n\nHormat kami,\nTim Humas FKIP UNIMOF`,
        pmb: `Halo ${current.nama},\n\nTerima kasih atas ketertarikan Anda bergabung dengan FKIP UNIMOF! Berikut informasi pendaftaran mahasiswa baru:\n1. Pendaftaran dilakukan secara online melalui website resmi.\n2. Berkas: ijazah, transkrip, pas foto, dan kartu identitas.\n3. Informasi biaya dan beasiswa tersedia di laman PMB.\n\nSalam,\nPanitia PMB FKIP UNIMOF`,
        followup: `Halo ${current.nama},\n\nMenindaklanjuti pesan Anda sebelumnya, berikut kami sampaikan perkembangan terbaru. Apabila masih ada hal yang perlu dikonfirmasi, silakan balas email ini.\n\nTerima kasih,\nTim Humas FKIP UNIMOF`
    };
    window.location.href = 'mailto:' + encodeURIComponent(current.email)
        + '?subject=' + encodeURIComponent(subj)
        + '&body=' + encodeURIComponent(bodies[tpl] || bodies.formal);
    // Tawarkan tandai dibalas
    setTimeout(() => {
        if (confirm('Tandai pesan ini sebagai DIBALAS?')) doAction('replied');
    }, 800);
}

// ===== Copy email =====
function copyEmail() {
    navigator.clipboard.writeText(current.email).then(() => {
        const btn = event.target;
        const old = btn.textContent;
        btn.textContent = '✅ Tersalin!';
        setTimeout(() => btn.textContent = old, 1500);
    });
}

// ===== Bulk selection =====
function toggleAll(master) {
    document.querySelectorAll('.row-check').forEach(cb => {
        cb.checked = master.checked;
        cb.closest('.msg-row')?.classList.toggle('selected', master.checked);
    });
    updateBulk();
}
document.querySelectorAll('.row-check').forEach(cb => cb.addEventListener('change', function(){
    this.closest('.msg-row')?.classList.toggle('selected', this.checked);
    updateBulk();
}));
function updateBulk() {
    const n = document.querySelectorAll('.row-check:checked').length;
    document.getElementById('bulkCount').textContent = n;
    document.getElementById('bulkBar').style.display = n > 0 ? 'flex' : 'none';
}
function clearSel() {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
    document.querySelectorAll('.msg-row').forEach(r => r.classList.remove('selected'));
    document.getElementById('selAll').checked = false;
    updateBulk();
}

// ===== Search debounce =====
let sTimer;
document.getElementById('inboxSearch')?.addEventListener('input', function(){
    clearTimeout(sTimer);
    const v = this.value;
    sTimer = setTimeout(() => {
        const url = new URL(window.location);
        if (v) url.searchParams.set('q', v); else url.searchParams.delete('q');
        url.searchParams.delete('halaman');
        window.location = url;
    }, 500);
});

// ===== Shortcuts =====
document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === '/') { e.preventDefault(); document.getElementById('inboxSearch')?.focus(); }
    if (e.key === 'Escape') { closeRead(); closeConfirm(); }
});

console.log('%c✉️ Inbox FKIP UNIMOF', 'color:#0a6847;font-size:16px;font-weight:bold');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>