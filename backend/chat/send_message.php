<?php
error_reporting(0);
ini_set('display_errors', 0);

try {
    include(__DIR__ . '/../session_init.php');
    include(__DIR__ . '/../db_config.php');
    include(__DIR__ . '/chat_common.php');

    $me = chat_guard();

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        $data = $_POST;
    }

    $csrf_token = $data['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        error_log("send_message FAIL: CSRF mismatch for user #{$me['id']} role={$me['role']}. Token provided=" . substr($csrf_token, 0, 8) . "... session=" . substr($_SESSION['csrf_token'] ?? '', 0, 8) . "...");
        chat_respond(['status' => 'error', 'message' => 'Invalid CSRF token — refresh the page to fix'], 403);
    }

    $receiver_id = isset($data['receiver_id']) ? intval($data['receiver_id']) : 0;
    $receiver_role = $data['receiver_role'] ?? '';
    $message = isset($data['message']) ? trim($data['message']) : '';

    if (strlen($message) > 5000) {
        chat_respond(['status' => 'error', 'message' => 'Message too long. Maximum 5000 characters.'], 400);
    }

    if ($receiver_id <= 0 || !in_array($receiver_role, ['admin', 'adviser', 'group'], true) || empty($message)) {
        error_log("send_message FAIL: Invalid params from user #{$me['id']} role={$me['role']}. receiver_id=$receiver_id receiver_role='$receiver_role' msg_len=" . strlen($message));
        chat_respond(['status' => 'error', 'message' => 'Invalid parameters'], 400);
    }

    if (!chat_partner_allowed($conn, $me, $receiver_id, $receiver_role)) {
        error_log("send_message FAIL: Forbidden user #{$me['id']} role={$me['role']} -> receiver #{$receiver_id} role={$receiver_role}");
        chat_respond(['status' => 'error', 'message' => 'Forbidden — you are not authorized to message this user. Check if adviser is assigned.'], 403);
    }

    chat_stmt(
        $conn,
        "INSERT INTO messages (sender_id, sender_role, receiver_id, receiver_role, message) VALUES (?, ?, ?, ?, ?)",
        ['isiss', $me['id'], $me['role'], $receiver_id, $receiver_role, $message],
        'Failed to send message.'
    );

    error_log("send_message OK: user #{$me['id']} role={$me['role']} -> receiver #{$receiver_id} role={$receiver_role} msg_len=" . strlen($message));
    chat_respond(['status' => 'success', 'message' => 'Message sent successfully']);
} catch (Throwable $e) {
    error_log('send_message EXCEPTION: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error. Please try again later.'], JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
