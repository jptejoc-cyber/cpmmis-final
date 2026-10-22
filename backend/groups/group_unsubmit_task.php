<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'group') {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../group/submissions.php");
    exit();
}

$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($token)) {
    $_SESSION['error'] = "Invalid security token. Please refresh the page.";
    header("Location: ../../group/submissions.php");
    exit();
}

$group_id = $_SESSION['user_id'];
$submission_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

$stmt = $conn->prepare("SELECT s.*, t.deadline FROM group_submissions s JOIN adviser_tasks t ON s.task_id = t.id WHERE s.id = ? AND s.group_id = ?");
$stmt->bind_param("ii", $submission_id, $group_id);
$stmt->execute();
$sub_result = $stmt->get_result();

if ($sub_result->num_rows == 0) {
    $_SESSION['error'] = "Invalid submission.";
    header("Location: ../../group/submissions.php");
    exit();
}

$sub = $sub_result->fetch_assoc();

if (($sub['status'] ?? '') === 'approved') {
    $_SESSION['error'] = "This submission was already approved by your adviser and can no longer be removed.";
    header("Location: ../../group/submissions.php");
    exit();
}

if (($sub['file_path'] ?? '') === 'auto_combined') {
    $_SESSION['error'] = "The compiled final project cannot be removed.";
    header("Location: ../../group/submissions.php");
    exit();
}

if (strtotime($sub['deadline']) < time()) {
    $_SESSION['error'] = "Deadline has passed. Cannot unsubmit.";
    header("Location: ../../group/submissions.php");
    exit();
}

$file_path = __DIR__ . '/../../uploads/submissions/' . $sub['file_path'];
if (file_exists($file_path) && !empty($sub['file_path'])) {
    unlink($file_path);
}

$stmt = $conn->prepare("DELETE FROM group_submissions WHERE id = ?");
$stmt->bind_param("i", $submission_id);
if ($stmt->execute()) {
    log_activity($conn, $group_id, 'group', $_SESSION['username'] ?? '', "Removed submission", "task ID " . $sub['task_id']);
    $_SESSION['success'] = "Submission removed successfully. You can now upload a new file.";
} else {
    $_SESSION['error'] = "Failed to unsubmit.";
}

header("Location: ../../group/submissions.php");
exit();
?>