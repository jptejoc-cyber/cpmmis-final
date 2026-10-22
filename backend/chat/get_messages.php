<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');
include(__DIR__ . '/chat_common.php');

$me = chat_guard();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    chat_respond(['status' => 'error', 'message' => 'Invalid request method'], 405);
}

// This endpoint both reads messages AND flips is_read, so it is state-changing:
// require POST + a CSRF token (a GET-imaged cross-site page can no longer
// trigger the read-sweep or exfiltrate thread history).
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$csrf_token = $data['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    error_log("get_messages FAIL: CSRF mismatch for user #{$me['id']} role={$me['role']}. Token provided=" . substr($csrf_token, 0, 8));
    chat_respond(['status' => 'error', 'message' => 'Invalid CSRF token — refresh the page to fix'], 403);
}

$other_id = isset($data['other_id']) ? intval($data['other_id']) : 0;
$other_role = $data['other_role'] ?? '';
$limit = min(max(intval($data['limit'] ?? 50), 1), 200);
$before_id = intval($data['before_id'] ?? 0);

if ($other_id <= 0 || !in_array($other_role, ['admin', 'adviser', 'group'], true)) {
    chat_respond(['status' => 'error', 'message' => 'Invalid parameters'], 400);
}

if (!chat_partner_allowed($conn, $me, $other_id, $other_role)) {
    chat_respond(['status' => 'error', 'message' => 'Forbidden'], 403);
}

chat_stmt(
    $conn,
    "UPDATE messages SET is_read = 1 WHERE sender_id = ? AND sender_role = ? AND receiver_id = ? AND receiver_role = ? AND is_read = 0",
    ['isis', $other_id, $other_role, $me['id'], $me['role']]
);

$params = ['isisisis', $me['id'], $me['role'], $other_id, $other_role, $other_id, $other_role, $me['id'], $me['role']];
$where_extra = '';
if ($before_id > 0) {
    $where_extra = ' AND id < ?';
    $params[0] .= 'i';
    $params[] = $before_id;
}
$params[0] .= 'i';
$params[] = $limit;

$stmt = chat_stmt(
    $conn,
    "SELECT * FROM messages
      WHERE ((sender_id = ? AND sender_role = ? AND receiver_id = ? AND receiver_role = ?)
         OR (sender_id = ? AND sender_role = ? AND receiver_id = ? AND receiver_role = ?))
      {$where_extra}
      ORDER BY created_at DESC
      LIMIT ?",
    $params
);

$result = $stmt->get_result();
$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'id' => $row['id'],
        'sender_id' => (int)$row['sender_id'],
        'sender_role' => $row['sender_role'],
        'receiver_id' => (int)$row['receiver_id'],
        'receiver_role' => $row['receiver_role'],
        'message' => $row['message'],
        'is_read' => (int)$row['is_read'],
        'created_at' => $row['created_at']
    ];
}
$messages = array_reverse($messages);

chat_respond(['status' => 'success', 'messages' => $messages, 'has_more' => count($messages) >= $limit]);
