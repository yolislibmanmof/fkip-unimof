<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$edit = null;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM berita WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch();
    if (!$edit) { header('Location: berita.php'); exit; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('error', 'Token keamanan tidak valid.');
        header('Location: berita-form.php' . ($id ? "?id=$id" : '')); exit;
    }

    $judul    = trim($_POST['judul'] ?? '');
    $kategori = $_POST['kategori'] ?? 'Umum';
    $status   = $_POST['status'] ?? 'Draft';
    $penulis  = trim($_POST['penulis'] ?? 'Humas FKIP');
    $excerpt  = trim($_POST['excerpt'] ?? '');
    $konten   = clean_html($_POST['konten'] ?? '');
    $tags     = trim($_POST['tags'] ?? '');
    $featured = isset($_POST['is_featured']) ? 1 : 0;
    $kat_ok   = ['Akademik','Pengumuman','Prestasi','Kegiatan','Riset','Umum'];
    $st_ok    = ['Draft','Published','Archived'];
    if (!in_array($kategori, $kat_ok, true)) $kategori = 'Umum';
    if (!in_array($status, $st_ok, true))   $status = 'Draft';

    if ($judul === '' || $konten === '') {
        flash_message('error', 'Judul dan konten wajib diisi.');
        header('Location: berita-form.php' . ($id ? "?id=$id" : '')); exit;
    }

    // Custom slug
    $custom_slug = trim($_POST['custom_slug'] ?? '');
    if ($custom_slug !== '') {
        $base = generate_slug($custom_slug);
    } else {
        $base = generate_slug($judul);
    }
    $slug = $base; $n = 2;
    while (true) {
        $chk = $pdo->prepare("SELECT id FROM berita WHERE slug = ? AND id != ?");
        $chk->execute([$slug, $id]);
        if (!$chk->fetchColumn()) break;
        $slug = $base . '-' . $n++;
    }

    // Upload gambar
    $gambar = $edit['gambar'] ?? null;
    if (!empty($_FILES['gambar']['name'])) {
        $up = upload_image($_FILES['gambar']);
        if (!$up['ok']) {
            flash_message('error', $up['error']);
            header('Location: berita-form.php' . ($id ? "?id=$id" : '')); exit;
        }
        if ($up['name']) {
            delete_upload($gambar);
            $gambar = $up['name'];
        }
    }

    try {
        if ($edit) {
            $pdo->prepare("UPDATE berita SET judul=?, slug=?, konten=?, excerpt=?, gambar=?, kategori=?, penulis=?, status=?, is_featured=? WHERE id=?")
                ->execute([$judul, $slug, $konten, $excerpt, $gambar, $kategori, $penulis, $status, $featured, $id]);
            flash_message('success', '✅ Berita berhasil diperbarui.');
        } else {
            $pub = $status === 'Published' ? date('Y-m-d H:i:s') : null;
            $pdo->prepare("INSERT INTO berita (judul, slug, konten, excerpt, gambar, kategori, penulis, status, is_featured, published_at) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$judul, $slug, $konten, $excerpt, $gambar, $kategori, $penulis, $status, $featured, $pub]);
            // Clear autosave
            flash_message('success', '✅ Berita berhasil ditambahkan.');
        }
    } catch (Exception $e) {
        flash_message('error', 'Gagal menyimpan: ' . $e->getMessage());
    }
    header('Location: berita.php');
    exit;
}

$csrf = generate_csrf_token();
$active_menu = 'berita';
$page_heading = $edit ? 'Edit Berita' : 'Tambah Berita';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Kelola Berita', 'berita.php'], [$page_heading, null]];
require __DIR__ . '/includes/header.php';
?>

