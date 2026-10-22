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
        header("Location: ../../adviser/groups.php?error=csrf");
        exit();
    }
    $task_id = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
    $new_deadline = trim($_POST['new_deadline']);

    if (empty($new_deadline)) {
        $_SESSION['error'] = "Please provide a deadline.";
        header("Location: ../../adviser/submissions.php");
        exit();
    }

    $deadline_formatted = date('Y-m-d H:i:s', strtotime($new_deadline));

    $stmt = $conn->prepare("SELECT id, caption, deadline FROM adviser_tasks WHERE id = ? AND adviser_id = ?");
    $stmt->bind_param("ii", $task_id, $adviser_id);
    $stmt->execute();
    $check_result = $stmt->get_result();
    if ($check_result->num_rows > 0) {
        $task = $check_result->fetch_assoc();
        $caption = $task['caption'];
        $old_deadline = $task['deadline'];

        $stmt = $conn->prepare("UPDATE adviser_tasks SET deadline = ? WHERE id = ?");
        $stmt->bind_param("si", $deadline_formatted, $task_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Task deadline successfully extended.";

            log_activity($conn, $adviser_id, 'adviser', $_SESSION['username'] ?? '', "Extended deadline", "task '$caption'");

            $hist_stmt = @$conn->prepare("INSERT INTO task_edit_history (task_id, old_deadline, new_deadline, edited_by) VALUES (?, ?, ?, ?)");
            if ($hist_stmt) {
                $hist_stmt->bind_param("issi", $task_id, $old_deadline, $deadline_formatted, $adviser_id);
                $hist_stmt->execute();
            }

            $stmt = $conn->prepare("SELECT id FROM `group` WHERE adviser_id = ?");
            $stmt->bind_param("i", $adviser_id);
            $stmt->execute();
            $groups_result = $stmt->get_result();
            if ($groups_result && $groups_result->num_rows > 0) {
                $notif_msg = "Deadline Extended for: $caption. New Deadline: " . date('M d, Y', strtotime($deadline_formatted));
                while ($g = $groups_result->fetch_assoc()) {
                    $gid = $g['id'];
                    $stmt2 = $conn->prepare("INSERT INTO notifications (user_id, user_role, message, is_read) VALUES (?, 'group', ?, 0)");
                    $stmt2->bind_param("is", $gid, $notif_msg);
                    $stmt2->execute();
                }
            }
        } else {
            $_SESSION['error'] = "Failed to update deadline.";
        }
    } else {
        $_SESSION['error'] = "Invalid task or permission denied.";
    }

    header("Location: ../../adviser/submissions.php");
    exit();
}
?>