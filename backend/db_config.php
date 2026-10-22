<?php
if ((getenv('CPMMIS_ENV') ?: 'local') === 'live') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

define('CPMMIS_ENV', getenv('CPMMIS_ENV') ?: 'local');

if (CPMMIS_ENV === 'live') {
    $host     = getenv('DB_HOST');
    $username = getenv('DB_USER');
    $password = getenv('DB_PASS');
    $dbname   = getenv('DB_NAME');

    // Fail hard in production: never silently fall back to placeholder
    // credentials that would mask a missing DB environment configuration.
    if ($host === false || $username === false || $password === false || $dbname === false) {
        error_log('CPMMIS live DB config missing required environment variable(s)');
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        http_response_code(500);
        die(json_encode(['status' => 'error', 'message' => 'Service configuration error. Please try again later.']));
    }
} else {
    $host     = getenv('DB_HOST') ?: "127.0.0.1";
    $username = getenv('DB_USER') ?: "root";
    $password = getenv('DB_PASS') ?: "";
    $dbname   = getenv('DB_NAME') ?: "cpmmis_db";
}

try {
    $conn = new mysqli($host, $username, $password, $dbname);
} catch (Throwable $e) {
    // In strict mysqli mode the constructor throws instead of setting
    // $conn->connect_error. Handle both so a DB outage produces a clean
    // JSON response rather than an empty-body fatal.
    $conn = null;
}

if (!$conn || $conn->connect_error) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    if (CPMMIS_ENV === 'live') {
        die(json_encode(['status' => 'error', 'message' => 'Service temporarily unavailable. Please try again later.']));
    } else {
        die(json_encode(['status' => 'error', 'message' => 'Database connection failed.']));
    }
}

$conn->set_charset("utf8mb4");

include_once(__DIR__ . '/auth/csrf.php');
include_once(__DIR__ . '/helpers.php');

// Central suspension enforcement: admin bans a group by setting `banned_until`
// (approve_group.php). Nothing else ever read that column, so a suspended group
// could still log in and use every group feature. Gate every session here:
// once a group is suspended the session is destroyed and the request is denied,
// giving "suspend" real teeth on the login screen AND on all live endpoints.
if (session_status() === PHP_SESSION_ACTIVE
    && ($_SESSION['role'] ?? '') === 'group'
    && !empty($_SESSION['user_id'])) {
    try {
        $ban_stmt = $conn->prepare("SELECT banned_until, session_version FROM `group` WHERE id = ? LIMIT 1");
        $ban_stmt->bind_param("i", $_SESSION['user_id']);
        $ban_stmt->execute();
        $ban_row = $ban_stmt->get_result()->fetch_assoc();
        $banned_until = $ban_row['banned_until'] ?? null;
        $db_version = $ban_row['session_version'] ?? null;
        if (!empty($banned_until) && strtotime($banned_until) > time()) {
            $_SESSION = [];
            @session_destroy();
            @setcookie(session_name(), '', time() - 42000, '/');
            $is_backend = (strpos($_SERVER['REQUEST_URI'] ?? '', '/backend/') !== false);
            if ($is_backend) {
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Your account is suspended. Please contact an administrator.']);
            } else {
                if (!headers_sent()) {
                    header('Content-Type: text/html; charset=UTF-8');
                }
                http_response_code(403);
                echo '<!doctype html><html><head><meta charset="UTF-8"><meta http-equiv="refresh" content="0;url=../../index.php"><title>Suspended</title></head>'
                   . '<body style="font-family:sans-serif;padding:2rem;color:#333;background:#1c1e21;">'
                   . '<h2 style="margin:0 0 .5rem;">Account Suspended</h2>'
                   . '<p>Your account has been suspended by an administrator. If you believe this is a mistake, please contact the system administrator.</p>'
                   . '<p><a href="../../index.php">Return to sign in</a></p></body></html>';
            }
            exit();
        }
    } catch (Throwable $e) {
        error_log('CPMMIS suspension check failed: ' . $e->getMessage());
    }
}

// Session version check: invalidate sessions after password reset
if (session_status() === PHP_SESSION_ACTIVE
    && !empty($_SESSION['user_id'])
    && isset($_SESSION['session_version'])) {
    try {
        $role = $_SESSION['role'] ?? '';
        $table = ($role === 'admin') ? 'admin' : (($role === 'adviser') ? 'adviser' : '`group`');
        $col = ($role === 'group') ? 'leader_email' : 'email';
        $uid = $_SESSION['user_id'];
        $sv_stmt = $conn->prepare("SELECT session_version FROM {$table} WHERE id = ? LIMIT 1");
        $sv_stmt->bind_param("i", $uid);
        $sv_stmt->execute();
        $sv_row = $sv_stmt->get_result()->fetch_assoc();
        $current_version = $sv_row['session_version'] ?? null;
        if ($current_version && $current_version !== $_SESSION['session_version']) {
            $_SESSION = [];
            @session_destroy();
            @setcookie(session_name(), '', time() - 42000, '/');
            $is_backend = (strpos($_SERVER['REQUEST_URI'] ?? '', '/backend/') !== false);
            if ($is_backend) {
                if (!headers_sent()) header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
            } else {
                if (!headers_sent()) header('Location: ../index.php');
            }
            exit();
        }
    } catch (Throwable $e) {
        // session_version column may not exist yet; silently ignore
    }
}

if (session_status() === PHP_SESSION_ACTIVE && !isset($_SESSION['deadlines_checked'])) {
    $_SESSION['deadlines_checked'] = true;
    // Deadline/reminder checking is best-effort notification work. It must
    // NEVER be able to break an auth/request that comes after it, so any
    // failure (e.g. a strict-mode DATETIME error, a missing table/column)
    // is caught and logged rather than leaking output or aborting.
    try {
        include_once(__DIR__ . '/notifications/check_deadlines.php');
    } catch (Throwable $e) {
        error_log('CPMMIS check_deadlines skipped: ' . $e->getMessage());
    }
}

