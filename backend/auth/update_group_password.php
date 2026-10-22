<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');
include(__DIR__ . '/csrf.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($token)) {
    echo json_encode(['status' => 'error', 'message' => 'Security token validation failed']);
    exit();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'group') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$group_id = (int)$_SESSION['user_id'];
$current = $_POST['current_password'] ?? '';
$new = $_POST['new_password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if ($new !== $confirm) {
    echo json_encode(['status' => 'error', 'message' => 'New passwords do not match']);
    exit();
}

$pw_err = validate_password($new);
if ($pw_err !== '') {
    echo json_encode(['status' => 'error', 'message' => $pw_err]);
    exit();
}

$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ip_wait = ip_rate_limit_check($conn, $client_ip);
if ($ip_wait > 0) {
    echo json_encode([
        'status' => 'error',
        'message' => "Too many attempts. Please wait {$ip_wait}s before trying again.",
        'retry_after' => $ip_wait
    ]);
    exit();
}

$stmt = $conn->prepare("SELECT password FROM `group` WHERE id = ?");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || !password_verify($current, $user['password'])) {
    ip_rate_limit_fail($conn, $client_ip);
    echo json_encode(['status' => 'error', 'message' => 'incorrect_current']);
    exit();
}
ip_rate_limit_clear($conn, $client_ip);

$hash = password_hash($new, PASSWORD_BCRYPT);
$stmt = $conn->prepare("UPDATE `group` SET password = ? WHERE id = ?");
$stmt->bind_param("si", $hash, $group_id);

if ($stmt->execute()) {
    session_regenerate_id(true);
    rotate_csrf_token();
    echo json_encode(['status' => 'success', 'message' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
?>