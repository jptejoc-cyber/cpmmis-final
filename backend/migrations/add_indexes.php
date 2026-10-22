<?php
if (php_sapi_name() !== 'cli') { http_response_code(403); die('Access denied'); }
include(__DIR__ . '/../db_config.php');

$indexes = [
    "CREATE INDEX IF NOT EXISTS idx_notif_lookup ON notifications (user_id, user_role, is_read)",
    "CREATE INDEX IF NOT EXISTS idx_notif_time ON notifications (id DESC)",
    "CREATE INDEX IF NOT EXISTS idx_submissions_group_task ON group_submissions (group_id, task_id)",
    "CREATE INDEX IF NOT EXISTS idx_submissions_status ON group_submissions (status)",
    "CREATE INDEX IF NOT EXISTS idx_login_user ON login_logs (user_id, role)",
    "CREATE INDEX IF NOT EXISTS idx_login_time ON login_logs (login_time DESC)",
    "CREATE INDEX IF NOT EXISTS idx_activity_user ON activity_logs (user_id)",
    "CREATE INDEX IF NOT EXISTS idx_letters_group ON admin_letters (group_id)",
    "CREATE INDEX IF NOT EXISTS idx_letters_time ON admin_letters (created_at DESC)",
    "CREATE INDEX IF NOT EXISTS idx_tasks_adviser ON adviser_tasks (adviser_id)",
    "CREATE INDEX IF NOT EXISTS idx_group_status ON `group` (status)",
    "CREATE INDEX IF NOT EXISTS idx_group_adviser ON `group` (adviser_id)",
];

foreach ($indexes as $sql) {
    if (mysqli_query($conn, $sql)) {
        echo "OK: " . substr($sql, 0, 60) . "...\n";
    } else {
        $err = mysqli_error($conn);
        if (stripos($err, 'duplicate key name') === false && stripos($err, 'already exists') === false) {
            echo "ERR: $err\n";
        } else {
            echo "SKIP (exists): " . substr($sql, 0, 60) . "...\n";
        }
    }
}
echo "Index migration complete.\n";
