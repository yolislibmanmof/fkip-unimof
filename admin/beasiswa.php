<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM beasiswa WHERE id = ?")->execute([$id]);
        flash_message('success', 'Beasiswa berhasil dihapus.');
    } elseif ($action === 'toggle') {
        $pdo->prepare("UPDATE beasiswa SET status = IF(status='Terbuka','Tertutup','Terbuka') WHERE id = ?")->execute([$id]);
        flash_message('success', 'Status beasiswa diperbarui.');
    }
    header('Location: beasiswa.php'); exit;
}

$beasiswa_list = $pdo->query("SELECT * FROM beasiswa ORDER BY deadline ASC")->fetchAll();
$stat_total = count($beasiswa_list);
$stat_terbuka = (int)$pdo->query("SELECT COUNT(*) FROM beasiswa WHERE status='Terbuka'")->fetchColumn();

$csrf = generate_csrf_token();
$active_menu = 'beasiswa'; $page_heading = 'Kelola Beasiswa';
require __DIR__ . '/includes/header.php';
?>

<div class="stats-ultimate" style="margin-bottom: 2rem;">
    <div class="stat-ultimate-card" style="--card-accent:#f59e0b" data-aos="fade-up">
        <div class="stat-ultimate-header"><div class="stat-ultimate-icon">🎓</div></div>
        <div class="stat-ultimate-value"><span class="count-up" data-target="<?= $stat_total ?>">0</span></div>
        <div class="stat-ultimate-label">Total Program</div>
    </div>
    <div class="stat-ultimate-card" style="--card-accent:#10b981" data-aos="fade-up" data-aos-delay="100">
        <div class="stat-ultimate-header"><div class="stat-ultimate-icon">✅</div></div>
        <div class="stat-ultimate-value"><span class="count-up" data-target="<?= $stat_terbuka ?>">0</span></div>
        <div class="stat-ultimate-label">Pendaftaran Terbuka</div>
    </div>
</div>

<div class="card ultimate" data-aos="fade-up">
    <div class="card-header"><h2>🎓 Daftar Program Beasiswa</h2><a href="beasiswa-form.php" class="btn-sm">+ Tambah Beasiswa</a></div>
    <table class="berita-table-pro">
        <thead><tr><th>Nama Beasiswa</th><th>Jenis</th><th>Nominal</th><th>Deadline</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php if (empty($beasiswa_list)): ?>
            <tr><td colspan="6" style="text-align:center;padding:2rem;color:#64748b">Belum ada data beasiswa.</td></tr>
        <?php else: foreach ($beasiswa_list as $b): 
            $is_urgent = $b['deadline'] && strtotime($b['deadline']) < strtotime('+30 days');
        ?>
        <tr>
            <td><strong><?= sanitize($b['nama']) ?></strong><br><small style="color:#64748b"><?= sanitize($b['sumber']) ?></small></td>
            <td><span class="badge-ultimate badge-info"><?= sanitize($b['jenis']) ?></span></td>
            <td><?= sanitize($b['nominal']) ?></td>
            <td style="<?= $is_urgent ? 'color:#ef4444;font-weight:700;' : '' ?>"><?= $b['deadline'] ? date('d M Y', strtotime($b['deadline'])) : '-' ?></td>
            <td><span class="badge-ultimate badge-<?= strtolower($b['status']) === 'terbuka' ? 'success' : 'warning' ?>"><?= $b['status'] ?></span></td>
            <td>
                <a href="beasiswa-form.php?id=<?= $b['id'] ?>" class="act-btn edit" title="Edit">✏️</a>
                <form method="POST" style="display:inline" onsubmit="return confirm('Ubah status beasiswa?')">
                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                    <input type="hidden" name="id" value="<?= $b['id'] ?>"><input type="hidden" name="action" value="toggle">
                    <button class="act-btn status" title="Toggle Status">🔄</button>
                </form>
                <form method="POST" style="display:inline" onsubmit="return confirm('Hapus beasiswa ini?')">
                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                    <input type="hidden" name="id" value="<?= $b['id'] ?>"><input type="hidden" name="action" value="delete">
                    <button class="act-btn danger" title="Hapus">🗑️</button>
                </form>
            </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>