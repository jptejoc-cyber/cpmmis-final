<?php
// Security: block direct web access
if (php_sapi_name() !== 'cli') { http_response_code(403); die('Access denied'); }
include(__DIR__ . '/../db_config.php');
$query = "CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sender_id` INT NOT NULL,
  `sender_role` ENUM('admin', 'adviser', 'group') NOT NULL,
  `receiver_id` INT NOT NULL,
  `receiver_role` ENUM('admin', 'adviser', 'group') NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if (mysqli_query($conn, $query)) {
    echo "messages table successfully created or already exists.\n";
} else {
    echo "Error creating messages table.\n";
}
?>
