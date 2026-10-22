CREATE DATABASE IF NOT EXISTS cpmmis_db;
USE cpmmis_db;

CREATE TABLE `group` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `leader_email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `project_title` varchar(255) DEFAULT 'Untitled Project',
  `email` varchar(100) DEFAULT NULL,
  `adviser_id` int(11) DEFAULT NULL,
  `role` varchar(10) DEFAULT 'student',
  `last_activity` timestamp NULL DEFAULT NULL,
  `session_version` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'pending',
  `banned_until` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `adviser_id` (`adviser_id`),
  KEY `idx_group_status` (`status`),
  KEY `idx_group_adviser` (`adviser_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `adviser` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('adviser','admin') DEFAULT 'adviser',
  `last_activity` timestamp NULL DEFAULT NULL,
  `session_version` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `last_activity` timestamp NULL DEFAULT NULL,
  `session_version` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_role` varchar(20) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activity_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `adviser_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) DEFAULT NULL,
  `adviser_id` int(11) DEFAULT NULL,
  `project_title` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('pending','accepted','declined') DEFAULT 'pending',
  `decline_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  KEY `adviser_id` (`adviser_id`),
  CONSTRAINT `adviser_requests_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `group` (`id`),
  CONSTRAINT `adviser_requests_ibfk_2` FOREIGN KEY (`adviser_id`) REFERENCES `adviser` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `adviser_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `adviser_id` int(11) NOT NULL,
  `task_type` enum('chapter','letter','other') NOT NULL,
  `chapter_number` int(1) DEFAULT NULL,
  `caption` text NOT NULL,
  `deadline` datetime NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deadline_notified` tinyint(1) NOT NULL DEFAULT 0,
  `overdue_notified` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_tasks_adviser` (`adviser_id`),
  CONSTRAINT `fk_advtasks_adviser` FOREIGN KEY (`adviser_id`) REFERENCES `adviser` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `admin_letters` (
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
  KEY `admin_id` (`admin_id`),
  KEY `idx_letters_group` (`group_id`),
  KEY `idx_letters_time` (`created_at`),
  CONSTRAINT `fk_letters_group` FOREIGN KEY (`group_id`) REFERENCES `group` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `capstone_archive` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `adviser` varchar(100) DEFAULT NULL,
  `year` varchar(10) DEFAULT NULL,
  `category` varchar(50) DEFAULT 'Standard',
  `file_path` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_archive_title` (`title`),
  KEY `idx_archive_year` (`year`),
  KEY `idx_archive_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `group_submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `task_id` int(11) DEFAULT NULL,
  `chapter_number` int(1) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `review_notes` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `version` int(11) NOT NULL DEFAULT 1,
  `is_final` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  KEY `idx_submissions_group_task` (`group_id`, `task_id`),
  KEY `idx_submissions_status` (`status`),
  UNIQUE KEY `uq_submissions_group_chapter_doc` (`group_id`, `chapter_number`, `document_name`),
  CONSTRAINT `group_submissions_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `group` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_submissions_task` FOREIGN KEY (`task_id`) REFERENCES `adviser_tasks` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `login_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `role` varchar(20) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_info` varchar(255) DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_login_user` (`user_id`, `role`),
  KEY `idx_login_time` (`login_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `meeting_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `adviser_id` int(11) NOT NULL,
  `meeting_time` datetime NOT NULL,
  `agenda` text NOT NULL,
  `status` enum('pending','accepted','declined') DEFAULT 'pending',
  `admin_remarks` text DEFAULT NULL,
  `suggested_reschedule` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reminder_sent` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_meetings_adviser_status` (`adviser_id`, `status`),
  KEY `idx_meetings_group` (`group_id`),
  KEY `idx_meetings_status_time` (`status`, `meeting_time`),
  CONSTRAINT `fk_meetings_group` FOREIGN KEY (`group_id`) REFERENCES `group` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_meetings_adviser` FOREIGN KEY (`adviser_id`) REFERENCES `adviser` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sender_id` INT NOT NULL,
  `sender_role` ENUM('admin', 'adviser', 'group') NOT NULL,
  `receiver_id` INT NOT NULL,
  `receiver_role` ENUM('admin', 'adviser', 'group') NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_msg_receiver_unread` (`receiver_id`, `receiver_role`, `is_read`, `sender_id`, `sender_role`),
  KEY `idx_msg_conversation` (`sender_id`, `sender_role`, `receiver_id`, `receiver_role`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_role` enum('admin','adviser','group') NOT NULL,
  `message` text NOT NULL,
  `notification_type` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `reference_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notif_lookup` (`user_id`, `user_role`, `is_read`),
  KEY `idx_notif_time` (`id`),
  KEY `idx_notif_user_read_type` (`user_id`, `user_role`, `is_read`, `notification_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `password_resets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL,
  `otp_code` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `task_edit_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `old_deadline` datetime DEFAULT NULL,
  `new_deadline` datetime NOT NULL,
  `edited_by` int(11) NOT NULL,
  `edited_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`),
  KEY `edited_by` (`edited_by`),
  CONSTRAINT `task_edit_history_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `adviser_tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_teh_editor` FOREIGN KEY (`edited_by`) REFERENCES `adviser` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Login brute-force rate limiter (auto-created at runtime by db_config.php;
-- declared here so fresh installs include it in the schema).
CREATE TABLE IF NOT EXISTS `login_rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `first_attempt` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cross-table foreign keys (declared after all tables so reference order,
-- e.g. adviser before group, holds on every engine).
ALTER TABLE `group`
  ADD CONSTRAINT `fk_group_adviser` FOREIGN KEY (`adviser_id`) REFERENCES `adviser` (`id`) ON DELETE SET NULL;
