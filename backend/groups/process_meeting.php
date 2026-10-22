<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'adviser') {
    header("Location: ../index.php");
    exit();
}

$adviser_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../adviser/adviser_dashboard.php");
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
    $meeting_id = (int)($input['id'] ?? 0);
    $action = $input['action'] ?? '';
    $csrf_token = $input['csrf_token'] ?? '';
} else {
    $meeting_id = (int)($_POST['meeting_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
}

if (!verify_csrf_token($csrf_token)) {
    header("Location: ../../adviser/adviser_dashboard.php?error=csrf");
    exit();
}

if ($meeting_id == 0 || empty($action)) {
    header("Location: ../../adviser/adviser_dashboard.php");
    exit();
}

$stmt = $conn->prepare("SELECT m.*, g.username, g.id as gid FROM meeting_requests m JOIN `group` g ON m.group_id = g.id WHERE m.id = ? AND g.adviser_id = ?");
$stmt->bind_param("ii", $meeting_id, $adviser_id);
$stmt->execute();
$meeting_data = $stmt->get_result()->fetch_assoc();

if (!$meeting_data) {
    die("Unauthorized action.");
}

$group_id = $meeting_data['gid'];

if ($action === 'approve') {
    $stmt = $conn->prepare("UPDATE meeting_requests SET status = 'accepted' WHERE id = ?");
    $stmt->bind_param("i", $meeting_id);

    if ($stmt->execute()) {
        $msg = "Your meeting request has been APPROVED by your adviser.";
        $stmt2 = $conn->prepare("INSERT INTO notifications (user_id, user_role, message, reference_id) VALUES (?, 'group', ?, ?)");
        $stmt2->bind_param("isi", $group_id, $msg, $meeting_id);
        $stmt2->execute();

        header("Location: ../../adviser/view_group.php?id=$group_id&approved=1");
        exit();
    }
}

if ($action === 'decline' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = trim($_POST['decline_reason'] ?? '');
    $new_date = trim($_POST['new_schedule'] ?? '');

    $formatted_date = $new_date ? date('M d, Y h:i A', strtotime($new_date)) : 'Not specified';
    $reason_display = $reason ?: 'No reason provided';
    $notif_msg = "Meeting Declined. Reason: {$reason_display}. Adviser suggested rescheduling to: {$formatted_date}";

    $stmt = $conn->prepare("UPDATE meeting_requests SET status = 'declined', admin_remarks = ?, suggested_reschedule = ? WHERE id = ?");
    $stmt->bind_param("ssi", $reason, $new_date, $meeting_id);

    if ($stmt->execute()) {
        $stmt2 = $conn->prepare("INSERT INTO notifications (user_id, user_role, message, reference_id) VALUES (?, 'group', ?, ?)");
        $stmt2->bind_param("isi", $group_id, $notif_msg, $meeting_id);
        $stmt2->execute();

        header("Location: ../../adviser/view_group.php?id=$group_id&declined=1");
        exit();
    }
}

header("Location: ../../adviser/view_group.php?id=$group_id");
exit();
?>