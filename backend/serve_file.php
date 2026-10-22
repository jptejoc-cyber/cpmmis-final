<?php
include(__DIR__ . '/session_init.php');
include(__DIR__ . '/db_config.php');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'adviser', 'group'])) {
    header('HTTP/1.0 401 Unauthorized');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];

$file = isset($_GET['file']) ? basename($_GET['file']) : '';
$type = isset($_GET['type']) ? $_GET['type'] : 'submissions';
$allowed_types = ['archive', 'letters', 'submissions', 'tasks'];
if (!in_array($type, $allowed_types)) {
    header('HTTP/1.0 400 Bad Request');
    exit;
}

$base = realpath(__DIR__ . '/../uploads/' . $type);
$path = ($base !== false) ? realpath($base . '/' . $file) : false;
if (!$path || strpos($path, $base) !== 0 || !file_exists($path)) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

function file_owner_allowed(mysqli $conn, $type, $file, $role, $user_id) {
    if ($type === 'tasks') {
        if ($role === 'adviser') {
            $stmt = $conn->prepare("SELECT id FROM adviser_tasks WHERE file_path = ? AND adviser_id = ? LIMIT 1");
            $stmt->bind_param("si", $file, $user_id);
            $stmt->execute();
            return (bool)$stmt->get_result()->fetch_assoc();
        }
        if ($role === 'group') {
            $stmt = $conn->prepare("SELECT at.id FROM adviser_tasks at JOIN `group` g ON at.adviser_id = g.adviser_id WHERE at.file_path = ? AND g.id = ? LIMIT 1");
            $stmt->bind_param("si", $file, $user_id);
            $stmt->execute();
            return (bool)$stmt->get_result()->fetch_assoc();
        }
    }
    if ($type === 'submissions') {
        if ($role === 'group') {
            $stmt = $conn->prepare("SELECT id FROM group_submissions WHERE file_path = ? AND group_id = ? LIMIT 1");
            $stmt->bind_param("si", $file, $user_id);
            $stmt->execute();
            return (bool)$stmt->get_result()->fetch_assoc();
        }
        if ($role === 'adviser') {
            $stmt = $conn->prepare("SELECT gs.id FROM group_submissions gs JOIN `group` g ON gs.group_id = g.id WHERE gs.file_path = ? AND g.adviser_id = ? LIMIT 1");
            $stmt->bind_param("si", $file, $user_id);
            $stmt->execute();
            return (bool)$stmt->get_result()->fetch_assoc();
        }
    }
    if ($type === 'letters') {
        if ($role === 'group') {
            $stmt = $conn->prepare("SELECT id FROM admin_letters WHERE file_path = ? AND group_id = ? LIMIT 1");
            $stmt->bind_param("si", $file, $user_id);
            $stmt->execute();
            return (bool)$stmt->get_result()->fetch_assoc();
        }
    }
    return false;
}

if ($type === 'archive') {
    // Archives are intentionally system-wide, but ONLY files actually
    // registered in capstone_archive may be served. A leftover/loose file on
    // disk must not be downloadable just by guessing its name, sidestepping
    // the ownership check entirely.
    $ar = $conn->prepare("SELECT id FROM capstone_archive WHERE file_path = ? LIMIT 1");
    $ar->bind_param("s", $file);
    $ar->execute();
    $registered = $ar->get_result()->fetch_assoc();
    $ar->close();
    if (!$registered) {
        header('HTTP/1.0 403 Forbidden');
        exit;
    }
} elseif ($role !== 'admin' && !file_owner_allowed($conn, $type, $file, $role, $user_id)) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

$mime = mime_content_type($path);
$safe_name = preg_replace('/[\\x00-\\x1F\\x7F"\\\\;]/', '_', basename($path));
$safe_name = str_replace("\r", '', str_replace("\n", '', $safe_name));
if ($safe_name === '') $safe_name = 'download';
header('Content-Type: ' . $mime);
// RFC 5987 UTF-8 fallback so non-ASCII names survive header handling
header("Content-Disposition: inline; filename=\"$safe_name\"; filename*=UTF-8''" . rawurlencode($safe_name));
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($path));
readfile($path);
