<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { http_response_code(403); exit; }
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { http_response_code(403); exit; }

$queries = [
    "ALTER TABLE `group_submissions` 
     ADD COLUMN IF NOT EXISTS `version` INT(11) NOT NULL DEFAULT 1 AFTER `file_path`,
     ADD COLUMN IF NOT EXISTS `is_final` TINYINT(1) NOT NULL DEFAULT 0 AFTER `version`;"
];

foreach ($queries as $query) {
    if (mysqli_query($conn, $query)) {
        echo "Success: " . substr($query, 0, 50) . "...\n";
    } else {
        echo "Error executing SQL query.\n";
    }
}
echo "Database update complete.\n";
?>
