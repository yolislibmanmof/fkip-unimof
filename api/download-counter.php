<?php
// api/download-counter.php
require_once __DIR__ . '/../includes/config.php';
$data = json_decode(file_get_contents('php://input'), true);
if (isset($data['id'])) {
    $pdo->prepare("UPDATE downloads SET downloads_count = downloads_count + 1 WHERE id = ?")->execute([(int)$data['id']]);
}
?>