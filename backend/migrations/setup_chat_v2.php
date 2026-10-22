<?php
// Security: block direct web access
if (php_sapi_name() !== 'cli') { http_response_code(403); die('Access denied'); }
chdir(__DIR__ . '/..');
include('db_config.php');

$success = [];
$errors = [];

// 1. Add 'group' to messages sender_role ENUM
try {
    $conn->query("ALTER TABLE messages MODIFY COLUMN `sender_role` ENUM('admin','adviser','group') NOT NULL");
    $success[] = "messages.sender_role updated";
} catch (mysqli_sql_exception $e) {
    $errors[] = "messages.sender_role: " . $e->getMessage();
}

// 2. Add 'group' to messages receiver_role ENUM
try {
    $conn->query("ALTER TABLE messages MODIFY COLUMN `receiver_role` ENUM('admin','adviser','group') NOT NULL");
    $success[] = "messages.receiver_role updated";
} catch (mysqli_sql_exception $e) {
    $errors[] = "messages.receiver_role: " . $e->getMessage();
}

// 3. Add last_activity to group table
try {
    $conn->query("ALTER TABLE `group` ADD COLUMN `last_activity` timestamp NULL DEFAULT NULL");
    $success[] = "group.last_activity added";
} catch (mysqli_sql_exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        $success[] = "group.last_activity already exists";
    } else {
        $errors[] = "group.last_activity: " . $e->getMessage();
    }
}

echo "Chat v2 Setup Results\n";
if ($success) echo implode("\n", $success) . "\n";
if ($errors) echo implode("\n", $errors) . "\n";
if (empty($errors)) echo "Chat v2 is ready!\n";
