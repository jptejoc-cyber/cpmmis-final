<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'adviser') {
    header("HTTP/1.1 403 Forbidden");
    exit("Unauthorized access.");
}

$adviser_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("HTTP/1.1 400 Bad Request");
    exit("Missing required parameters.");
}

$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
    $submission_id = (int)($input['id'] ?? 0);
    $action = $input['action'] ?? '';
    $csrf_token = $input['csrf_token'] ?? '';
} else {
    $submission_id = (int)($_POST['submission_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
}

if (!verify_csrf_token($csrf_token)) {
    header("HTTP/1.1 400 Bad Request");
    exit("Invalid CSRF token.");
}

if ($submission_id == 0 || empty($action)) {
    header("HTTP/1.1 400 Bad Request");
    exit("Missing required parameters.");
}

$stmt = $conn->prepare("SELECT s.*, g.username, g.id as gid FROM group_submissions s JOIN `group` g ON s.group_id = g.id WHERE s.id = ? AND g.adviser_id = ?");
$stmt->bind_param("ii", $submission_id, $adviser_id);
$stmt->execute();
$sub_data = $stmt->get_result()->fetch_assoc();

if (!$sub_data) {
    header("HTTP/1.1 401 Unauthorized");
    exit("Unauthorized action.");
}

$group_id = $sub_data['gid'];
$chapter_number = $sub_data['chapter_number'];

if ($action === 'approve') {
    $stmt = $conn->prepare("UPDATE group_submissions SET status = 'approved', reviewed_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $submission_id);

    if ($stmt->execute()) {
        $msg = "Great news! Your submission for Chapter {$chapter_number} has been APPROVED by your adviser.";
        $stmt2 = $conn->prepare("INSERT INTO notifications (user_id, user_role, message, is_read, created_at, reference_id) VALUES (?, 'group', ?, 0, NOW(), ?)");
        $stmt2->bind_param("isi", $group_id, $msg, $submission_id);
        $stmt2->execute();

        log_activity($conn, $adviser_id, 'adviser', $_SESSION['username'] ?? '', "Approved submission", "Chapter $chapter_number from group " . $sub_data['username']);

        echo "Success";
        exit();
    }
}

if ($action === 'reject' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = $_POST['reject_reason'] ?? 'No reason provided.';
    $reason = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');

    $stmt = $conn->prepare("UPDATE group_submissions SET status = 'rejected', reviewed_at = NOW(), review_notes = ? WHERE id = ?");
    $stmt->bind_param("si", $reason, $submission_id);

    if ($stmt->execute()) {
        $msg = "Your submission for Chapter {$chapter_number} was rejected. Reason: " . $reason;
        $stmt2 = $conn->prepare("INSERT INTO notifications (user_id, user_role, message, is_read, created_at, reference_id) VALUES (?, 'group', ?, 0, NOW(), ?)");
        $stmt2->bind_param("isi", $group_id, $msg, $submission_id);
        $stmt2->execute();

        log_activity($conn, $adviser_id, 'adviser', $_SESSION['username'] ?? '', "Rejected submission", "Chapter $chapter_number from group " . $sub_data['username']);

        echo "Success";
        exit();
    }
}

header("HTTP/1.1 500 Internal Server Error");
exit("Processing failed.");
?>