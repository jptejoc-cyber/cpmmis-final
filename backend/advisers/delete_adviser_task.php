<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'adviser') {
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

$task_id = isset($input['id']) ? (int)$input['id'] : 0;
if (!$task_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid task ID']);
    exit();
}

// Ownership-scoped fetch: an adviser may only delete their own tasks
$stmt = $conn->prepare("SELECT caption, file_path FROM adviser_tasks WHERE id = ? AND adviser_id = ?");
$stmt->bind_param("ii", $task_id, $_SESSION['user_id']);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();

if (!$task) {
    echo json_encode(['status' => 'error', 'message' => 'Task not found or not yours.']);
    exit();
}

// Safety rail: never silently destroy student work submitted against this task
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM group_submissions WHERE task_id = ?");
$stmt->bind_param("i", $task_id);
$stmt->execute();
$sub_count = (int)$stmt->get_result()->fetch_assoc()['cnt'];

if ($sub_count > 0) {
    echo json_encode([
        'status' => 'error',
        'message' => "Cannot delete: {$sub_count} student submission(s) are attached to this task. Extend its deadline instead, or contact the admin."
    ]);
    exit();
}

$conn->begin_transaction();
try {
    // Remove edit history (covers installs where no FK cascade exists)
    $stmt = $conn->prepare("DELETE FROM task_edit_history WHERE task_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare("DELETE FROM adviser_tasks WHERE id = ?");
    $stmt->bind_param("i", $task_id);
    $stmt->execute();

    $conn->commit();
} catch (Exception $e) {
    @$conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Database error while deleting.']);
    exit();
}

// DB commit succeeded — now remove the orphaned attachment file from disk
if (!empty($task['file_path'])) {
    $file = basename($task['file_path']);
    $path = __DIR__ . '/../../uploads/tasks/' . $file;
    if (is_file($path)) {
        @unlink($path);
    }
}

log_activity($conn, $_SESSION['user_id'], 'adviser', $_SESSION['username'] ?? '', "Deleted task", "'{$task['caption']}'");

echo json_encode(['status' => 'success', 'message' => 'Task deleted.']);
