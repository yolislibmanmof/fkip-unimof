<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$duplicate_from = (int)($_GET['duplicate'] ?? 0);
$edit = null;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM agenda WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch();
    if (!$edit) {
        flash_message('error', 'Agenda tidak ditemukan.');
        header('Location: agenda.php');
        exit;
    }
} elseif ($duplicate_from > 0) {
    $stmt = $pdo->prepare("SELECT * FROM agenda WHERE id = ?");
    $stmt->execute([$duplicate_from]);
    $source = $stmt->fetch();
    if ($source) {
        $edit = $source;
        $edit['id'] = null;
        $edit['judul'] = $source['judul'] . ' (Copy)';
        flash_message('info', '📋 Menduplikasi agenda: ' . htmlspecialchars($source['judul']));
    }
}

// Parse metadata dari deskripsi (backward compatible)
$metadata = [];
if ($edit && !empty($edit['deskripsi'])) {
    if (preg_match('/<!--META:(.*?)-->/s', $edit['deskripsi'], $m)) {
        $metadata = json_decode($m[1], true) ?: [];
        $edit['deskripsi'] = trim(preg_replace('/<!--META:.*?-->/s', '', $edit['deskripsi']));
    }
}

// Handle ICS export for single event
if (isset($_GET['ics']) && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM agenda WHERE id = ?");
    $stmt->execute([$id]);
    $a = $stmt->fetch();
    if ($a) {
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9]+/i', '-', $a['judul']) . '.ics"');
        $dtstart = date('Ymd\THis', strtotime($a['tanggal_mulai'] . ' 08:00:00'));
        $dtend = $a['tanggal_selesai'] ? date('Ymd\THis', strtotime($a['tanggal_selesai'] . ' 17:00:00')) : date('Ymd\THis', strtotime($a['tanggal_mulai'] . ' 17:00:00'));
        echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//FKIP UNIMOF//Agenda//ID\r\nCALSCALE:GREGORIAN\r\n";
        echo "BEGIN:VEVENT\r\n";
        echo "UID:agenda-" . $a['id'] . "@fkip-unimof.ac.id\r\n";
        echo "DTSTAMP:" . date('Ymd\THis') . "\r\n";
        echo "DTSTART:$dtstart\r\n";
        echo "DTEND:$dtend\r\n";
        echo "SUMMARY:" . str_replace(["\r", "\n"], ' ', $a['judul']) . "\r\n";
        if (!empty($a['deskripsi'])) echo "DESCRIPTION:" . str_replace(["\r", "\n"], ' ', $a['deskripsi']) . "\r\n";
        if (!empty($a['lokasi'])) echo "LOCATION:" . $a['lokasi'] . "\r\n";
        echo "STATUS:" . ($a['status'] === 'Aktif' ? 'CONFIRMED' : 'CANCELLED') . "\r\n";
        echo "END:VEVENT\r\nEND:VCALENDAR\r\n";
        exit;
    }
}

