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

$group_id = isset($input['id']) ? (int)$input['id'] : 0;
$action = $input['action'] ?? 'approve';

if (!$group_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid group ID']);
    exit();
}

if ($action === 'approve') {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE `group` SET status = 'active' WHERE id = ?");
        $stmt->bind_param("i", $group_id);
        $stmt->execute();

        $msg = "Your group registration has been approved! You now have full access.";
        $stmt2 = $conn->prepare("INSERT INTO notifications (user_id, user_role, message, is_read) VALUES (?, 'group', ?, 0)");
        $stmt2->bind_param("is", $group_id, $msg);
        $stmt2->execute();

        $conn->commit();

        $stmt3 = $conn->prepare("SELECT username, full_name FROM `group` WHERE id = ?");
        $stmt3->bind_param("i", $group_id);
        $stmt3->execute();
        $g = $stmt3->get_result()->fetch_assoc();
        $gname = $g['username'] ?? $g['full_name'] ?? "ID $group_id";
        log_activity($conn, $_SESSION['user_id'], 'admin', $_SESSION['username'] ?? '', "Approved group", $gname);

        echo json_encode(['status' => 'success', 'message' => 'Group approved']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
    exit();

} elseif ($action === 'decline') {
    $stmt = $conn->prepare("SELECT username, full_name FROM `group` WHERE id = ?");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $g = $stmt->get_result()->fetch_assoc();
    $gname = $g['username'] ?? $g['full_name'] ?? "ID $group_id";

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE `group` SET status = 'declined' WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("i", $group_id);
        $stmt->execute();

        $msg = "Your group registration has been declined by the administrator.";
        $stmt2 = $conn->prepare("INSERT INTO notifications (user_id, user_role, message, is_read) VALUES (?, 'group', ?, 0)");
        $stmt2->bind_param("is", $group_id, $msg);
        $stmt2->execute();

        $conn->commit();
        log_activity($conn, $_SESSION['user_id'], 'admin', $_SESSION['username'] ?? '', "Declined group registration", $gname);
        echo json_encode(['status' => 'success', 'message' => 'Group registration declined']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
    exit();

} elseif ($action === 'delete') {
    $stmt = $conn->prepare("SELECT username, full_name FROM `group` WHERE id = ?");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $g = $stmt->get_result()->fetch_assoc();
    $gname = $g['username'] ?? $g['full_name'] ?? "ID $group_id";

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("DELETE FROM adviser_requests WHERE group_id = ?");
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ? AND user_role = 'group'");
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        $stmt = $conn->prepare("DELETE FROM `group` WHERE id = ?");
        $stmt->bind_param("i", $group_id);
        $stmt->execute();

        $conn->commit();
        log_activity($conn, $_SESSION['user_id'], 'admin', $_SESSION['username'] ?? '', "Deleted group", $gname);
        echo json_encode(['status' => 'success', 'message' => 'Group deleted']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
    exit();

} elseif ($action === 'ban') {
    $stmt = $conn->prepare("SELECT username FROM `group` WHERE id = ?");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $g = $stmt->get_result()->fetch_assoc();
    if (!$g) {
        echo json_encode(['status' => 'error', 'message' => 'Group not found']);
        exit();
    }
    $gname = $g['username'];

    $days = $input['days'] ?? null;
    if ($days === 'permanent') {
        $until = '9999-12-31 23:59:59';
        $stmt = $conn->prepare("UPDATE `group` SET banned_until = ? WHERE id = ?");
        $stmt->bind_param("si", $until, $group_id);
        $desc = 'permanently';
    } else {
        $days = (int)$days;
        if ($days < 1 || $days > 3650) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid suspension length']);
            exit();
        }
        $stmt = $conn->prepare("UPDATE `group` SET banned_until = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id = ?");
        $stmt->bind_param("ii", $days, $group_id);
        $desc = "for {$days} day(s)";
    }

    if ($stmt->execute()) {
        $msg = "Your account has been suspended {$desc} by the administrator.";
        $nstmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, message, is_read) VALUES (?, 'group', ?, 0)");
        $nstmt->bind_param("is", $group_id, $msg);
        $nstmt->execute();

        log_activity($conn, $_SESSION['user_id'], 'admin', $_SESSION['username'] ?? '', "Suspended group", "{$gname} {$desc}");
        echo json_encode(['status' => 'success', 'message' => "Account suspended {$desc}"]);
        exit();
    }
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
    exit();

} elseif ($action === 'unban') {
    $stmt = $conn->prepare("SELECT username FROM `group` WHERE id = ?");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $g = $stmt->get_result()->fetch_assoc();
    $gname = $g['username'] ?? "ID $group_id";

    $stmt = $conn->prepare("UPDATE `group` SET banned_until = NULL WHERE id = ?");
    $stmt->bind_param("i", $group_id);
    if ($stmt->execute()) {
        $msg = "Your account suspension has been lifted. Welcome back!";
        $nstmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, message, is_read) VALUES (?, 'group', ?, 0)");
        $nstmt->bind_param("is", $group_id, $msg);
        $nstmt->execute();

        log_activity($conn, $_SESSION['user_id'], 'admin', $_SESSION['username'] ?? '', "Lifted suspension", $gname);
        echo json_encode(['status' => 'success', 'message' => 'Suspension lifted']);
        exit();
    }
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
    exit();

} elseif ($action === 'set_password') {
    $new_password = $input['password'] ?? '';
    $pw_err = validate_password($new_password);
    if ($pw_err !== '') {
        echo json_encode(['status' => 'error', 'message' => $pw_err]);
        exit();
    }

    $stmt = $conn->prepare("SELECT username FROM `group` WHERE id = ?");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $g = $stmt->get_result()->fetch_assoc();
    if (!$g) {
        echo json_encode(['status' => 'error', 'message' => 'Group not found']);
        exit();
    }

    $hash = password_hash($new_password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE `group` SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hash, $group_id);

    if ($stmt->execute()) {
        log_activity($conn, $_SESSION['user_id'], 'admin', $_SESSION['username'] ?? '', "Reset group password", $g['username']);
        echo json_encode(['status' => 'success', 'message' => 'Password updated successfully']);
        exit();
    }
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
    exit();
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
?>