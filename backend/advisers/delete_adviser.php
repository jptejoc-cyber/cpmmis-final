<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');
include(__DIR__ . '/../auth/csrf.php');

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
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

$id = isset($input['id']) ? (int)$input['id'] : 0;
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
    exit();
}

$stmt = $conn->prepare("SELECT full_name FROM adviser WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$adviser = $stmt->get_result()->fetch_assoc();

if (!$adviser) {
    echo json_encode(['status' => 'error', 'message' => 'Adviser not found']);
    exit();
}

// Task files the adviser uploaded live on disk (uploads/tasks/); collect their
// paths so the delete below can unlink them too.
$files_to_delete = [];
$stmt = $conn->prepare("SELECT file_path FROM adviser_tasks WHERE adviser_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    if (!empty($row['file_path'])) {
        $files_to_delete[] = __DIR__ . '/../../uploads/tasks/' . basename($row['file_path']);
    }
}
$stmt->close();

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("DELETE FROM adviser_requests WHERE adviser_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE `group` SET adviser_id = NULL WHERE adviser_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM adviser_tasks WHERE adviser_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM meeting_requests WHERE adviser_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ? AND user_role = 'adviser'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM messages WHERE (sender_id = ? AND sender_role = 'adviser') OR (receiver_id = ? AND receiver_role = 'adviser')");
    $stmt->bind_param("ii", $id, $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM adviser WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $deleted = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$deleted) {
        throw new Exception('Adviser row delete failed');
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
    exit();
}

if ($deleted) {
    foreach ($files_to_delete as $file) {
        if (is_file($file)) @unlink($file);
    }
    $full_name = $adviser['full_name'];
    log_activity($conn, $_SESSION['user_id'], 'admin', $_SESSION['username'] ?? '', "Deleted adviser", $full_name);
    echo json_encode(['status' => 'success']);
    exit();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
    exit();
}
?>