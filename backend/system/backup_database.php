<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Invalid request method. Use POST.");
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    header("Location: ../index.php");
    exit();
}

// Mitigate script lifecycle time limits for heavier tracking tables
set_time_limit(180);
ini_set('memory_limit', '256M');

$backup_date = date('Y-m-d_H-i-s');
$sql_filename = "db_snapshot_" . $backup_date . ".sql";

// Initialize the raw stream data mapping variable
$sql_dump = "-- CPMMIS Database Snapshot Node Extraction Map\n";
$sql_dump .= "-- Generated: " . date('Y-m-d g:i A') . "\n\n";
$sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n";
$sql_dump .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
$sql_dump .= "START TRANSACTION;\n\n";

// --- FETCH ALL ACTIVE SCHEMA NODES AND STRUCTURAL SCHEMA DATA ---
$tables_q = mysqli_query($conn, "SHOW TABLES");
if (!$tables_q) {
    die("Database extraction failed. Check server logs for details.");
}

while ($row = mysqli_fetch_row($tables_q)) {
    $table = $row[0];
    
    // Construct Structural Schema Map Table Blueprint Arrays
    $create_q = mysqli_fetch_row(mysqli_query($conn, "SHOW CREATE TABLE `$table`"));
    $sql_dump .= "-- --------------------------------------------------------\n";
    $sql_dump .= "-- Table structural map configuration rules for table `$table`\n";
    $sql_dump .= "-- --------------------------------------------------------\n";
    $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n" . $create_q[1] . ";\n\n";
    
    // Extract row records values tracking map data matrices
    $data_q = mysqli_query($conn, "SELECT * FROM `$table`");
    $num_fields = mysqli_num_fields($data_q);
    $total_rows = mysqli_num_rows($data_q);
    
    if ($total_rows > 0) {
        $sql_dump .= "-- Dumping data array instances inside table `$table`\n";
        
        while ($data_row = mysqli_fetch_row($data_q)) {
            $sql_dump .= "INSERT INTO `$table` VALUES(";
            for ($i = 0; $i < $num_fields; $i++) {
                if (isset($data_row[$i])) {
                    // Sanitize internal layout strings safely
                    $escaped = mysqli_real_escape_string($conn, $data_row[$i]);
                    $sql_dump .= '"' . $escaped . '"';
                } else {
                    $sql_dump .= 'NULL';
                }
                
                if ($i < ($num_fields - 1)) {
                    $sql_dump .= ',';
                }
            }
            $sql_dump .= ");\n";
        }
        $sql_dump .= "\n";
    }
}

$sql_dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
$sql_dump .= "COMMIT;\n";

// --- PROGRAMMATIC BROWSER STREAM DOWNLOAD TRIGGER ---
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $sql_filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . strlen($sql_dump));

// Stream string generation payload down directly
echo $sql_dump;
exit();
?>