<?php
// tentang.php - Redirect permanen ke about.php (halaman Tentang Kami)
require_once __DIR__ . '/includes/config.php';
header('Location: ' . base_url('about.php'), true, 301);
exit;