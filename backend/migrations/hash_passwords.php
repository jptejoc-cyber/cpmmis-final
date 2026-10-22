<?php
// Security: block direct web access
if (php_sapi_name() !== 'cli') { http_response_code(403); die('Access denied'); }
include(__DIR__ . '/../db_config.php');

echo "Hashing admin passwords...\n";
$admin_result = $conn->query("SELECT id, password FROM admin");
while ($row = $admin_result->fetch_assoc()) {
    if (strlen($row['password']) < 20 || strpos($row['password'], '$2y$') !== 0) {
        $hash = password_hash($row['password'], PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hash, $row['id']);
        $stmt->execute();
        echo "  Hashed admin id={$row['id']}\n";
    }
}

echo "Hashing adviser passwords...\n";
$adv_result = $conn->query("SELECT id, password FROM adviser");
while ($row = $adv_result->fetch_assoc()) {
    if (strlen($row['password']) < 20 || strpos($row['password'], '$2y$') !== 0) {
        $hash = password_hash($row['password'], PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE adviser SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hash, $row['id']);
        $stmt->execute();
        echo "  Hashed adviser id={$row['id']}\n";
    }
}

echo "Migration complete.\n";
?>