<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');

error_reporting(0);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../admin/groups.php?error=failed");
    exit();
}

$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($token)) {
    header("Location: ../../admin/groups.php?error=failed&reason=csrf");
    exit();
}

$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
if (!$group_id) {
    header("Location: ../../admin/groups.php?error=failed");
    exit();
}

$category = isset($_POST['category']) && in_array($_POST['category'], ['best', 'regular'], true)
    ? $_POST['category']
    : 'regular';

// --- Load group + adviser ---
$group_stmt = $conn->prepare("SELECT g.username, g.project_title, u.full_name AS adviser_name
                              FROM `group` g
                              LEFT JOIN adviser u ON g.adviser_id = u.id
                              WHERE g.id = ?");
if (!$group_stmt) { header("Location: ../../admin/group_stats.php?id=$group_id&error=failed"); exit(); }
$group_stmt->bind_param("i", $group_id);
$group_stmt->execute();
$group = $group_stmt->get_result()->fetch_assoc();
$group_stmt->close();

if (!$group) { header("Location: ../../admin/groups.php?error=failed"); exit(); }

$raw_title = trim($group['project_title'] ?? '');
if ($raw_title === '' || $raw_title === 'Untitled Project') {
    $raw_title = $group['username'];
}
$title = htmlspecialchars($raw_title, ENT_QUOTES, 'UTF-8');
$adviser_name = trim($group['adviser_name'] ?? '');

// Academic year convention mirrors archive.php: current Y-(Y+1)
$cur_year = (int)date('Y');
$year_label = $cur_year . "-" . ($cur_year + 1);

// --- Duplication guard (mirrors manual upload_archive.php) ---
$dup = $conn->prepare("SELECT id FROM capstone_archive WHERE title = ?");
$dup->bind_param("s", $title);
$dup->execute();
$dup_exists = $dup->get_result()->num_rows > 0;
$dup->close();
if ($dup_exists) {
    header("Location: ../../admin/group_stats.php?id=$group_id&error=duplicate");
    exit();
}

// --- Ensure all 5 chapters approved ---
$sub_stmt = $conn->prepare("SELECT chapter_number, file_path, document_name
                            FROM group_submissions
                            WHERE group_id = ? AND status = 'approved'
                            ORDER BY chapter_number ASC");
$sub_stmt->bind_param("i", $group_id);
$sub_stmt->execute();
$subs = [];
while ($row = $sub_stmt->get_result()->fetch_assoc()) {
    $subs[(int)$row['chapter_number']] = $row;
}
$sub_stmt->close();

$required = [1, 2, 3, 4, 5];
$approved = [];
foreach ($required as $ch) {
    if (!isset($subs[$ch])) {
        header("Location: ../../admin/group_stats.php?id=$group_id&error=not_complete");
        exit();
    }
    $approved[$ch] = $subs[$ch];
}

$uploads_sub = realpath(__DIR__ . '/../../uploads/submissions/');
$uploads_archive = realpath(__DIR__ . '/../../uploads/archive/');
if (!is_dir($uploads_sub)) { mkdir($uploads_sub, 0755, true); }
if (!is_dir($uploads_archive)) { mkdir($uploads_archive, 0755, true); }

// --- Build + persist the final project compilation (fixes the 'auto_combined' placeholder) ---
$final_filename = 'Final_Project_' . bin2hex(random_bytes(8)) . '.zip';
$persisted_path = $uploads_sub . DIRECTORY_SEPARATOR . $final_filename;

$zip = new ZipArchive();
if ($zip->open($persisted_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    header("Location: ../../admin/group_stats.php?id=$group_id&error=zip");
    exit();
}

foreach ($required as $ch) {
    $sub = $approved[$ch];
    $src = $uploads_sub . DIRECTORY_SEPARATOR . $sub['file_path'];
    if (is_file($src)) {
        $ext = strtolower(pathinfo($sub['file_path'], PATHINFO_EXTENSION));
        $zip->addFile($src, "Chapter_$ch." . $ext);
    } else {
        $zip->addFromString("Chapter_{$ch}_MISSING.txt", "The file for Chapter {$ch} was not found on the server.");
    }
}
$readme = "FINAL PROJECT COMPILATION\n============================\n\n";
$readme .= "Group: " . $group['username'] . "\n";
$readme .= "Title: " . $title . "\n";
$readme .= "Adviser: " . ($adviser_name !== '' ? $adviser_name : 'N/A') . "\n";
$readme .= "Generated: " . date('F d, Y h:i A') . "\n\n";
$readme .= "Contents:\n";
foreach ($required as $ch) {
    $readme .= "  - Chapter_$ch (Original: " . $approved[$ch]['document_name'] . ")\n";
}
$readme .= "\nArchived into the Capstone Vault by the administrator.\n";
$zip->addFromString('README.txt', $readme);
$zip->close();

// Update chapter-6 (final_project) row file_path to the real persisted file.
$upd = $conn->prepare("UPDATE group_submissions SET file_path = ? WHERE group_id = ? AND chapter_number = 6");
$upd->bind_param("si", $final_filename, $group_id);
$upd->execute();
$upd->close();

// --- Copy into the archive vault ---
$archive_filename = bin2hex(random_bytes(16)) . '.zip';
if (!@copy($persisted_path, $uploads_archive . DIRECTORY_SEPARATOR . $archive_filename)) {
    header("Location: ../../admin/group_stats.php?id=$group_id&error=copy");
    exit();
}

// --- Insert archive row ---
$ins = $conn->prepare("INSERT INTO capstone_archive (title, adviser, year, file_path, category) VALUES (?, ?, ?, ?, ?)");
if (!$ins) { header("Location: ../../admin/group_stats.php?id=$group_id&error=failed"); exit(); }
$ins->bind_param("sssss", $title, $adviser_name, $year_label, $archive_filename, $category);
$ins->execute();
$archive_id = $conn->insert_id;
$ins->close();

// --- Broadcast notification (mirrors upload_archive.php) ---
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

header("Location: ../../admin/group_stats.php?id=$group_id&archive=success");
exit();