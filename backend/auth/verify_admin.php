<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        echo json_encode(['success' => false, 'message' => 'Security token validation failed']);
        exit();
    }

    // Enforce the admin role explicitly. Without this check, a low-privilege
    // group account whose auto-increment id equals an admin's id could query
    // the admin table and brute-force the admin password (IDOR escalation).
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $admin_id = $_SESSION['user_id'];
    $inputPassword = $_POST['password'] ?? '';

    // IP-based rate limiting (5 attempts per 60s). Uses the same DB-backed
    // limiter as login_process so it cannot be bypassed by clearing cookies.
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $rl_wait = ip_rate_limit_check($conn, $client_ip);
    if ($rl_wait > 0) {
        echo json_encode(['success' => false, 'message' => "Too many attempts. Please wait {$rl_wait} seconds."]);
        exit();
    }

    if ($inputPassword === '') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $stmt = $conn->prepare("SELECT password FROM admin WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();

    if ($admin) {
        $dbPassword = $admin['password'];
        $isMatch = password_verify($inputPassword, $dbPassword);
    } else {
        $isMatch = false;
    }

    if ($isMatch) {
        ip_rate_limit_clear($conn, $client_ip);
        rotate_csrf_token();
        echo json_encode(['success' => true]);
    } else {
        // Record the failure so the IP is throttled. Use a constant-time-ish
        // delay to slow automated guessing.
        ip_rate_limit_fail($conn, $client_ip);
        usleep(300000); // 300ms
        // Generic message matches the "admin not found" case to avoid leaking
        // whether the session id maps to a real admin row.
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    }
}