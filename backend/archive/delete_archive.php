<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');
include(__DIR__ . '/../auth/csrf.php');

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

if (!isset($input['csrf_token']) || !verify_csrf_token($input['csrf_token'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit();
}

$archive_id = isset($input['id']) ? (int)$input['id'] : 0;
if (!$archive_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
    exit();
}

$stmt = $conn->prepare("SELECT title, file_path FROM capstone_archive WHERE id = ?");
$stmt->bind_param("i", $archive_id);
$stmt->execute();
$archive_data = $stmt->get_result()->fetch_assoc();

if (!$archive_data) {
    echo json_encode(['status' => 'error', 'message' => 'Record not found']);
    exit();
}

$title_to_remove = trim($archive_data['title']);

$stmt = $conn->prepare("DELETE FROM capstone_archive WHERE id = ?");
$stmt->bind_param("i", $archive_id);
$stmt->execute();
$deleted = $stmt->affected_rows > 0;
$stmt->close();

if ($deleted) {
    // DB row removed first; only now release the file so a failed DELETE
    // cannot leave a row pointing at a missing file.
    $file_path = __DIR__ . "/../../uploads/archive/" . basename($archive_data['file_path']);
    if (is_file($file_path)) {
        @unlink($file_path);
    }

    // Scoped notification cleanup: archive broadcasts store the archive id in
    // reference_id, so remove exactly those rows instead of substring-matching
    // every user's notifications by title.
    $stmt2 = $conn->prepare("DELETE FROM notifications WHERE message LIKE ? AND reference_id = ?");
    $pattern = 'Capstone Archive Added: %';
    $stmt2->bind_param("si", $pattern, $archive_id);
    $stmt2->execute();
    echo json_encode(['status' => 'success']);
    exit();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete archive entry.']);
    exit();
}
?>