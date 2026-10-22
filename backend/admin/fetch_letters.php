<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

if (!$group_id) {
    echo json_encode(['success' => false, 'message' => 'No group ID provided']);
    exit();
}

$stmt = $conn->prepare("SELECT al.*, a.full_name as admin_name FROM admin_letters al LEFT JOIN admin a ON al.admin_id = a.id WHERE al.group_id = ? ORDER BY al.created_at DESC");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$letters_q = $stmt->get_result();

$letters = [];
while ($letter = mysqli_fetch_assoc($letters_q)) {
    $letter['file_size_formatted'] = '';
    if ($letter['file_size']) {
        $size = (int)$letter['file_size'];
        if ($size < 1024) {
            $letter['file_size_formatted'] = $size . ' B';
        } elseif ($size < 1048576) {
            $letter['file_size_formatted'] = round($size / 1024, 1) . ' KB';
        } else {
            $letter['file_size_formatted'] = round($size / 1048576, 1) . ' MB';
        }
    }
    $letter['created_at_formatted'] = date('M d, Y | g:i A', strtotime($letter['created_at']));
    $letters[] = $letter;
}

echo json_encode(['success' => true, 'letters' => $letters]);
?>
