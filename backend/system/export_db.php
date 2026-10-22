<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');

// Security Gate - Ensure only authenticated administrators can trigger database extractions
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    header("Location: ../index.php");
    exit();
}

// Set time and memory runway boundaries for larger datasets
set_time_limit(300);
ini_set('memory_limit', '512M');

// Initialize SQL backup file header string
$backup_content = "-- CPMMIS Automated Database Backup\n";
$backup_content .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
$backup_content .= "SET FOREIGN_KEY_CHECKS=0;\n";
$backup_content .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
$backup_content .= "START TRANSACTION;\n\n";

// 1. EXTRACT ALL TABLES REGISTERED IN THE DATABASE
$tables = [];
$result = mysqli_query($conn, "SHOW TABLES");
while ($row = mysqli_fetch_row($result)) {
    $tables[] = $row[0];
}

// 2. ITERATE THROUGH TABLES TO GENERATE STRUCTURE AND ROW INSERTS
foreach ($tables as $table) {
    // Escape backticks inside table name mapping checks
    $table_escaped = "`" . $table . "`";
    
    // Generate Structure (DROP & CREATE statement blocks)
    $backup_content .= "------------ Structural Mapping For Table: $table_escaped ------------\n";
    $backup_content .= "DROP TABLE IF EXISTS $table_escaped;\n";
    
    $create_table_q = mysqli_query($conn, "SHOW CREATE TABLE $table_escaped");
    $create_table_row = mysqli_fetch_row($create_table_q);
    $backup_content .= $create_table_row[1] . ";\n\n";
    
    // Generate Rows (INSERT structural queries)
    $rows_query = mysqli_query($conn, "SELECT * FROM $table_escaped");
    $num_fields = mysqli_num_fields($rows_query);
    
    if (mysqli_num_rows($rows_query) > 0) {
        $backup_content .= "-- Dumping Data For Table: $table_escaped\n";
        
        while ($row = mysqli_fetch_row($rows_query)) {
            $backup_content .= "INSERT INTO $table_escaped VALUES(";
            
            for ($i = 0; $i < $num_fields; $i++) {
                if (isset($row[$i])) {
                    // Sanitize text formatting strings inside value parameters cleanly
                    $value = mysqli_real_escape_string($conn, $row[$i]);
                    // Normalize break rules cleanly inside target dump metrics
                    $value = str_replace("\n", "\\n", $value);
                    $backup_content .= '"' . $value . '"';
                } else {
                    $backup_content .= 'NULL';
                }
                
                if ($i < ($num_fields - 1)) {
                    $backup_content .= ',';
                }
            }
            $backup_content .= ");\n";
        }
        $backup_content .= "\n";
    }
}

// Wrap up operational constraints cleanly
$backup_content .= "SET FOREIGN_KEY_CHECKS=1;\n";
$backup_content .= "COMMIT;\n";

// 3. COMPOSE SYSTEM HTTP DOWNLOAD HEADERS
$filename = "cpmmis_backup_" . date('Ymd_His') . ".sql";

header('Content-Type: application/octet-stream');
header("Content-Transfer-Encoding: Binary");
header("Content-disposition: attachment; filename=\"" . $filename . "\"");

// Stream file payload data blocks to target system client instantly
echo $backup_content;
exit();
?>