// Check conflicts
$conflicts = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tanggal_mulai'])) {
    $check_date = $_POST['tanggal_mulai'];
    $stmt = $pdo->prepare("SELECT id, judul FROM agenda WHERE tanggal_mulai = ? AND id != ?");
    $stmt->execute([$check_date, $id]);
    $conflicts = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $judul = trim($_POST['judul'] ?? '');
    $jenis = $_POST['jenis'] ?? 'umum';
    $status = $_POST['status'] ?? 'Aktif';
    $tgl_mulai = $_POST['tanggal_mulai'] ?? '';
    $tgl_selesai = $_POST['tanggal_selesai'] ?? null;
    $lokasi = trim($_POST['lokasi'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $reminder = (int)($_POST['reminder'] ?? 0);
    $priority = $_POST['priority'] ?? 'normal';
    $time_start = $_POST['time_start'] ?? '';
    $time_end = $_POST['time_end'] ?? '';
    $recurring = $_POST['recurring'] ?? 'none';
    $recurring_end = $_POST['recurring_end'] ?? null;
    $participants = trim($_POST['participants'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $budget = (float)($_POST['budget'] ?? 0);
    $venue_capacity = (int)($_POST['venue_capacity'] ?? 0);
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    
    // Simpan metadata sebagai JSON di dalam deskripsi
    $metadata = [
        'priority' => $priority,
        'time_start' => $time_start,
        'time_end' => $time_end,
        'recurring' => $recurring,
        'recurring_end' => $recurring_end,
        'participants' => $participants,
        'tags' => $tags,
        'budget' => $budget,
        'venue_capacity' => $venue_capacity,
        'is_public' => $is_public,
        'version' => ($edit['id'] ?? 0) > 0 ? (int)($metadata['version'] ?? 0) + 1 : 1,
        'created_by' => $_SESSION['admin_id'] ?? null,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $full_deskripsi = $deskripsi . "\n<!--META:" . json_encode($metadata, JSON_UNESCAPED_UNICODE) . "-->";
    
    if ($judul === '' || $tgl_mulai === '') {
        flash_message('error', 'Judul dan tanggal mulai wajib diisi.');
        header('Location: agenda-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }
    
    // Handle file attachments
    $attachments = [];
    if (!empty($_FILES['attachments']['name'][0])) {
        $upload_dir = __DIR__ . '/../uploads/agenda/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        
        foreach ($_FILES['attachments']['tmp_name'] as $i => $tmp) {
            if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['attachments']['name'][$i], PATHINFO_EXTENSION));
                $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip'];
                if (in_array($ext, $allowed)) {
                    $new_name = 'agenda_' . time() . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($tmp, $upload_dir . $new_name)) {
                        $attachments[] = [
                            'name' => $_FILES['attachments']['name'][$i],
                            'file' => $new_name,
                            'size' => $_FILES['attachments']['size'][$i]
                        ];
                    }
                }
            }
        }
    }
    
    // Merge dengan attachments lama
    if (!empty($metadata['attachments'])) {
        $attachments = array_merge($metadata['attachments'], $attachments);
    }
    
    // Handle delete attachments
    if (!empty($_POST['delete_attachments'])) {
        $to_delete = (array)$_POST['delete_attachments'];
        $attachments = array_filter($attachments, function($a) use ($to_delete) {
            if (in_array($a['file'], $to_delete)) {
                @unlink(__DIR__ . '/../uploads/agenda/' . $a['file']);
                return false;
            }
            return true;
        });
        $attachments = array_values($attachments);
    }
    
    $metadata['attachments'] = $attachments;
    $full_deskripsi = $deskripsi . "\n<!--META:" . json_encode($metadata, JSON_UNESCAPED_UNICODE) . "-->";
    
    try {
        if ($edit && $edit['id']) {
            $pdo->prepare("UPDATE agenda SET judul=?, jenis=?, tanggal_mulai=?, tanggal_selesai=?, lokasi=?, deskripsi=?, status=?, reminder=? WHERE id=?")
                ->execute([$judul, $jenis, $tgl_mulai, $tgl_selesai, $lokasi, $full_deskripsi, $status, $reminder, $edit['id']]);
            flash_message('success', '✅ Agenda berhasil diperbarui (v' . $metadata['version'] . ').');
            $redirect_id = $edit['id'];
        } else {
            $pdo->prepare("INSERT INTO agenda (judul, jenis, tanggal_mulai, tanggal_selesai, lokasi, deskripsi, status, reminder) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$judul, $jenis, $tgl_mulai, $tgl_selesai, $lokasi, $full_deskripsi, $status, $reminder]);
            $redirect_id = $pdo->lastInsertId();
            flash_message('success', '✅ Agenda baru berhasil ditambahkan.');
        }
        
        // Handle recurring: buat agenda tambahan
        if ($recurring !== 'none' && !empty($tgl_mulai) && !empty($recurring_end)) {
            $start = new DateTime($tgl_mulai);
            $end_limit = new DateTime($recurring_end);
            $interval = [
                'daily' => 'P1D', 'weekly' => 'P1W', 
                'monthly' => 'P1M', 'yearly' => 'P1Y'
            ][$recurring] ?? 'P1W';
            
            $count = 0;
            $start->modify('+' . ($recurring === 'daily' ? '1 day' : ($recurring === 'weekly' ? '1 week' : ($recurring === 'monthly' ? '1 month' : '1 year'))));
            
            while ($start <= $end_limit && $count < 52) {
                $new_date = $start->format('Y-m-d');
                $new_end = $tgl_selesai ? date('Y-m-d', strtotime($new_date . ' +' . (strtotime($tgl_selesai) - strtotime($tgl_mulai)) . ' seconds')) : null;
                $recurring_title = $judul . ' (Berulang #' . ($count + 2) . ')';
                
                $pdo->prepare("INSERT INTO agenda (judul, jenis, tanggal_mulai, tanggal_selesai, lokasi, deskripsi, status, reminder) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$recurring_title, $jenis, $new_date, $new_end, $lokasi, $full_deskripsi, $status, $reminder]);
                
                $count++;
                $start->modify('+' . ($recurring === 'daily' ? '1 day' : ($recurring === 'weekly' ? '1 week' : ($recurring === 'monthly' ? '1 month' : '1 year'))));
            }
            
            if ($count > 0) {
                flash_message('success', '📅 +' . $count . ' agenda berulang telah dibuat.');
            }
        }
        
        header('Location: agenda-form.php?id=' . $redirect_id);
        exit;
        
    } catch (PDOException $e) {
        flash_message('error', '❌ Gagal menyimpan: ' . $e->getMessage());
    }
}

// Fetch recent locations for autocomplete
$recent_locations = $pdo->query("SELECT lokasi FROM agenda WHERE lokasi IS NOT NULL AND lokasi != '' GROUP BY lokasi ORDER BY MAX(id) DESC LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
$csrf = generate_csrf_token();
$active_menu = 'agenda';
$page_heading = $edit ? 'Edit Agenda' : 'Tambah Agenda';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Kelola Agenda', 'agenda.php'], [$page_heading, null]];

require __DIR__ . '/includes/header.php';
?>

<style>
/* ===== PREMIUM LAYOUT ===== */
.agenda-form-layout { display: grid; grid-template-columns: 1.3fr 1fr; gap: 2rem; align-items: start; }

/* Progress Indicator */
.progress-indicator { 
    background: var(--bg-primary); border: 1px solid var(--border); 
    border-radius: var(--radius-xl); padding: 1rem 1.5rem; margin-bottom: 1.5rem;
    display: flex; align-items: center; gap: 1rem;
}
.progress-bar-wrap { flex: 1; height: 8px; background: var(--bg-tertiary); border-radius: 999px; overflow: hidden; }
.progress-bar-fill { 
    height: 100%; background: linear-gradient(90deg, #ef4444 0%, #f59e0b 50%, #10b981 100%); 
    transition: width 0.4s ease; border-radius: 999px;
}
.progress-stats { display: flex; align-items: center; gap: 0.75rem; font-size: 0.85rem; font-weight: 600; }
.progress-percent { color: var(--primary); font-weight: 800; font-size: 1rem; }
.progress-label { color: var(--text-muted); }

/* Form Card */
.form-card-premium { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); position: relative; overflow: hidden; }
.form-card-premium::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--primary), var(--primary-light), var(--secondary)); }

.form-header-premium { margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border); }
.form-header-premium h2 { font-family: var(--font-display); font-size: 1.75rem; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; }
.form-header-premium p { color: var(--text-muted); font-size: 0.9rem; }
.version-badge { 
    display: inline-flex; align-items: center; gap: 0.3rem;
    padding: 0.2rem 0.6rem; background: #dbeafe; color: #1e40af; 
    border-radius: 999px; font-size: 0.7rem; font-weight: 700; margin-left: 0.5rem;
}

/* Section Dividers */
.form-section-divider {
    display: flex; align-items: center; gap: 1rem; margin: 2rem 0 1.5rem;
    font-size: 0.85rem; font-weight: 700; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: 0.1em;
}
.form-section-divider::before, .form-section-divider::after {
    content: ''; flex: 1; height: 1px; background: var(--border);
}

/* Visual Type Selector */
.type-selector { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; margin-bottom: 1.5rem; }
.type-option { position: relative; }
.type-option input { position: absolute; opacity: 0; pointer-events: none; }
.type-option label {
    display: flex; flex-direction: column; align-items: center; gap: 0.5rem;
    padding: 1rem 0.5rem; background: var(--bg-secondary); border: 2px solid var(--border);
    border-radius: var(--radius-md); cursor: pointer; transition: all 0.3s; text-align: center;
}
.type-option label:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.type-option input:checked + label { border-color: var(--primary); background: rgba(10,104,71,0.05); box-shadow: 0 4px 12px rgba(10,104,71,0.15); }
.type-icon { font-size: 1.75rem; }
.type-name { font-size: 0.8rem; font-weight: 700; color: var(--text-primary); }

/* Priority Selector */
.priority-selector { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem; margin-bottom: 1.5rem; }
.priority-option { position: relative; }
.priority-option input { position: absolute; opacity: 0; pointer-events: none; }
.priority-option label {
    display: flex; align-items: center; justify-content: center; gap: 0.4rem;
    padding: 0.75rem 0.5rem; background: var(--bg-secondary); border: 2px solid var(--border);
    border-radius: var(--radius-md); cursor: pointer; transition: all 0.3s; font-weight: 600; font-size: 0.8rem;
}
.priority-option label:hover { transform: translateY(-2px); }
.priority-option input:checked + label.priority-low { background: #f0fdf4; border-color: #10b981; color: #166534; }
.priority-option input:checked + label.priority-normal { background: #dbeafe; border-color: #3b82f6; color: #1e40af; }
.priority-option input:checked + label.priority-high { background: #fef3c7; border-color: #f59e0b; color: #92400e; }
.priority-option input:checked + label.priority-urgent { background: #fee2e2; border-color: #ef4444; color: #991b1b; }

/* Template Buttons */
.template-section { margin-bottom: 1.5rem; }
.template-label { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem; }
.template-buttons { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.template-btn { padding: 0.5rem 0.85rem; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 999px; font-size: 0.78rem; font-weight: 600; cursor: pointer; transition: all 0.2s; font-family: inherit; color: var(--text-secondary); }
.template-btn:hover { background: var(--primary); color: white; border-color: var(--primary); transform: translateY(-2px); }

/* Duplicate Dropdown */
.duplicate-dropdown { position: relative; display: inline-block; }
.duplicate-btn { 
    padding: 0.5rem 0.85rem; background: linear-gradient(135deg, #8b5cf6, #6d28d9); 
    color: white; border: none; border-radius: 999px; font-size: 0.78rem; 
    font-weight: 600; cursor: pointer; transition: all 0.2s; font-family: inherit;
}
.duplicate-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(139,92,246,0.4); }
.duplicate-menu {
    position: absolute; top: 100%; left: 0; margin-top: 0.5rem;
    background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg); min-width: 280px; max-height: 300px; overflow-y: auto;
    display: none; z-index: 100;
}
.duplicate-menu.show { display: block; }
.duplicate-item { 
    padding: 0.75rem 1rem; cursor: pointer; transition: background 0.2s;
    border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; gap: 0.5rem;
}
.duplicate-item:last-child { border-bottom: none; }
.duplicate-item:hover { background: var(--bg-secondary); }
.duplicate-item-title { font-weight: 600; font-size: 0.85rem; color: var(--text-primary); }
.duplicate-item-date { font-size: 0.75rem; color: var(--text-muted); }

/* Floating Label Form */
.form-group-premium { margin-bottom: 1.5rem; position: relative; }
.input-wrapper-premium { position: relative; }
.form-input-premium {
    width: 100%; padding: 1rem 1rem 1rem 3rem; border: 2px solid var(--border);
    border-radius: var(--radius-md); font-family: inherit; font-size: 0.95rem;
    background: var(--bg-secondary); transition: all 0.3s; color: var(--text-primary);
}
.form-input-premium:focus { outline: none; border-color: var(--primary); background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.form-input-premium::placeholder { color: transparent; }
.input-icon-premium { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 1.1rem; pointer-events: none; transition: color 0.3s; }
.form-input-premium:focus ~ .input-icon-premium { color: var(--primary); }
.floating-label-premium {
    position: absolute; left: 3rem; top: 50%; transform: translateY(-50%);
    color: var(--text-muted); font-size: 0.95rem; pointer-events: none;
    transition: all 0.25s; background: var(--bg-secondary); padding: 0 0.35rem;
}
.form-input-premium:focus ~ .floating-label-premium,
.form-input-premium:not(:placeholder-shown) ~ .floating-label-premium {
    top: 0; font-size: 0.75rem; color: var(--primary); font-weight: 600;
}
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

/* Time inputs - no icon */
.form-input-premium.time-input { padding-left: 1rem; }
.form-input-premium.time-input ~ .floating-label-premium { left: 1rem; }

/* Autocomplete dropdown */
.autocomplete-list {
    position: absolute; top: 100%; left: 0; right: 0; margin-top: 0.25rem;
    background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg); max-height: 200px; overflow-y: auto; z-index: 50; display: none;
}
.autocomplete-list.show { display: block; }
.autocomplete-item { padding: 0.6rem 1rem; cursor: pointer; transition: background 0.15s; font-size: 0.9rem; }
.autocomplete-item:hover { background: var(--bg-secondary); }

/* Rich Text Toolbar */
.rich-toolbar {
    display: flex; flex-wrap: wrap; gap: 0.25rem; padding: 0.5rem;
    background: var(--bg-tertiary); border: 1px solid var(--border);
    border-bottom: none; border-radius: var(--radius-md) var(--radius-md) 0 0;
}
.rich-btn {
    width: 32px; height: 32px; border: none; background: transparent;
    border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 700;
    color: var(--text-secondary); transition: all 0.15s; display: flex; align-items: center; justify-content: center;
}
.rich-btn:hover { background: var(--bg-primary); color: var(--primary); }
.rich-divider { width: 1px; background: var(--border); margin: 0.25rem; }

/* Textarea with counter */
.textarea-wrapper { position: relative; }
.form-textarea-premium {
    width: 100%; padding: 1rem; padding-bottom: 2.5rem; border: 2px solid var(--border);
    border-radius: 0 0 var(--radius-md) var(--radius-md); font-family: inherit; font-size: 0.95rem;
    background: var(--bg-secondary); transition: all 0.3s; min-height: 150px; resize: vertical;
    border-top: none;
}
.form-textarea-premium:focus { outline: none; border-color: var(--primary); background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.char-counter-premium { position: absolute; bottom: 0.75rem; right: 1rem; font-size: 0.75rem; color: var(--text-muted); font-weight: 600; background: var(--bg-primary); padding: 0.2rem 0.5rem; border-radius: 999px; }
.char-counter-premium.warn { color: #f59e0b; }
.char-counter-premium.danger { color: #ef4444; }

/* Smart Suggestions */
.smart-suggestions {
    background: linear-gradient(135deg, #fef3c7, #fde68a); border: 1px solid #fcd34d;
    border-radius: var(--radius-md); padding: 1rem; margin-bottom: 1rem;
    display: none; font-size: 0.85rem; color: #92400e;
}
.smart-suggestions.show { display: block; }
.suggestion-header { font-weight: 700; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem; }
.suggestion-list { list-style: none; padding: 0; margin: 0; }
.suggestion-item {
    padding: 0.4rem 0; cursor: pointer; transition: all 0.15s;
    display: flex; align-items: center; gap: 0.5rem;
}
.suggestion-item:hover { color: #78350f; transform: translateX(4px); }
.suggestion-item::before { content: '💡'; }

/* Duration Calculator */
.duration-calc { background: linear-gradient(135deg, #f0fdf4, #dcfce7); border: 1px solid #86efac; border-radius: var(--radius-md); padding: 1rem; margin-top: 1rem; display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; color: #166534; font-weight: 600; }
.duration-calc .duration-icon { font-size: 1.5rem; }

/* Conflict Warning */
.conflict-warning { background: linear-gradient(135deg, #fef3c7, #fde68a); border: 1px solid #fcd34d; border-radius: var(--radius-md); padding: 1rem; margin-top: 1rem; display: none; align-items: flex-start; gap: 0.75rem; font-size: 0.85rem; color: #92400e; }
.conflict-warning.show { display: flex; }
.conflict-icon { font-size: 1.5rem; flex-shrink: 0; }
.conflict-list { margin-top: 0.5rem; padding-left: 1.25rem; font-size: 0.8rem; }

/* Status Selector */
.status-selector { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; }
.status-option { position: relative; }
.status-option input { position: absolute; opacity: 0; pointer-events: none; }
.status-option label {
    display: flex; align-items: center; justify-content: center; gap: 0.5rem;
    padding: 0.85rem; background: var(--bg-secondary); border: 2px solid var(--border);
    border-radius: var(--radius-md); cursor: pointer; transition: all 0.3s; font-weight: 600; font-size: 0.85rem;
}
.status-option label:hover { transform: translateY(-2px); }
.status-option input:checked + label.status-aktif { background: #dcfce7; border-color: #10b981; color: #166534; }
.status-option input:checked + label.status-selesai { background: #dbeafe; border-color: #3b82f6; color: #1e40af; }
.status-option input:checked + label.status-dibatalkan { background: #fee2e2; border-color: #ef4444; color: #991b1b; }

/* Reminder Selector */
.reminder-selector { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.reminder-option { position: relative; }
.reminder-option input { position: absolute; opacity: 0; pointer-events: none; }
.reminder-option label {
    display: block; padding: 0.6rem 1rem; background: var(--bg-secondary); border: 2px solid var(--border);
    border-radius: 999px; cursor: pointer; transition: all 0.2s; font-size: 0.8rem; font-weight: 600;
}
.reminder-option input:checked + label { background: var(--primary); color: white; border-color: var(--primary); }

/* Recurring Section */
.recurring-section { 
    background: linear-gradient(135deg, var(--bg-secondary), var(--bg-tertiary)); 
    border: 1px solid var(--border); border-radius: var(--radius-md); 
    padding: 1.25rem; margin-top: 1rem;
}
.recurring-toggle { display: flex; align-items: center; gap: 0.75rem; cursor: pointer; }
.recurring-toggle input { width: 18px; height: 18px; accent-color: var(--primary); }
.recurring-options { display: none; margin-top: 1rem; gap: 1rem; grid-template-columns: 1fr 1fr; }
.recurring-options.show { display: grid; }

/* Tags Input */
.tags-input-wrapper {
    display: flex; flex-wrap: wrap; gap: 0.4rem; padding: 0.5rem;
    border: 2px solid var(--border); border-radius: var(--radius-md);
    background: var(--bg-secondary); min-height: 48px; cursor: text;
    transition: all 0.3s;
}
.tags-input-wrapper:focus-within { border-color: var(--primary); background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.tag-chip {
    display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.3rem 0.6rem;
    background: var(--primary); color: white; border-radius: 999px;
    font-size: 0.75rem; font-weight: 600;
}
.tag-remove { cursor: pointer; opacity: 0.7; font-size: 1rem; line-height: 1; }
.tag-remove:hover { opacity: 1; }
.tags-input {
    border: none; outline: none; background: transparent; flex: 1; min-width: 100px;
    font-family: inherit; font-size: 0.9rem; color: var(--text-primary);
}

/* Attachments */
.attachments-area {
    border: 2px dashed var(--border); border-radius: var(--radius-md);
    padding: 1.5rem; text-align: center; cursor: pointer; transition: all 0.2s;
    background: var(--bg-secondary);
}
.attachments-area:hover { border-color: var(--primary); background: rgba(10,104,71,0.03); }
.attachments-area.dragover { border-color: var(--primary); background: rgba(10,104,71,0.08); }
.attachments-area input { display: none; }
.attachments-list { margin-top: 1rem; display: flex; flex-direction: column; gap: 0.5rem; }
.attachment-item {
    display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem;
    background: var(--bg-secondary); border: 1px solid var(--border); border-radius: var(--radius-md);
}
.attachment-icon { font-size: 1.5rem; }
.attachment-info { flex: 1; min-width: 0; }
.attachment-name { font-weight: 600; font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.attachment-size { font-size: 0.75rem; color: var(--text-muted); }
.attachment-remove { cursor: pointer; color: #ef4444; padding: 0.25rem 0.5rem; border-radius: 4px; }
.attachment-remove:hover { background: #fee2e2; }

/* Budget Field */
.budget-wrapper { position: relative; }
.budget-prefix {
    position: absolute; left: 1rem; top: 50%; transform: translateY(-50%);
    font-weight: 700; color: var(--text-muted); font-size: 0.9rem; pointer-events: none;
}
.form-input-premium.budget-input { padding-left: 3rem; }

/* Preview Card */
.preview-card-premium { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); position: sticky; top: 100px; }
.preview-header { margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.preview-header h3 { font-family: var(--font-display); font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem; }
.preview-actions { display: flex; gap: 0.5rem; }
.preview-action-btn {
    width: 36px; height: 36px; border: 1px solid var(--border); border-radius: 8px;
    background: var(--bg-secondary); cursor: pointer; transition: all 0.2s;
    display: flex; align-items: center; justify-content: center; font-size: 1rem;
}
.preview-action-btn:hover { background: var(--primary); color: white; border-color: var(--primary); transform: translateY(-2px); }

.preview-badge { display: inline-block; padding: 0.3rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 1rem; }
.preview-priority { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.75rem; }
.preview-title { font-family: var(--font-display); font-size: 1.5rem; font-weight: 800; margin-bottom: 1rem; line-height: 1.3; min-height: 3rem; }
.preview-meta { display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.5rem; }
.preview-meta-item { display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; color: var(--text-secondary); }
.preview-meta-item strong { color: var(--text-primary); font-weight: 600; }
.preview-desc { background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius-md); font-size: 0.9rem; line-height: 1.6; color: var(--text-secondary); min-height: 80px; border-left: 3px solid var(--primary); }
.preview-empty { color: var(--text-muted); font-style: italic; }
.preview-tags { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 1rem; }
.preview-tag { padding: 0.2rem 0.6rem; background: var(--bg-tertiary); border-radius: 999px; font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); }

/* Submit Button */
.submit-btn-premium {
    width: 100%; padding: 1.1rem; background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; border: none; border-radius: var(--radius-md); font-family: inherit;
    font-size: 1rem; font-weight: 700; cursor: pointer; transition: all 0.3s;
    display: flex; align-items: center; justify-content: center; gap: 0.5rem; position: relative; overflow: hidden;
}
.submit-btn-premium:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(10,104,71,0.35); }
.submit-btn-premium:disabled { opacity: 0.7; cursor: not-allowed; }
.submit-btn-premium .spinner { width: 20px; height: 20px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.7s linear infinite; display: none; }
.submit-btn-premium.loading .spinner { display: inline-block; }
.submit-btn-premium.loading .btn-text { display: none; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Secondary Actions */
.secondary-actions { display: flex; gap: 0.75rem; margin-top: 1rem; flex-wrap: wrap; }
.secondary-btn {
    flex: 1; padding: 0.75rem; background: var(--bg-secondary); color: var(--text-primary);
    border: 1px solid var(--border); border-radius: var(--radius-md); font-family: inherit;
    font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
    display: flex; align-items: center; justify-content: center; gap: 0.4rem;
    text-decoration: none;
}
.secondary-btn:hover { background: var(--bg-tertiary); border-color: var(--primary); color: var(--primary); transform: translateY(-1px); }

/* Autosave indicator */
.autosave-indicator { position: fixed; bottom: 2rem; right: 2rem; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 999px; padding: 0.75rem 1.25rem; box-shadow: var(--shadow-lg); display: flex; align-items: center; gap: 0.75rem; font-size: 0.85rem; font-weight: 600; opacity: 0; transform: translateY(20px); transition: all 0.3s; z-index: 100; }
.autosave-indicator.show { opacity: 1; transform: translateY(0); }
.autosave-indicator.saving { background: #fef3c7; border-color: #fcd34d; color: #92400e; }
.autosave-indicator.saved { background: #dcfce7; border-color: #86efac; color: #166534; }

/* Share Modal */
.share-modal-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.75); backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center; z-index: 10001; padding: 2rem; }
.share-modal-overlay.show { display: flex; }
.share-modal-box { background: var(--bg-primary); border-radius: var(--radius-xl); padding: 2rem; max-width: 450px; width: 100%; box-shadow: 0 30px 60px rgba(0,0,0,0.3); }
.share-qr-wrap { display: flex; justify-content: center; margin: 1.5rem 0; padding: 1.5rem; background: white; border-radius: var(--radius-md); }
.share-url-input {
    width: 100%; padding: 0.75rem; border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: monospace; font-size: 0.85rem; background: var(--bg-secondary);
}

/* Responsive */
@media (max-width: 968px) {
    .agenda-form-layout { grid-template-columns: 1fr; }
    .preview-card-premium { position: static; order: -1; }
    .type-selector { grid-template-columns: repeat(2, 1fr); }
    .priority-selector { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
    .form-card-premium, .preview-card-premium { padding: 1.5rem; }
    .status-selector, .form-row { grid-template-columns: 1fr; }
    .recurring-options { grid-template-columns: 1fr; }
}
</style>

<!-- Progress Indicator -->
<div class="progress-indicator" data-aos="fade-down">
    <span style="font-size: 1.25rem;">📊</span>
    <div class="progress-bar-wrap">
        <div class="progress-bar-fill" id="progressBar" style="width: 0%"></div>
    </div>
    <div class="progress-stats">
        <span class="progress-percent" id="progressPercent">0%</span>
        <span class="progress-label">Kelengkapan</span>
    </div>
</div>

<div class="agenda-form-layout">
    <!-- ===== LEFT: FORM ===== -->
    <div class="form-card-premium" data-aos="fade-right">
        <div class="form-header-premium">
            <h2>
                <?= $edit ? '✏️ Edit Agenda' : '➕ Agenda Baru' ?>
                <?php if (!empty($metadata['version'])): ?>
                    <span class="version-badge">v<?= $metadata['version'] ?></span>
                <?php endif; ?>
            </h2>
            <p><?= $edit ? 'Perbarui detail kegiatan di bawah ini.' : 'Isi detail kegiatan untuk ditambahkan ke kalender.' ?></p>
        </div>

        <form method="POST" id="agendaForm" novalidate enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

            <!-- Smart Suggestions -->
            <div class="smart-suggestions" id="smartSuggestions">
                <div class="suggestion-header">💡 Saran Cerdas</div>
                <ul class="suggestion-list" id="suggestionList"></ul>
            </div>

            <!-- Judul -->
            <div class="form-group-premium">
                <div class="input-wrapper-premium">
                    <input type="text" id="judul" name="judul" class="form-input-premium" placeholder=" " required maxlength="255" value="<?= sanitize($edit['judul'] ?? '') ?>">
                    <span class="input-icon-premium">📝</span>
                    <label class="floating-label-premium" for="judul">Judul Kegiatan *</label>
                </div>
            </div>

            <div class="form-section-divider">🎯 Klasifikasi</div>

            <!-- Jenis Agenda -->
            <div class="form-group-premium">
                <label class="form-label" style="margin-bottom: 0.75rem;">Jenis Agenda *</label>
                <div class="type-selector">
                    <div class="type-option">
                        <input type="radio" name="jenis" id="jenis-ujian" value="ujian" <?= ($edit['jenis'] ?? '') === 'ujian' ? 'checked' : '' ?>>
                        <label for="jenis-ujian"><span class="type-icon">📝</span><span class="type-name">Ujian</span></label>
                    </div>
                    <div class="type-option">
                        <input type="radio" name="jenis" id="jenis-seminar" value="seminar" <?= ($edit['jenis'] ?? '') === 'seminar' ? 'checked' : '' ?>>
                        <label for="jenis-seminar"><span class="type-icon">🎤</span><span class="type-name">Seminar</span></label>
                    </div>
                    <div class="type-option">
                        <input type="radio" name="jenis" id="jenis-wisuda" value="wisuda" <?= ($edit['jenis'] ?? '') === 'wisuda' ? 'checked' : '' ?>>
                        <label for="jenis-wisuda"><span class="type-icon">🎓</span><span class="type-name">Wisuda</span></label>
                    </div>
                    <div class="type-option">
                        <input type="radio" name="jenis" id="jenis-libur" value="libur" <?= ($edit['jenis'] ?? '') === 'libur' ? 'checked' : '' ?>>
                        <label for="jenis-libur"><span class="type-icon">🏖️</span><span class="type-name">Libur</span></label>
                    </div>
                    <div class="type-option">
                        <input type="radio" name="jenis" id="jenis-pmb" value="pmb" <?= ($edit['jenis'] ?? '') === 'pmb' ? 'checked' : '' ?>>
                        <label for="jenis-pmb"><span class="type-icon">📋</span><span class="type-name">PMB</span></label>
                    </div>
                    <div class="type-option">
                        <input type="radio" name="jenis" id="jenis-umum" value="umum" <?= ($edit['jenis'] ?? 'umum') === 'umum' ? 'checked' : '' ?>>
                        <label for="jenis-umum"><span class="type-icon">📌</span><span class="type-name">Umum</span></label>
                    </div>
                </div>
            </div>

            <!-- Priority -->
            <div class="form-group-premium">
                <label class="form-label" style="margin-bottom: 0.75rem;">⚡ Prioritas</label>
                <div class="priority-selector">
                    <div class="priority-option">
                        <input type="radio" name="priority" id="priority-low" value="low" <?= ($metadata['priority'] ?? 'normal') === 'low' ? 'checked' : '' ?>>
                        <label for="priority-low" class="priority-low">🟢 Rendah</label>
                    </div>
                    <div class="priority-option">
                        <input type="radio" name="priority" id="priority-normal" value="normal" <?= ($metadata['priority'] ?? 'normal') === 'normal' ? 'checked' : '' ?>>
                        <label for="priority-normal" class="priority-normal">🔵 Normal</label>
                    </div>
                    <div class="priority-option">
                        <input type="radio" name="priority" id="priority-high" value="high" <?= ($metadata['priority'] ?? '') === 'high' ? 'checked' : '' ?>>
                        <label for="priority-high" class="priority-high">🟠 Tinggi</label>
                    </div>
                    <div class="priority-option">
                        <input type="radio" name="priority" id="priority-urgent" value="urgent" <?= ($metadata['priority'] ?? '') === 'urgent' ? 'checked' : '' ?>>
                        <label for="priority-urgent" class="priority-urgent">🔴 Urgent</label>
                    </div>
                </div>
            </div>

            <!-- Quick Templates -->
            <div class="template-section">
                <div class="template-label">⚡ Template Cepat & Duplikasi</div>
                <div class="template-buttons">
                    <button type="button" class="template-btn" onclick="applyTemplate('uts')">📝 UTS</button>
                    <button type="button" class="template-btn" onclick="applyTemplate('uas')">📝 UAS</button>
                    <button type="button" class="template-btn" onclick="applyTemplate('wisuda')">🎓 Wisuda</button>
                    <button type="button" class="template-btn" onclick="applyTemplate('seminar')">🎤 Seminar</button>
                    <button type="button" class="template-btn" onclick="applyTemplate('libur')">🏖️ Libur</button>
                    <button type="button" class="template-btn" onclick="applyTemplate('pmb')">📋 PMB</button>
                    <div class="duplicate-dropdown">
                        <button type="button" class="duplicate-btn" onclick="toggleDuplicateMenu()">📋 Duplikasi dari...</button>
                        <div class="duplicate-menu" id="duplicateMenu">
                            <?php
                            $recent_agendas = $pdo->query("SELECT id, judul, tanggal_mulai FROM agenda ORDER BY id DESC LIMIT 10")->fetchAll();
                            foreach ($recent_agendas as $ra):
                            ?>
                            <a href="?duplicate=<?= $ra['id'] ?>" class="duplicate-item" onclick="return confirm('Duplikasi agenda ini?')">
                                <span class="duplicate-item-title"><?= sanitize($ra['judul']) ?></span>
                                <span class="duplicate-item-date"><?= date('d M Y', strtotime($ra['tanggal_mulai'])) ?></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section-divider">📅 Waktu & Lokasi</div>

            <!-- Tanggal + Jam -->
            <div class="form-row">
                <div class="form-group-premium">
                    <div class="input-wrapper-premium">
                        <input type="date" id="tanggal_mulai" name="tanggal_mulai" class="form-input-premium" placeholder=" " required value="<?= $edit['tanggal_mulai'] ?? '' ?>" style="padding-left: 1rem;">
                        <label class="floating-label-premium" for="tanggal_mulai" style="left: 1rem;">Tanggal Mulai *</label>
                    </div>
                </div>
                <div class="form-group-premium">
                    <div class="input-wrapper-premium">
                        <input type="time" id="time_start" name="time_start" class="form-input-premium time-input" value="<?= sanitize($metadata['time_start'] ?? '') ?>">
                        <label class="floating-label-premium" for="time_start">Jam Mulai</label>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group-premium">
                    <div class="input-wrapper-premium">
                        <input type="date" id="tanggal_selesai" name="tanggal_selesai" class="form-input-premium" placeholder=" " value="<?= $edit['tanggal_selesai'] ?? '' ?>" style="padding-left: 1rem;">
                        <label class="floating-label-premium" for="tanggal_selesai" style="left: 1rem;">Tanggal Selesai</label>
                    </div>
                </div>
                <div class="form-group-premium">
                    <div class="input-wrapper-premium">
                        <input type="time" id="time_end" name="time_end" class="form-input-premium time-input" value="<?= sanitize($metadata['time_end'] ?? '') ?>">
                        <label class="floating-label-premium" for="time_end">Jam Selesai</label>
                    </div>
                </div>
            </div>

            <!-- Duration Calculator -->
            <div class="duration-calc" id="durationCalc" style="display: none;">
                <span class="duration-icon">⏱️</span>
                <span id="durationText">Durasi: -</span>
            </div>

            <!-- Conflict Warning -->
            <div class="conflict-warning" id="conflictWarning">
                <span class="conflict-icon">⚠️</span>
                <div>
                    <strong>Ada agenda lain di tanggal ini!</strong>
                    <ul class="conflict-list" id="conflictList"></ul>
                </div>
            </div>

            <!-- Recurring -->
            <div class="recurring-section">
                <label class="recurring-toggle">
                    <input type="checkbox" id="enableRecurring" <?= ($metadata['recurring'] ?? 'none') !== 'none' ? 'checked' : '' ?>>
                    <span>🔁 Buat agenda berulang</span>
                </label>
                <div class="recurring-options <?= ($metadata['recurring'] ?? 'none') !== 'none' ? 'show' : '' ?>" id="recurringOptions">
                    <div class="form-group-premium" style="margin-bottom: 0;">
                        <select name="recurring" id="recurring" class="form-input-premium" style="padding-left: 1rem;">
                            <option value="none">Tidak berulang</option>
                            <option value="daily" <?= ($metadata['recurring'] ?? '') === 'daily' ? 'selected' : '' ?>>Harian</option>
                            <option value="weekly" <?= ($metadata['recurring'] ?? '') === 'weekly' ? 'selected' : '' ?>>Mingguan</option>
                            <option value="monthly" <?= ($metadata['recurring'] ?? '') === 'monthly' ? 'selected' : '' ?>>Bulanan</option>
                            <option value="yearly" <?= ($metadata['recurring'] ?? '') === 'yearly' ? 'selected' : '' ?>>Tahunan</option>
                        </select>
                    </div>
                    <div class="form-group-premium" style="margin-bottom: 0;">
                        <input type="date" name="recurring_end" id="recurring_end" class="form-input-premium" style="padding-left: 1rem;" value="<?= sanitize($metadata['recurring_end'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Lokasi dengan Autocomplete -->
            <div class="form-group-premium" style="position: relative;">
                <div class="input-wrapper-premium">
                    <input type="text" id="lokasi" name="lokasi" class="form-input-premium" placeholder=" " maxlength="255" value="<?= sanitize($edit['lokasi'] ?? '') ?>" autocomplete="off">
                    <span class="input-icon-premium">📍</span>
                    <label class="floating-label-premium" for="lokasi">Lokasi Kegiatan</label>
                    <div class="autocomplete-list" id="lokasiAutocomplete">
                        <?php foreach ($recent_locations as $loc): ?>
                        <div class="autocomplete-item" onclick="selectLocation('<?= addslashes($loc) ?>')"><?= sanitize($loc) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Venue Capacity + Budget -->
            <div class="form-row">
                <div class="form-group-premium">
                    <div class="input-wrapper-premium">
                        <input type="number" id="venue_capacity" name="venue_capacity" class="form-input-premium" placeholder=" " min="0" value="<?= (int)($metadata['venue_capacity'] ?? 0) ?>" style="padding-left: 3rem;">
                        <span class="input-icon-premium">👥</span>
                        <label class="floating-label-premium" for="venue_capacity">Kapasitas Venue</label>
                    </div>
                </div>
                <div class="form-group-premium">
                    <div class="input-wrapper-premium budget-wrapper">
                        <span class="budget-prefix">Rp</span>
                        <input type="number" id="budget" name="budget" class="form-input-premium budget-input" placeholder=" " min="0" value="<?= (float)($metadata['budget'] ?? 0) ?>">
                        <label class="floating-label-premium" for="budget" style="left: 3.5rem;">Estimasi Anggaran</label>
                    </div>
                </div>
            </div>

            <div class="form-section-divider">📄 Detail & Lampiran</div>

            <!-- Deskripsi dengan Rich Toolbar -->
            <div class="form-group-premium">
                <div class="rich-toolbar">
                    <button type="button" class="rich-btn" onclick="insertMarkdown('**', '**')" title="Bold"><b>B</b></button>
                    <button type="button" class="rich-btn" onclick="insertMarkdown('*', '*')" title="Italic"><i>I</i></button>
                    <button type="button" class="rich-btn" onclick="insertMarkdown('__', '__')" title="Underline"><u>U</u></button>
                    <div class="rich-divider"></div>
                    <button type="button" class="rich-btn" onclick="insertMarkdown('\n• ', '')" title="Bullet list">•</button>
                    <button type="button" class="rich-btn" onclick="insertMarkdown('\n1. ', '')" title="Numbered list">1.</button>
                    <div class="rich-divider"></div>
                    <button type="button" class="rich-btn" onclick="insertMarkdown('## ', '')" title="Heading">H2</button>
                    <button type="button" class="rich-btn" onclick="insertMarkdown('### ', '')" title="Subheading">H3</button>
                    <div class="rich-divider"></div>
                    <button type="button" class="rich-btn" onclick="insertMarkdown('[', '](url)')" title="Link">🔗</button>
                    <button type="button" class="rich-btn" onclick="insertMarkdown('\n> ', '')" title="Quote">❝</button>
                </div>
                <div class="textarea-wrapper">
                    <textarea id="deskripsi" name="deskripsi" class="form-textarea-premium" placeholder=" " maxlength="5000"><?= sanitize($edit['deskripsi'] ?? '') ?></textarea>
                    <span class="char-counter-premium" id="charCounter">0/5000</span>
                </div>
            </div>

            <!-- Tags -->
            <div class="form-group-premium">
                <label class="form-label" style="margin-bottom: 0.5rem;">🏷️ Tags (tekan Enter untuk menambah)</label>
                <div class="tags-input-wrapper" id="tagsWrapper" onclick="document.getElementById('tagsInput').focus()">
                    <input type="text" class="tags-input" id="tagsInput" placeholder="Tambah tag...">
                    <input type="hidden" name="tags" id="tagsHidden" value="<?= sanitize($metadata['tags'] ?? '') ?>">
                </div>
            </div>

            <!-- Participants -->
            <div class="form-group-premium">
                <div class="input-wrapper-premium">
                    <input type="text" id="participants" name="participants" class="form-input-premium" placeholder=" " value="<?= sanitize($metadata['participants'] ?? '') ?>">
                    <span class="input-icon-premium">👥</span>
                    <label class="floating-label-premium" for="participants">Peserta / Penanggung Jawab</label>
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.3rem;">💡 Pisahkan dengan koma (mis: Budi, Siti, Andi)</div>
            </div>

            <!-- Attachments -->
            <div class="form-group-premium">
                <label class="form-label" style="margin-bottom: 0.5rem;">📎 Lampiran</label>
                <div class="attachments-area" id="attachmentsArea">
                    <input type="file" id="attachmentsInput" name="attachments[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">📤</div>
                    <div style="font-weight: 600;">Klik atau drag file ke sini</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">PDF, DOC, XLS, JPG, PNG, ZIP (max 5MB/file)</div>
                </div>
                <div class="attachments-list" id="attachmentsList">
                    <?php if (!empty($metadata['attachments'])): foreach ($metadata['attachments'] as $att): ?>
                    <div class="attachment-item" data-file="<?= sanitize($att['file']) ?>">
                        <span class="attachment-icon">📄</span>
                        <div class="attachment-info">
                            <div class="attachment-name"><?= sanitize($att['name']) ?></div>
                            <div class="attachment-size"><?= number_format($att['size'] / 1024, 1) ?> KB</div>
                        </div>
                        <a href="<?= base_url('uploads/agenda/' . $att['file']) ?>" target="_blank" style="color: var(--primary); text-decoration: none; padding: 0.25rem 0.5rem;">👁️</a>
                        <label style="cursor: pointer; color: #ef4444; padding: 0.25rem 0.5rem; display: flex; align-items: center;">
                            <input type="checkbox" name="delete_attachments[]" value="<?= sanitize($att['file']) ?>" style="margin-right: 0.3rem;">
                            Hapus
                        </label>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <div class="form-section-divider">⚙️ Status & Notifikasi</div>

            <!-- Status -->
            <div class="form-group-premium">
                <label class="form-label" style="margin-bottom: 0.75rem;">📊 Status *</label>
                <div class="status-selector">
                    <div class="status-option">
                        <input type="radio" name="status" id="status-aktif" value="Aktif" <?= ($edit['status'] ?? 'Aktif') === 'Aktif' ? 'checked' : '' ?>>
                        <label for="status-aktif" class="status-aktif">✅ Aktif</label>
                    </div>
                    <div class="status-option">
                        <input type="radio" name="status" id="status-selesai" value="Selesai" <?= ($edit['status'] ?? '') === 'Selesai' ? 'checked' : '' ?>>
                        <label for="status-selesai" class="status-selesai">✔️ Selesai</label>
                    </div>
                    <div class="status-option">
                        <input type="radio" name="status" id="status-dibatalkan" value="Dibatalkan" <?= ($edit['status'] ?? '') === 'Dibatalkan' ? 'checked' : '' ?>>
                        <label for="status-dibatalkan" class="status-dibatalkan">❌ Dibatalkan</label>
                    </div>
                </div>
            </div>

            <!-- Reminder + Public -->
            <div class="form-row">
                <div class="form-group-premium">
                    <label class="form-label" style="margin-bottom: 0.75rem;">🔔 Pengingat</label>
                    <div class="reminder-selector">
                        <div class="reminder-option">
                            <input type="radio" name="reminder" id="reminder-0" value="0" <?= ($edit['reminder'] ?? 0) == 0 ? 'checked' : '' ?>>
                            <label for="reminder-0">Tidak</label>
                        </div>
                        <div class="reminder-option">
                            <input type="radio" name="reminder" id="reminder-1" value="1" <?= ($edit['reminder'] ?? 0) == 1 ? 'checked' : '' ?>>
                            <label for="reminder-1">1 hari</label>
                        </div>
                        <div class="reminder-option">
                            <input type="radio" name="reminder" id="reminder-3" value="3" <?= ($edit['reminder'] ?? 0) == 3 ? 'checked' : '' ?>>
                            <label for="reminder-3">3 hari</label>
                        </div>
                        <div class="reminder-option">
                            <input type="radio" name="reminder" id="reminder-7" value="7" <?= ($edit['reminder'] ?? 0) == 7 ? 'checked' : '' ?>>
                            <label for="reminder-7">1 minggu</label>
                        </div>
                    </div>
                </div>
                <div class="form-group-premium">
                    <label class="form-label" style="margin-bottom: 0.75rem;">🌐 Visibilitas</label>
                    <div class="reminder-selector">
                        <div class="reminder-option">
                            <input type="radio" name="is_public" id="is_public-1" value="1" <?= ($metadata['is_public'] ?? 0) == 1 ? 'checked' : '' ?>>
                            <label for="is_public-1">🌍 Publik</label>
                        </div>
                        <div class="reminder-option">
                            <input type="radio" name="is_public" id="is_public-0" value="0" <?= ($metadata['is_public'] ?? 0) == 0 ? 'checked' : '' ?>>
                            <label for="is_public-0">🔒 Internal</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit -->
            <button type="submit" class="submit-btn-premium" id="submitBtn">
                <span class="btn-text"><?= $edit ? '💾 Perbarui Agenda' : '✨ Simpan Agenda' ?></span>
                <span class="spinner"></span>
            </button>

            <div class="secondary-actions">
                <?php if ($edit && $edit['id']): ?>
                <a href="?ics=1&id=<?= $edit['id'] ?>" class="secondary-btn">📥 Download ICS</a>
                <button type="button" class="secondary-btn" onclick="openShareModal()">🔗 Bagikan</button>
                <a href="?duplicate=<?= $edit['id'] ?>" class="secondary-btn" onclick="return confirm('Duplikasi agenda ini?')">📋 Duplikasi</a>
                <?php endif; ?>
                <a href="agenda.php" class="secondary-btn">← Kembali</a>
            </div>

            <p style="text-align: center; margin-top: 1rem; font-size: 0.78rem; color: var(--text-muted);">
                💡 Shortcuts: <kbd style="background: var(--bg-secondary); padding: 0.15rem 0.4rem; border-radius: 4px; font-family: monospace;">Ctrl+S</kbd> Simpan • 
                <kbd style="background: var(--bg-secondary); padding: 0.15rem 0.4rem; border-radius: 4px; font-family: monospace;">Ctrl+K</kbd> Template •
                <kbd style="background: var(--bg-secondary); padding: 0.15rem 0.4rem; border-radius: 4px; font-family: monospace;">Ctrl+D</kbd> Duplikasi
            </p>
        </form>
    </div>

    <!-- ===== RIGHT: LIVE PREVIEW ===== -->
    <div class="preview-card-premium" data-aos="fade-left">
        <div class="preview-header">
            <h3>👁️ Live Preview</h3>
            <div class="preview-actions">
                <button class="preview-action-btn" onclick="togglePreviewMode()" title="Toggle mode">🔄</button>
                <?php if ($edit && $edit['id']): ?>
                <a href="agenda.php" class="preview-action-btn" title="Lihat di list" target="_blank">📋</a>
                <?php endif; ?>
            </div>
        </div>

        <div id="previewContent">
            <div class="preview-priority" id="previewPriority" style="background: #dbeafe; color: #1e40af;">🔵 NORMAL</div>
            <div class="preview-badge" id="previewBadge" style="background: #f3f4f6; color: #4b5563;">📌 UMUM</div>
            <h2 class="preview-title" id="previewTitle">Judul agenda akan muncul di sini...</h2>
            
            <div class="preview-meta">
                <div class="preview-meta-item">
                    <span>📅</span>
                    <strong id="previewDate">-</strong>
                </div>
                <div class="preview-meta-item">
                    <span>🕐</span>
                    <strong id="previewTime">-</strong>
                </div>
                <div class="preview-meta-item">
                    <span>📍</span>
                    <strong id="previewLocation">Belum diisi</strong>
                </div>
                <div class="preview-meta-item">
                    <span>⏱️</span>
                    <strong id="previewDuration">-</strong>
                </div>
                <div class="preview-meta-item">
                    <span>👥</span>
                    <strong id="previewParticipants">-</strong>
                </div>
                <div class="preview-meta-item">
                    <span>💰</span>
                    <strong id="previewBudget">-</strong>
                </div>
                <div class="preview-meta-item">
                    <span>📊</span>
                    <strong id="previewStatus">Aktif</strong>
                </div>
            </div>

            <div class="preview-desc" id="previewDesc">
                <span class="preview-empty">Deskripsi akan muncul di sini...</span>
            </div>

            <div class="preview-tags" id="previewTags"></div>
        </div>
    </div>
</div>

<!-- Autosave Indicator -->
<div class="autosave-indicator" id="autosaveIndicator">
    <span id="autosaveIcon">💾</span>
    <span id="autosaveText">Menyimpan...</span>
</div>

<!-- Share Modal -->
<div class="share-modal-overlay" id="shareModal" onclick="if(event.target===this)closeShareModal()">
    <div class="share-modal-box">
        <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">🔗 Bagikan Agenda</h3>
        <div class="share-qr-wrap">
            <div id="qrCode" style="width: 200px; height: 200px; display: flex; align-items: center; justify-content: center; background: #f3f4f6; border-radius: 8px; color: #6b7280;">QR Code</div>
        </div>
        <input type="text" class="share-url-input" id="shareUrl" readonly>
        <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
            <button onclick="copyShareUrl()" class="secondary-btn" style="flex: 1;">📋 Copy URL</button>
            <button onclick="shareWhatsApp()" class="secondary-btn" style="flex: 1; background: #25D366; color: white; border-color: #25D366;">💬 WhatsApp</button>
        </div>
        <button onclick="closeShareModal()" class="secondary-btn" style="width: 100%; margin-top: 0.5rem;">Tutup</button>
    </div>
</div>

<script>
// ===== DATA =====
const existingAttachments = <?= json_encode($metadata['attachments'] ?? []) ?>;
let newAttachments = [];

// ===== TEMPLATES =====
const templates = {
    uts: { judul: 'Ujian Tengah Semester (UTS) Ganjil', jenis: 'ujian', deskripsi: '**Pelaksanaan Ujian Tengah Semester** untuk seluruh program studi.\n\n• Mahasiswa wajib membawa KTM\n• Mengikuti jadwal yang telah ditentukan\n• Dilarang membawa catatan', priority: 'high' },
    uas: { judul: 'Ujian Akhir Semester (UAS) Ganjil', jenis: 'ujian', deskripsi: '**Pelaksanaan Ujian Akhir Semester**\n\n• Seluruh mahasiswa wajib mengikuti sesuai jadwal\n• Persiapkan diri dengan baik\n• Jaga integritas akademik', priority: 'high' },
    wisuda: { judul: 'Wisuda Periode I', jenis: 'wisuda', deskripsi: '**Upacara Wisuda dan Pelepasan Sarjana Baru FKIP UNIMOF**\n\n🎓 Undangan terbuka untuk keluarga dan tamu undangan\n👔 Dress code: Formal\n📍 Lokasi: Auditorium Utama', priority: 'urgent' },
    seminar: { judul: 'Seminar Nasional Pendidikan', jenis: 'seminar', deskripsi: '**Seminar Nasional** dengan tema pendidikan terkini.\n\n🎤 Narasumber dari berbagai universitas terkemuka\n📝 E-sertifikat untuk peserta\n☕ Coffee break disediakan', priority: 'normal' },
    libur: { judul: 'Libur Antar Semester', jenis: 'libur', deskripsi: '**Masa Libur Antar Semester**\n\n🏖️ Tidak ada kegiatan perkuliahan\n📚 Waktu untuk belajar mandiri\n🎯 Persiapan semester depan', priority: 'low' },
    pmb: { judul: 'Pendaftaran Mahasiswa Baru Gelombang 1', jenis: 'pmb', deskripsi: '**Pembukaan PMB FKIP UNIMOF**\n\n📝 Pendaftaran online & offline\n💰 Biaya pendaftaran: Rp 250.000\n📅 Gelombang 1 dibuka hingga akhir bulan', priority: 'high' }
};

function applyTemplate(key) {
    const t = templates[key];
    if (!t) return;
    document.getElementById('judul').value = t.judul;
    const jenisRadio = document.querySelector(`input[name="jenis"][value="${t.jenis}"]`);
    if (jenisRadio) jenisRadio.checked = true;
    document.getElementById('deskripsi').value = t.deskripsi;
    const priorityRadio = document.querySelector(`input[name="priority"][value="${t.priority}"]`);
    if (priorityRadio) priorityRadio.checked = true;
    updatePreview();
    updateCharCounter();
    updateProgress();
    triggerAutosave();
}

function toggleDuplicateMenu() {
    document.getElementById('duplicateMenu').classList.toggle('show');
}

document.addEventListener('click', (e) => {
    if (!e.target.closest('.duplicate-dropdown')) {
        document.getElementById('duplicateMenu').classList.remove('show');
    }
});

// ===== LIVE PREVIEW =====
const typeConfig = {
    ujian: { icon: '📝', color: '#dc2626', bg: '#fee2e2', label: 'UJIAN' },
    seminar: { icon: '🎤', color: '#2563eb', bg: '#dbeafe', label: 'SEMINAR' },
    wisuda: { icon: '🎓', color: '#d97706', bg: '#fef3c7', label: 'WISUDA' },
    libur: { icon: '🏖️', color: '#4338ca', bg: '#e0e7ff', label: 'LIBUR' },
    pmb: { icon: '📋', color: '#16a34a', bg: '#dcfce7', label: 'PMB' },
    umum: { icon: '📌', color: '#4b5563', bg: '#f3f4f6', label: 'UMUM' }
};

const priorityConfig = {
    low: { icon: '🟢', color: '#166534', bg: '#dcfce7', label: 'RENDAH' },
    normal: { icon: '🔵', color: '#1e40af', bg: '#dbeafe', label: 'NORMAL' },
    high: { icon: '🟠', color: '#92400e', bg: '#fef3c7', label: 'TINGGI' },
    urgent: { icon: '🔴', color: '#991b1b', bg: '#fee2e2', label: 'URGENT' }
};

function renderMarkdown(text) {
    if (!text) return '<span class="preview-empty">Deskripsi akan muncul di sini...</span>';
    return text
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/__(.+?)__/g, '<u>$1</u>')
        .replace(/^### (.+)$/gm, '<h4 style="margin:0.5rem 0 0.25rem;font-size:0.95rem;">$1</h4>')
        .replace(/^## (.+)$/gm, '<h3 style="margin:0.5rem 0 0.25rem;font-size:1.05rem;">$1</h3>')
        .replace(/^> (.+)$/gm, '<blockquote style="border-left:3px solid var(--primary);padding-left:0.75rem;margin:0.5rem 0;font-style:italic;">$1</blockquote>')
        .replace(/\[(.+?)\]\((.+?)\)/g, '<a href="$2" style="color:var(--primary);">$1</a>')
        .replace(/\n/g, '<br>');
}

function updatePreview() {
    const judul = document.getElementById('judul').value || 'Judul agenda akan muncul di sini...';
    const jenis = document.querySelector('input[name="jenis"]:checked')?.value || 'umum';
    const priority = document.querySelector('input[name="priority"]:checked')?.value || 'normal';
    const tglMulai = document.getElementById('tanggal_mulai').value;
    const tglSelesai = document.getElementById('tanggal_selesai').value;
    const timeStart = document.getElementById('time_start').value;
    const timeEnd = document.getElementById('time_end').value;
    const lokasi = document.getElementById('lokasi').value || 'Belum diisi';
    const deskripsi = document.getElementById('deskripsi').value;
    const status = document.querySelector('input[name="status"]:checked')?.value || 'Aktif';
    const participants = document.getElementById('participants').value;
    const budget = parseFloat(document.getElementById('budget').value) || 0;
    const tags = document.getElementById('tagsHidden').value;

    const typeC = typeConfig[jenis];
    const priC = priorityConfig[priority];
    
    const badge = document.getElementById('previewBadge');
    badge.textContent = `${typeC.icon} ${typeC.label}`;
    badge.style.background = typeC.bg;
    badge.style.color = typeC.color;
    
    const priBadge = document.getElementById('previewPriority');
    priBadge.textContent = `${priC.icon} ${priC.label}`;
    priBadge.style.background = priC.bg;
    priBadge.style.color = priC.color;

    document.getElementById('previewTitle').textContent = judul;
    document.getElementById('previewLocation').textContent = lokasi;
    document.getElementById('previewStatus').textContent = status;
    document.getElementById('previewParticipants').textContent = participants || '-';
    document.getElementById('previewBudget').textContent = budget > 0 ? 'Rp ' + budget.toLocaleString('id-ID') : '-';

    if (tglMulai) {
        const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        const d = new Date(tglMulai);
        let dateStr = `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
        if (tglSelesai) {
            const d2 = new Date(tglSelesai);
            dateStr += ` - ${d2.getDate()} ${months[d2.getMonth()]} ${d2.getFullYear()}`;
        }
        document.getElementById('previewDate').textContent = dateStr;
    } else {
        document.getElementById('previewDate').textContent = '-';
    }

    // Time
    let timeStr = '-';
    if (timeStart) {
        timeStr = timeStart;
        if (timeEnd) timeStr += ' - ' + timeEnd;
    }
    document.getElementById('previewTime').textContent = timeStr;

    document.getElementById('previewDesc').innerHTML = renderMarkdown(deskripsi);

    // Tags
    const tagsWrap = document.getElementById('previewTags');
    tagsWrap.innerHTML = '';
    if (tags) {
        tags.split(',').filter(t => t.trim()).forEach(tag => {
            const el = document.createElement('span');
            el.className = 'preview-tag';
            el.textContent = '#' + tag.trim();
            tagsWrap.appendChild(el);
        });
    }
}

// ===== DURATION CALCULATOR =====
function calculateDuration() {
    const mulai = document.getElementById('tanggal_mulai').value;
    const selesai = document.getElementById('tanggal_selesai').value;
    const calc = document.getElementById('durationCalc');
    const text = document.getElementById('durationText');
    const previewDuration = document.getElementById('previewDuration');

    if (!mulai) {
        calc.style.display = 'none';
        previewDuration.textContent = '-';
        return;
    }

    if (!selesai) {
        calc.style.display = 'flex';
        text.textContent = '⏱️ Durasi: 1 hari (sehari)';
        previewDuration.textContent = '1 hari';
        return;
    }

    const start = new Date(mulai);
    const end = new Date(selesai);
    const diff = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;

    if (diff < 0) {
        calc.style.display = 'flex';
        calc.style.background = 'linear-gradient(135deg, #fee2e2, #fecaca)';
        calc.style.borderColor = '#fca5a5';
        calc.style.color = '#991b1b';
        text.textContent = '⚠️ Tanggal selesai harus setelah tanggal mulai!';
        previewDuration.textContent = 'Error';
    } else {
        calc.style.display = 'flex';
        calc.style.background = 'linear-gradient(135deg, #f0fdf4, #dcfce7)';
        calc.style.borderColor = '#86efac';
        calc.style.color = '#166534';
        text.textContent = `⏱️ Durasi: ${diff} hari`;
        previewDuration.textContent = diff + ' hari';
    }
}

// ===== CHARACTER COUNTER =====
function updateCharCounter() {
    const textarea = document.getElementById('deskripsi');
    const counter = document.getElementById('charCounter');
    const len = textarea.value.length;
    counter.textContent = `${len}/5000`;
    counter.classList.toggle('warn', len > 4000 && len <= 4750);
    counter.classList.toggle('danger', len > 4750);
}

// ===== RICH TEXT TOOLBAR =====
function insertMarkdown(before, after) {
    const ta = document.getElementById('deskripsi');
    const start = ta.selectionStart;
    const end = ta.selectionEnd;
    const selected = ta.value.substring(start, end);
    const newText = ta.value.substring(0, start) + before + selected + after + ta.value.substring(end);
    ta.value = newText;
    ta.focus();
    ta.selectionStart = start + before.length;
    ta.selectionEnd = start + before.length + selected.length;
    updatePreview();
    updateCharCounter();
    updateProgress();
}

// ===== TAGS INPUT =====
const tagsInput = document.getElementById('tagsInput');
const tagsWrapper = document.getElementById('tagsWrapper');
const tagsHidden = document.getElementById('tagsHidden');
let currentTags = (tagsHidden.value || '').split(',').map(t => t.trim()).filter(t => t);

function renderTags() {
    tagsWrapper.querySelectorAll('.tag-chip').forEach(el => el.remove());
    currentTags.forEach((tag, i) => {
        const chip = document.createElement('span');
        chip.className = 'tag-chip';
        chip.innerHTML = `#${tag} <span class="tag-remove" onclick="removeTag(${i})">×</span>`;
        tagsWrapper.insertBefore(chip, tagsInput);
    });
    tagsHidden.value = currentTags.join(',');
    updatePreview();
}

function addTag(tag) {
    tag = tag.trim().toLowerCase();
    if (tag && !currentTags.includes(tag) && currentTags.length < 10) {
        currentTags.push(tag);
        renderTags();
        updateProgress();
    }
}

function removeTag(index) {
    currentTags.splice(index, 1);
    renderTags();
    updateProgress();
}

tagsInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        addTag(tagsInput.value);
        tagsInput.value = '';
    } else if (e.key === 'Backspace' && !tagsInput.value && currentTags.length > 0) {
        currentTags.pop();
        renderTags();
    }
});

// ===== AUTOCOMPLETE LOKASI =====
const lokasiInput = document.getElementById('lokasi');
const lokasiList = document.getElementById('lokasiAutocomplete');

lokasiInput.addEventListener('focus', () => {
    if (lokasiList.children.length > 0) lokasiList.classList.add('show');
});
lokasiInput.addEventListener('blur', () => setTimeout(() => lokasiList.classList.remove('show'), 200));

function selectLocation(loc) {
    lokasiInput.value = loc;
    lokasiList.classList.remove('show');
    updatePreview();
    updateProgress();
}

// ===== ATTACHMENTS =====
const attachmentsArea = document.getElementById('attachmentsArea');
const attachmentsInput = document.getElementById('attachmentsInput');
const attachmentsList = document.getElementById('attachmentsList');

attachmentsArea.addEventListener('click', () => attachmentsInput.click());
attachmentsArea.addEventListener('dragover', (e) => { e.preventDefault(); attachmentsArea.classList.add('dragover'); });
attachmentsArea.addEventListener('dragleave', () => attachmentsArea.classList.remove('dragover'));
attachmentsArea.addEventListener('drop', (e) => {
    e.preventDefault();
    attachmentsArea.classList.remove('dragover');
    if (e.dataTransfer.files.length > 0) {
        attachmentsInput.files = e.dataTransfer.files;
        handleAttachments(e.dataTransfer.files);
    }
});
attachmentsInput.addEventListener('change', (e) => handleAttachments(e.target.files));

function handleAttachments(files) {
    Array.from(files).forEach(file => {
        if (file.size > 5 * 1024 * 1024) {
            alert(`File ${file.name} terlalu besar (max 5MB)`);
            return;
        }
        const item = document.createElement('div');
        item.className = 'attachment-item';
        item.innerHTML = `
            <span class="attachment-icon">📄</span>
            <div class="attachment-info">
                <div class="attachment-name">${file.name}</div>
                <div class="attachment-size">${(file.size/1024).toFixed(1)} KB (Baru)</div>
            </div>
            <span class="attachment-remove" onclick="this.parentElement.remove()">×</span>
        `;
        attachmentsList.appendChild(item);
        newAttachments.push(file);
    });
    updateProgress();
}

// ===== RECURRING TOGGLE =====
document.getElementById('enableRecurring').addEventListener('change', function() {
    document.getElementById('recurringOptions').classList.toggle('show', this.checked);
    if (!this.checked) {
        document.getElementById('recurring').value = 'none';
        document.getElementById('recurring_end').value = '';
    }
});

// ===== CONFLICT DETECTOR =====
let conflictTimeout;
function checkConflicts() {
    const tgl = document.getElementById('tanggal_mulai').value;
    if (!tgl) return;
    clearTimeout(conflictTimeout);
    conflictTimeout = setTimeout(async () => {
        try {
            const response = await fetch(`agenda-check-conflict.php?date=${tgl}&exclude=<?= $id ?>`);
            const data = await response.json();
            const warning = document.getElementById('conflictWarning');
            const list = document.getElementById('conflictList');
            if (data.conflicts && data.conflicts.length > 0) {
                list.innerHTML = data.conflicts.map(c => `<li>${c.judul}</li>`).join('');
                warning.classList.add('show');
            } else {
                warning.classList.remove('show');
            }
        } catch (e) {}
    }, 500);
}

// ===== SMART SUGGESTIONS =====
function checkSmartSuggestions() {
    const judul = document.getElementById('judul').value.toLowerCase();
    const jenis = document.querySelector('input[name="jenis"]:checked')?.value;
    const suggestions = [];
    
    if (judul.includes('uts') && jenis !== 'ujian') {
        suggestions.push({ text: 'Sepertinya ini UTS - ubah jenis ke "Ujian"?', action: () => document.querySelector('input[name="jenis"][value="ujian"]').checked = true });
    }
    if (judul.includes('seminar') && jenis !== 'seminar') {
        suggestions.push({ text: 'Sepertinya ini seminar - ubah jenis ke "Seminar"?', action: () => document.querySelector('input[name="jenis"][value="seminar"]').checked = true });
    }
    if (judul.includes('wisuda') && jenis !== 'wisuda') {
        suggestions.push({ text: 'Sepertinya ini wisuda - ubah jenis ke "Wisuda"?', action: () => document.querySelector('input[name="jenis"][value="wisuda"]').checked = true });
    }
    
    const sugBox = document.getElementById('smartSuggestions');
    const sugList = document.getElementById('suggestionList');
    if (suggestions.length > 0) {
        sugList.innerHTML = '';
        suggestions.forEach(s => {
            const li = document.createElement('li');
            li.className = 'suggestion-item';
            li.textContent = s.text;
            li.onclick = () => { s.action(); updatePreview(); sugBox.classList.remove('show'); };
            sugList.appendChild(li);
        });
        sugBox.classList.add('show');
    } else {
        sugBox.classList.remove('show');
    }
}

// ===== PROGRESS INDICATOR =====
function updateProgress() {
    const fields = [
        document.getElementById('judul').value,
        document.querySelector('input[name="jenis"]:checked'),
        document.getElementById('tanggal_mulai').value,
        document.getElementById('lokasi').value,
        document.getElementById('deskripsi').value,
        document.querySelector('input[name="status"]:checked')
    ];
    const filled = fields.filter(f => f && (typeof f === 'string' ? f.trim() : true)).length;
    const percent = Math.round((filled / fields.length) * 100);
    document.getElementById('progressBar').style.width = percent + '%';
    document.getElementById('progressPercent').textContent = percent + '%';
}

// ===== AUTOSAVE =====
const storageKey = 'fkip_agenda_draft_<?= $id ?: "new" ?>';
let autosaveTimer;

function triggerAutosave() {
    clearTimeout(autosaveTimer);
    const indicator = document.getElementById('autosaveIndicator');
    indicator.classList.add('show', 'saving');
    indicator.classList.remove('saved');
    document.getElementById('autosaveIcon').textContent = '⏳';
    document.getElementById('autosaveText').textContent = 'Menyimpan draft...';

    autosaveTimer = setTimeout(() => {
        const data = {
            judul: document.getElementById('judul').value,
            jenis: document.querySelector('input[name="jenis"]:checked')?.value,
            priority: document.querySelector('input[name="priority"]:checked')?.value,
            tanggal_mulai: document.getElementById('tanggal_mulai').value,
            tanggal_selesai: document.getElementById('tanggal_selesai').value,
            time_start: document.getElementById('time_start').value,
            time_end: document.getElementById('time_end').value,
            lokasi: document.getElementById('lokasi').value,
            deskripsi: document.getElementById('deskripsi').value,
            status: document.querySelector('input[name="status"]:checked')?.value,
            participants: document.getElementById('participants').value,
            budget: document.getElementById('budget').value,
            tags: document.getElementById('tagsHidden').value,
            saved_at: new Date().toISOString()
        };
        try {
            localStorage.setItem(storageKey, JSON.stringify(data));
            indicator.classList.remove('saving');
            indicator.classList.add('saved');
            document.getElementById('autosaveIcon').textContent = '✅';
            document.getElementById('autosaveText').textContent = 'Draft tersimpan';
            setTimeout(() => indicator.classList.remove('show'), 2000);
        } catch (e) {}
    }, 1000);
}

// Load autosaved draft
<?php if (!$edit): ?>
(function() {
    try {
        const saved = localStorage.getItem(storageKey);
        if (saved) {
            const data = JSON.parse(saved);
            if (confirm(`Ada draft tersimpan dari ${new Date(data.saved_at).toLocaleString('id-ID')}. Muat draft tersebut?`)) {
                document.getElementById('judul').value = data.judul || '';
                if (data.jenis) { const r = document.querySelector(`input[name="jenis"][value="${data.jenis}"]`); if (r) r.checked = true; }
                if (data.priority) { const r = document.querySelector(`input[name="priority"][value="${data.priority}"]`); if (r) r.checked = true; }
                document.getElementById('tanggal_mulai').value = data.tanggal_mulai || '';
                document.getElementById('tanggal_selesai').value = data.tanggal_selesai || '';
                document.getElementById('time_start').value = data.time_start || '';
                document.getElementById('time_end').value = data.time_end || '';
                document.getElementById('lokasi').value = data.lokasi || '';
                document.getElementById('deskripsi').value = data.deskripsi || '';
                if (data.status) { const r = document.querySelector(`input[name="status"][value="${data.status}"]`); if (r) r.checked = true; }
                document.getElementById('participants').value = data.participants || '';
                document.getElementById('budget').value = data.budget || '';
                if (data.tags) { currentTags = data.tags.split(',').filter(t => t); renderTags(); }
                updatePreview();
                updateCharCounter();
                calculateDuration();
                updateProgress();
            } else {
                localStorage.removeItem(storageKey);
            }
        }
    } catch (e) {}
})();
<?php endif; ?>

// ===== SHARE MODAL =====
function openShareModal() {
    <?php if ($edit && $edit['id']): ?>
    const url = window.location.origin + '/agenda-detail.php?id=<?= $edit['id'] ?>';
    document.getElementById('shareUrl').value = url;
    // Generate QR code (simple placeholder - integrate with QR library in production)
    document.getElementById('qrCode').innerHTML = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(url)}" alt="QR" style="width:100%;height:100%;">`;
    document.getElementById('shareModal').classList.add('show');
    <?php else: ?>
    alert('Simpan agenda terlebih dahulu untuk mendapatkan link berbagi.');
    <?php endif; ?>
}

function closeShareModal() {
    document.getElementById('shareModal').classList.remove('show');
}

function copyShareUrl() {
    const input = document.getElementById('shareUrl');
    input.select();
    document.execCommand('copy');
    alert('URL berhasil disalin!');
}

function shareWhatsApp() {
    const url = document.getElementById('shareUrl').value;
    const judul = document.getElementById('judul').value;
    window.open(`https://wa.me/?text=${encodeURIComponent('📅 ' + judul + '\n' + url)}`, '_blank');
}

function togglePreviewMode() {
    const card = document.querySelector('.preview-card-premium');
    card.style.transform = card.style.transform === 'scale(0.95)' ? 'scale(1)' : 'scale(0.95)';
}

// ===== EVENT LISTENERS =====
['judul', 'tanggal_mulai', 'tanggal_selesai', 'time_start', 'time_end', 'lokasi', 'deskripsi', 'participants', 'budget', 'venue_capacity'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', () => {
        updatePreview();
        triggerAutosave();
        updateProgress();
        if (id === 'judul') checkSmartSuggestions();
    });
});

document.getElementById('tanggal_mulai').addEventListener('change', () => { calculateDuration(); checkConflicts(); });
document.getElementById('tanggal_selesai').addEventListener('change', calculateDuration);
document.getElementById('deskripsi').addEventListener('input', updateCharCounter);
document.querySelectorAll('input[name="jenis"], input[name="priority"], input[name="status"], input[name="is_public"]').forEach(r => r.addEventListener('change', () => { updatePreview(); updateProgress(); }));

// Init
renderTags();
updatePreview();
updateCharCounter();
calculateDuration();
updateProgress();

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('agendaForm').submit();
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const btn = document.querySelector('.template-btn');
        if (btn) btn.click();
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
        e.preventDefault();
        toggleDuplicateMenu();
    }
});

// Form submit
document.getElementById('agendaForm').addEventListener('submit', function(e) {
    const judul = document.getElementById('judul').value.trim();
    const tgl = document.getElementById('tanggal_mulai').value;
    
    if (!judul || !tgl) {
        e.preventDefault();
        alert('Mohon lengkapi field yang wajib diisi (Judul & Tanggal Mulai).');
        return;
    }
    
    document.getElementById('submitBtn').classList.add('loading');
    document.getElementById('submitBtn').disabled = true;
    setTimeout(() => { try { localStorage.removeItem(storageKey); } catch(e) {} }, 500);
});

console.log('%c📅 Form Agenda FKIP UNIMOF - SUPER EXTREME', 'color: #0a6847; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: Ctrl+S (Simpan), Ctrl+K (Template), Ctrl+D (Duplikasi)', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>