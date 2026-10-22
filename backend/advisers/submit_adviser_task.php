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
        header("Location: ../../adviser/submissions.php?error=csrf");
        exit();
    }
    $task_type = $_POST['task_type'];
    $chapter_number = isset($_POST['chapter_number']) && is_numeric($_POST['chapter_number']) ? (int)$_POST['chapter_number'] : null;

    if (isset($_POST['caption']) && trim($_POST['caption']) !== '') {
        $caption = trim($_POST['caption']);
    } else {
        if ($task_type === 'chapter') {
            $caption = "Chapter " . $chapter_number . " Submission";
        } elseif ($task_type === 'letter') {
            $caption = "Letter / Forms Submission";
        } else {
            $caption = "Assignment Submission";
        }
    }

    $deadline = isset($_POST['deadline']) ? trim($_POST['deadline']) : '';

    $file_path_val = null;
    $original_filename_val = null;

    // A file is considered "attempted" unless the user submitted without choosing one.
    $upload_err = isset($_FILES['task_file']) ? $_FILES['task_file']['error'] : UPLOAD_ERR_NO_FILE;

    if ($upload_err !== UPLOAD_ERR_NO_FILE) {

        // Never fail silently: report exactly why PHP refused the upload.
        if ($upload_err !== UPLOAD_ERR_OK) {
            switch ($upload_err) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $_SESSION['error'] = "Attachment rejected: your file exceeds the server upload limit (currently " . ini_get('upload_max_filesize') . "). Use a smaller PDF or ask the administrator to raise upload_max_filesize in php.ini.";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $_SESSION['error'] = "Attachment was only partially uploaded. Please try again.";
                    break;
                default:
                    $_SESSION['error'] = "Attachment upload failed (PHP error code $upload_err). No file was attached.";
            }
            header("Location: ../../adviser/submissions.php");
            exit();
        }

        $upload_dir = __DIR__ . '/../../uploads/tasks/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $original_name = $_FILES['task_file']['name'];
        $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

        $max_size = 20 * 1024 * 1024;
        if ($_FILES['task_file']['size'] > $max_size) {
            $_SESSION['error'] = "File exceeds the 20MB size limit.";
            header("Location: ../../adviser/submissions.php");
            exit();
        }

        $allowed = ['pdf'];
        if (in_array($file_ext, $allowed)) {
            $allowed_mime = ['application/pdf'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $_FILES['task_file']['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime_type, $allowed_mime)) {
                $_SESSION['error'] = "Invalid file content. Only PDF files are allowed.";
                header("Location: ../../adviser/submissions.php");
                exit();
            }
            $new_filename = uniqid('task_') . '.' . $file_ext;
            $dest = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['task_file']['tmp_name'], $dest)) {
                $file_path_val = $new_filename;
                $original_filename_val = $original_name;
            } else {
                $_SESSION['error'] = "Could not save the uploaded file to server storage (check folder permissions). Task was NOT posted.";
                header("Location: ../../adviser/submissions.php");
                exit();
            }
        } else {
            $_SESSION['error'] = "Invalid file format. Only PDF files are allowed.";
            header("Location: ../../adviser/submissions.php");
            exit();
        }
    }

    $stmt = $conn->prepare("INSERT INTO adviser_tasks (adviser_id, task_type, chapter_number, caption, deadline, file_path, original_filename) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $deadline_formatted = $deadline ? date('Y-m-d H:i:s', strtotime($deadline)) : null;
    $stmt->bind_param("isissss", $adviser_id, $task_type, $chapter_number, $caption, $deadline_formatted, $file_path_val, $original_filename_val);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Task successfully posted.";

        log_activity($conn, $adviser_id, 'adviser', $_SESSION['username'] ?? '', "Created task", "'$caption'");

        $stmt = $conn->prepare("SELECT id FROM `group` WHERE adviser_id = ?");
        $stmt->bind_param("i", $adviser_id);
        $stmt->execute();
        $assigned_groups = $stmt->get_result();
        if ($assigned_groups && $assigned_groups->num_rows > 0) {
            $adviser_name = $_SESSION['full_name'] ?? 'Your Adviser';

            $prefix = ($task_type === 'chapter') ? "New Task Posted for Chapter $chapter_number" : "New Task Posted";
            if ($deadline_formatted) {
                $notif_msg = "$prefix: $caption (Deadline: " . date('M d, Y', strtotime($deadline)) . ")";
            } else {
                $notif_msg = "$prefix: $caption";
            }

            while ($g = $assigned_groups->fetch_assoc()) {
                $gid = $g['id'];
                $stmt2 = $conn->prepare("INSERT INTO notifications (user_id, user_role, message) VALUES (?, 'group', ?)");
                $stmt2->bind_param("is", $gid, $notif_msg);
                $stmt2->execute();
            }
        }
    } else {
        $_SESSION['error'] = "Failed to post task. Database error.";
    }

    header("Location: ../../adviser/submissions.php");
    exit();
}
?>