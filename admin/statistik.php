<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// Tabel statistik hanya berisi 1 record (konfigurasi global)
$stmt = $pdo->query("SELECT * FROM statistik ORDER BY id LIMIT 1");
$stat = $stmt->fetch() ?: [
    'id' => 0,
    'total_mahasiswa' => 0,
    'total_dosen' => 0,
    'total_prodi' => 8,
    'total_penelitian' => 0,
    'total_alumni' => 0,
    'tahun_ajaran' => date('Y') . '/' . (date('Y') + 1),
];

// ===== PROSES SIMPAN =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('error', '❌ Token keamanan tidak valid.');
    } else {
        $data = [
            'total_mahasiswa' => max(0, (int)($_POST['total_mahasiswa'] ?? 0)),
            'total_dosen' => max(0, (int)($_POST['total_dosen'] ?? 0)),
            'total_prodi' => max(0, (int)($_POST['total_prodi'] ?? 0)),
            'total_penelitian' => max(0, (int)($_POST['total_penelitian'] ?? 0)),
            'total_alumni' => max(0, (int)($_POST['total_alumni'] ?? 0)),
            'tahun_ajaran' => trim($_POST['tahun_ajaran'] ?? ''),
        ];
        if ($data['tahun_ajaran'] === '') $data['tahun_ajaran'] = date('Y') . '/' . (date('Y') + 1);

        try {
            if ($stat['id'] > 0) {
                $pdo->prepare("UPDATE statistik SET total_mahasiswa=?, total_dosen=?, total_prodi=?, total_penelitian=?, total_alumni=?, tahun_ajaran=? WHERE id=?")
                    ->execute([$data['total_mahasiswa'], $data['total_dosen'], $data['total_prodi'], $data['total_penelitian'], $data['total_alumni'], $data['tahun_ajaran'], $stat['id']]);
            } else {
                $pdo->prepare("INSERT INTO statistik (total_mahasiswa, total_dosen, total_prodi, total_penelitian, total_alumni, tahun_ajaran) VALUES (?,?,?,?,?,?)")
                    ->execute([$data['total_mahasiswa'], $data['total_dosen'], $data['total_prodi'], $data['total_penelitian'], $data['total_alumni'], $data['tahun_ajaran']]);
            }
            flash_message('success', '✅ Statistik fakultas berhasil diperbarui.');
            header('Location: statistik.php');
            exit;
        } catch (Exception $e) {
            flash_message('error', '❌ Gagal menyimpan: ' . $e->getMessage());
        }
    }
}

// Live count dari tabel sebenarnya (sebagai pembanding)
$live = [
    'dosen' => (int)$pdo->query("SELECT COUNT(*) FROM dosen WHERE status='Aktif'")->fetchColumn(),
    'prodi' => (int)$pdo->query("SELECT COUNT(*) FROM program_studi WHERE status='Aktif'")->fetchColumn(),
    'alumni' => (int)$pdo->query("SELECT COUNT(*) FROM alumni WHERE status='Aktif'")->fetchColumn(),
    'riset' => (int)$pdo->query("SELECT COUNT(*) FROM riset WHERE status='Published'")->fetchColumn(),
];

$csrf = generate_csrf_token();
$active_menu = 'statistik';
$page_heading = 'Statistik Fakultas';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Statistik', null]];
require __DIR__ . '/includes/header.php';
?>

