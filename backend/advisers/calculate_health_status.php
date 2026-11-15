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
$health = calculate_group_health($conn, $group_id);

header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'health_status' => $health['status']
]);
?>
