<?php
// Security: block direct web access
if (php_sapi_name() !== 'cli') { http_response_code(403); die('Access denied'); }
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../db_config.php';

echo "=== Fix Empty Deadline Values ===\n\n";

// Fix empty strings in adviser_tasks deadline
$stmt = $conn->prepare("UPDATE adviser_tasks SET deadline = NULL WHERE deadline = ''");
$stmt->execute();
echo "Fixed " . $stmt->affected_rows . " rows with empty deadlines in adviser_tasks.\n";

// Also fix any empty strings in group_submissions submitted_at
$stmt = $conn->prepare("UPDATE group_submissions SET submitted_at = NULL WHERE submitted_at = ''");
$stmt->execute();
echo "Fixed " . $stmt->affected_rows . " rows with empty submitted_at in group_submissions.\n";

// Fix empty meeting_time in meeting_requests
$stmt = $conn->prepare("UPDATE meeting_requests SET meeting_time = NULL WHERE meeting_time = ''");
$stmt->execute();
echo "Fixed " . $stmt->affected_rows . " rows with empty meeting_time in meeting_requests.\n";

echo "\nDone.\n";
$conn->close();
