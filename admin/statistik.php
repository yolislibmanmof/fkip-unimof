<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_login();

function statistik_default_tahun_ajaran(): string
{
    $year = (int) date('Y');

    return $year . '/' . ($year + 1);
}

function statistik_default_data(): array
{
    return [
        'id' => 0,
        'total_mahasiswa' => 0,
        'total_dosen' => 0,
        'total_prodi' => 8,
        'total_penelitian' => 0,
        'total_alumni' => 0,
        'tahun_ajaran' => statistik_default_tahun_ajaran(),
    ];
}

function statistik_read_current(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT * FROM statistik ORDER BY id ASC LIMIT 1');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : statistik_default_data();
}

function statistik_valid_count(mixed $value): int|false
{
    if (!is_string($value) && !is_int($value)) {
        return false;
    }

    return filter_var(
        (string) $value,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0, 'max_range' => 2147483647]]
    );
}

function statistik_redirect(): never
{
    header('Location: statistik.php');
    exit;
}

try {
    $stat = statistik_read_current($pdo);
} catch (PDOException $exception) {
    error_log('Admin statistics read failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Data statistik sementara tidak tersedia. Silakan coba kembali.');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Permintaan tidak valid. Muat ulang halaman dan coba kembali.');
    }

    $fields = [
        'total_mahasiswa' => 'Total mahasiswa',
        'total_dosen' => 'Total dosen',
        'total_prodi' => 'Total program studi',
        'total_penelitian' => 'Total penelitian',
        'total_alumni' => 'Total alumni',
    ];

    $data = [];
    $errors = [];

    foreach ($fields as $key => $label) {
        $value = statistik_valid_count($_POST[$key] ?? null);

        if ($value === false) {
            $errors[] = $label . ' harus berupa bilangan bulat antara 0 dan 2.147.483.647.';
        } else {
            $data[$key] = $value;
        }
    }

    $tahunAjaran = $_POST['tahun_ajaran'] ?? null;

    if (!is_string($tahunAjaran)) {
        $errors[] = 'Tahun ajaran tidak valid.';
    } else {
        $tahunAjaran = trim($tahunAjaran);

        if (
            preg_match('/^(19|20|21)\d{2}\/(19|20|21)\d{2}$/', $tahunAjaran) !== 1
        ) {
            $errors[] = 'Tahun ajaran harus berformat YYYY/YYYY, misalnya 2026/2027.';
        } else {
            [$tahunMulai, $tahunSelesai] = array_map('intval', explode('/', $tahunAjaran));

            if ($tahunSelesai !== $tahunMulai + 1) {
                $errors[] = 'Tahun akhir harus satu tahun setelah tahun awal.';
            }
        }
    }

    if ($errors !== []) {
        flash_message('error', implode(' ', $errors));
    } else {
        $data['tahun_ajaran'] = $tahunAjaran;

        try {
            if ((int) ($stat['id'] ?? 0) > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE statistik
                     SET total_mahasiswa = ?,
                         total_dosen = ?,
                         total_prodi = ?,
                         total_penelitian = ?,
                         total_alumni = ?,
                         tahun_ajaran = ?
                     WHERE id = ?'
                );
                $stmt->execute([
                    $data['total_mahasiswa'],
                    $data['total_dosen'],
                    $data['total_prodi'],
                    $data['total_penelitian'],
                    $data['total_alumni'],
                    $data['tahun_ajaran'],
                    (int) $stat['id'],
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO statistik
                        (total_mahasiswa, total_dosen, total_prodi, total_penelitian, total_alumni, tahun_ajaran)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $data['total_mahasiswa'],
                    $data['total_dosen'],
                    $data['total_prodi'],
                    $data['total_penelitian'],
                    $data['total_alumni'],
                    $data['tahun_ajaran'],
                ]);
            }

            flash_message('success', 'Statistik fakultas berhasil diperbarui.');
            statistik_redirect();
        } catch (PDOException $exception) {
            error_log('Admin statistics save failed: ' . $exception->getMessage());
            flash_message('error', 'Gagal menyimpan statistik. Silakan coba kembali.');
        }
    }
} elseif (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Metode request tidak diizinkan.');
}

