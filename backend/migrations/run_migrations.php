<?php
// Security: block direct web access (schema-mutation tool)
if (php_sapi_name() !== 'cli') { http_response_code(403); die('Access denied'); }
error_reporting(E_ALL);
ini_set('display_errors', 1);

include(__DIR__ . '/../db_config.php');
$is_cli = true;

function has_column(mysqli $conn, $table, $column) {
    $r = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $r && $r->num_rows > 0;
}

function has_table(mysqli $conn, $table) {
    $r = $conn->query("SHOW TABLES LIKE '$table'");
    return $r && $r->num_rows > 0;
}

$conn->query("CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$applied = [];
$res = $conn->query("SELECT name FROM schema_migrations");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $applied[] = $r['name'];
    }
}

$migrations = [
    'messages_sender_role_group' => function ($conn) {
        $conn->query("ALTER TABLE messages MODIFY COLUMN sender_role ENUM('admin','adviser','group') NOT NULL");
    },
    'messages_receiver_role_group' => function ($conn) {
        $conn->query("ALTER TABLE messages MODIFY COLUMN receiver_role ENUM('admin','adviser','group') NOT NULL");
    },
    'notifications_role_group' => function ($conn) {
        $conn->query("ALTER TABLE notifications MODIFY COLUMN user_role ENUM('admin','adviser','group') NOT NULL");
    },
    'group_last_activity' => function ($conn) {
        if (!has_column($conn, 'group', 'last_activity')) {
            $conn->query("ALTER TABLE `group` ADD COLUMN `last_activity` timestamp NULL DEFAULT NULL AFTER `email`");
        }
    },
    'adviser_last_activity' => function ($conn) {
        if (!has_column($conn, 'adviser', 'last_activity')) {
            $conn->query("ALTER TABLE adviser ADD COLUMN `last_activity` timestamp NULL DEFAULT NULL AFTER `email`");
        }
    },
    'admin_last_activity' => function ($conn) {
        if (!has_column($conn, 'admin', 'last_activity')) {
            $conn->query("ALTER TABLE admin ADD COLUMN `last_activity` timestamp NULL DEFAULT NULL AFTER `email`");
        }
    },
    'activity_logs_columns' => function ($conn) {
        if (!has_table($conn, 'activity_logs')) {
            return;
        }
        if (!has_column($conn, 'activity_logs', 'user_role')) {
            $conn->query("ALTER TABLE activity_logs ADD COLUMN `user_role` VARCHAR(20) DEFAULT NULL AFTER `user_id`");
        }
        if (!has_column($conn, 'activity_logs', 'username')) {
            $conn->query("ALTER TABLE activity_logs ADD COLUMN `username` VARCHAR(100) DEFAULT NULL AFTER `user_role`");
        }
        if (!has_column($conn, 'activity_logs', 'details')) {
            $conn->query("ALTER TABLE activity_logs ADD COLUMN `details` TEXT DEFAULT NULL AFTER `action`");
        }
        if (!has_column($conn, 'activity_logs', 'ip_address')) {
            $conn->query("ALTER TABLE activity_logs ADD COLUMN `ip_address` VARCHAR(45) DEFAULT NULL AFTER `details`");
        }
    },
    'task_edit_history_table' => function ($conn) {
        if (!has_table($conn, 'task_edit_history')) {
            $conn->query("CREATE TABLE `task_edit_history` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `task_id` int(11) NOT NULL,
                `old_deadline` datetime DEFAULT NULL,
                `new_deadline` datetime NOT NULL,
                `edited_by` int(11) NOT NULL,
                `edited_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `task_id` (`task_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        }
    },
    'admin_letters_table' => function ($conn) {
        if (!has_table($conn, 'admin_letters')) {
            $conn->query("CREATE TABLE `admin_letters` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `group_id` int(11) NOT NULL,
                `admin_id` int(11) NOT NULL,
                `title` varchar(255) NOT NULL,
                `description` text DEFAULT NULL,
                `file_path` varchar(255) NOT NULL,
                `file_name` varchar(255) NOT NULL,
                `file_size` int(11) DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `group_id` (`group_id`),
                KEY `admin_id` (`admin_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        }
    },
    'group_submissions_submission_type' => function ($conn) {
        if (has_table($conn, 'group_submissions') && !has_column($conn, 'group_submissions', 'submission_type')) {
            $conn->query("ALTER TABLE group_submissions ADD COLUMN `submission_type` ENUM('adviser') NOT NULL DEFAULT 'adviser' AFTER `status`");
        }
    },
    'group_submissions_feedback' => function ($conn) {
        if (has_table($conn, 'group_submissions') && !has_column($conn, 'group_submissions', 'feedback')) {
            $conn->query("ALTER TABLE group_submissions ADD COLUMN `feedback` TEXT DEFAULT NULL AFTER `review_notes`");
        }
    },
    'group_submissions_version' => function ($conn) {
        if (has_table($conn, 'group_submissions') && !has_column($conn, 'group_submissions', 'version')) {
            $conn->query("ALTER TABLE group_submissions ADD COLUMN `version` int(11) NOT NULL DEFAULT 1 AFTER `file_path`");
        }
    },
'group_submissions_is_final' => function ($conn) {
        if (has_table($conn, 'group_submissions') && !has_column($conn, 'group_submissions', 'is_final')) {
            $conn->query("ALTER TABLE group_submissions ADD COLUMN `is_final` tinyint(1) NOT NULL DEFAULT 0 AFTER `version`");
        }
    },
    'group_banned_until' => function ($conn) {
        if (!has_column($conn, 'group', 'banned_until')) {
            $conn->query("ALTER TABLE `group` ADD COLUMN `banned_until` datetime DEFAULT NULL AFTER `status`");
        }
    },
    'group_session_version' => function ($conn) {
        if (!has_column($conn, 'group', 'session_version')) {
            $conn->query("ALTER TABLE `group` ADD COLUMN `session_version` int(11) DEFAULT NULL AFTER `banned_until`");
        }
    },
    'adviser_session_version' => function ($conn) {
        if (!has_column($conn, 'adviser', 'session_version')) {
            $conn->query("ALTER TABLE adviser ADD COLUMN `session_version` int(11) DEFAULT NULL AFTER `last_activity`");
        }
    },
    'admin_session_version' => function ($conn) {
        if (!has_column($conn, 'admin', 'session_version')) {
            $conn->query("ALTER TABLE admin ADD COLUMN `session_version` int(11) DEFAULT NULL AFTER `last_activity`");
        }
    },
    'group_submissions_auto_combine_unique' => function ($conn) {
        $r = $conn->query("SHOW INDEX FROM group_submissions WHERE Key_name = 'uq_submissions_group_chapter_doc'");
        if ($r && $r->num_rows == 0) {
            // Remove pre-existing duplicates from the auto-combine race so the
            // unique key can be created (keep the lowest id per group/chapter/doc).
            $conn->query("DELETE s1 FROM group_submissions s1
                          INNER JOIN group_submissions s2
                          ON s1.group_id = s2.group_id
                             AND s1.chapter_number = s2.chapter_number
                             AND s1.document_name = s2.document_name
                             AND s1.id > s2.id");
            $conn->query("ALTER TABLE group_submissions ADD UNIQUE KEY `uq_submissions_group_chapter_doc` (`group_id`, `chapter_number`, `document_name`)");
        }
    },
    'capstone_archive_indexes' => function ($conn) {
        $r = $conn->query("SHOW INDEX FROM capstone_archive WHERE Key_name = 'idx_archive_title'");
        if ($r && $r->num_rows == 0) {
            $conn->query("ALTER TABLE capstone_archive ADD KEY `idx_archive_title` (`title`)");
        }
        $r = $conn->query("SHOW INDEX FROM capstone_archive WHERE Key_name = 'idx_archive_year'");
        if ($r && $r->num_rows == 0) {
            $conn->query("ALTER TABLE capstone_archive ADD KEY `idx_archive_year` (`year`)");
        }
        $r = $conn->query("SHOW INDEX FROM capstone_archive WHERE Key_name = 'idx_archive_category'");
        if ($r && $r->num_rows == 0) {
            $conn->query("ALTER TABLE capstone_archive ADD KEY `idx_archive_category` (`category`)");
        }
    },
    'meeting_requests_indexes' => function ($conn) {
        if (!has_table($conn, 'meeting_requests')) {
            return;
        }
        $r = $conn->query("SHOW INDEX FROM meeting_requests WHERE Key_name = 'idx_meetings_adviser_status'");
        if ($r && $r->num_rows == 0) {
            $conn->query("ALTER TABLE meeting_requests ADD KEY `idx_meetings_adviser_status` (`adviser_id`, `status`)");
        }
        $r = $conn->query("SHOW INDEX FROM meeting_requests WHERE Key_name = 'idx_meetings_group'");
        if ($r && $r->num_rows == 0) {
            $conn->query("ALTER TABLE meeting_requests ADD KEY `idx_meetings_group` (`group_id`)");
        }
        $r = $conn->query("SHOW INDEX FROM meeting_requests WHERE Key_name = 'idx_meetings_status_time'");
        if ($r && $r->num_rows == 0) {
            $conn->query("ALTER TABLE meeting_requests ADD KEY `idx_meetings_status_time` (`status`, `meeting_time`)");
        }
    },
    'notifications_type_column' => function ($conn) {
        if (!has_column($conn, 'notifications', 'notification_type')) {
            $conn->query("ALTER TABLE notifications ADD COLUMN `notification_type` VARCHAR(50) DEFAULT NULL AFTER `message`");
        }
    },
    'notifications_index_type' => function ($conn) {
        $r = $conn->query("SHOW INDEX FROM notifications WHERE Key_name = 'idx_notif_user_read_type'");
        if (has_table($conn, 'notifications') && $r && $r->num_rows == 0) {
            $conn->query("ALTER TABLE notifications ADD KEY `idx_notif_user_read_type` (`user_id`, `user_role`, `is_read`, `notification_type`)");
        }
    },
    'drop_panelist_tables' => function ($conn) {
        foreach (['panelists', 'panel_scores', 'group_panelists'] as $t) {
            if (has_table($conn, $t)) {
                $conn->query("DROP TABLE IF EXISTS `$t`");
            }
        }
    },
    'group_submissions_task_fk' => function ($conn) {
        $r = $conn->query("SELECT COUNT(*) AS c FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_submissions_task'");
        $row = $r->fetch_assoc();
        if ((int)$row['c'] == 0) {
            $conn->query("ALTER TABLE group_submissions ADD CONSTRAINT `fk_submissions_task` FOREIGN KEY (`task_id`) REFERENCES `adviser_tasks`(`id`) ON DELETE SET NULL");
        }
    },
'meeting_requests_fks' => function ($conn) {
        if (!has_table($conn, 'meeting_requests')) {
            return;
        }
        $r = $conn->query("SELECT COUNT(*) AS c FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_meetings_group'");
        $row = $r->fetch_assoc();
        if ((int)$row['c'] == 0) {
            $conn->query("ALTER TABLE meeting_requests ADD CONSTRAINT `fk_meetings_group` FOREIGN KEY (`group_id`) REFERENCES `group`(`id`) ON DELETE CASCADE");
        }
        $r = $conn->query("SELECT COUNT(*) AS c FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_meetings_adviser'");
        $row = $r->fetch_assoc();
        if ((int)$row['c'] == 0) {
            $conn->query("ALTER TABLE meeting_requests ADD CONSTRAINT `fk_meetings_adviser` FOREIGN KEY (`adviser_id`) REFERENCES `adviser`(`id`) ON DELETE CASCADE");
        }
    },
    'group_adviser_fk' => function ($conn) {
        $r = $conn->query("SELECT COUNT(*) AS c FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_group_adviser'");
        $row = $r->fetch_assoc();
        if ((int)$row['c'] == 0) {
            // Clear any orphaned adviser ids before adding the constraint.
            $conn->query("UPDATE `group` SET adviser_id = NULL WHERE adviser_id IS NOT NULL AND adviser_id NOT IN (SELECT id FROM adviser)");
            $conn->query("ALTER TABLE `group` ADD CONSTRAINT `fk_group_adviser` FOREIGN KEY (`adviser_id`) REFERENCES `adviser`(`id`) ON DELETE SET NULL");
        }
    },
    'adviser_tasks_adviser_fk' => function ($conn) {
        if (!has_table($conn, 'adviser_tasks')) {
            return;
        }
        $r = $conn->query("SELECT COUNT(*) AS c FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_advtasks_adviser'");
        $row = $r->fetch_assoc();
        if ((int)$row['c'] == 0) {
            // Remove orphaned task rows before adding the constraint (myisam-era leftovers).
            $conn->query("DELETE FROM adviser_tasks WHERE adviser_id NOT IN (SELECT id FROM adviser)");
            $conn->query("ALTER TABLE adviser_tasks ADD CONSTRAINT `fk_advtasks_adviser` FOREIGN KEY (`adviser_id`) REFERENCES `adviser`(`id`) ON DELETE CASCADE");
        }
    },
    'group_milestones_group_fk' => function ($conn) {
        if (!has_table($conn, 'group_milestones')) {
            return;
        }
        $r = $conn->query("SELECT COUNT(*) AS c FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_milestones_group'");
        $row = $r->fetch_assoc();
        if ((int)$row['c'] == 0) {
            $conn->query("DELETE FROM group_milestones WHERE group_id NOT IN (SELECT id FROM `group`)");
            $conn->query("ALTER TABLE group_milestones ADD CONSTRAINT `fk_milestones_group` FOREIGN KEY (`group_id`) REFERENCES `group`(`id`) ON DELETE CASCADE");
        }
    },
    'admin_letters_group_fk' => function ($conn) {
        if (!has_table($conn, 'admin_letters')) {
            return;
        }
        $r = $conn->query("SELECT COUNT(*) AS c FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_letters_group'");
        $row = $r->fetch_assoc();
        if ((int)$row['c'] == 0) {
            $conn->query("DELETE FROM admin_letters WHERE group_id NOT IN (SELECT id FROM `group`)");
            // Keep a single index on group_id: drop the duplicate first.
            $conn->query("ALTER TABLE admin_letters DROP INDEX group_id");
            $conn->query("ALTER TABLE admin_letters ADD CONSTRAINT `fk_letters_group` FOREIGN KEY (`group_id`) REFERENCES `group`(`id`) ON DELETE CASCADE");
        }
    },
    'task_edit_history_editor_fk' => function ($conn) {
        if (!has_table($conn, 'task_edit_history')) {
            return;
        }
        $r = $conn->query("SELECT COUNT(*) AS c FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_teh_editor'");
        $row = $r->fetch_assoc();
        if ((int)$row['c'] == 0) {
            $conn->query("DELETE FROM task_edit_history WHERE edited_by NOT IN (SELECT id FROM adviser)");
            $conn->query("ALTER TABLE task_edit_history ADD CONSTRAINT `fk_teh_editor` FOREIGN KEY (`edited_by`) REFERENCES `adviser`(`id`)");
        }
    }
];

echo "CPMMIS Migrations\n";
$success = 0;
$failed = 0;

foreach ($migrations as $name => $run) {
    if (in_array($name, $applied)) {
        echo "SKIP: $name (already applied)\n";
        continue;
    }
    echo "RUN: $name ... ";
    try {
        $run($conn);
        $stmt = $conn->prepare("INSERT INTO schema_migrations (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        echo "OK\n";
        $success++;
    } catch (mysqli_sql_exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n--- Migrations run: $success applied, $failed failed ---\n";
if ($failed === 0) {
    echo "All pending migrations applied successfully.\n";
} else {
    echo "Some migrations failed. Review the output above.\n";
}
echo $is_cli ? "" : "</pre>";
