<?php
// Security: block direct web access
if (php_sapi_name() !== 'cli') { http_response_code(403); die('Access denied'); }
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../db_config.php';

echo "=== Activity Logs Migration ===\n\n";

echo "Dropping old activity_logs table...\n";
$conn->query("DROP TABLE IF EXISTS activity_logs");
if ($conn->error) {
    die("Error dropping table: " . $conn->error . "\n");
}
echo "DONE\n\n";

echo "Creating new activity_logs table...\n";
$sql = "CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_role VARCHAR(20) NOT NULL,
    username VARCHAR(100) DEFAULT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_logs_role (user_role),
    INDEX idx_logs_time (created_at),
    INDEX idx_logs_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if ($conn->query($sql)) {
    echo "DONE\n\n";
    echo "Migration completed successfully!\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

$conn->close();
