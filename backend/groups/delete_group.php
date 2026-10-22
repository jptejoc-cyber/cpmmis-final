<?php
// Redirect to approve_group.php which now handles both approve and delete via POST
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

$stmt = $conn->prepare("SELECT username FROM `group` WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();

if (!$group) {
    echo json_encode(['status' => 'error', 'message' => 'Group not found']);
    exit();
}

// Collect every file this group owns so the delete below can unlink them.
$files_to_delete = [];

$stmt = $conn->prepare("SELECT file_path FROM group_submissions WHERE group_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    if (!empty($row['file_path'])) {
        $files_to_delete[] = __DIR__ . '/../../uploads/submissions/' . basename($row['file_path']);
    }
}
$stmt->close();

$stmt = $conn->prepare("SELECT message FROM adviser_requests WHERE group_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    if (strpos($row['message'] ?? '', 'uploads/letters/') === 0) {
        $files_to_delete[] = __DIR__ . '/../../' . $row['message'];
    }
}
$stmt->close();

$stmt = $conn->prepare("SELECT file_path FROM admin_letters WHERE group_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    if (!empty($row['file_path'])) {
        $files_to_delete[] = __DIR__ . '/../../uploads/letters/' . basename($row['file_path']);
    }
}
$stmt->close();

$cleanup_tables = ['group_submissions', 'meeting_requests', 'adviser_requests', 'admin_letters'];

$conn->begin_transaction();
try {
    foreach ($cleanup_tables as $table) {
        $stmt = $conn->prepare("DELETE FROM `$table` WHERE group_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ? AND user_role = 'group'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM group_milestones WHERE group_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM messages WHERE (sender_id = ? AND sender_role = 'group') OR (receiver_id = ? AND receiver_role = 'group')");
    $stmt->bind_param("ii", $id, $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM `group` WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $deleted = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$deleted) {
        throw new Exception('Group row delete failed');
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
    exit();
}

if ($deleted) {
    // DB rows are gone — release the referenced files so a deleted group does
    // not leave orphaned uploads behind. Best-effort only: files must not be
    // able to break the delete that already succeeded.
    foreach ($files_to_delete as $file) {
        if (is_file($file)) @unlink($file);
    }
    log_activity($conn, $_SESSION['user_id'], 'admin', $_SESSION['username'] ?? '', "Deleted group", $group['username']);
    echo json_encode(['status' => 'success']);
    exit();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
    exit();
}
?>