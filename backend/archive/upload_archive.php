<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');

error_reporting(0);
ini_set('display_errors', 0);

$error_log_path = __DIR__ . '/../../uploads/archive/upload_errors.log';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_SESSION['role'] === 'admin') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        header("Location: ../../admin/archive.php?error=failed&reason=csrf");
        exit();
    }
    
    // 1. Validate File Extension
    if (!isset($_FILES["pdf_file"]) || $_FILES["pdf_file"]["error"] !== UPLOAD_ERR_OK) {
        $err_code = isset($_FILES["pdf_file"]) ? $_FILES["pdf_file"]["error"] : 'NO_FILE';
        $msg = "Upload error (code: $err_code). File too large or not sent.";
        file_put_contents($error_log_path, date('Y-m-d H:i:s') . " - $msg\n", FILE_APPEND);
        header("Location: ../../admin/archive.php?error=failed&reason=upload_error");
        exit();
    }

    $allowed_extensions = ['pdf', 'zip'];
    $file_ext = strtolower(pathinfo($_FILES["pdf_file"]["name"], PATHINFO_EXTENSION));

    if (!in_array($file_ext, $allowed_extensions)) {
        header("Location: ../../admin/archive.php?error=failed&reason=format");
        exit();
    }

    // Validate MIME type using finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $_FILES["pdf_file"]["tmp_name"]);
    finfo_close($finfo);
    $allowed_mimes = ['application/pdf', 'application/zip', 'application/x-zip-compressed', 'application/x-compressed', 'multipart/x-zip'];
    if (!in_array($mime_type, $allowed_mimes)) {
        file_put_contents($error_log_path, date('Y-m-d H:i:s') . " - MIME rejection: $mime_type\n", FILE_APPEND);
        header("Location: ../../admin/archive.php?error=failed&reason=format");
        exit();
    }

    // 1b. ZIP inspection: reject malformed archives and ZIP bombs before they
    //     are stored. We cap total declared-uncompressed size, per-ratio, and
    //     entry count so a tiny upload cannot expand into a huge archive that
    //     exhausts disk on later extraction.
    if ($file_ext === 'zip') {
        $upload_size = (int)filesize($_FILES["pdf_file"]["tmp_name"]);
        $zip = new ZipArchive();
        if ($zip->open($_FILES["pdf_file"]["tmp_name"]) !== true) {
            file_put_contents($error_log_path, date('Y-m-d H:i:s') . " - ZIP inspection: could not open archive\n", FILE_APPEND);
            header("Location: ../../admin/archive.php?error=failed&reason=format");
            exit();
        }
        $total_uncompressed = 0;
        $max_ratio = 0.0;
        $entry_count = $zip->numFiles;
        for ($i = 0; $i < $entry_count; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) continue;
            $size = (int)$stat['size'];
            $total_uncompressed += $size;
            if ($size > 0 && $upload_size > 0) {
                $max_ratio = max($max_ratio, $size / $upload_size);
            }
        }
        $zip->close();
        $blocked = $total_uncompressed > (200 * 1024 * 1024) || $entry_count > 500 || $max_ratio > 500;
        if ($blocked) {
            file_put_contents($error_log_path, date('Y-m-d H:i:s') . " - ZIP inspection rejected: entries=$entry_count total_uncompressed={$total_uncompressed} max_ratio=" . round($max_ratio, 1) . "\n", FILE_APPEND);
            header("Location: ../../admin/archive.php?error=failed&reason=format");
            exit();
        }
    }

    // 2. Sanitize Inputs
    $title = trim($_POST['title']);
    $adviser = $_POST['adviser'];
    $year = $_POST['year'];
    $category = $_POST['category'];

    // --- DUPLICATION CHECK ---
    $check_stmt = $conn->prepare("SELECT id FROM capstone_archive WHERE title = ?");
    $check_stmt->bind_param("s", $title);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        header("Location: ../../admin/archive.php?error=duplicate");
        exit();
    }
    $check_stmt->close();
    
    // 3. Setup Directory and Filename
    $target_dir = __DIR__ . "/../../uploads/archive/";
    if (!file_exists($target_dir)) mkdir($target_dir, 0755, true);

    // High-entropy storage name: the old "title_timestamp.ext" scheme made
    // files guessable by anyone who knew (or could brute-force) the title/time.
    // The human-readable title stays in the DB; the on-disk name only needs to
    // be unique and unguessable.
    $final_name = bin2hex(random_bytes(16)) . "." . $file_ext;
    $target_file = $target_dir . $final_name;

    // 4. Move File and Insert
    if (move_uploaded_file($_FILES["pdf_file"]["tmp_name"], $target_file)) {
        $stmt = $conn->prepare("INSERT INTO capstone_archive (title, adviser, year, file_path, category) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            $db_err = $conn->error;
            error_log("Archive upload prepare failed: $db_err");
            die("A database error occurred while preparing to save the archive entry.");
        }
        $stmt->bind_param("sssss", $title, $adviser, $year, $final_name, $category);
        
        if ($stmt->execute()) {
            $archive_id = $conn->insert_id;
            
            // --- AUTOMATED GLOBAL NOTIFICATION BROADCAST ---
            $notif_msg = "Capstone Archive Added: " . $title;

            $broadcast_sql = "
                SELECT id, 'admin' as target_role FROM admin
                UNION
                SELECT id, 'adviser' as target_role FROM adviser
                UNION
                SELECT id, 'group' as target_role FROM `group`
            ";

            $all_users = mysqli_query($conn, $broadcast_sql);

            if ($all_users) {
                $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, user_role, message, is_read, reference_id) VALUES (?, ?, ?, 0, ?)");
                while ($user = mysqli_fetch_assoc($all_users)) {
                    $stmt_notif->bind_param("issi", $user['id'], $user['target_role'], $notif_msg, $archive_id);
                    $stmt_notif->execute();
                }
                $stmt_notif->close();
            }
            
            header("Location: ../../admin/archive.php?status=success");
            exit();
        } else {
            error_log("Archive upload execute failed: " . $stmt->error);
            die("A database error occurred. Please try again later.");
        }
    } else {
        error_log("Archive upload move_uploaded_file failed for title: $title");
        die("An upload error occurred. Please try again later.");
    }
}
header("Location: ../../admin/archive.php?error=failed");
exit();
?>