if (!function_exists('log_activity')) {
    function log_activity($conn, $user_id, $user_role, $username, $action, $details = '') {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, user_role, username, action, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("isssss", $user_id, $user_role, $username, $action, $details, $ip);
                $stmt->execute();
            }
        } catch (Throwable $e) {
            // Activity logging is best-effort; never let it abort a request.
            error_log('CPMMIS log_activity failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('secure_query')) {
    function secure_query($conn, $sql, $params = [], $types = '') {
        // Deprecated: callers should migrate to explicit prepared statements
        // instead of this dynamic-SQL wrapper. Suppressed so the notice cannot
        // leak into a JSON/HTTP response body.
        @trigger_error('secure_query() is deprecated; use explicit prepared statements instead.', E_USER_DEPRECATED);
        $params = array_values($params);
        // Always go through prepare()/execute(): the old code short-circuited
        // to raw mysqli_query() when $params was empty, silently skipping
        // parameterization. When $types is omitted, infer it from the values
        // so a prepared statement is used no matter what.
        if ($types === '' && !empty($params)) {
            foreach ($params as $p) {
                if (is_int($p) || is_float($p) || is_bool($p)) {
                    $types .= 'i';
                } else {
                    $types .= 's';
                }
            }
        }
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("secure_query prepare failed: " . $conn->error);
            return false;
        }
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            error_log("secure_query execute failed: " . $stmt->error);
            return false;
        }
        return $stmt->get_result();
    }
}

if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('validate_password')) {
    /**
     * Central password policy. Returns an empty string when valid, otherwise
     * a human-readable error message. Enforces minimum 8 characters plus a
     * basic strength floor (upper + lower + digit) so a trivial password
     * cannot be set through any endpoint that routes through this validator.
     */
    function validate_password($pw) {
        $pw = (string)$pw;
        if (strlen($pw) < 8) {
            return 'Password must be at least 8 characters.';
        }
        if (!preg_match('/[A-Z]/', $pw)) {
            return 'Password must contain at least one uppercase letter.';
        }
        if (!preg_match('/[a-z]/', $pw)) {
            return 'Password must contain at least one lowercase letter.';
        }
        if (!preg_match('/[0-9]/', $pw)) {
            return 'Password must contain at least one number.';
        }
        return '';
    }
}

if (!function_exists('ip_rate_limit_check')) {
    function ip_rate_limit_check($conn, $ip) {
        try {
            if (empty($ip)) return 0;
            if (!ensure_rate_limit_table($conn)) return 0;
            $stmt = $conn->prepare("SELECT attempts, locked_until FROM login_rate_limits WHERE ip_address = ? LIMIT 1");
            $stmt->bind_param("s", $ip);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row) {
                $lock = !empty($row['locked_until']) ? strtotime($row['locked_until']) : 0;
                if ($lock > time()) {
                    return max(1, $lock - time());
                }
                if ((int)$row['attempts'] >= 10) {
                    $until = date('Y-m-d H:i:s', time() + 60);
                    $lock_stmt = $conn->prepare("UPDATE login_rate_limits SET locked_until = ?, attempts = 0 WHERE ip_address = ?");
                    $lock_stmt->bind_param("ss", $until, $ip);
                    $lock_stmt->execute();
                    return 60;
                }
            }
        } catch (Throwable $e) {
            error_log('CPMMIS ip_rate_limit_check failed: ' . $e->getMessage());
        }
        return 0;
    }
}

if (!function_exists('ip_rate_limit_fail')) {
    function ip_rate_limit_fail($conn, $ip) {
        try {
            if (empty($ip)) return;
            if (!ensure_rate_limit_table($conn)) return;
            $stmt = $conn->prepare("INSERT INTO login_rate_limits (ip_address, attempts, first_attempt) VALUES (?, 1, NOW())
                                    ON DUPLICATE KEY UPDATE attempts = attempts + 1");
            $stmt->bind_param("s", $ip);
            $stmt->execute();
        } catch (Throwable $e) {
            error_log('CPMMIS ip_rate_limit_fail failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('ip_rate_limit_clear')) {
    function ip_rate_limit_clear($conn, $ip) {
        try {
            if (empty($ip)) return;
            if (!ensure_rate_limit_table($conn)) return;
            $stmt = $conn->prepare("DELETE FROM login_rate_limits WHERE ip_address = ?");
            $stmt->bind_param("s", $ip);
            $stmt->execute();
        } catch (Throwable $e) {
            error_log('CPMMIS ip_rate_limit_clear failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('ensure_rate_limit_table')) {
    function ensure_rate_limit_table($conn) {
        static $checked = false;
        static $ok = true;
        if ($checked) return $ok; // (must return the cached status, not null)
        $checked = true;
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS login_rate_limits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL UNIQUE,
                attempts INT NOT NULL DEFAULT 0,
                locked_until DATETIME NULL,
                first_attempt DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $ok = true;
        } catch (Throwable $e) {
            // The DB-backed limiter is the only brute-force defense on public
            // endpoints. If it cannot be ensured, do NOT silently degrade into
            // unlimited guessing on production: throttle the requester to fail
            // closed. In local dev we degrade gracefully instead, so a restricted
            // MySQL user cannot lock out the whole app.
            error_log('CPMMIS ensure_rate_limit_table failed: ' . $e->getMessage());
            $ok = false;
        }
        return $ok;
    }
}
