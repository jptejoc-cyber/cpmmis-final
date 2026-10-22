<?php
if (isset($conn) && $conn) {
    $check_q = "SELECT id, adviser_id, caption, deadline FROM adviser_tasks 
                WHERE deadline_notified = 0 
                AND deadline IS NOT NULL 
                AND deadline > '1970-01-01 00:00:00'
                AND deadline > NOW() 
                AND deadline <= DATE_ADD(NOW(), INTERVAL 3 DAY)";
    $res = $conn->query($check_q);

    if ($res && $res->num_rows > 0) {
        while ($task = $res->fetch_assoc()) {
            $task_id = $task['id'];
            $adv_id = $task['adviser_id'];
            $caption = $task['caption'];

            $due_date = date('M d, Y g:i A', strtotime($task['deadline']));
            $msg = "Urgent: The deadline for '$caption' is approaching on $due_date.";

            $stmt = $conn->prepare("SELECT id FROM `group` WHERE adviser_id = ? AND status = 'active'");
            $stmt->bind_param("i", $adv_id);
            $stmt->execute();
            $groups = $stmt->get_result();
            if ($groups && $groups->num_rows > 0) {
                while ($g = $groups->fetch_assoc()) {
                    $gid = $g['id'];
                    $stmt2 = $conn->prepare("INSERT INTO notifications (user_id, user_role, message) VALUES (?, 'group', ?)");
                    $stmt2->bind_param("is", $gid, $msg);
                    $stmt2->execute();
                }
            }

            $stmt = $conn->prepare("UPDATE adviser_tasks SET deadline_notified = 1 WHERE id = ?");
            $stmt->bind_param("i", $task_id);
            $stmt->execute();
        }
    }

    $overdue_q = "SELECT id, adviser_id, caption, deadline FROM adviser_tasks 
                  WHERE overdue_notified = 0 
                  AND deadline IS NOT NULL 
                  AND deadline > '1970-01-01 00:00:00'
                  AND deadline < NOW()";
    $overdue_res = $conn->query($overdue_q);

    if ($overdue_res && $overdue_res->num_rows > 0) {
        while ($task = $overdue_res->fetch_assoc()) {
            $task_id = $task['id'];
            $adv_id = $task['adviser_id'];
            $caption = $task['caption'];

            $msg = "At Risk: You missed the deadline for '$caption'. Please submit immediately.";

            $stmt = $conn->prepare("SELECT id FROM `group` WHERE adviser_id = ? AND status = 'active'");
            $stmt->bind_param("i", $adv_id);
            $stmt->execute();
            $groups = $stmt->get_result();
            if ($groups && $groups->num_rows > 0) {
                while ($g = $groups->fetch_assoc()) {
                    $gid = $g['id'];
                    $stmt2 = $conn->prepare("SELECT id FROM group_submissions WHERE group_id = ? AND task_id = ?");
                    $stmt2->bind_param("ii", $gid, $task_id);
                    $stmt2->execute();
                    if ($stmt2->get_result()->num_rows == 0) {
                        $stmt3 = $conn->prepare("INSERT INTO notifications (user_id, user_role, message) VALUES (?, 'group', ?)");
                        $stmt3->bind_param("is", $gid, $msg);
                        $stmt3->execute();
                    }
                }
            }

            $stmt = $conn->prepare("UPDATE adviser_tasks SET overdue_notified = 1 WHERE id = ?");
            $stmt->bind_param("i", $task_id);
            $stmt->execute();
        }
    }

    $upcoming_meetings_q = "SELECT m.id, m.adviser_id, m.meeting_time, g.project_title, g.username as group_name 
                            FROM meeting_requests m 
                            JOIN `group` g ON m.group_id = g.id 
                            WHERE m.status = 'accepted' 
                            AND m.reminder_sent = 0 
                            AND m.meeting_time > NOW() 
                            AND m.meeting_time <= DATE_ADD(NOW(), INTERVAL 24 HOUR)";
    $upcoming_res = $conn->query($upcoming_meetings_q);

    if ($upcoming_res && $upcoming_res->num_rows > 0) {
        while ($meet = $upcoming_res->fetch_assoc()) {
            $meet_id = $meet['id'];
            $adv_id = $meet['adviser_id'];
            $group_name = $meet['group_name'];

            $meet_time = date('M d, Y g:i A', strtotime($meet['meeting_time']));
            $msg = "Reminder: You have an upcoming consultation with $group_name scheduled on $meet_time.";

            $stmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, message, is_read, created_at) VALUES (?, 'adviser', ?, 0, NOW())");
            $stmt->bind_param("is", $adv_id, $msg);
            $stmt->execute();

            $stmt = $conn->prepare("UPDATE meeting_requests SET reminder_sent = 1 WHERE id = ?");
            $stmt->bind_param("i", $meet_id);
            $stmt->execute();
        }
    }
}