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
        header("Location: ../../group/advisers.php?error=csrf");
        exit();
    }
    $max_post = ini_get('post_max_size');
    if (empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        header("Location: ../../group/advisers.php?error=file_too_large&max=" . urlencode($max_post));
        exit();
    }

    $group_id = $_SESSION['user_id'];
    $adviser_id = isset($_POST['adviser_id']) ? (int)$_POST['adviser_id'] : 0;
    $project_title = trim($_POST['project_title'] ?? '');

    if (empty($adviser_id)) {
        header("Location: ../../group/advisers.php?error=missing_data");
        exit();
    }

    // Request-rate throttle: blocks automated resubmission floods per IP.
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ip_wait = ip_rate_limit_check($conn, $client_ip);
    if ($ip_wait > 0) {
        header("Location: ../../group/advisers.php?error=rate_limit");
        exit();
    }

    // --- Ownership / eligibility checks BEFORE any upload, so a blocked group
    // never leaves orphaned files behind (the old code uploaded a 10MB file
    // before verifying the group could actually request).
    $stmt = $conn->prepare("SELECT adviser_id FROM `group` WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $group_data = $stmt->get_result()->fetch_assoc();
    if (!empty($group_data['adviser_id'])) {
        ip_rate_limit_fail($conn, $client_ip);
        die("You already have an assigned adviser. You cannot request a new one.");
    }

    $stmt = $conn->prepare("SELECT id, message FROM adviser_requests WHERE group_id = ? AND adviser_id = ? LIMIT 1");
    $stmt->bind_param("ii", $group_id, $adviser_id);
    $stmt->execute();
    $existing_row = $stmt->get_result()->fetch_assoc();
    $old_file = $existing_row['message'] ?? '';

    $message = '';
    if (isset($_FILES['message']) && $_FILES['message']['error'] == 0) {
        $upload_dir = __DIR__ . '/../../uploads/letters/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $max_size = 10 * 1024 * 1024;
        if ($_FILES['message']['size'] > $max_size) {
            die("File exceeds the 10MB size limit.");
        }

        $file_name = basename($_FILES['message']['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if ($file_ext !== "pdf") {
            die("Only PDF files are allowed.");
        }

        // MIME validation
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['message']['tmp_name']);
        finfo_close($finfo);
        if ($mime !== 'application/pdf') {
            die("File content is not a valid PDF.");
        }

        $unique_name = uniqid('letter_') . '.pdf';
        $target_file = $upload_dir . $unique_name;

        if (move_uploaded_file($_FILES['message']['tmp_name'], $target_file)) {
            $message = 'uploads/letters/' . $unique_name;
        } else {
            die("Error uploading file.");
        }
    } else {
        die("Please upload a PDF letter.");
    }

    if ($existing_row) {
        $sql = "UPDATE adviser_requests SET project_title = ?, message = ?, status = 'pending', created_at = NOW() WHERE group_id = ? AND adviser_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $project_title, $message, $group_id, $adviser_id);
    } else {
        $sql = "INSERT INTO adviser_requests (group_id, adviser_id, project_title, message, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiss", $group_id, $adviser_id, $project_title, $message);
    }

    if ($stmt->execute()) {
        ip_rate_limit_fail($conn, $client_ip);

        // Re-request replaces the previously uploaded letter: remove the old
        // file so uploads/letters cannot accumulate orphans on resubmission.
        if ($existing_row && $old_file !== $message && strpos($old_file, 'uploads/letters/') === 0) {
            $old_path = __DIR__ . '/../../' . $old_file;
            if (is_file($old_path)) @unlink($old_path);
        }

        $stmt = $conn->prepare("SELECT username FROM `group` WHERE id = ?");
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        $gname = $stmt->get_result()->fetch_assoc()['username'] ?? 'Unknown Group';

        log_activity($conn, $group_id, 'group', $gname, "Requested adviser", "ID $adviser_id");

        $notif_msg = "Group Advisership Request: " . $gname;
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, message, is_read, created_at, reference_id) VALUES (?, 'adviser', ?, 0, NOW(), ?)");
        $stmt->bind_param("isi", $adviser_id, $notif_msg, $group_id);
        $stmt->execute();

        header("Location: ../../group/advisers.php?status=sent");
        exit();
    } else {
        // DB write failed — remove the just-uploaded file so we do not leave a
        // letter that references a request that never got recorded.
        if ($message && strpos($message, 'uploads/letters/') === 0) {
            $cleanup_path = __DIR__ . '/../../' . $message;
            if (is_file($cleanup_path)) @unlink($cleanup_path);
        }
        die("An error occurred. Please try again.");
    }
}
?>