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

$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($token)) {
    echo json_encode(['status' => 'error', 'message' => 'Security token validation failed']);
    exit();
}

// Layer 1 (session-bound): 10 failed verifications trigger a 1-minute cooldown
$attempt_key = 'otp_fail_' . date('YmdH');
$lock_until = $_SESSION['otp_fail_lock_until'] ?? 0;

if (time() < $lock_until) {
    $retry_after = $lock_until - time();
    echo json_encode([
        'status' => 'error',
        'message' => "Too many failed attempts. Please wait {$retry_after}s before trying again.",
        'retry_after' => $retry_after
    ]);
    exit();
}

if ($lock_until > 0) {
    // Cooldown served — grant a fresh set of attempts
    unset($_SESSION['otp_fail_lock_until']);
    $_SESSION[$attempt_key] = 0;
}

// IP-level throttle: this endpoint is public and previously only carried a
// session counter for non-existent emails, which cookie-clearing bypassed.
// DB-backed IP limiting bounds account-existence probing across sessions.
$verify_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ip_wait = ip_rate_limit_check($conn, $verify_ip);
if ($ip_wait > 0) {
    echo json_encode([
        'status' => 'error',
        'message' => "Too many attempts. Please wait {$ip_wait}s before trying again.",
        'retry_after' => $ip_wait
    ]);
    exit();
}

$email = strtolower(trim($_POST['email'] ?? ''));
$otp = trim($_POST['otp'] ?? '');

if (empty($email) || empty($otp)) {
    echo json_encode(['status' => 'error', 'message' => 'Please provide the OTP code.']);
    exit();
}

// Strict input shape checks before doing any DB work / bcrypt cost.
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{6}$/', $otp)) {
    echo json_encode(['status' => 'error', 'message' => 'The code you entered is invalid or has expired.']);
    exit();
}

// Layer 2 (database-backed): fetch the hashed OTP and its failure counter.
$stmt = $conn->prepare("SELECT id, otp_code, attempts, locked_at FROM password_resets WHERE email = ? AND created_at >= NOW() - INTERVAL 15 MINUTE LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

// Hard cap: a single code is permanently burned after this many total
// failures. 6-digit codes allow ~70-75 guesses under the old "reset after
// cooldown" design; this bounds a code to a strictly limited budget.
$otp_hard_cap = 10;
$otp_lock_threshold = 5;

if (!$row) {
    $new_fails = ($_SESSION[$attempt_key] ?? 0) + 1;
    $_SESSION[$attempt_key] = $new_fails;
    if ($new_fails >= 10) $_SESSION['otp_fail_lock_until'] = time() + 60;
    ip_rate_limit_fail($conn, $verify_ip);
    echo json_encode(['status' => 'error', 'message' => 'The code you entered is invalid or has expired. Please click Resend Code to get a new one.']);
    exit();
}

$attempts = (int)$row['attempts'];
$lock_seconds = 60;

if ($attempts >= $otp_hard_cap) {
    // Code budget exhausted: burn the code so every later guess hits the
    // generic "expired" path instead of revealing a live reset request.
    $burn = $conn->prepare("DELETE FROM password_resets WHERE id = ?");
    $burn->bind_param("i", $row['id']);
    $burn->execute();
    ip_rate_limit_fail($conn, $verify_ip);
    echo json_encode(['status' => 'error', 'message' => 'The code you entered is invalid or has expired. Please click Resend Code to get a new one.']);
    exit();
}

if ($attempts >= $otp_lock_threshold) {
    // A cooldown between attempt bursts. It does NOT reset `attempts`, so the
    // total failure budget above still holds for this code.
    $locked_ts = !empty($row['locked_at']) ? strtotime($row['locked_at']) : 0;
    $elapsed = time() - $locked_ts;
    if ($elapsed < $lock_seconds) {
        $wait = min(max(1, $lock_seconds - $elapsed), $lock_seconds);
        echo json_encode([
            'status' => 'error',
            'message' => "Too many incorrect attempts. Please wait {$wait}s before trying again.",
            'retry_after' => $wait
        ]);
        exit();
    }
}

// otp_code stores a bcrypt hash; compare with password_verify().
if (password_verify($otp, $row['otp_code'])) {
    unset($_SESSION[$attempt_key], $_SESSION['otp_fail_lock_until']); // success clears all failure tracking
    ip_rate_limit_clear($conn, $verify_ip);
    $_SESSION['otp_verified'] = true;
    $_SESSION['otp_verified_email'] = $email;
    echo json_encode(['status' => 'success', 'message' => 'Code verified successfully.']);
} else {
    $new_fails = ($_SESSION[$attempt_key] ?? 0) + 1;
    $_SESSION[$attempt_key] = $new_fails;
    if ($new_fails >= 10) {
        $_SESSION['otp_fail_lock_until'] = time() + 60;
        echo json_encode([
            'status' => 'error',
            'message' => 'All verification attempts used. Please wait 60 seconds before trying again.',
            'retry_after' => 60
        ]);
        exit();
    }

    $next_attempts = $attempts + 1;
    $locked_now = date('Y-m-d H:i:s');
    $rid = (int)$row['id'];
    $upd = $conn->prepare("UPDATE password_resets SET attempts = ? , locked_at = ? WHERE id = ?");
    $upd->bind_param("isi", $next_attempts, $locked_now, $rid);
    $upd->execute();
    ip_rate_limit_fail($conn, $verify_ip);

    if ($next_attempts >= $otp_hard_cap) {
        // Budget exhausted — burn again for correctness (the code is dead).
        $burn = $conn->prepare("DELETE FROM password_resets WHERE id = ?");
        $burn->bind_param("i", $rid);
        $burn->execute();
        echo json_encode(['status' => 'error', 'message' => 'The code you entered is invalid or has expired. Please click Resend Code to get a new one.']);
        exit();
    }

    if ($next_attempts >= $otp_lock_threshold) {
        echo json_encode([
            'status' => 'error',
            'message' => 'All attempts used. Please wait 60 seconds before trying again.',
            'retry_after' => $lock_seconds
        ]);
        exit();
    }

    // Generic failure — the "attempt(s) remaining" leak is intentionally gone.
    echo json_encode(['status' => 'error', 'message' => 'The code you entered is invalid or has expired. Please click Resend Code to get a new one.']);
}
?>
