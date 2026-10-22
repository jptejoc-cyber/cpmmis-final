<?php
header('Content-Type: application/json');
include(__DIR__ . '/../db_config.php');
include(__DIR__ . '/../session_init.php');

error_reporting(0);
ini_set('display_errors', 0);
ini_set('max_execution_time', 55);

// Guarantee a JSON response even if a fatal error occurs mid-SMTP
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $detail = sprintf("[%s] %s in %s:%d", date('Y-m-d H:i:s'), $e['message'], $e['file'], $e['line']);
        @error_log($detail);
        $msg = 'Mail service error. Please try again later.';
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $msg]);
    }
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($token)) {
    echo json_encode(['status' => 'error', 'message' => 'Security token validation failed']);
    exit();
}

// Simple rate limiting: check if OTP was sent within last 60s
if (isset($_SESSION['otp_last_sent']) && time() - $_SESSION['otp_last_sent'] < 60) {
    $retry_after = 60 - (time() - $_SESSION['otp_last_sent']);
    echo json_encode([
        'status' => 'error',
        'message' => "Please wait {$retry_after} second(s) before requesting another code.",
        'retry_after' => $retry_after
    ]);
    exit();
}

$email = strtolower(trim($_POST['email'] ?? ''));

if (empty($email)) {
    echo json_encode(['status' => 'error', 'message' => 'Please provide an email address.']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email format.']);
    exit();
}

// DB-backed per-email send cooldown (60s). Unlike the session-only check above,
// this cannot be bypassed by clearing cookies.
$table_check = $conn->query("SHOW TABLES LIKE 'password_resets'");
if ($table_check && $table_check->num_rows > 0) {
    $db_cooldown_stmt = @$conn->prepare("SELECT created_at FROM password_resets WHERE email = ? ORDER BY created_at DESC LIMIT 1");
    if ($db_cooldown_stmt) {
        $db_cooldown_stmt->bind_param("s", $email);
        $db_cooldown_stmt->execute();
        $db_cooldown_row = $db_cooldown_stmt->get_result()->fetch_assoc();
        if ($db_cooldown_row && !empty($db_cooldown_row['created_at'])) {
            $last_sent_ts = strtotime($db_cooldown_row['created_at']);
            if ($last_sent_ts !== false && (time() - $last_sent_ts) < 60) {
                $retry_after = max(1, 60 - (time() - $last_sent_ts));
                echo json_encode([
                    'status' => 'error',
                    'message' => "Please wait {$retry_after} second(s) before requesting another code.",
                    'retry_after' => $retry_after
                ]);
                exit();
            }
        }
    }
}

// Check if email exists in any table
$found = false;

$stmt = $conn->prepare("SELECT id FROM admin WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) $found = true;

if (!$found) {
    $stmt = $conn->prepare("SELECT id FROM adviser WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $found = true;
}

if (!$found) {
    $stmt = $conn->prepare("SELECT id FROM `group` WHERE leader_email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $found = true;
}

if (!$found) {
    // Identical branch to the "sent" case below so an attacker cannot tell
    // whether the address exists (account-existence oracle). The frontend also
    // gets the same cooldown, keeping the two flows visually indistinguishable.
    echo json_encode([
        'status' => 'success',
        'message' => 'If an account exists for that email, a recovery code has been sent.',
        'cooldown' => 60
    ]);
    exit();
}

// Generate 6-digit OTP
$otp = sprintf("%06d", random_int(100000, 999999));

// Self-heal: make sure the password_resets table exists with all required columns
$table_check = $conn->query("SHOW TABLES LIKE 'password_resets'");
if (!$table_check || $table_check->num_rows === 0) {
    $created = $conn->query("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        otp_code VARCHAR(255) NOT NULL,
        attempts INT NOT NULL DEFAULT 0,
        locked_at DATETIME NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (!$created) {
        echo json_encode(['status' => 'error', 'message' => 'Database setup failed. Please contact the administrator.']);
        exit();
    }
} else {
    // Check for missing columns and log warning instead of modifying schema at runtime.
    // Run backend/migrations/migration_025_system_improvements.sql to add missing columns.
    $col_check = $conn->query("SHOW COLUMNS FROM password_resets LIKE 'attempts'");
    if (!$col_check || $col_check->num_rows === 0) {
        error_log('CPMMIS WARNING: password_resets.attempts column missing. Run migration_025_system_improvements.sql');
    }
    $len_check = $conn->query("SELECT CHARACTER_MAXIMUM_LENGTH AS len FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'password_resets' AND COLUMN_NAME = 'otp_code'");
    if ($len_check && ($len_row = $len_check->fetch_assoc()) && (int)$len_row['len'] < 100) {
        error_log('CPMMIS WARNING: password_resets.otp_code column too short. Run migration_025_system_improvements.sql');
    }
    $lock_col = $conn->query("SHOW COLUMNS FROM password_resets LIKE 'locked_at'");
    if (!$lock_col || $lock_col->num_rows === 0) {
        error_log('CPMMIS WARNING: password_resets.locked_at column missing. Run migration_025_system_improvements.sql');
    }
}

// Clean up old OTPs for this email
$del = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
if ($del) {
    $del->bind_param("s", $email);
    $del->execute();
}

// Insert new OTP with timestamp (hashed — plaintext codes are never stored)
$otp_hash = password_hash($otp, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO password_resets (email, otp_code, attempts, created_at) VALUES (?, ?, 0, NOW())");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to generate secure token.']);
    exit();
}
$stmt->bind_param("ss", $email, $otp_hash);
if (!$stmt->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to generate secure token.']);
    exit();
}

// Try sending email via Gmail SMTP
include_once(__DIR__ . '/../mailer.php');

$subject = "CPMMIS Password Recovery Code";
$message = "
<html>
<body style='font-family:Arial,sans-serif;background:#f4f6f8;padding:30px;'>
  <div style='max-width:480px;margin:auto;background:#ffffff;border-radius:12px;padding:32px;border:1px solid #e5e7eb;'>
    <h2 style='color:#F0F2F5;margin-top:0;'>Password Recovery</h2>
    <p style='color:#475569;font-size:14px;'>You requested a password reset for your CPMMIS account. Use the verification code below to continue:</p>
    <div style='text-align:center;margin:24px 0;'>
      <span style='display:inline-block;font-size:28px;font-weight:bold;letter-spacing:8px;color:#1877F2;background:#ecfdf5;padding:14px 24px;border-radius:10px;'>{$otp}</span>
    </div>
    <p style='color:#94a3b8;font-size:12px;'>This code will expire in 15 minutes. If you did not request this, you can safely ignore this email. Do not share this code with anyone.</p>
  </div>
</body>
</html>";

$mail_error = '';
$mail_sent = cpmmis_send_email($email, $subject, $message, $mail_error);

$_SESSION['otp_last_sent'] = time();

if ($mail_sent) {
    echo json_encode(['status' => 'success', 'message' => 'If an account exists for that email, a recovery code has been sent.', 'cooldown' => 60]);
} else {
    // Failure details (incl. SMTP host/response text) are logged only — never
    // returned, so they cannot reveal config OR confirm the account exists.
    // The client-facing response is identical to the "not found" branch.
    error_log('CPMMIS mail error for ' . $email . ': ' . $mail_error);
    echo json_encode(['status' => 'success', 'message' => 'If an account exists for that email, a recovery code has been sent.', 'cooldown' => 60]);
}
?>
