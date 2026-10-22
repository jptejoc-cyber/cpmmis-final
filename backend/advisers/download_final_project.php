<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['adviser', 'admin'])) {
    http_response_code(403);
    die("Unauthorized access.");
}

$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
if (!$group_id) {
    die("Invalid group ID.");
}

// Advisers may only compile their own advisees; admins are unrestricted.
if ($_SESSION['role'] === 'adviser') {
    $own_stmt = $conn->prepare("SELECT id FROM `group` WHERE id = ? AND adviser_id = ?");
    $own_stmt->bind_param("ii", $group_id, $_SESSION['user_id']);
    $own_stmt->execute();
    if ($own_stmt->get_result()->num_rows === 0) {
        http_response_code(403);
        die("You are not the adviser of this group.");
    }
}

$stmt = $conn->prepare("SELECT username FROM `group` WHERE id = ?");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$group_data = $stmt->get_result()->fetch_assoc();
if (!$group_data) {
    die("Group not found.");
}
$group_name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $group_data['username']);

$stmt = $conn->prepare("
    SELECT chapter_number, file_path, document_name
    FROM group_submissions
    WHERE group_id = ? AND status = 'approved'
    ORDER BY chapter_number ASC
");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$subs_result = $stmt->get_result();

if ($subs_result->num_rows === 0) {
    die("No approved chapters found for this group.");
}

$submissions = [];
while ($row = $subs_result->fetch_assoc()) {
    $submissions[$row['chapter_number']] = $row;
}

$required = [1, 2, 3, 4, 5];
$missing = array_diff($required, array_keys($submissions));
if (!empty($missing)) {
    die("Not all chapters are approved yet. Missing: Chapter(s) " . implode(', ', $missing));
}

$zip_filename = $group_name . '_Final_Project_' . date('Ymd') . '.zip';
// Use an unguessable temp name so the predictable final filename cannot be
// pre-placed (symlink/race) by another local user, and concurrent requests
// never collide with each other's temp files.
$zip_temp = tempnam(sys_get_temp_dir(), 'cpmmis_fp_');
if ($zip_temp === false) {
    die("Could not create temporary file.");
}

$zip = new ZipArchive();
if ($zip->open($zip_temp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    @unlink($zip_temp);
    die("Could not create ZIP file.");
}

$uploads_dir = __DIR__ . '/../../uploads/submissions/';
$all_found = true;

foreach ($required as $ch_num) {
    $sub = $submissions[$ch_num];
    $file_path = $uploads_dir . $sub['file_path'];

    if (file_exists($file_path)) {
        $ext = strtolower(pathinfo($sub['file_path'], PATHINFO_EXTENSION));
        $entry_name = "Chapter_{$ch_num}." . $ext;
        $zip->addFile($file_path, $entry_name);
    } else {
        $all_found = false;
        $zip->addFromString("Chapter_{$ch_num}_MISSING.txt", "The file for Chapter {$ch_num} was not found on the server.");
    }
}

$readme_content = "FINAL PROJECT COMPILATION\n";
$readme_content .= "=========================\n\n";
$readme_content .= "Group: " . $group_data['username'] . "\n";
$readme_content .= "Generated: " . date('F d, Y h:i A') . "\n\n";
$readme_content .= "Contents:\n";
foreach ($required as $ch_num) {
    $sub = $submissions[$ch_num];
    $readme_content .= "  - Chapter_{$ch_num} (Original: " . $sub['document_name'] . ")\n";
}
$readme_content .= "\nAll files approved by adviser.\n";
$zip->addFromString('README.txt', $readme_content);

if (!$zip->close()) {
    @unlink($zip_temp);
    die("Failed to finalize ZIP file.");
}

if (file_exists($zip_temp)) {
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($zip_filename) . '"');
    header('Content-Length: ' . filesize($zip_temp));
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');
    ob_clean();
    flush();
    $fh = @fopen($zip_temp, 'rb');
    if ($fh) {
        while (!feof($fh)) {
            echo fread($fh, 8192);
        }
        fclose($fh);
    }
    @unlink($zip_temp);
    exit();
} else {
    @unlink($zip_temp);
    die("Failed to generate ZIP file.");
}
?>