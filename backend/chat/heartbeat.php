<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');
include(__DIR__ . '/chat_common.php');

$me = chat_guard();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    chat_respond(['status' => 'error', 'message' => 'Invalid request method'], 405);
}

$allowed_tables = ['admin' => 'admin', 'adviser' => 'adviser', 'group' => 'group'];
$table = $allowed_tables[$me['role']] ?? null;
if (!$table) {
    chat_respond(['status' => 'error', 'message' => 'Invalid role.'], 400);
}

chat_stmt($conn, "UPDATE `$table` SET last_activity = NOW() WHERE id = ?", ['i', $me['id']]);

chat_respond(['status' => 'success']);
