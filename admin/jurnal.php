<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM jurnal WHERE id = ?")->execute([$id]);
        flash_message('success', 'Jurnal berhasil dihapus.');
    } elseif ($action === 'toggle') {
        $pdo->prepare("UPDATE jurnal SET status = IF(status='Aktif','Non-Aktif','Aktif') WHERE id = ?")->execute([$id]);
        flash_message('success', 'Status jurnal diperbarui.');
    }
    header('Location: jurnal.php'); exit;
}

$jurnal_list = $pdo->query("SELECT * FROM jurnal ORDER BY created_at DESC")->fetchAll();
$stat_total = count($jurnal_list);
$stat_aktif = (int)$pdo->query("SELECT COUNT(*) FROM jurnal WHERE status='Aktif'")->fetchColumn();
$stat_url = (int)$pdo->query("SELECT COUNT(*) FROM jurnal WHERE url IS NOT NULL AND url != ''")->fetchColumn();

$csrf = generate_csrf_token();
$active_menu = 'jurnal'; $page_heading = 'Kelola Jurnal Ilmiah';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Jurnal Ilmiah', null]];
require __DIR__ . '/includes/header.php';
?>

<div class="stats-ultimate" style="margin-bottom: 2rem;">
    <div class="stat-ultimate-card" style="--card-accent:#3b82f6" data-aos="fade-up">
        <div class="stat-ultimate-header"><div class="stat-ultimate-icon">📚</div></div>
        <div class="stat-ultimate-value"><span class="count-up" data-target="<?= $stat_total ?>">0</span></div>
        <div class="stat-ultimate-label">Total Jurnal</div>
    </div>
    <div class="stat-ultimate-card" style="--card-accent:#10b981" data-aos="fade-up" data-aos-delay="100">
        <div class="stat-ultimate-header"><div class="stat-ultimate-icon">✅</div></div>
        <div class="stat-ultimate-value"><span class="count-up" data-target="<?= $stat_aktif ?>">0</span></div>
        <div class="stat-ultimate-label">Jurnal Aktif</div>
    </div>
    <div class="stat-ultimate-card" style="--card-accent:#f59e0b" data-aos="fade-up" data-aos-delay="200">
        <div class="stat-ultimate-header"><div class="stat-ultimate-icon">🔗</div></div>
        <div class="stat-ultimate-value"><span class="count-up" data-target="<?= $stat_url ?>">0</span></div>
        <div class="stat-ultimate-label">Memiliki URL</div>
    </div>
</div>

<div class="card ultimate" data-aos="fade-up">
    <div class="card-header">
        <h2>📚 Daftar Jurnal Ilmiah</h2>
        <a href="jurnal-form.php" class="btn-sm">+ Tambah Jurnal</a>
    </div>
    <table class="berita-table-pro">
        <thead><tr><th>Nama Jurnal</th><th>Penerbit</th><th>Akreditasi</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php if (empty($jurnal_list)): ?>
            <tr><td colspan="5" style="text-align:center;padding:2rem;color:#64748b">Belum ada data jurnal.</td></tr>
        <?php else: foreach ($jurnal_list as $j): ?>
        <tr>
            <td>
                <strong><?= sanitize($j['nama']) ?></strong><br>
                <small style="color:#64748b"><?= sanitize($j['issn'] ?: 'ISSN belum diisi') ?></small>
            </td>
            <td><?= sanitize($j['penerbit']) ?></td>
            <td><span class="badge-ultimate badge-info"><?= sanitize($j['akreditasi']) ?></span></td>
            <td><span class="badge-ultimate badge-<?= strtolower($j['status']) === 'aktif' ? 'success' : 'warning' ?>"><?= $j['status'] ?></span></td>
            <td>
                <a href="jurnal-form.php?id=<?= $j['id'] ?>" class="act-btn edit" title="Edit">✏️</a>
                <?php if (!empty($j['url'])): ?>
                <a href="<?= sanitize($j['url']) ?>" target="_blank" class="act-btn" title="Kunjungi">🔗</a>
                <?php endif; ?>
                <form method="POST" style="display:inline" onsubmit="return confirm('Ubah status jurnal ini?')">
                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                    <input type="hidden" name="id" value="<?= $j['id'] ?>"><input type="hidden" name="action" value="toggle">
                    <button class="act-btn status" title="Toggle Status">🔄</button>
                </form>
                <form method="POST" style="display:inline" onsubmit="return confirm('Hapus jurnal ini?')">
                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                    <input type="hidden" name="id" value="<?= $j['id'] ?>"><input type="hidden" name="action" value="delete">
                    <button class="act-btn danger" title="Hapus">🗑️</button>
                </form>
            </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>