<style>
.stat-hero {
    background: linear-gradient(135deg, #0891b2 0%, #0e7490 50%, #155e75 100%);
    color: white;
    padding: 2rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(14, 116, 144, 0.3);
}
.stat-hero::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 5px;
    background: linear-gradient(90deg, #22d3ee, #06b6d4, #22d3ee);
}
.stat-hero::after {
    content: 'DATA';
    position: absolute;
    top: 2rem; right: 2rem;
    font-family: 'Georgia', serif;
    font-size: 6rem;
    font-weight: 900;
    color: rgba(255,255,255,0.05);
    letter-spacing: 0.2em;
    pointer-events: none;
}
.stat-hero h2 {
    font-family: 'Georgia', serif;
    font-size: 2rem;
    font-weight: 900;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    position: relative;
    z-index: 1;
}
.stat-hero p { opacity: 0.95; font-size: 0.95rem; max-width: 600px; line-height: 1.6; position: relative; z-index: 1; }

.stat-container {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 2rem;
    box-shadow: var(--shadow-sm);
}
.stat-container h3 {
    font-family: 'Georgia', serif;
    font-size: 1.35rem;
    margin-bottom: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.stat-subtitle {
    color: var(--text-muted);
    font-size: 0.85rem;
    margin-bottom: 1.5rem;
}

.stat-grid-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}
.stat-field {
    background: var(--bg-secondary);
    border: 2px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.25rem;
    transition: all 0.2s;
}
.stat-field:hover { border-color: var(--primary); }
.stat-field:focus-within { border-color: #0891b2; box-shadow: 0 0 0 4px rgba(8,145,178,0.1); }
.stat-field-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.85rem;
}
.stat-field-icon {
    width: 44px; height: 44px;
    border-radius: 10px;
    background: var(--ic, linear-gradient(135deg,#0891b2,#0e7490));
    color: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}
.stat-field-title {
    font-size: 0.75rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
}
.stat-field-desc {
    font-size: 0.72rem;
    color: var(--text-muted);
}
.stat-field input {
    width: 100%;
    padding: 0.65rem 0.9rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    font-size: 1.5rem;
    font-weight: 900;
    font-family: 'Georgia', serif;
    color: var(--text-primary);
    background: var(--bg-primary);
    transition: all 0.2s;
}
.stat-field input:focus { outline: none; border-color: #0891b2; }

.stat-compare {
    margin-top: 0.5rem;
    font-size: 0.72rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.stat-compare .match { color: #10b981; }
.stat-compare .diff { color: #f59e0b; }

.stat-preview {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}
.stat-preview h4 {
    font-size: 0.85rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 1rem;
    font-weight: 700;
}
.stat-preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}
.stat-preview-item {
    text-align: center;
    padding: 1rem;
    background: var(--bg-primary);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
}
.stat-preview-num {
    font-family: 'Georgia', serif;
    font-size: 2rem;
    font-weight: 900;
    color: var(--primary);
    line-height: 1;
    margin-bottom: 0.25rem;
}
.stat-preview-label {
    font-size: 0.72rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
}

.stat-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    padding-top: 1.5rem;
    border-top: 2px solid var(--border);
    flex-wrap: wrap;
}
.btn-stat {
    padding: 0.75rem 1.5rem;
    border-radius: var(--radius-md);
    border: 2px solid var(--border);
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.btn-stat:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); }
.btn-stat.primary {
    background: linear-gradient(135deg, #0891b2, #0e7490);
    color: white;
    border-color: #0891b2;
    box-shadow: 0 4px 12px rgba(8,145,178,0.25);
}
.btn-stat.primary:hover { box-shadow: 0 8px 20px rgba(8,145,178,0.4); color: white; }

.stat-info-box {
    background: rgba(8,145,178,0.08);
    border: 1px solid rgba(8,145,178,0.25);
    border-radius: var(--radius-md);
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    font-size: 0.85rem;
    color: var(--text-secondary);
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
}
.stat-info-box .icon { font-size: 1.25rem; }
</style>

<div class="stat-hero">
    <h2>📊 Statistik Fakultas</h2>
    <p>Kelola angka-angka statistik yang ditampilkan di halaman beranda website. Data ini akan dibaca oleh halaman publik <code style="background:rgba(255,255,255,0.15);padding:0.15rem 0.5rem;border-radius:4px">index.php</code>.</p>
</div>

<div class="stat-container">
    <h3>📈 Angka Statistik FKIP UNIMOF</h3>
    <p class="stat-subtitle">Angka-angka berikut akan ditampilkan di halaman beranda. Perbarui secara berkala sesuai data aktual fakultas.</p>

    <div class="stat-info-box">
        <span class="icon">💡</span>
        <div>
            <strong>Live Count dari Database:</strong> Beberapa angka dapat dihitung otomatis dari tabel aktual (ditampilkan sebagai pembanding di bawah form). Anda tetap dapat mengisi manual dengan angka berbeda bila diperlukan (mis. termasuk data historis).
        </div>
    </div>

    <form method="POST" id="statForm">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
        <div class="stat-grid-form">
            <div class="stat-field">
                <div class="stat-field-header">
                    <div class="stat-field-icon" style="--ic: linear-gradient(135deg,#0891b2,#0e7490)">🎓</div>
                    <div>
                        <div class="stat-field-title">Total Mahasiswa</div>
                        <div class="stat-field-desc">Mahasiswa aktif saat ini</div>
                    </div>
                </div>
                <input type="number" name="total_mahasiswa" value="<?= (int)$stat['total_mahasiswa'] ?>" min="0" required>
                <div class="stat-compare">ℹ️ Belum tercatat di DB — isi manual</div>
            </div>

            <div class="stat-field">
                <div class="stat-field-header">
                    <div class="stat-field-icon" style="--ic: linear-gradient(135deg,#10b981,#059669)">👨‍🏫</div>
                    <div>
                        <div class="stat-field-title">Total Dosen</div>
                        <div class="stat-field-desc">Dosen aktif</div>
                    </div>
                </div>
                <input type="number" name="total_dosen" value="<?= (int)$stat['total_dosen'] ?>" min="0" required>
                <div class="stat-compare">
                    Live DB: <strong><?= $live['dosen'] ?></strong>
                    <?= (int)$stat['total_dosen'] === $live['dosen'] ? '<span class="match">✓ sinkron</span>' : '<span class="diff">⚠ beda</span>' ?>
                </div>
            </div>

            <div class="stat-field">
                <div class="stat-field-header">
                    <div class="stat-field-icon" style="--ic: linear-gradient(135deg,#3b82f6,#2563eb)">📚</div>
                    <div>
                        <div class="stat-field-title">Total Prodi</div>
                        <div class="stat-field-desc">Program studi aktif</div>
                    </div>
                </div>
                <input type="number" name="total_prodi" value="<?= (int)$stat['total_prodi'] ?>" min="0" required>
                <div class="stat-compare">
                    Live DB: <strong><?= $live['prodi'] ?></strong>
                    <?= (int)$stat['total_prodi'] === $live['prodi'] ? '<span class="match">✓ sinkron</span>' : '<span class="diff">⚠ beda</span>' ?>
                </div>
            </div>

            <div class="stat-field">
                <div class="stat-field-header">
                    <div class="stat-field-icon" style="--ic: linear-gradient(135deg,#8b5cf6,#6d28d9)">🔬</div>
                    <div>
                        <div class="stat-field-title">Total Penelitian</div>
                        <div class="stat-field-desc">Riset terpublikasi</div>
                    </div>
                </div>
                <input type="number" name="total_penelitian" value="<?= (int)$stat['total_penelitian'] ?>" min="0" required>
                <div class="stat-compare">
                    Live DB: <strong><?= $live['riset'] ?></strong>
                    <?= (int)$stat['total_penelitian'] === $live['riset'] ? '<span class="match">✓ sinkron</span>' : '<span class="diff">⚠ beda</span>' ?>
                </div>
            </div>

            <div class="stat-field">
                <div class="stat-field-header">
                    <div class="stat-field-icon" style="--ic: linear-gradient(135deg,#f59e0b,#d97706)">🎓</div>
                    <div>
                        <div class="stat-field-title">Total Alumni</div>
                        <div class="stat-field-desc">Lulusan tercatat</div>
                    </div>
                </div>
                <input type="number" name="total_alumni" value="<?= (int)$stat['total_alumni'] ?>" min="0" required>
                <div class="stat-compare">
                    Live DB: <strong><?= $live['alumni'] ?></strong>
                    <?= (int)$stat['total_alumni'] === $live['alumni'] ? '<span class="match">✓ sinkron</span>' : '<span class="diff">⚠ beda</span>' ?>
                </div>
            </div>

            <div class="stat-field">
                <div class="stat-field-header">
                    <div class="stat-field-icon" style="--ic: linear-gradient(135deg,#64748b,#475569)">📅</div>
                    <div>
                        <div class="stat-field-title">Tahun Ajaran</div>
                        <div class="stat-field-desc">Periode data</div>
                    </div>
                </div>
                <input type="text" name="tahun_ajaran" value="<?= htmlspecialchars($stat['tahun_ajaran'], ENT_QUOTES, 'UTF-8') ?>" placeholder="2025/2026" required>
                <div class="stat-compare">ℹ️ Format: YYYY/YYYY (mis. 2025/2026)</div>
            </div>
        </div>

        <!-- LIVE PREVIEW -->
        <div class="stat-preview">
            <h4>👁️ Preview Tampilan di Beranda</h4>
            <div class="stat-preview-grid">
                <div class="stat-preview-item">
                    <div class="stat-preview-num" id="prevMhs"><?= (int)$stat['total_mahasiswa'] ?></div>
                    <div class="stat-preview-label">Mahasiswa</div>
                </div>
                <div class="stat-preview-item">
                    <div class="stat-preview-num" id="prevDosen"><?= (int)$stat['total_dosen'] ?></div>
                    <div class="stat-preview-label">Dosen</div>
                </div>
                <div class="stat-preview-item">
                    <div class="stat-preview-num" id="prevProdi"><?= (int)$stat['total_prodi'] ?></div>
                    <div class="stat-preview-label">Prodi</div>
                </div>
                <div class="stat-preview-item">
                    <div class="stat-preview-num" id="prevRiset"><?= (int)$stat['total_penelitian'] ?></div>
                    <div class="stat-preview-label">Penelitian</div>
                </div>
                <div class="stat-preview-item">
                    <div class="stat-preview-num" id="prevAlumni"><?= (int)$stat['total_alumni'] ?></div>
                    <div class="stat-preview-label">Alumni</div>
                </div>
            </div>
        </div>

        <div class="stat-actions">
            <button type="reset" class="btn-stat">🔄 Reset</button>
            <button type="submit" class="btn-stat primary">
                <span>💾</span><span>Simpan Statistik</span>
            </button>
        </div>
    </form>
</div>

<script>
// Live update preview
const inputs = {
    total_mahasiswa: 'prevMhs',
    total_dosen: 'prevDosen',
    total_prodi: 'prevProdi',
    total_penelitian: 'prevRiset',
    total_alumni: 'prevAlumni'
};
Object.entries(inputs).forEach(([name, id]) => {
    const el = document.querySelector(`input[name="${name}"]`);
    const prev = document.getElementById(id);
    if (el && prev) {
        el.addEventListener('input', () => {
            const v = parseInt(el.value) || 0;
            prev.textContent = v.toLocaleString('id-ID');
        });
    }
});

// Form submission
document.getElementById('statForm').addEventListener('submit', function(e) {
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span>⏳</span><span>Menyimpan...</span>';
});

console.log('%c📊 Statistik Fakultas - READY','color:#0891b2;font-size:16px;font-weight:bold');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>