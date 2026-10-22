<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'group') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        header("Location: ../../group/submissions.php?error=csrf");
        exit();
    }
    $doc_name = trim($_POST['doc_name']);

    $stmt = $conn->prepare("SELECT adviser_id, username FROM `group` WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $group_data = $stmt->get_result()->fetch_assoc();
    $adviser_id = $group_data['adviser_id'];
    $group_name = $group_data['username'];

    if (!$adviser_id) {
        $_SESSION['error'] = "No adviser assigned. Please contact the administrator.";
        header("Location: ../../group/submissions.php");
        exit();
    }

    $target_dir = __DIR__ . "/../../uploads/submissions/";

    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    if (!isset($_FILES["manuscript"]) || $_FILES["manuscript"]["error"] !== UPLOAD_ERR_OK) {
        header("Location: ../../group/submissions.php?error=upload");
        exit();
    }

    $file_extension = strtolower(pathinfo($_FILES["manuscript"]["name"], PATHINFO_EXTENSION));
    $new_filename = $user_id . "_" . preg_replace("/[^a-zA-Z0-9]/", "", $doc_name) . "_" . time() . "_" . uniqid() . "." . $file_extension;
    $target_file = $target_dir . $new_filename;

    $allowed_types = ['pdf'];
    if (!in_array($file_extension, $allowed_types)) {
        $_SESSION['error'] = "Only PDF files are allowed.";
        header("Location: ../../group/submissions.php");
        exit();
    }

    $allowed_mime = ['application/pdf'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $_FILES["manuscript"]["tmp_name"]);
    finfo_close($finfo);
    if (!in_array($mime_type, $allowed_mime)) {
        $_SESSION['error'] = "Invalid file content. Only PDF files are allowed.";
        header("Location: ../../group/submissions.php");
        exit();
    }

    if ($_FILES["manuscript"]["size"] > 20 * 1024 * 1024) {
        $_SESSION['error'] = "File is too large (Maximum 20MB).";
        header("Location: ../../group/submissions.php");
        exit();
    }

    if (move_uploaded_file($_FILES["manuscript"]["tmp_name"], $target_file)) {

        preg_match('/\d+/', $doc_name, $matches);
        $chapter_num = isset($matches[0]) ? (int)$matches[0] : 0;

        $stmt = $conn->prepare("INSERT INTO group_submissions (group_id, document_name, file_path, chapter_number, status, submitted_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param("issi", $user_id, $doc_name, $new_filename, $chapter_num);

        if ($stmt->execute()) {
            $submission_id = $conn->insert_id;

            log_activity($conn, $user_id, 'group', $group_name, "Submitted manuscript", $doc_name);

            $notif_text = "Research Group '$group_name' has submitted a new document: $doc_name";
            $stmt2 = $conn->prepare("INSERT INTO notifications (user_id, user_role, message, is_read, created_at, reference_id) VALUES (?, 'adviser', ?, 0, NOW(), ?)");
            $stmt2->bind_param("isi", $adviser_id, $notif_text, $submission_id);

            if ($stmt2->execute()) {
                header("Location: ../../group/submissions.php?upload=success");
                exit();
            } else {
                $_SESSION['error'] = "An error occurred while sending notification.";
                header("Location: ../../group/submissions.php");
                exit();
            }
        } else {
            $_SESSION['error'] = "An error occurred while saving your submission.";
            header("Location: ../../group/submissions.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "There was an issue uploading your file.";
        header("Location: ../../group/submissions.php");
        exit();
    }
} else {
    header("Location: ../../group/submissions.php");
    exit();
}
?>