<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../admin/maintenance.php");
    exit();
}

$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($token)) {
    header("Location: ../../admin/maintenance.php?error=csrf");
    exit();
}

$action = $_POST['action'] ?? '';
$isApi = isset($_POST['api']);

if ($action === 'optimize_db') {
    $tables = [];
    $table_query = $conn->query("SHOW TABLES");
    while ($row = $table_query->fetch_row()) {
        $tables[] = $row[0];
    }

    if (!empty($tables)) {
        $table_list = implode(", ", array_map(function($t) { return "`$t`"; }, $tables));
        $conn->query("OPTIMIZE TABLE $table_list");
    }

    $tables_q = $conn->query("SHOW TABLE STATUS");
    $total_size = 0;
    while ($row = $tables_q->fetch_assoc()) {
        $total_size += ($row['Data_length'] + $row['Index_length']);
    }
    $db_size_mb = round($total_size / 1024 / 1024, 2);

    log_activity($conn, $_SESSION['user_id'], 'admin', $_SESSION['username'] ?? '', "Optimized database tables", "$db_size_mb MB");

    if ($isApi) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'new_size' => $db_size_mb]);
        exit();
    } else {
        header("Location: ../../admin/maintenance.php?optimization=success");
        exit();
    }

} elseif ($action === 'clear_logs') {
    // Only purge audit/system tables. TRUNCATE on `notifications` destroys
    // every user's read/unread alerts, so it is intentionally excluded.
    $conn->query("TRUNCATE TABLE `login_logs`");
    $conn->query("TRUNCATE TABLE `activity_logs`");

    log_activity($conn, $_SESSION['user_id'], 'admin', $_SESSION['username'] ?? '', "Cleared all system logs", '');

    if ($isApi) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit();
    } else {
        header("Location: ../../admin/maintenance.php?purge=success");
        exit();
    }
} else {
    header("Location: ../../admin/maintenance.php");
    exit();
}
?>