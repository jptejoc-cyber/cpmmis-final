<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'adviser'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
if (!$group_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid group ID']);
    exit();
}

// Advisers may only view the health status of their OWN assigned groups.
$stmt = $conn->prepare("SELECT adviser_id FROM `group` WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$owner = $stmt->get_result()->fetch_assoc();
if (!$owner) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Group not found']);
    exit();
}
if ($_SESSION['role'] === 'adviser' && (int)$owner['adviser_id'] !== (int)$_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// Calculate Health Status dynamically
$health_status = 'on_track';

// A group that has ALL FIVE chapters (1-5) approved is considered fully
// complete, which overrides any pending/at-risk task state.
$fin_stmt = $conn->prepare("SELECT COUNT(DISTINCT chapter_number) AS c FROM group_submissions WHERE group_id = ? AND status = 'approved' AND chapter_number BETWEEN 1 AND 5");
$fin_stmt->bind_param("i", $group_id);
$fin_stmt->execute();
$fin_row = $fin_stmt->get_result()->fetch_assoc();
if ((int)$fin_row['c'] >= 5) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'health_status' => 'completed']);
    exit();
}

$group_adviser = $owner;

if ($group_adviser && $group_adviser['adviser_id']) {
    $adviser_id = (int)$group_adviser['adviser_id'];
    $stmt = $conn->prepare("SELECT id, deadline FROM adviser_tasks WHERE adviser_id = ? AND task_type = 'chapter'");
    $stmt->bind_param("i", $adviser_id);
    $stmt->execute();
    $tasks_q = $stmt->get_result();
    
    if ($tasks_q && $tasks_q->num_rows > 0) {
        $now = time();
        while ($t = $tasks_q->fetch_assoc()) {
            $task_id = (int)$t['id'];
            if (empty($t['deadline'])) continue;
            
            $deadline_time = strtotime($t['deadline']);
            $diff_days = ($deadline_time - $now) / 86400;

            $sub_stmt = $conn->prepare("SELECT id FROM group_submissions WHERE task_id = ? AND group_id = ?");
            $sub_stmt->bind_param("ii", $task_id, $group_id);
            $sub_stmt->execute();
            $has_submitted = ($sub_stmt->get_result()->num_rows > 0);

            if (!$has_submitted) {
                if ($diff_days < 0) {
                    $health_status = 'at_risk';
                    break;
                } elseif ($diff_days <= 3) {
                    if ($health_status !== 'at_risk') {
                        $health_status = 'needs_attention';
                    }
                }
            }
        }
    }
}

header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'health_status' => $health_status
]);
?>
