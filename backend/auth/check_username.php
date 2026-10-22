<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['exists' => false]);
    exit();
}

// Restrict to administrators only — this endpoint can otherwise be used
// to enumerate valid adviser usernames.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['exists' => false, 'error' => 'Forbidden']);
    exit();
}

// Rate limiting: max 20 checks per minute per session
$rate_key = 'username_check_count';
$rate_first = 'username_check_first';
$count = $_SESSION[$rate_key] ?? 0;
$first = $_SESSION[$rate_first] ?? time();
if (time() - $first > 60) {
    $count = 0;
    $first = time();
}
if ($count >= 20) {
    echo json_encode(['exists' => false, 'error' => 'Rate limit exceeded']);
    exit();
}
$_SESSION[$rate_key] = $count + 1;
$_SESSION[$rate_first] = $first;

$user = trim($_GET['username'] ?? '');
if (empty($user)) {
    echo json_encode(['exists' => false]);
    exit();
}

$stmt = $conn->prepare("SELECT id FROM adviser WHERE username = ?");
$stmt->bind_param("s", $user);
$stmt->execute();
echo json_encode(['exists' => $stmt->get_result()->num_rows > 0]);
?>