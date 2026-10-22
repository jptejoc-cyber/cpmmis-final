<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');
include(__DIR__ . '/../auth/csrf.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    exit(json_encode(['status' => 'error', 'message' => 'Unauthorized access.']));
}

if (isset($_POST['action']) && $_POST['action'] == 'upload') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        header("Location: ../../admin/archive.php?status=csrf_error");
        exit();
    }
    $title    = trim($_POST['title']);
    $group    = trim($_POST['group']);
    $adviser  = trim($_POST['adviser']);
    $year     = trim($_POST['year']);
    $category = trim($_POST['category']);

    if (!isset($_FILES["file"]) || $_FILES["file"]["error"] !== UPLOAD_ERR_OK) {
        header("Location: ../../admin/archive.php?status=upload_error");
        exit();
    }

    // File size check (20MB max)
    $max_size = 20 * 1024 * 1024;
    if ($_FILES["file"]["size"] > $max_size) {
        header("Location: ../../admin/archive.php?status=file_too_large");
        exit();
    }

    $file_name = $_FILES["file"]["name"];
    $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed   = array('pdf');

    if (in_array($file_ext, $allowed)) {
        // MIME validation via finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES["file"]["tmp_name"]);
        finfo_close($finfo);
        if ($mime !== 'application/pdf') {
            header("Location: ../../admin/archive.php?status=invalid_format");
            exit();
        }

        $target_dir = __DIR__ . "/../../uploads/archive/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        // Random, non-guessable storage name (old scheme was timestamp-based
        // and kept attacker-controlled dots in the name). The original file
        // name is a display detail; it must not dictate the on-disk identity.
        $new_file_name = bin2hex(random_bytes(16)) . "." . $file_ext;
        $target_file   = $target_dir . $new_file_name;

        if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {

            $stmt = $conn->prepare("INSERT INTO capstone_archive (title, adviser, year, file_path, category) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $title, $adviser, $year, $new_file_name, $category);

            if ($stmt->execute()) {
                header("Location: ../../admin/archive.php?tab=$category&status=success");
                exit();
            } else {
                die("Database Error: An error occurred while saving to the archive.");
            }
        } else {
            die("Upload Error: Could not move file. Check folder permissions.");
        }
    } else {
        header("Location: ../../admin/archive.php?status=invalid_format");
        exit();
    }
}

if (isset($_POST['delete_id']) && isset($_POST['csrf_token']) && verify_csrf_token($_POST['csrf_token'])) {
    $id = (int)$_POST['delete_id'];

    $stmt = $conn->prepare("SELECT file_path FROM capstone_archive WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if ($data) {
        $full_path = __DIR__ . "/../../uploads/archive/" . basename($data['file_path']);
        if (file_exists($full_path)) {
            unlink($full_path);
        }
    }

    $stmt = $conn->prepare("DELETE FROM capstone_archive WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: ../../admin/archive.php?status=deleted");
    exit();
}
?>
