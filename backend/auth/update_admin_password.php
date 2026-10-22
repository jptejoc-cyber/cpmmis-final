<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');
include(__DIR__ . '/csrf.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

header('Content-Type: application/json');

$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($token)) {
    echo json_encode(['status' => 'error', 'message' => 'Security token validation failed.']);
    exit();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit();
}

$pw_err = validate_password($_POST['new_password'] ?? '');
if ($pw_err !== '') {
    echo json_encode(['status' => 'error', 'message' => $pw_err]);
    exit();
}

$admin_id = $_SESSION['user_id'];
$current_p = $_POST['current_password'] ?? '';
$new_p = $_POST['new_password'];

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

$stmt = $conn->prepare("SELECT password FROM admin WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row || !password_verify($current_p, $row['password'])) {
    ip_rate_limit_fail($conn, $client_ip);
    echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect.']);
    exit();
}
ip_rate_limit_clear($conn, $client_ip);

$hash = password_hash($new_p, PASSWORD_BCRYPT);
$stmt = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
$stmt->bind_param("si", $hash, $admin_id);
if ($stmt->execute()) {
    // Invalidate the current session cookie id and rotate the CSRF token so a
    // stolen pre-change session/token cannot be reused after the password change.
    session_regenerate_id(true);
    rotate_csrf_token();
    log_activity($conn, $admin_id, 'admin', $_SESSION['username'] ?? '', "Changed account password", '');
    echo json_encode(['status' => 'success', 'message' => 'Password updated successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred.']);
}
?>
