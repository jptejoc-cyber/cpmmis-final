<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'adviser') {
    header("Location: ../index.php");
    exit();
}

$adviser_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        header("Location: ../../adviser/meetings.php?error=csrf");
        exit();
    }
    $meeting_id = (int)$_POST['meeting_id'];
    $action_type = $_POST['action_type'];
    $remarks = htmlspecialchars($_POST['remarks'] ?? '', ENT_QUOTES, 'UTF-8');

    $stmt = $conn->prepare("SELECT * FROM meeting_requests WHERE id = ? AND adviser_id = ? AND status = 'pending'");
    $stmt->bind_param("ii", $meeting_id, $adviser_id);
    $stmt->execute();
    $check_result = $stmt->get_result();
    if ($check_result->num_rows > 0) {
        $meeting = $check_result->fetch_assoc();
        $group_id = $meeting['group_id'];

        $new_status = ($action_type === 'accept') ? 'accepted' : 'declined';

        $stmt = $conn->prepare("UPDATE meeting_requests SET status = ?, admin_remarks = ? WHERE id = ?");
        $stmt->bind_param("ssi", $new_status, $remarks, $meeting_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Meeting successfully " . $new_status . ".";

            $action_label = ($new_status === 'accepted') ? 'Accepted' : 'Declined';
            log_activity($conn, $adviser_id, 'adviser', $_SESSION['username'] ?? '', "$action_label consultation", "group ID $group_id");

            $adviser_name = $_SESSION['full_name'] ?? 'Your Adviser';
            $msg = "Consultation " . ucfirst($new_status) . ": Your meeting on " . date('M d g:i A', strtotime($meeting['meeting_time'])) . " has been " . $new_status . " by $adviser_name.";
            $stmt2 = $conn->prepare("INSERT INTO notifications (user_id, user_role, message) VALUES (?, 'group', ?)");
            $stmt2->bind_param("is", $group_id, $msg);
            $stmt2->execute();
        } else {
            $_SESSION['error'] = "Failed to update meeting.";
        }
    } else {
        $_SESSION['error'] = "Meeting request not found or already processed.";
    }

    header("Location: ../../adviser/meetings.php");
    exit();
}
?>