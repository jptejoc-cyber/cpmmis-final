<?php
function addNotification($conn, $user_id, $user_role, $message) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
    $stmt->bind_param("iss", $user_id, $user_role, $message);
    $stmt->execute();
}
?>
