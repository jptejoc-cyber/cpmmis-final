<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$letter_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$letter_id) {
    die("Invalid request.");
}

$stmt = $conn->prepare("SELECT * FROM admin_letters WHERE id = ?");
$stmt->bind_param("i", $letter_id);
$stmt->execute();
$letter = $stmt->get_result()->fetch_assoc();

if (!$letter) {
    die("Letter not found.");
}

// Authorization: admin can access any, group can only access their own
if ($_SESSION['role'] === 'admin') {
    // Admin can download any letter
} elseif ($_SESSION['role'] === 'group') {
    if ($letter['group_id'] != $_SESSION['user_id']) {
        die("Unauthorized access.");
    }
} else {
    die("Unauthorized access.");
}

$file_path = __DIR__ . '/../../uploads/letters/' . $letter['file_path'];

if (!file_exists($file_path)) {
    die("File not found.");
}

$file_name = $letter['file_name'];
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

// Determine MIME type (PDF only)
$mime = 'application/pdf';

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
$safe_name = preg_replace('/[\\x00-\\x1F\\x7F"\\\\;]/', '_', $file_name);
$safe_name = str_replace("\r", '', str_replace("\n", '', $safe_name));
if ($safe_name === '') $safe_name = 'letter.pdf';
// RFC 5987 UTF-8 fallback so non-ASCII names survive header handling
header("Content-Disposition: attachment; filename=\"$safe_name\"; filename*=UTF-8''" . rawurlencode($safe_name));
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: no-cache, must-revalidate');
readfile($file_path);
exit();
?>
