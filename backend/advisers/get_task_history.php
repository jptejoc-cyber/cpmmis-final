<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'adviser') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$adviser_id = (int)$_SESSION['user_id'];
$task_id = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;

if ($task_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid task_id']);
    exit();
}

$stmt = $conn->prepare("SELECT id FROM adviser_tasks WHERE id = ? AND adviser_id = ?");
$stmt->bind_param("ii", $task_id, $adviser_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Task not found or access denied']);
    exit();
}

// Gracefully handle missing table
$hist_stmt = @$conn->prepare("
    SELECT h.id, h.old_deadline, h.new_deadline, h.edited_at, a.full_name AS edited_by_name
    FROM task_edit_history h
    LEFT JOIN adviser a ON h.edited_by = a.id
    WHERE h.task_id = ?
    ORDER BY h.edited_at DESC
");

$history = [];

if ($hist_stmt) {
    $hist_stmt->bind_param("i", $task_id);
    $hist_stmt->execute();
    $result = $hist_stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $history[] = [
            'id' => (int)$row['id'],
            'old_deadline' => $row['old_deadline'],
            'new_deadline' => $row['new_deadline'],
            'edited_at' => $row['edited_at'],
            'edited_by_name' => $row['edited_by_name'] ?? 'Unknown'
        ];
    }
}

echo json_encode($history);
