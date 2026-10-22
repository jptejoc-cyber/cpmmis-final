CREATE TABLE IF NOT EXISTS `group_milestones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `milestone_title` varchar(255) NOT NULL,
  `deadline_date` date NOT NULL,
  `status` enum('pending','completed','overdue') DEFAULT 'pending',
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `group_submissions` 
ADD COLUMN `version` INT(11) NOT NULL DEFAULT 1 AFTER `file_path`,
ADD COLUMN `is_final` TINYINT(1) NOT NULL DEFAULT 0 AFTER `version`;

CREATE TABLE IF NOT EXISTS `task_edit_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `old_deadline` datetime DEFAULT NULL,
  `new_deadline` datetime NOT NULL,
  `edited_by` int(11) NOT NULL,
  `edited_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`),
  CONSTRAINT `task_edit_history_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `adviser_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cron_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_name` varchar(100) NOT NULL,
  `last_run` date NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
