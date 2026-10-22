<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    $data = $_POST;
}

// State-changing endpoint: require a valid CSRF token. The body carries the
// token the page generated, so a cross-site page cannot forge a "read" sweep.
$token = $data['csrf_token'] ?? '';
if (!verify_csrf_token($token)) {
    echo "error";
    exit;
}

$user_id = isset($data['user_id']) ? (int)$data['user_id'] : 0;
$user_role = $data['user_role'] ?? '';

// A user may only mark their OWN notifications read. The strict session-identity
// match below is the security boundary: a forged request could ever only affect
// the attacker-victim's own bell badge, which is equivalent to them clicking it.
if ($user_id > 0 && in_array($user_role, ['admin', 'adviser', 'group'], true)
    && isset($_SESSION['user_id'], $_SESSION['role'])
    && $_SESSION['role'] === $user_role
    && (int)$_SESSION['user_id'] === $user_id) {

    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND user_role = ? AND is_read = 0");
    $stmt->bind_param("is", $user_id, $user_role);
    $stmt->execute();
    echo "success";
    exit;
}

echo "error";
