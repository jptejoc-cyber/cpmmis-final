<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'group') {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $_SESSION['error'] = "Invalid request.";
        header("Location: ../../group/submissions.php");
        exit();
    }
    $group_id = $_SESSION['user_id'];
    $task_id = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;

    $stmt = $conn->prepare("SELECT * FROM adviser_tasks WHERE id = ? AND adviser_id = (SELECT adviser_id FROM `group` WHERE id = ?)");
    $stmt->bind_param("ii", $task_id, $group_id);
    $stmt->execute();
    $task_result = $stmt->get_result();
    if ($task_result->num_rows == 0) {
        $_SESSION['error'] = "Invalid task.";
        header("Location: ../../group/submissions.php");
        exit();
    }
    $task = $task_result->fetch_assoc();

    if (strtotime($task['deadline']) < time()) {
        $_SESSION['error'] = "Deadline has passed. Cannot submit.";
        header("Location: ../../group/submissions.php");
        exit();
    }

    $stmt = $conn->prepare("SELECT * FROM group_submissions WHERE task_id = ? AND group_id = ?");
    $stmt->bind_param("ii", $task_id, $group_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $_SESSION['error'] = "You have already submitted this task. Unsubmit first to replace.";
        header("Location: ../../group/submissions.php");
        exit();
    }

    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] == UPLOAD_ERR_OK) {
        $max_size = 20 * 1024 * 1024;
        if ($_FILES['submission_file']['size'] > $max_size) {
            $_SESSION['error'] = "File exceeds the 20MB size limit.";
            header("Location: ../../group/submissions.php");
            exit();
        }
        $upload_dir = __DIR__ . '/../../uploads/submissions/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $original_name = $_FILES['submission_file']['name'];
        $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

        $allowed = ['pdf'];
        if (in_array($file_ext, $allowed)) {
            $allowed_mime = ['application/pdf'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $_FILES['submission_file']['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime_type, $allowed_mime)) {
                $_SESSION['error'] = "Invalid file content. Only PDF files are allowed.";
                header("Location: ../../group/submissions.php");
                exit();
            }
            $new_filename = uniqid('sub_') . '_' . $group_id . '.' . $file_ext;
            $dest = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $dest)) {
                $doc_name = $original_name;
                $file_path = $new_filename;

                $chap_num = (int)$task['chapter_number'];
                $stmt = $conn->prepare("INSERT INTO group_submissions (group_id, task_id, document_name, file_path, chapter_number, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                $stmt->bind_param("iissi", $group_id, $task_id, $doc_name, $file_path, $chap_num);

                if ($stmt->execute()) {
                    $_SESSION['success'] = "Submission uploaded successfully.";

                    log_activity($conn, $group_id, 'group', $_SESSION['username'] ?? '', "Submitted task", $task['caption']);

                    $submission_id = $conn->insert_id;
                    $adv_id = $task['adviser_id'];
                    $stmt = $conn->prepare("SELECT username FROM `group` WHERE id = ?");
                    $stmt->bind_param("i", $group_id);
                    $stmt->execute();
                    $group_name = $stmt->get_result()->fetch_assoc()['username'] ?? 'A group';
                    $msg = "Submission uploaded by: " . $group_name;
                    $stmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, message, is_read, reference_id, created_at) VALUES (?, 'adviser', ?, 0, ?, NOW())");
                    $stmt->bind_param("isi", $adv_id, $msg, $submission_id);
                    $stmt->execute();

                    $clear_pattern = "Urgent: The deadline for '" . $task['caption'] . "'%";
                    $clear_pattern2 = "At Risk: You missed the deadline for '" . $task['caption'] . "'%";
                    $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ? AND user_role = 'group' AND (message LIKE ? OR message LIKE ?)");
                    $stmt->bind_param("iss", $group_id, $clear_pattern, $clear_pattern2);
                    $stmt->execute();
                } else {
                    $_SESSION['error'] = "Database error during submission.";
                }
            } else {
                $_SESSION['error'] = "Failed to upload file to server.";
            }
        } else {
            $_SESSION['error'] = "Invalid file format. Only PDF files are allowed.";
        }
    } else {
        $_SESSION['error'] = "No valid file uploaded.";
    }

    header("Location: ../../group/submissions.php");
    exit();
}
?>