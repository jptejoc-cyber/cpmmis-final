<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');
include(__DIR__ . '/../auth/csrf.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'group') {
    header("Location: ../../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        header("Location: ../../group/meetings.php?error=csrf");
        exit();
    }
    $group_id = (int)$_SESSION['user_id'];
    $meeting_time_raw = trim($_POST['meeting_time'] ?? '');
    $agenda = trim($_POST['agenda'] ?? '');

    if (empty($agenda)) {
        header("Location: ../../group/meetings.php?error=invalid_agenda");
        exit();
    }

    $meeting_time = str_replace('T', ' ', $meeting_time_raw) . ':00';
    $meeting_ts = strtotime($meeting_time);
    if (empty($meeting_time_raw) || $meeting_ts === false || date('Y-m-d H:i', $meeting_ts) !== substr($meeting_time, 0, 16)) {
        header("Location: ../../group/meetings.php?error=invalid_time");
        exit();
    }
    if ($meeting_ts < time()) {
        header("Location: ../../group/meetings.php?error=past_time");
        exit();
    }

    $stmt = $conn->prepare("SELECT adviser_id FROM `group` WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $group_data = $stmt->get_result()->fetch_assoc();
    $adviser_id = $group_data['adviser_id'] ?? 0;

    if (empty($adviser_id)) {
        header("Location: ../../group/meetings.php?error=no_adviser");
        exit();
    }

    $stmt = $conn->prepare("SELECT id FROM meeting_requests WHERE group_id = ? AND meeting_time = ? LIMIT 1");
    $stmt->bind_param("is", $group_id, $meeting_time);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        header("Location: ../../group/meetings.php?error=duplicate");
        exit();
    }

    $stmt = $conn->prepare("SELECT id FROM meeting_requests WHERE adviser_id = ? AND status IN ('pending','accepted') AND ABS(TIMESTAMPDIFF(MINUTE, meeting_time, ?)) < 60 LIMIT 1");
    $stmt->bind_param("is", $adviser_id, $meeting_time);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        header("Location: ../../group/meetings.php?error=conflict");
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO meeting_requests (group_id, adviser_id, meeting_time, agenda, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
    $stmt->bind_param("iiss", $group_id, $adviser_id, $meeting_time, $agenda);

    if ($stmt->execute()) {
        $meeting_id = $conn->insert_id;
        $stmt = $conn->prepare("SELECT username FROM `group` WHERE id = ?");
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        $group_name = $stmt->get_result()->fetch_assoc()['username'] ?? 'A group';
        $notif_msg = "Meeting Request from: " . $group_name;
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, message, is_read, reference_id, created_at) VALUES (?, 'adviser', ?, 0, ?, NOW())");
        $stmt->bind_param("isi", $adviser_id, $notif_msg, $meeting_id);
        $stmt->execute();

        header("Location: ../../group/meetings.php?status=success");
        exit();
    } else {
        header("Location: ../../group/meetings.php?error=failed");
        exit();
    }
}