<div class="editor-wrap">
    <!-- ===== EDITOR HEADER ===== -->
    <div class="editor-header">
        <div class="editor-header-left">
            <a href="berita.php" class="back-btn">← Kembali</a>
            <h2><?= $edit ? '✏️ Edit Berita' : '➕ Berita Baru' ?></h2>
        </div>
        <div class="editor-header-right">
            <span class="autosave-indicator" id="autosaveStatus">
                <span class="as-icon">💾</span>
                <span class="as-text">Tersimpan di draft lokal</span>
            </span>
            <button type="button" class="btn-sm gray" onclick="openPreview()">👁️ Preview</button>
            <button type="submit" form="editorForm" class="btn-sm">💾 <?= $edit ? 'Update' : 'Simpan' ?></button>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data" id="editorForm" class="editor-form">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

        <div class="editor-grid">
            <!-- ===== MAIN COLUMN ===== -->
            <div class="editor-main">
                
                <!-- Judul -->
                <div class="editor-card title-card">
                    <input type="text" name="judul" id="judulInput" 
                        class="judul-input" 
                        placeholder="Tulis judul berita yang menarik..." 
                        required maxlength="255"
                        value="<?= sanitize($edit['judul'] ?? '') ?>">
                    <div class="slug-preview">
                        <span class="slug-prefix"><?= base_url('berita-detail.php?slug=') ?></span>
                        <span class="slug-text" id="slugPreview"><?= sanitize($edit['slug'] ?? 'slug-akan-dibuat-otomatis') ?></span>
                    </div>
                    <input type="text" name="custom_slug" id="customSlug" 
                        class="custom-slug-input" 
                        placeholder="Atau ketik slug kustom di sini (opsional)"
                        style="display:none">
                    <button type="button" class="slug-edit-btn" onclick="toggleCustomSlug()">✏️ Edit slug</button>
                </div>

                <!-- Excerpt -->
                <div class="editor-card">
                    <label class="field-label">
                        <span>📝 Ringkasan (excerpt)</span>
                        <span class="char-count"><span id="excerptCount"><?= mb_strlen($edit['excerpt'] ?? '') ?></span>/500</span>
                    </label>
                    <textarea name="excerpt" id="excerptInput" class="excerpt-textarea" 
                        maxlength="500" rows="3"
                        placeholder="Ringkasan singkat yang menarik pembaca..."><?= sanitize($edit['excerpt'] ?? '') ?></textarea>
                </div>

                <!-- Konten dengan Rich Toolbar -->
                <div class="editor-card">
                    <label class="field-label">
                        <span>📄 Konten Berita *</span>
                        <span class="content-stats">
                            <span id="wordCount">0</span> kata • 
                            <span id="readTime">0</span> menit baca
                        </span>
                    </label>
                    
                    <div class="rich-toolbar">
                        <button type="button" class="tb-btn" onclick="execCmd('bold')" title="Bold (Ctrl+B)"><b>B</b></button>
                        <button type="button" class="tb-btn" onclick="execCmd('italic')" title="Italic (Ctrl+I)"><i>I</i></button>
                        <button type="button" class="tb-btn" onclick="execCmd('underline')" title="Underline"><u>U</u></button>
                        <div class="tb-divider"></div>
                        <button type="button" class="tb-btn" onclick="execCmd('formatBlock','<h2>')" title="Heading 2">H2</button>
                        <button type="button" class="tb-btn" onclick="execCmd('formatBlock','<h3>')" title="Heading 3">H3</button>
                        <button type="button" class="tb-btn" onclick="execCmd('formatBlock','<p>')" title="Paragraph">P</button>
                        <div class="tb-divider"></div>
                        <button type="button" class="tb-btn" onclick="execCmd('insertUnorderedList')" title="Bullet List">• List</button>
                        <button type="button" class="tb-btn" onclick="execCmd('insertOrderedList')" title="Numbered List">1. List</button>
                        <button type="button" class="tb-btn" onclick="execCmd('formatBlock','<blockquote>')" title="Quote">❝ Quote</button>
                        <div class="tb-divider"></div>
                        <button type="button" class="tb-btn" onclick="insertLink()" title="Link">🔗</button>
                        <button type="button" class="tb-btn" onclick="insertHR()" title="Horizontal Line">―</button>
                        <button type="button" class="tb-btn" onclick="execCmd('removeFormat')" title="Clear Format">🧹</button>
                    </div>

                    <textarea name="konten" id="kontenInput" class="konten-textarea" 
                        required placeholder="Tulis konten berita di sini...

