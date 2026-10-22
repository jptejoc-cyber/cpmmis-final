<?php
// Security: block direct web access
if (php_sapi_name() !== 'cli') { http_response_code(403); die('Access denied'); }
include(__DIR__ . '/../db_config.php');

$queries = [
    "CREATE TABLE IF NOT EXISTS `adviser_tasks` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `adviser_id` int(11) NOT NULL,
        `task_type` enum('chapter','letter','other') NOT NULL,
        `chapter_number` int(1) DEFAULT NULL,
        `caption` text NOT NULL,
        `deadline` datetime NOT NULL,
        `file_path` varchar(255) DEFAULT NULL,
        `original_filename` varchar(255) DEFAULT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    "ALTER TABLE `group_submissions` 
     ADD COLUMN IF NOT EXISTS `task_id` int(11) DEFAULT NULL;"
];

foreach ($queries as $q) {
    if (mysqli_query($conn, $q)) {
        echo "Success: " . substr($q, 0, 50) . "...\n";
    } else {
        echo "Migration error.\n";
    }
}
echo "Migration Complete.\n";
?>
