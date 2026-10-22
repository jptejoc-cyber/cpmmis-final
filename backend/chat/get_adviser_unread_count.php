<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');
include(__DIR__ . '/chat_common.php');

$me = chat_guard(['adviser']);

$stmt = chat_stmt(
    $conn,
    "SELECT COUNT(*) AS unread_count FROM messages WHERE receiver_id = ? AND receiver_role = 'adviser' AND is_read = 0",
    ['i', $me['id']]
);

$row = $stmt->get_result()->fetch_assoc();

chat_respond(['status' => 'success', 'count' => (int)$row['unread_count']]);