Tips:
• Gunakan toolbar di atas untuk formatting
• HTML sederhana diizinkan: p, strong, em, ul, li, h2-h4, blockquote, a"></textarea>
                </div>

                <!-- Tags -->
                <div class="editor-card">
                    <label class="field-label">
                        <span>🏷️ Tags</span>
                        <small style="color:#94a3b8;margin-left:.5rem">Pisahkan dengan koma</small>
                    </label>
                    <div class="tags-input-wrap">
                        <div class="tags-container" id="tagsContainer"></div>
                        <input type="text" id="tagInput" class="tag-input-field" 
                            placeholder="Ketik tag lalu tekan Enter atau koma...">
                    </div>
                    <input type="hidden" name="tags" id="tagsHidden" value="">
                </div>
            </div>

            <!-- ===== SIDEBAR ===== -->
            <aside class="editor-sidebar">
                
                <!-- Status -->
                <div class="editor-card">
                    <h3 class="card-title">📊 Status & Publikasi</h3>
                    <div class="field-group">
                        <label class="field-label">Status</label>
                        <div class="status-radios">
                            <label class="status-radio status-draft">
                                <input type="radio" name="status" value="Draft" <?= ($edit['status'] ?? 'Draft') === 'Draft' ? 'checked' : '' ?>>
                                <span class="sr-dot"></span>
                                <span>📝 Draft</span>
                            </label>
                            <label class="status-radio status-published">
                                <input type="radio" name="status" value="Published" <?= ($edit['status'] ?? '') === 'Published' ? 'checked' : '' ?>>
                                <span class="sr-dot"></span>
                                <span>✅ Published</span>
                            </label>
                            <label class="status-radio status-archived">
                                <input type="radio" name="status" value="Archived" <?= ($edit['status'] ?? '') === 'Archived' ? 'checked' : '' ?>>
                                <span class="sr-dot"></span>
                                <span>🗄️ Archived</span>
                            </label>
                        </div>
                    </div>

                    <div class="field-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_featured" <?= !empty($edit['is_featured']) ? 'checked' : '' ?>>
                            <span class="checkbox-custom"></span>
                            <span>⭐ Jadikan Berita Utama (Featured)</span>
                        </label>
                    </div>

                    <?php if ($edit): ?>
                    <div class="meta-info">
                        <div class="meta-row"><span>Dibuat:</span><strong><?= date('d M Y H:i', strtotime($edit['created_at'])) ?></strong></div>
                        <div class="meta-row"><span>Views:</span><strong><?= number_format($edit['views']) ?></strong></div>
                        <?php if ($edit['published_at']): ?>
                        <div class="meta-row"><span>Published:</span><strong><?= date('d M Y H:i', strtotime($edit['published_at'])) ?></strong></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Kategori & Penulis -->
                <div class="editor-card">
                    <h3 class="card-title">📁 Kategori</h3>
                    <div class="field-group">
                        <select name="kategori" class="styled-select">
                            <?php foreach (['Akademik','Pengumuman','Prestasi','Kegiatan','Riset','Umum'] as $k): ?>
                            <option value="<?= $k ?>" <?= ($edit['kategori'] ?? 'Umum') === $k ? 'selected' : '' ?>><?= $k ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-group">
                        <label class="field-label">Penulis</label>
                        <input type="text" name="penulis" class="styled-input" 
                            maxlength="100" 
                            value="<?= sanitize($edit['penulis'] ?? $_SESSION['admin_name'] ?? 'Humas FKIP') ?>">
                    </div>
                </div>

                <!-- Gambar -->
                <div class="editor-card">
                    <h3 class="card-title">🖼️ Gambar Utama</h3>
                    <div class="upload-zone" id="uploadZone">
                        <input type="file" name="gambar" id="gambarInput" 
                            accept="image/jpeg,image/png,image/webp,image/gif" 
                            class="upload-file-input">
                        <div class="upload-placeholder" id="uploadPlaceholder">
                            <div class="upload-icon">📤</div>
                            <p class="upload-text">Drag & drop gambar di sini</p>
                            <p class="upload-sub">atau klik untuk memilih file</p>
                            <p class="upload-hint">JPG, PNG, WEBP, GIF (maks 2MB)</p>
                        </div>
                        <div class="upload-preview" id="uploadPreview" style="display:none">
                            <img id="previewImg" src="" alt="">
                            <button type="button" class="upload-remove" onclick="removePreview()">✕ Hapus</button>
                        </div>
                    </div>
                    <?php if (!empty($edit['gambar'])): ?>
                        <div class="current-image">
                            <label class="field-label">Gambar saat ini:</label>
                            <img src="<?= asset('uploads/' . basename($edit['gambar'])) ?>" alt="">
                            <small>Upload gambar baru untuk mengganti</small>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- SEO Tips -->
                <div class="editor-card tips-card">
                    <h3 class="card-title">💡 Tips Menulis</h3>
                    <ul class="tips-list">
                        <li>✓ Gunakan judul yang menarik & jelas</li>
                        <li>✓ Ringkasan singkat 1-2 kalimat</li>
                        <li>✓ Minimal 300 kata untuk SEO</li>
                        <li>✓ Tambahkan gambar berkualitas</li>
                        <li>✓ Gunakan heading untuk struktur</li>
                    </ul>
                </div>
            </aside>
        </div>
    </form>
</div>

<!-- ===== PREVIEW MODAL ===== -->
<div class="modal-overlay" id="previewModal" onclick="if(event.target===this)closePreview()">
    <div class="preview-modal-box">
        <div class="preview-header">
            <span>👁️ Preview Tampilan</span>
            <button class="preview-close" onclick="closePreview()">✕</button>
        </div>
        <div class="preview-body">
            <article class="preview-article">
                <div class="preview-badge" id="previewBadge">Kategori</div>
                <h1 class="preview-title" id="previewTitle">Judul berita akan muncul di sini...</h1>
                <div class="preview-meta">
                    <span id="previewAuthor">Penulis</span> • 
                    <span><?= date('d F Y') ?></span> • 
                    <span id="previewReadTime">0 menit baca</span>
                </div>
                <div class="preview-content" id="previewContent"></div>
            </article>
        </div>
    </div>