try {
    $live = [
        'dosen' => (int) $pdo->query(
            "SELECT COUNT(*) FROM dosen WHERE status = 'Aktif'"
        )->fetchColumn(),
        'prodi' => (int) $pdo->query(
            "SELECT COUNT(*) FROM program_studi WHERE status = 'Aktif'"
        )->fetchColumn(),
        'alumni' => (int) $pdo->query(
            "SELECT COUNT(*) FROM alumni WHERE status = 'Aktif'"
        )->fetchColumn(),
        'riset' => (int) $pdo->query(
            "SELECT COUNT(*) FROM riset WHERE status = 'Published'"
        )->fetchColumn(),
    ];
} catch (PDOException $exception) {
    error_log('Admin live statistics query failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Perbandingan statistik langsung sementara tidak tersedia. Silakan coba kembali.');
}

$csrf = generate_csrf_token();
$active_menu = 'statistik';
$page_heading = 'Statistik Fakultas';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Statistik', null]];

require __DIR__ . '/includes/header.php';
?>

<style>
.stat-hero {
    background: linear-gradient(135deg, #0891b2 0%, #0e7490 50%, #155e75 100%);
    color: #fff;
    padding: 2rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(14, 116, 144, .3);
}
.stat-hero::before {
    content: '';
    position: absolute;
    inset: 0 0 auto;
    height: 5px;
    background: linear-gradient(90deg, #22d3ee, #06b6d4, #22d3ee);
}
.stat-hero::after {
    content: 'DATA';
    position: absolute;
    top: 2rem;
    right: 2rem;
    font: 900 6rem Georgia, serif;
    color: rgba(255,255,255,.05);
    letter-spacing: .2em;
    pointer-events: none;
}
.stat-hero h2 {
    font: 900 2rem Georgia, serif;
    margin-bottom: .5rem;
    display: flex;
    align-items: center;
    gap: .75rem;
    position: relative;
    z-index: 1;
}
.stat-hero p {
    opacity: .95;
    font-size: .95rem;
    max-width: 600px;
    line-height: 1.6;
    position: relative;
    z-index: 1;
}
.stat-container {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 2rem;
    box-shadow: var(--shadow-sm);
}
.stat-container h3 {
    font: 700 1.35rem Georgia, serif;
    margin-bottom: .25rem;
}
.stat-subtitle {
    color: var(--text-muted);
    font-size: .85rem;
    margin-bottom: 1.5rem;
}
.stat-info-box {
    background: rgba(8,145,178,.08);
    border: 1px solid rgba(8,145,178,.25);
    border-radius: var(--radius-md);
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    font-size: .85rem;
    color: var(--text-secondary);
    display: flex;
    gap: .75rem;
    align-items: flex-start;
}
.stat-info-box .icon { font-size: 1.25rem; }
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
    transition: border-color .2s, box-shadow .2s;
}
.stat-field:hover { border-color: var(--primary); }
.stat-field:focus-within {
    border-color: #0891b2;
    box-shadow: 0 0 0 4px rgba(8,145,178,.1);
}
.stat-field-header {
    display: flex;
    align-items: center;
    gap: .75rem;
    margin-bottom: .85rem;
}
.stat-field-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: var(--ic, linear-gradient(135deg,#0891b2,#0e7490));
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}
.stat-field-title {
    font-size: .75rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .05em;
    font-weight: 700;
}
.stat-field-desc { font-size: .72rem; color: var(--text-muted); }
.stat-field input {
    width: 100%;
    padding: .65rem .9rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    font-size: 1.25rem;
    font-weight: 900;
    font-family: Georgia, serif;
    color: var(--text-primary);
    background: var(--bg-primary);
}
.stat-field input:focus { outline: none; border-color: #0891b2; }
.stat-compare {
    margin-top: .5rem;
    font-size: .72rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: .35rem;
    flex-wrap: wrap;
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
    font-size: .85rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .05em;
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
    font: 900 2rem Georgia, serif;
    color: var(--primary);
    line-height: 1;
    margin-bottom: .25rem;
}
.stat-preview-label {
    font-size: .72rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .05em;
    font-weight: 700;
}
.stat-actions {
    display: flex;
    gap: .75rem;
    justify-content: flex-end;
    padding-top: 1.5rem;
    border-top: 2px solid var(--border);
    flex-wrap: wrap;
}
.btn-stat {
    padding: .75rem 1.5rem;
    border-radius: var(--radius-md);
    border: 2px solid var(--border);
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-weight: 700;
    cursor: pointer;
    transition: all .2s;
    font: 700 .9rem inherit;
    display: inline-flex;
    align-items: center;
    gap: .5rem;
}
.btn-stat:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); }
.btn-stat.primary {
    background: linear-gradient(135deg, #0891b2, #0e7490);
    color: #fff;
    border-color: #0891b2;
    box-shadow: 0 4px 12px rgba(8,145,178,.25);
}
.btn-stat.primary:hover { box-shadow: 0 8px 20px rgba(8,145,178,.4); color: #fff; }
.btn-stat:disabled { opacity: .7; cursor: wait; transform: none; }
@media (max-width: 640px) {
    .stat-container { padding: 1rem; }
    .stat-hero { padding: 1.5rem; }
    .stat-hero::after { font-size: 3.5rem; }
}
</style>

<div class="stat-hero">
    <h2>📊 Statistik Fakultas</h2>
    <p>
        Kelola angka statistik yang ditampilkan di halaman beranda.
        Data ini dibaca oleh halaman publik <code>index.php</code>.
    </p>
</div>

<div class="stat-container">
    <h3>📈 Angka Statistik FKIP UNIMOF</h3>
    <p class="stat-subtitle">
        Perbarui angka sesuai data aktual fakultas. Angka manual dapat berbeda
        dari jumlah baris di tabel karena mungkin mencakup data historis.
    </p>

    <div class="stat-info-box">
        <span class="icon" aria-hidden="true">💡</span>
        <div>
            <strong>Perbandingan dengan data database:</strong>
            jumlah dosen, program studi, alumni, dan penelitian aktif ditampilkan
            sebagai pembanding. Nilai yang disimpan tetap dapat mencakup data historis.
        </div>
    </div>

    <form method="post" id="statForm">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

        <div class="stat-grid-form">
            <?php
            $statFields = [
                'total_mahasiswa' => [
                    'label' => 'Total Mahasiswa',
                    'description' => 'Mahasiswa aktif saat ini',
                    'icon' => '🎓',
                    'gradient' => 'linear-gradient(135deg,#0891b2,#0e7490)',
                    'preview' => 'prevMhs',
                    'comparison' => null,
                    'comparisonLabel' => 'Belum tercatat di database — isi manual',
                ],
                'total_dosen' => [
                    'label' => 'Total Dosen',
                    'description' => 'Dosen aktif',
                    'icon' => '👨‍🏫',
                    'gradient' => 'linear-gradient(135deg,#10b981,#059669)',
                    'preview' => 'prevDosen',
                    'comparison' => $live['dosen'],
                    'comparisonLabel' => 'Live DB',
                ],
                'total_prodi' => [
                    'label' => 'Total Prodi',
                    'description' => 'Program studi aktif',
                    'icon' => '📚',
                    'gradient' => 'linear-gradient(135deg,#3b82f6,#2563eb)',
                    'preview' => 'prevProdi',
                    'comparison' => $live['prodi'],
                    'comparisonLabel' => 'Live DB',
                ],
                'total_penelitian' => [
                    'label' => 'Total Penelitian',
                    'description' => 'Riset terpublikasi',
                    'icon' => '🔬',
                    'gradient' => 'linear-gradient(135deg,#8b5cf6,#6d28d9)',
                    'preview' => 'prevRiset',
                    'comparison' => $live['riset'],
                    'comparisonLabel' => 'Live DB',
                ],
                'total_alumni' => [
                    'label' => 'Total Alumni',
                    'description' => 'Lulusan tercatat',
                    'icon' => '🎓',
                    'gradient' => 'linear-gradient(135deg,#f59e0b,#d97706)',
                    'preview' => 'prevAlumni',
                    'comparison' => $live['alumni'],
                    'comparisonLabel' => 'Live DB',
                ],
            ];
            ?>

            <?php foreach ($statFields as $key => $field): ?>
                <?php
                $value = (int) ($stat[$key] ?? 0);
                $comparison = $field['comparison'];
                $isMatch = $comparison !== null && $value === $comparison;
                ?>
                <div class="stat-field">
                    <div class="stat-field-header">
                        <div
                            class="stat-field-icon"
                            style="--ic: <?= sanitize($field['gradient']) ?>"
                            aria-hidden="true"
                        ><?= $field['icon'] ?></div>
                        <div>
                            <label class="stat-field-title" for="<?= sanitize($key) ?>">
                                <?= sanitize($field['label']) ?>
                            </label>
                            <div class="stat-field-desc"><?= sanitize($field['description']) ?></div>
                        </div>
                    </div>

                    <input
                        type="number"
                        id="<?= sanitize($key) ?>"
                        name="<?= sanitize($key) ?>"
                        value="<?= $value ?>"
                        min="0"
                        max="2147483647"
                        step="1"
                        required
                    >

                    <div class="stat-compare">
                        <?php if ($comparison === null): ?>
                            ℹ️ <?= sanitize($field['comparisonLabel']) ?>
                        <?php else: ?>
                            <?= sanitize($field['comparisonLabel']) ?>:
                            <strong><?= (int) $comparison ?></strong>
                            <span class="<?= $isMatch ? 'match' : 'diff' ?>">
                                <?= $isMatch ? '✓ sinkron' : '⚠ berbeda' ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="stat-field">
                <div class="stat-field-header">
                    <div
                        class="stat-field-icon"
                        style="--ic: linear-gradient(135deg,#64748b,#475569)"
                        aria-hidden="true"
                    >📅</div>
                    <div>
                        <label class="stat-field-title" for="tahun_ajaran">Tahun Ajaran</label>
                        <div class="stat-field-desc">Periode data statistik</div>
                    </div>
                </div>

                <input
                    type="text"
                    id="tahun_ajaran"
                    name="tahun_ajaran"
                    value="<?= sanitize((string) ($stat['tahun_ajaran'] ?? statistik_default_tahun_ajaran())) ?>"
                    placeholder="2026/2027"
                    pattern="(19|20|21)[0-9]{2}/(19|20|21)[0-9]{2}"
                    maxlength="9"
                    title="Gunakan format YYYY/YYYY, misalnya 2026/2027"
                    required
                >
                <div class="stat-compare">ℹ️ Format YYYY/YYYY; tahun akhir harus satu tahun setelah tahun awal.</div>
            </div>
        </div>

        <div class="stat-preview">
            <h4>👁️ Preview Tampilan di Beranda</h4>
            <div class="stat-preview-grid">
                <?php
                $previewFields = [
                    ['total_mahasiswa', 'prevMhs', 'Mahasiswa'],
                    ['total_dosen', 'prevDosen', 'Dosen'],
                    ['total_prodi', 'prevProdi', 'Prodi'],
                    ['total_penelitian', 'prevRiset', 'Penelitian'],
                    ['total_alumni', 'prevAlumni', 'Alumni'],
                ];
                ?>

                <?php foreach ($previewFields as [$key, $previewId, $label]): ?>
                    <div class="stat-preview-item">
                        <div class="stat-preview-num" id="<?= sanitize($previewId) ?>">
                            <?= number_format((int) ($stat[$key] ?? 0), 0, ',', '.') ?>
                        </div>
                        <div class="stat-preview-label"><?= sanitize($label) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="stat-actions">
            <button type="reset" class="btn-stat">🔄 Reset</button>
            <button type="submit" class="btn-stat primary" id="saveStatButton">
                <span>💾</span><span>Simpan Statistik</span>
            </button>
        </div>
    </form>
</div>

<script>
(() => {
    const form = document.getElementById('statForm');
    const fields = {
        total_mahasiswa: 'prevMhs',
        total_dosen: 'prevDosen',
        total_prodi: 'prevProdi',
        total_penelitian: 'prevRiset',
        total_alumni: 'prevAlumni'
    };

    function updatePreview() {
        Object.entries(fields).forEach(([name, previewId]) => {
            const input = form.elements.namedItem(name);
            const preview = document.getElementById(previewId);

            if (!input || !preview) {
                return;
            }

            const value = Number.parseInt(input.value, 10);
            preview.textContent = (Number.isFinite(value) ? value : 0)
                .toLocaleString('id-ID');
        });
    }

    Object.keys(fields).forEach((name) => {
        form.elements.namedItem(name).addEventListener('input', updatePreview);
    });

    form.addEventListener('reset', () => {
        window.setTimeout(updatePreview, 0);
    });

    form.addEventListener('submit', (event) => {
        if (!form.reportValidity()) {
            event.preventDefault();
            return;
        }

        const button = document.getElementById('saveStatButton');
        button.disabled = true;
        button.innerHTML = '<span>⏳</span><span>Menyimpan...</span>';
    });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>