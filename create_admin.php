<?php
// REMOVED FOR SECURITY — This file must not be accessible via web.
// Use CLI to create admin accounts: php create_admin.php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Access denied');
}

if (!isset($argv[1]) || !isset($argv[2])) {
    echo "Usage: php create_admin.php <username> <password> [full_name] [email]\n";
    exit(1);
}

require_once __DIR__ . '/backend/db_config.php';

$username = $argv[1];
$password = $argv[2];
$full_name = $argv[3] ?? 'Administrator';
$email = $argv[4] ?? '';

$hash = password_hash($password, PASSWORD_BCRYPT);

$check = $conn->prepare("SELECT id FROM admin WHERE username = ?");
$check->bind_param("s", $username);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    $stmt = $conn->prepare("UPDATE admin SET password = ?, full_name = ?, email = ? WHERE username = ?");
    $stmt->bind_param("ssss", $hash, $full_name, $email, $username);
    $stmt->execute();
    echo "Admin '$username' password updated.\n";
} else {
    $stmt = $conn->prepare("INSERT INTO admin (username, password, full_name, email) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $hash, $full_name, $email);
    $stmt->execute();
    echo "Admin '$username' created.\n";
}
?>