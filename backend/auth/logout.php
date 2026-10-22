<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');

// Logout CSRF protection. Accept a POST (recommended) or a GET that carries a
// valid CSRF token in the query string. A plain GET with no valid token is
// rejected, so an attacker cannot force a logout via <img>/cross-site link.
$token = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = $_GET['csrf_token'] ?? '';
}

if (!verify_csrf_token($token)) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Security token validation failed.']);
    exit();
}

if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    log_activity($conn, $_SESSION['user_id'], $_SESSION['role'], $_SESSION['username'] ?? '', "Logged out", "IP: " . ($_SERVER['REMOTE_ADDR'] ?? ''));
}

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header("Location: ../../index.php");
} else {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'redirect' => 'index.php']);
}
exit();
?>
