<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

$admin_id = (int)$_SESSION['user_id'];
$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
$broadcast_all = isset($_POST['broadcast_all']) && $_POST['broadcast_all'] == '1';
$raw_title = isset($_POST['letter_title']) ? $_POST['letter_title'] : '';
$raw_description = isset($_POST['letter_description']) ? $_POST['letter_description'] : '';

if (!$raw_title) {
    echo json_encode(['success' => false, 'message' => 'Letter title is required']);
    exit();
}

if (!$broadcast_all && !$group_id) {
    echo json_encode(['success' => false, 'message' => 'Please select a recipient group or check "Post to All Groups"']);
    exit();
}

if (!isset($_FILES['letter_file']) || $_FILES['letter_file']['error'] === UPLOAD_ERR_NO_FILE) {
    echo json_encode(['success' => false, 'message' => 'Please attach a PDF document before posting the letter']);
    exit();
}

if (!$broadcast_all) {
    $stmt = $conn->prepare("SELECT id FROM `group` WHERE id = ?");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Group not found']);
        exit();
    }
}

$file = $_FILES['letter_file'];
$max_size = 10 * 1024 * 1024;
$allowed_types = ['application/pdf'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds server upload_max_filesize limit',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was selected',
        UPLOAD_ERR_NO_TMP_DIR => 'Server missing temp directory',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
    ];
    echo json_encode(['success' => false, 'message' => $upload_errors[$file['error']] ?? 'Unknown upload error (' . $file['error'] . ')']);
    exit();
}

if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit']);
    exit();
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if ($ext !== 'pdf' || $mime !== 'application/pdf') {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only PDF files are allowed.']);
    exit();
}

$uploads_dir = __DIR__ . '/../../uploads/letters/';
if (!is_dir($uploads_dir)) {
    if (!mkdir($uploads_dir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create uploads directory']);
        exit();
    }
}

$filename = 'letter_' . ($broadcast_all ? 'all' : $group_id) . '_' . time() . '_' . uniqid() . '.' . $ext;
$file_path = $uploads_dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
    exit();
}

$orig_name = $file['name'];
$file_size = (int)$file['size'];

// Helper: insert letter + notifications using prepared statements
function insertLetterAndNotify($conn, $gid, $admin_id, $title, $desc, $fname, $orig_name, $fsize) {
    $stmt = $conn->prepare("INSERT INTO admin_letters (group_id, admin_id, title, description, file_path, file_name, file_size) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissssi", $gid, $admin_id, $title, $desc, $fname, $orig_name, $fsize);
    if (!$stmt->execute()) {
        return false;
    }
    $lid = (int)$stmt->insert_id;

    // Notify group
    $msg = "New letter '" . $title . "' has been uploaded for your group";
    $nstmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, message, is_read, reference_id, created_at) VALUES (?, 'group', ?, 0, ?, NOW())");
    $nstmt->bind_param("isi", $gid, $msg, $lid);
    $nstmt->execute();

    // Notify adviser if exists
    $astmt = $conn->prepare("SELECT adviser_id FROM `group` WHERE id = ?");
    $astmt->bind_param("i", $gid);
    $astmt->execute();
    $arow = $astmt->get_result()->fetch_assoc();
    if ($arow && $arow['adviser_id']) {
        $adv_id = (int)$arow['adviser_id'];
        $amsg = "Letter '" . $title . "' has been uploaded for your group by admin";
        $nstmt2 = $conn->prepare("INSERT INTO notifications (user_id, user_role, message, is_read, reference_id, created_at) VALUES (?, 'adviser', ?, 0, ?, NOW())");
        $nstmt2->bind_param("isi", $adv_id, $amsg, $lid);
        $nstmt2->execute();
    }

    return true;
}

if ($broadcast_all) {
    $all_groups = $conn->prepare("SELECT id FROM `group` WHERE status = 'active' ORDER BY id");
    $all_groups->execute();
    $groups_result = $all_groups->get_result();
    $all_ok = true;
    while ($g = $groups_result->fetch_assoc()) {
        $gid = (int)$g['id'];
        if (!insertLetterAndNotify($conn, $gid, $admin_id, $raw_title, $raw_description, $filename, $orig_name, $file_size)) {
            $all_ok = false;
        }
    }
    log_activity($conn, $admin_id, 'admin', $_SESSION['username'] ?? '', "Broadcast letter", $raw_title);
    echo json_encode(['success' => true, 'message' => $all_ok ? 'Letter broadcast to all groups successfully' : 'Letter broadcast completed with some errors']);
} else {
    if (insertLetterAndNotify($conn, $group_id, $admin_id, $raw_title, $raw_description, $filename, $orig_name, $file_size)) {
        log_activity($conn, $admin_id, 'admin', $_SESSION['username'] ?? '', "Uploaded letter", "'$raw_title' for group ID $group_id");
        echo json_encode(['success' => true, 'message' => 'Letter uploaded successfully']);
    } else {
        unlink($file_path);
        error_log("upload_letter DB error: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'A database error occurred. Please try again later.']);
    }
}
?>
