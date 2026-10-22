<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');
include(__DIR__ . '/chat_common.php');

$me = chat_guard(['adviser']);

$stmt = chat_stmt(
    $conn,
    "SELECT sender_id, sender_role, COUNT(*) AS count FROM messages WHERE receiver_id = ? AND receiver_role = 'adviser' AND is_read = 0 GROUP BY sender_id, sender_role",
    ['i', $me['id']]
);

$counts = [];
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $counts[$row['sender_role'] . '_' . $row['sender_id']] = (int)$row['count'];
}

chat_respond(['status' => 'success', 'counts' => $counts]);