</div>

<!-- ===== SCOPED STYLES ===== -->
<style>
.editor-wrap{max-width:1400px}
.editor-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem}
.editor-header-left{display:flex;align-items:center;gap:1rem}
.editor-header-left h2{margin:0;font-size:1.35rem}
.back-btn{color:#64748b;text-decoration:none;font-size:.9rem;padding:.4rem .8rem;background:#f1f5f9;border-radius:8px;transition:all .2s}
.back-btn:hover{background:#e2e8f0;color:#0f172a}
.editor-header-right{display:flex;align-items:center;gap:.75rem}
.autosave-indicator{display:flex;align-items:center;gap:.4rem;font-size:.8rem;color:#64748b;background:#f1f5f9;padding:.4rem .75rem;border-radius:999px;transition:all .3s}
.autosave-indicator.saving{background:#fef3c7;color:#92400e}
.autosave-indicator.saved{background:#dcfce7;color:#166534}

.editor-form{display:block}
.editor-grid{display:grid;grid-template-columns:1fr 380px;gap:1.5rem;align-items:start}
.editor-main,.editor-sidebar{display:flex;flex-direction:column;gap:1.25rem}
.editor-card{background:#fff;border-radius:16px;padding:1.5rem;box-shadow:0 2px 8px rgba(0,0,0,.04);border:1px solid #f1f5f9}
.card-title{font-size:.95rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;padding-bottom:.75rem;border-bottom:1px solid #f1f5f9}
.field-label{display:flex;justify-content:space-between;align-items:center;font-size:.85rem;font-weight:600;color:#334155;margin-bottom:.5rem}
.field-group{margin-bottom:1rem}
.field-group:last-child{margin-bottom:0}
.styled-input,.styled-select{width:100%;padding:.7rem .9rem;border:2px solid #e2e8f0;border-radius:10px;font-size:.9rem;font-family:inherit;background:#f8fafc;transition:all .2s}
.styled-input:focus,.styled-select:focus{outline:none;border-color:#0a6847;background:#fff;box-shadow:0 0 0 4px rgba(10,104,71,.1)}

/* Title Card */
.title-card{padding:2rem!important;background:linear-gradient(135deg,#fff 0%,#f0fdf4 100%)}
.judul-input{width:100%;border:none;font-size:1.75rem;font-weight:800;color:#0f172a;background:transparent;outline:none;font-family:inherit;padding:0;margin-bottom:.75rem}
.judul-input::placeholder{color:#cbd5e1}
.slug-preview{display:flex;align-items:center;gap:.25rem;font-size:.8rem;color:#64748b;background:#f1f5f9;padding:.5rem .85rem;border-radius:8px;margin-bottom:.5rem;font-family:ui-monospace,monospace;overflow:hidden}
.slug-prefix{color:#94a3b8;flex-shrink:0}
.slug-text{color:#0a6847;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.custom-slug-input{width:100%;padding:.6rem .85rem;border:2px solid #0a6847;border-radius:8px;font-family:ui-monospace,monospace;font-size:.85rem;margin-bottom:.5rem}
.slug-edit-btn{background:transparent;border:1px solid #e2e8f0;padding:.35rem .75rem;border-radius:6px;font-size:.75rem;cursor:pointer;color:#64748b;transition:all .2s;font-family:inherit}
.slug-edit-btn:hover{background:#0a6847;color:#fff;border-color:#0a6847}

/* Excerpt */
.excerpt-textarea{width:100%;padding:.85rem 1rem;border:2px solid #e2e8f0;border-radius:10px;font-size:.9rem;font-family:inherit;background:#f8fafc;resize:vertical;transition:all .2s;line-height:1.6}
.excerpt-textarea:focus{outline:none;border-color:#0a6847;background:#fff;box-shadow:0 0 0 4px rgba(10,104,71,.1)}
.char-count{font-size:.75rem;color:#94a3b8;font-weight:500;font-variant-numeric:tabular-nums}
.char-count.warn{color:#f59e0b}
.char-count.danger{color:#ef4444}

/* Content & Toolbar */
.content-stats{font-size:.75rem;color:#94a3b8;font-weight:500}
.rich-toolbar{display:flex;flex-wrap:wrap;gap:.25rem;padding:.5rem;background:#f8fafc;border:2px solid #e2e8f0;border-bottom:none;border-radius:10px 10px 0 0;align-items:center}
.tb-btn{background:#fff;border:1px solid #e2e8f0;padding:.35rem .65rem;border-radius:6px;font-size:.8rem;cursor:pointer;transition:all .15s;color:#475569;font-family:inherit;min-width:32px;display:flex;align-items:center;justify-content:center;gap:.25rem}
.tb-btn:hover{background:#0a6847;color:#fff;border-color:#0a6847;transform:translateY(-1px)}
.tb-btn b,.tb-btn i,.tb-btn u{font-size:.85rem}
.tb-divider{width:1px;height:20px;background:#e2e8f0;margin:0 .25rem}
.konten-textarea{width:100%;padding:1.25rem;border:2px solid #e2e8f0;border-top:1px solid #f1f5f9;border-radius:0 0 10px 10px;font-size:.92rem;font-family:ui-monospace,monospace;background:#fff;resize:vertical;min-height:400px;line-height:1.7;transition:border-color .2s}
.konten-textarea:focus{outline:none;border-color:#0a6847;box-shadow:0 0 0 4px rgba(10,104,71,.1)}

/* Tags */
.tags-input-wrap{border:2px solid #e2e8f0;border-radius:10px;padding:.5rem;background:#f8fafc;transition:all .2s;min-height:48px;display:flex;flex-wrap:wrap;gap:.35rem;align-items:center}
.tags-input-wrap:focus-within{border-color:#0a6847;background:#fff;box-shadow:0 0 0 4px rgba(10,104,71,.1)}
.tags-container{display:flex;flex-wrap:wrap;gap:.35rem}
.tag-pill{background:linear-gradient(135deg,#0a6847,#16a34a);color:#fff;padding:.3rem .65rem;border-radius:999px;font-size:.78rem;font-weight:600;display:inline-flex;align-items:center;gap:.3rem;animation:tagPop .2s}
@keyframes tagPop{from{transform:scale(0)}to{transform:scale(1)}}
.tag-pill button{background:rgba(255,255,255,.3);border:none;color:#fff;width:18px;height:18px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.7rem;transition:all .2s;padding:0}
.tag-pill button:hover{background:rgba(255,255,255,.6)}
.tag-input-field{border:none;outline:none;flex:1;min-width:120px;padding:.3rem;background:transparent;font-family:inherit;font-size:.85rem}

/* Status radios */
.status-radios{display:flex;flex-direction:column;gap:.5rem}
.status-radio{display:flex;align-items:center;gap:.75rem;padding:.75rem 1rem;background:#f8fafc;border:2px solid transparent;border-radius:10px;cursor:pointer;transition:all .2s;font-size:.85rem}
.status-radio input{display:none}
.status-radio:hover{background:#f1f5f9}
.status-radio:has(input:checked).status-draft{background:#fef3c7;border-color:#f59e0b}
.status-radio:has(input:checked).status-published{background:#dcfce7;border-color:#10b981}
.status-radio:has(input:checked).status-archived{background:#f1f5f9;border-color:#64748b}
.sr-dot{width:12px;height:12px;border-radius:50%;background:#cbd5e1;position:relative}
.status-radio:has(input:checked) .sr-dot{background:currentColor}
.status-radio:has(input:checked) .sr-dot::after{content:'';position:absolute;inset:3px;background:#fff;border-radius:50%}
.status-draft{color:#92400e}
.status-published{color:#166534}
.status-archived{color:#475569}

/* Checkbox custom */
.checkbox-label{display:flex;align-items:center;gap:.75rem;cursor:pointer;font-size:.85rem;color:#334155;padding:.5rem;border-radius:8px;transition:background .2s}
.checkbox-label:hover{background:#f8fafc}
.checkbox-label input{display:none}
.checkbox-custom{width:20px;height:20px;border:2px solid #cbd5e1;border-radius:5px;flex-shrink:0;position:relative;transition:all .2s}
.checkbox-label input:checked + .checkbox-custom{background:#0a6847;border-color:#0a6847}
.checkbox-label input:checked + .checkbox-custom::after{content:'✓';position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.85rem;font-weight:700}

/* Meta info */
.meta-info{margin-top:1rem;padding-top:1rem;border-top:1px solid #f1f5f9;display:flex;flex-direction:column;gap:.4rem}
.meta-row{display:flex;justify-content:space-between;font-size:.8rem}
.meta-row span{color:#64748b}
.meta-row strong{color:#0f172a;font-weight:600}

/* Upload Zone */
.upload-zone{position:relative;border:2px dashed #cbd5e1;border-radius:12px;transition:all .2s;overflow:hidden;background:#fafafa}
.upload-zone:hover,.upload-zone.dragover{border-color:#0a6847;background:#f0fdf4}
.upload-file-input{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer}
.upload-placeholder{padding:2rem;text-align:center}
.upload-icon{font-size:2.5rem;margin-bottom:.5rem;animation:uploadFloat 3s ease-in-out infinite}
@keyframes uploadFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-5px)}}
.upload-text{font-weight:600;color:#0f172a;margin-bottom:.25rem}
.upload-sub{font-size:.85rem;color:#64748b;margin-bottom:.5rem}
.upload-hint{font-size:.72rem;color:#94a3b8}
.upload-preview{position:relative}
.upload-preview img{width:100%;max-height:250px;object-fit:cover;display:block}
.upload-remove{position:absolute;top:.5rem;right:.5rem;background:rgba(0,0,0,.7);color:#fff;border:none;padding:.4rem .7rem;border-radius:6px;font-size:.75rem;cursor:pointer;transition:all .2s}
.upload-remove:hover{background:#dc2626}
.current-image{margin-top:1rem;padding-top:1rem;border-top:1px solid #f1f5f9}
.current-image img{width:100%;border-radius:8px;margin:.5rem 0}
.current-image small{font-size:.75rem;color:#94a3b8}

/* Tips card */
.tips-card{background:linear-gradient(135deg,#fef3c7,#fde68a);border-color:#fcd34d}
.tips-list{list-style:none;font-size:.82rem;color:#92400e;line-height:1.8}
.tips-list li{padding-left:.25rem}

/* Preview Modal */
.preview-modal-box{background:#fff;border-radius:16px;max-width:800px;width:95%;max-height:90vh;overflow:hidden;animation:zoomIn .3s;box-shadow:0 30px 80px rgba(0,0,0,.4)}
@keyframes zoomIn{from{transform:scale(.95);opacity:0}to{transform:scale(1);opacity:1}}
.preview-header{display:flex;justify-content:space-between;align-items:center;padding:1rem 1.5rem;background:#0f172a;color:#fff}
.preview-close{background:rgba(255,255,255,.2);border:none;color:#fff;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:1rem;transition:all .2s}
.preview-close:hover{background:#dc2626;transform:rotate(90deg)}
.preview-body{max-height:calc(90vh - 60px);overflow-y:auto;padding:2.5rem}
.preview-article{font-family:Georgia,serif}
.preview-badge{display:inline-block;padding:.3rem .85rem;background:#0a6847;color:#fff;border-radius:999px;font-size:.72rem;font-weight:700;text-transform:uppercase;margin-bottom:1rem;font-family:sans-serif}
.preview-title{font-size:2rem;line-height:1.2;margin-bottom:1rem;color:#0f172a;font-weight:800}
.preview-meta{color:#94a3b8;font-size:.85rem;margin-bottom:2rem;padding-bottom:1rem;border-bottom:1px solid #e2e8f0;font-family:sans-serif}
.preview-content{font-size:1.05rem;line-height:1.9;color:#334155}
.preview-content p{margin-bottom:1rem}
.preview-content h2,.preview-content h3{margin:1.5rem 0 .75rem;color:#0f172a;font-family:sans-serif}
.preview-content blockquote{border-left:4px solid #0a6847;padding:.5rem 1rem;margin:1rem 0;background:#f0fdf4;font-style:italic;border-radius:0 8px 8px 0}

/* Modal overlay (reused) */
.modal-overlay{position:fixed;inset:0;background:rgba(15,23,42,.8);backdrop-filter:blur(8px);display:none;align-items:center;justify-content:center;z-index:9999;padding:2rem}
.modal-overlay.open{display:flex}

/* Responsive */
@media(max-width:968px){
    .editor-grid{grid-template-columns:1fr}
    .editor-header{flex-direction:column;align-items:flex-start}
    .judul-input{font-size:1.4rem}
    .preview-body{padding:1.5rem}
    .preview-title{font-size:1.5rem}
}
</style>

<script>
// ===== Auto-generate slug from judul =====
const judulInput = document.getElementById('judulInput');
const slugPreview = document.getElementById('slugPreview');
const customSlug = document.getElementById('customSlug');

function slugify(text) {
    return text.toString().toLowerCase()
        .replace(/\s+/g, '-')
        .replace(/[^\w\-]+/g, '')
        .replace(/\-\-+/g, '-')
        .replace(/^-+/, '')
        .replace(/-+$/, '');
}

judulInput.addEventListener('input', function() {
    if (!customSlug.style.display || customSlug.style.display === 'none') {
        const slug = slugify(this.value) || 'slug-akan-dibuat-otomatis';
        slugPreview.textContent = slug;
    }
    autoSave();
});

function toggleCustomSlug() {
    const isShown = customSlug.style.display === 'block';
    customSlug.style.display = isShown ? 'none' : 'block';
    if (!isShown) customSlug.focus();
}
customSlug.addEventListener('input', function() {
    slugPreview.textContent = slugify(this.value) || slugify(judulInput.value);
});

// ===== Word counter & read time =====
const kontenInput = document.getElementById('kontenInput');
const wordCountEl = document.getElementById('wordCount');
const readTimeEl = document.getElementById('readTime');

function updateWordCount() {
    const text = kontenInput.value.trim();
    const words = text ? text.split(/\s+/).length : 0;
    wordCountEl.textContent = words;
    readTimeEl.textContent = Math.max(1, Math.ceil(words / 200));
    autoSave();
}
kontenInput.addEventListener('input', updateWordCount);
updateWordCount();

// ===== Excerpt char counter =====
const excerptInput = document.getElementById('excerptInput');
const excerptCount = document.getElementById('excerptCount');
excerptInput.addEventListener('input', function() {
    const len = this.value.length;
    excerptCount.textContent = len;
    const parent = excerptCount.parentElement;
    parent.classList.toggle('warn', len > 400 && len <= 500);
    parent.classList.toggle('danger', len > 500);
});

// ===== Rich Text Commands =====
function execCmd(cmd, val = null) {
    const ta = kontenInput;
    const start = ta.selectionStart;
    const end = ta.selectionEnd;
    const text = ta.value;
    const selected = text.substring(start, end);
    
    const wrappers = {
        'bold': ['<strong>', '</strong>'],
        'italic': ['<em>', '</em>'],
        'underline': ['<u>', '</u>'],
        'formatBlock': {
            '<h2>': ['<h2>', '</h2>'],
            '<h3>': ['<h3>', '</h3>'],
            '<p>': ['<p>', '</p>'],
            '<blockquote>': ['<blockquote>', '</blockquote>']
        },
        'insertUnorderedList': ['<ul>\n<li>', '</li>\n</ul>'],
        'insertOrderedList': ['<ol>\n<li>', '</li>\n</ol>']
    };
    
    let before = '', after = '';
    if (cmd === 'formatBlock') {
        [before, after] = wrappers[cmd][val];
    } else if (wrappers[cmd]) {
        [before, after] = wrappers[cmd];
    }
    
    if (cmd === 'removeFormat') {
        const cleaned = selected.replace(/<[^>]+>/g, '');
        ta.value = text.substring(0, start) + cleaned + text.substring(end);
    } else {
        ta.value = text.substring(0, start) + before + selected + after + text.substring(end);
        ta.selectionStart = start + before.length;
        ta.selectionEnd = start + before.length + selected.length;
    }
    ta.focus();
    updateWordCount();
}

function insertLink() {
    const url = prompt('Masukkan URL:', 'https://');
    if (url) {
        const ta = kontenInput;
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        const text = ta.value;
        const selected = text.substring(start, end) || url;
        const tag = `<a href="${url}" target="_blank">${selected}</a>`;
        ta.value = text.substring(0, start) + tag + text.substring(end);
        ta.focus();
        updateWordCount();
    }
}

function insertHR() {
    const ta = kontenInput;
    const pos = ta.selectionStart;
    ta.value = ta.value.substring(0, pos) + '\n<hr>\n' + ta.value.substring(pos);
    ta.focus();
}

// ===== Tags input =====
const tagInput = document.getElementById('tagInput');
const tagsContainer = document.getElementById('tagsContainer');
const tagsHidden = document.getElementById('tagsHidden');
let tags = [];

function renderTags() {
    tagsContainer.innerHTML = '';
    tags.forEach((tag, i) => {
        const pill = document.createElement('span');
        pill.className = 'tag-pill';
        pill.innerHTML = `${tag} <button type="button" onclick="removeTag(${i})">✕</button>`;
        tagsContainer.appendChild(pill);
    });
    tagsHidden.value = tags.join(', ');
}

function addTag(val) {
    val = val.trim();
    if (val && !tags.includes(val) && tags.length < 10) {
        tags.push(val);
        renderTags();
    }
}

window.removeTag = function(i) {
    tags.splice(i, 1);
    renderTags();
};

tagInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        addTag(this.value.replace(',', ''));
        this.value = '';
    } else if (e.key === 'Backspace' && !this.value && tags.length > 0) {
        tags.pop();
        renderTags();
    }
});
tagInput.addEventListener('blur', function() {
    if (this.value.trim()) {
        addTag(this.value);
        this.value = '';
    }
});

// ===== Upload drag & drop =====
const uploadZone = document.getElementById('uploadZone');
const gambarInput = document.getElementById('gambarInput');
const uploadPlaceholder = document.getElementById('uploadPlaceholder');
const uploadPreview = document.getElementById('uploadPreview');
const previewImg = document.getElementById('previewImg');

['dragenter','dragover'].forEach(ev => {
    uploadZone.addEventListener(ev, e => { e.preventDefault(); uploadZone.classList.add('dragover'); });
});
['dragleave','drop'].forEach(ev => {
    uploadZone.addEventListener(ev, e => { e.preventDefault(); uploadZone.classList.remove('dragover'); });
});
uploadZone.addEventListener('drop', e => {
    const files = e.dataTransfer.files;
    if (files.length > 0) handleFile(files[0]);
});
gambarInput.addEventListener('change', e => {
    if (e.target.files.length > 0) handleFile(e.target.files[0]);
});

function handleFile(file) {
    if (!file.type.startsWith('image/')) {
        alert('File harus berupa gambar!');
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        alert('Ukuran maksimal 2MB!');
        return;
    }
    const reader = new FileReader();
    reader.onload = e => {
        previewImg.src = e.target.result;
        uploadPlaceholder.style.display = 'none';
        uploadPreview.style.display = 'block';
    };
    reader.readAsDataURL(file);
}

window.removePreview = function() {
    gambarInput.value = '';
    previewImg.src = '';
    uploadPlaceholder.style.display = 'block';
    uploadPreview.style.display = 'none';
};

// ===== Auto-save to localStorage =====
const storageKey = 'fkip_draft_' + (<?= $id ?> || 'new');
let autoSaveTimer;

function autoSave() {
    clearTimeout(autoSaveTimer);
    const indicator = document.getElementById('autosaveStatus');
    indicator.classList.add('saving');
    indicator.querySelector('.as-text').textContent = 'Menyimpan...';
    
    autoSaveTimer = setTimeout(() => {
        const data = {
            judul: judulInput.value,
            excerpt: excerptInput.value,
            konten: kontenInput.value,
            tags: tags.join(','),
            saved_at: new Date().toISOString()
        };
        try {
            localStorage.setItem(storageKey, JSON.stringify(data));
            indicator.classList.remove('saving');
            indicator.classList.add('saved');
            indicator.querySelector('.as-text').textContent = 'Tersimpan ' + new Date().toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'});
        } catch(e) {}
    }, 800);
}

// Load autosaved data if new post and nothing in form
(function(){
    <?php if (!$edit): ?>
    try {
        const saved = localStorage.getItem(storageKey);
        if (saved) {
            const data = JSON.parse(saved);
            if (confirm('Ada draft tersimpan dari ' + new Date(data.saved_at).toLocaleString('id-ID') + '. Muat draft tersebut?')) {
                judulInput.value = data.judul || '';
                excerptInput.value = data.excerpt || '';
                kontenInput.value = data.konten || '';
                if (data.tags) { tags = data.tags.split(',').filter(t => t); renderTags(); }
                judulInput.dispatchEvent(new Event('input'));
                updateWordCount();
            } else {
                localStorage.removeItem(storageKey);
            }
        }
    } catch(e) {}
    <?php endif; ?>
})();

// Clear autosave after successful submit
document.getElementById('editorForm').addEventListener('submit', function() {
    try { localStorage.removeItem(storageKey); } catch(e) {}
});

// ===== Preview modal =====
function openPreview() {
    document.getElementById('previewTitle').textContent = judulInput.value || 'Judul berita akan muncul di sini...';
    document.getElementById('previewContent').innerHTML = kontenInput.value.replace(/\n/g, '<br>') || '<p style="color:#94a3b8"><em>Belum ada konten...</em></p>';
    document.getElementById('previewBadge').textContent = document.querySelector('[name="kategori"]').value;
    document.getElementById('previewAuthor').textContent = document.querySelector('[name="penulis"]').value || 'Humas FKIP';
    document.getElementById('previewReadTime').textContent = readTimeEl.textContent + ' menit baca';
    document.getElementById('previewModal').classList.add('open');
}
function closePreview() {
    document.getElementById('previewModal').classList.remove('open');
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closePreview();
});

// ===== Keyboard shortcuts in editor =====
kontenInput.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'b') { e.preventDefault(); execCmd('bold'); }
    if ((e.ctrlKey || e.metaKey) && e.key === 'i') { e.preventDefault(); execCmd('italic'); }
    if ((e.ctrlKey || e.metaKey) && e.key === 'u') { e.preventDefault(); execCmd('underline'); }
    // Tab for indent
    if (e.key === 'Tab') {
        e.preventDefault();
        const start = this.selectionStart;
        this.value = this.value.substring(0, start) + '    ' + this.value.substring(this.selectionEnd);
        this.selectionStart = this.selectionEnd = start + 4;
    }
});

console.log('%c✏️ Editor Berita FKIP UNIMOF', 'color:#0a6847;font-size:16px;font-weight:bold');
console.log('%cShortcuts: Ctrl+B (bold), Ctrl+I (italic), Ctrl+U (underline)', 'color:#64748b');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>