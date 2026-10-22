<?php
// Security: block direct web access
if (php_sapi_name() !== 'cli') { http_response_code(403); die('Access denied'); }
include(__DIR__ . '/../db_config.php');

$query = "ALTER TABLE adviser_tasks ADD COLUMN deadline_notified TINYINT(1) DEFAULT 0";

if (mysqli_query($conn, $query)) {
    echo "Successfully added deadline_notified column to adviser_tasks.\n";
} else {
    // If it already exists, error is expected
    echo "Column already exists or error occurred.\n";
}
?>
