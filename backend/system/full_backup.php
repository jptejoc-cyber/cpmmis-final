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

// Increase script execution limitations for handling larger project files
set_time_limit(300);
ini_set('memory_limit', '512M');

$backup_date = date('Y-m-d_H-i-s');

// Write the zip outside the web root (system temp dir) so that an interrupted
// download cannot leave a full source+DB dump sitting in a web-accessible
// folder. This path is never reachable via HTTP.
$backup_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cpmmis_backups' . DIRECTORY_SEPARATOR;
if (!is_dir($backup_dir)) {
    @mkdir($backup_dir, 0700, true);
}
@chmod($backup_dir, 0700);

// Filename Definitions
$sql_filename = "db_snapshot_" . $backup_date . ".sql";
$zip_filename = "cpmmis_full_archive_" . $backup_date . ".zip";

$sql_path = $backup_dir . $sql_filename;
$zip_path = $backup_dir . $zip_filename;

// ==========================================
// STAGE 1: EXECUTE SQL BLUEPRINT EXTRACTION
// ==========================================
$sql_dump = "-- CPMMIS Automated Database Backup Schema Map\n";
$sql_dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
$sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

$tables_q = mysqli_query($conn, "SHOW TABLES");
while ($row = mysqli_fetch_row($tables_q)) {
    $table = $row[0];
    
    // Structure Map Construction
    $create_q = mysqli_fetch_row(mysqli_query($conn, "SHOW CREATE TABLE `$table`"));
    $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n" . $create_q[1] . ";\n\n";
    
    // Row Element Data Mapping
    $data_q = mysqli_query($conn, "SELECT * FROM `$table`");
    $num_fields = mysqli_num_fields($data_q);
    
    while ($data_row = mysqli_fetch_row($data_q)) {
        $sql_dump .= "INSERT INTO `$table` VALUES(";
        for ($i = 0; $i < $num_fields; $i++) {
            if (isset($data_row[$i])) {
                // Sanitize string entries securely
                $escaped = mysqli_real_escape_string($conn, $data_row[$i]);
                $sql_dump .= '"' . $escaped . '"';
            } else {
                $sql_dump .= 'NULL';
            }
            if ($i < ($num_fields - 1)) { $sql_dump .= ','; }
        }
        $sql_dump .= ");\n";
    }
    $sql_dump .= "\n\n";
}
$sql_dump .= "SET FOREIGN_KEY_CHECKS=1;\n";

// Write SQL file momentarily to include inside zip
file_put_contents($sql_path, $sql_dump);

// ==========================================
// STAGE 2: BUILD FULL ZIP FILE ARCHIVE
// ==========================================
$zip = new ZipArchive();
if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    
    // Define relative project root path relative to this backend script directory
    $root_path = realpath(__DIR__ . '/../');
    
    // Iterate securely through all internal workspace directory structures recursively
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root_path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    // Credential-bearing files are excluded from the archive. Their values
    // live in the environment (env vars / Apache SetEnv), never in the backup.
    $excluded_basenames = [
        'db_config.php',
        'mail_config.php',
        'mailer.php',
        '.env',
        '.htpasswd',
    ];

    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $file_path = $file->getRealPath();

            // Skip target temporary backup folder layout to avoid nested infinite download feedback loop
            if (strpos($file_path, $backup_dir) !== false) {
                continue;
            }

            // Skip credential-bearing / sensitive configuration files
            $base_name = basename($file_path);
            if (in_array($base_name, $excluded_basenames, true)) {
                continue;
            }
            // Skip any .sql/.log/.env artifacts that may have leaked anywhere in the tree
            if (preg_match('/\.(sql|log|env|key|pem|bak|old|swp)$/i', $base_name)) {
                continue;
            }

            // Extract local pathing name strings matching core directories
            $relative_path = substr($file_path, strlen($root_path) + 1);
            $zip->addFile($file_path, $relative_path);
        }
    }
    
    // Append the freshly extracted database .sql file into the root of the backup zip
    $zip->addFile($sql_path, 'database_blueprint_backup.sql');
    $zip->close();
}

// Remove standalone temporary database sql node now that it is archived inside zip file
if (file_exists($sql_path)) {
    unlink($sql_path);
}

// ==========================================
// STAGE 3: STREAM DIRECT DOWNLOAD STREAM
// ==========================================
if (file_exists($zip_path)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($zip_path));
    
    // Stream payload buffer safely to mitigate huge server overhead loads
    readfile($zip_path);
    
    // Flush storage of generated zip array cleanly
    unlink($zip_path);
    exit();
} else {
    die("System Core Archiver Generation Failure. Authorization aborted.");
}
?>