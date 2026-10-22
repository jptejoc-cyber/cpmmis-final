<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'adviser') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit();
    }
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    // Fetch request scoped to the logged-in adviser only
    $stmt = $conn->prepare("SELECT group_id, adviser_id FROM adviser_requests WHERE id = ? AND adviser_id = ?");
    $stmt->bind_param("ii", $request_id, $_SESSION['user_id']);
    $stmt->execute();
    $req_data = $stmt->get_result()->fetch_assoc();

    if (!$req_data) {
        echo json_encode(['status' => 'error', 'message' => 'Request not found or not assigned to you.']);
        exit();
    }

    $group_id = (int)$req_data['group_id'];
    $adviser_id = (int)$req_data['adviser_id'];

    if ($action === 'accept') {
        $conn->begin_transaction();
        try {
            // Guard against double-accept races: only a still-pending request can be accepted
            $stmt = $conn->prepare("UPDATE adviser_requests SET status = 'accepted' WHERE id = ? AND status = 'pending'");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            if ($stmt->affected_rows === 0) {
                $conn->rollback();
                echo json_encode(['status' => 'error', 'message' => 'This request is no longer pending.']);
                exit();
            }

            $stmt = $conn->prepare("UPDATE `group` SET adviser_id = ? WHERE id = ? AND (adviser_id IS NULL OR adviser_id = 0 OR adviser_id = ?)");
            $stmt->bind_param("iii", $adviser_id, $group_id, $adviser_id);
            $stmt->execute();
            if ($stmt->affected_rows === 0) {
                // Another adviser won the race — this group is already assigned.
                $conn->rollback();
                echo json_encode(['status' => 'error', 'message' => 'This group has already been assigned to another adviser.']);
                exit();
            }

            // Auto-decline this group's other pending requests so another adviser cannot accept later
            $auto_reason = "Adviser slot already filled.";
            $stmt = $conn->prepare("UPDATE adviser_requests SET status = 'declined', decline_reason = ? WHERE group_id = ? AND id != ? AND status = 'pending'");
            $stmt->bind_param("sii", $auto_reason, $group_id, $request_id);
            $stmt->execute();

            $conn->commit();

            $stmt2 = $conn->prepare("SELECT username FROM `group` WHERE id = ?");
            $stmt2->bind_param("i", $group_id);
            $stmt2->execute();
            $gn = $stmt2->get_result()->fetch_assoc()['username'] ?? "ID $group_id";
            log_activity($conn, $adviser_id, 'adviser', $_SESSION['username'] ?? '', "Accepted group", "$gn as advisee");
            echo json_encode(['status' => 'success', 'message' => 'Group accepted']);
        } catch (Exception $e) {
            @$conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'Database error occurred.']);
        }
    } else {
        // Store reason in the dedicated column; never overwrite `message`, which holds the student's uploaded letter path
        $stmt = $conn->prepare("UPDATE adviser_requests SET status = 'declined', decline_reason = ? WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("si", $reason, $request_id);
        if ($stmt->execute()) {
            $stmt2 = $conn->prepare("SELECT username FROM `group` WHERE id = ?");
            $stmt2->bind_param("i", $group_id);
            $stmt2->execute();
            $gn = $stmt2->get_result()->fetch_assoc()['username'] ?? "ID $group_id";
            log_activity($conn, $adviser_id, 'adviser', $_SESSION['username'] ?? '', "Declined group", $gn);
            echo json_encode(['status' => 'success', 'message' => 'Request declined']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error occurred.']);
        }
    }
}
?>