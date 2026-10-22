<?php
include(__DIR__ . '/session_init.php');
include(__DIR__ . '/db_config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required.']);
    exit();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit();
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token.']);
    exit();
}

header('Content-Type: application/json');

$cleared = 0;

// Clear PHP opcode cache files in temp if writable
$temp_dir = sys_get_temp_dir();
$prefixes = ['php_opcache', 'opcache_'];
foreach ($prefixes as $prefix) {
    foreach (glob($temp_dir . DIRECTORY_SEPARATOR . $prefix . '*') as $file) {
        if (is_file($file) && is_writable($file)) {
            unlink($file);
            $cleared++;
        }
    }
}

log_activity($conn, $_SESSION['user_id'], 'admin', $_SESSION['username'] ?? '', "Cleared system cache", "$cleared files");

echo json_encode(['status' => 'success', 'cleared' => $cleared]);
?>
