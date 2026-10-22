<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');
include(__DIR__ . '/chat_common.php');

$me = chat_guard();

$check_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$check_role = $_GET['role'] ?? '';

if ($check_id <= 0 || !in_array($check_role, ['admin', 'adviser', 'group'], true)) {
    chat_respond(['status' => 'error', 'message' => 'Invalid parameters'], 400);
}

if (!chat_partner_allowed($conn, $me, $check_id, $check_role)) {
    chat_respond(['status' => 'error', 'message' => 'Forbidden'], 403);
}

$allowed_tables = ['admin' => 'admin', 'adviser' => 'adviser', 'group' => 'group'];
$table = $allowed_tables[$check_role] ?? null;
if (!$table) {
    chat_respond(['status' => 'error', 'message' => 'Invalid role.'], 400);
}

$stmt = chat_stmt($conn, "SELECT last_activity FROM `$table` WHERE id = ?", ['i', $check_id]);
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    chat_respond(['status' => 'error', 'message' => 'User not found'], 404);
}

$last_activity = $row['last_activity'];
$is_online = ($last_activity !== null) && ((time() - strtotime($last_activity)) <= 120);

chat_respond([
    'status' => 'success',
    'is_online' => $is_online,
    'last_activity' => $last_activity
]);
