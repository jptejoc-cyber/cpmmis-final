<?php
/**
 * P0 fixes migration: adds columns required by the 2026-08 security/bugfix batch.
 *
 *  1. password_resets.attempts      - DB-backed OTP failure counter (anti-brute-force)
 *  2. adviser_requests.decline_reason - dedicated column so decline reasons never
 *                                       overwrite the student's uploaded letter path
 *
 * Idempotent: safe to run multiple times. CLI-only for security.
 * Delete this file after running on production.
 */
// Security: block direct web access
if (php_sapi_name() !== 'cli') { http_response_code(403); die('Access denied'); }
include(__DIR__ . '/../db_config.php');

$is_cli = true;

function column_exists(mysqli $conn, string $table, string $column): bool {
    $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->bind_param("s", $column);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

$results = [];

// 1. password_resets.attempts
if (column_exists($conn, 'password_resets', 'attempts')) {
    $results['password_resets.attempts'] = 'already exists';
} else {
    if ($conn->query("ALTER TABLE password_resets ADD COLUMN attempts INT NOT NULL DEFAULT 0 AFTER otp_code")) {
        $results['password_resets.attempts'] = 'added';
    } else {
        $results['password_resets.attempts'] = 'FAILED: ' . $conn->error;
    }
}

// 2. adviser_requests.decline_reason
if (column_exists($conn, 'adviser_requests', 'decline_reason')) {
    $results['adviser_requests.decline_reason'] = 'already exists';
} else {
    if ($conn->query("ALTER TABLE adviser_requests ADD COLUMN decline_reason TEXT NULL AFTER message")) {
        $results['adviser_requests.decline_reason'] = 'added';
    } else {
        $results['adviser_requests.decline_reason'] = 'FAILED: ' . $conn->error;
    }
}

foreach ($results as $k => $v) {
    echo str_pad($k, 40) . $v . PHP_EOL;
}
echo ($is_cli ? "Done." : '<p>Done. Refresh to re-check.</p>');
