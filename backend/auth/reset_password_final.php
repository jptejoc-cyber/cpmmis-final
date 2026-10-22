<?php
header('Content-Type: application/json');
include(__DIR__ . '/../db_config.php');
include(__DIR__ . '/../session_init.php');

error_reporting(0);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

$max_attempts = 5;
$lockout_seconds = 60;

$email = strtolower(trim($_POST['email'] ?? ''));
$otp = trim($_POST['otp'] ?? '');
$new_password = $_POST['new_password'] ?? '';

if (empty($email) || empty($otp) || empty($new_password)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required data.']);
    exit();
}

// Strict input-shape checks before any session/DB work (mirrors verify_otp.php)
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{6}$/', $otp)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or expired OTP. Cannot reset password.']);
    exit();
}

// Session key derived from a validated, bounded value — never raw attacker
// input — so a crafted email cannot inject an arbitrary session key.
$attempt_key = 'reset_pwd_attempts_' . md5($email);

$attempts = $_SESSION[$attempt_key] ?? ['count' => 0, 'first_failed' => 0];
if ($attempts['count'] >= $max_attempts) {
    $elapsed = time() - $attempts['first_failed'];
    if ($elapsed < $lockout_seconds) {
        $remaining = max(1, $lockout_seconds - $elapsed);
        echo json_encode([
            'status' => 'error',
            'message' => "Too many attempts. Please wait {$remaining}s before trying again.",
            'retry_after' => $remaining
        ]);
        exit();
    }
    // Reset after lockout period
    $_SESSION[$attempt_key] = ['count' => 0, 'first_failed' => 0];
}

$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($token)) {
    echo json_encode(['status' => 'error', 'message' => 'Security token validation failed']);
    exit();
}

$pw_err = validate_password($new_password);
if ($pw_err !== '') {
    echo json_encode(['status' => 'error', 'message' => $pw_err]);
    exit();
}

$conn->begin_transaction();

try {
    // Verify OTP with 15-minute expiry (otp_code is a bcrypt hash)
    $stmt = $conn->prepare("SELECT id, otp_code, attempts FROM password_resets WHERE email = ? AND created_at >= NOW() - INTERVAL 15 MINUTE LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $otp_row = $stmt->get_result()->fetch_assoc();

    $otp_valid = false;
    if ($otp_row && (int)$otp_row['attempts'] < 5 && password_verify($otp, $otp_row['otp_code'])) {
        $otp_valid = true;
    }

    if (!$otp_valid) {
        $conn->rollback();
        // Persist the failure so it survives cookie clearing
        if ($otp_row) {
            $upd = $conn->prepare("UPDATE password_resets SET attempts = attempts + 1 WHERE id = ?");
            $upd->bind_param("i", $otp_row['id']);
            $upd->execute();
        }
        // Track failed attempt
        $attempts['count']++;
        if ($attempts['count'] === 1) {
            $attempts['first_failed'] = time();
        }
        $_SESSION[$attempt_key] = $attempts;
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired OTP. Cannot reset password.']);
        exit();
    }

    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
    $updated = false;

    $stmt = $conn->prepare("SELECT id FROM `group` WHERE leader_email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE `group` SET password = ? WHERE leader_email = ?");
        $stmt->bind_param("ss", $hashed_password, $email);
        $stmt->execute();
        $updated = true;
    }

    if (!$updated) {
        $stmt = $conn->prepare("SELECT id FROM adviser WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE adviser SET password = ? WHERE email = ?");
            $stmt->bind_param("ss", $hashed_password, $email);
            $stmt->execute();
            $updated = true;
        }
    }

    if (!$updated) {
        $stmt = $conn->prepare("SELECT id FROM admin WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE admin SET password = ? WHERE email = ?");
            $stmt->bind_param("ss", $hashed_password, $email);
            $stmt->execute();
            $updated = true;
        }
    }

    if ($updated) {
        unset($_SESSION[$attempt_key]); // Reset rate limit on success
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();

        // Bump session version to invalidate all other sessions for this user
        $new_version = bin2hex(random_bytes(16));
        $stmt = $conn->prepare("UPDATE `group` SET session_version = ? WHERE leader_email = ?");
        $stmt->bind_param("ss", $new_version, $email);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            $stmt = $conn->prepare("UPDATE adviser SET session_version = ? WHERE email = ?");
            $stmt->bind_param("ss", $new_version, $email);
            $stmt->execute();
            if ($stmt->affected_rows === 0) {
                $stmt = $conn->prepare("UPDATE admin SET session_version = ? WHERE email = ?");
                $stmt->bind_param("ss", $new_version, $email);
                $stmt->execute();
            }
        }

        $conn->commit();
        // Invalidate any existing session that may be tied to this device and
        // rotate the CSRF token so a pre-reset token cannot be replayed.
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
            $_SESSION['session_version'] = $new_version;
            session_regenerate_id(true);
            rotate_csrf_token();
        }
        echo json_encode(['status' => 'success', 'message' => 'Password reset securely. You can now log in.']);
    } else {
        $conn->rollback();
        // Generic message to avoid revealing whether the account exists.
        echo json_encode(['status' => 'error', 'message' => 'The reset link or code is invalid or has expired.']);
    }
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'An error occurred. Please try again.']);
}
?>
