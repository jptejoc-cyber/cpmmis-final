<?php
include(__DIR__ . '/../session_init.php');
header('Content-Type: application/json');
include(__DIR__ . '/../db_config.php');

error_reporting(0);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid security token. Please refresh the page.']);
        exit();
    }

    // Rate limiting (session-based, per-session)
    $login_attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'first' => 0];
    if ($login_attempts['count'] >= 5 && time() - $login_attempts['first'] < 60) {
        echo json_encode(['status' => 'error', 'message' => 'Too many login attempts. Please wait 60 seconds.']);
        exit();
    }
    if (time() - $login_attempts['first'] > 60) {
        $login_attempts = ['count' => 0, 'first' => time()];
    }

    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    // Rate limiting (IP-based) backed by the database so it cannot
    // be bypassed by clearing cookies or changing sessions.
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $rl_wait = ip_rate_limit_check($conn, $client_ip);
    if ($rl_wait > 0) {
        echo json_encode(['status' => 'error', 'message' => "Too many login attempts. Please wait {$rl_wait} seconds."]);
        exit();
    }

    if (empty($user) || empty($pass)) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill all fields.']);
        exit();
    }

    $login_found = false;

    // 1. CHECK ADMIN TABLE (hashed password)
    $stmt = $conn->prepare("SELECT * FROM admin WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $admin_result = $stmt->get_result();
    if ($admin_result->num_rows > 0) {
        $row = $admin_result->fetch_assoc();
        if (password_verify($pass, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['role'] = 'admin';
            $_SESSION['username'] = $row['username'];
            $_SESSION['session_version'] = $row['session_version'] ?? null;
            $login_found = true;
        }
    }

    // 2. CHECK ADVISER TABLE (hashed password)
    if (!$login_found) {
        $stmt = $conn->prepare("SELECT * FROM adviser WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $adv_result = $stmt->get_result();
        if ($adv_result->num_rows > 0) {
            $row = $adv_result->fetch_assoc();
            if (password_verify($pass, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['role'] = 'adviser';
                $_SESSION['username'] = $row['username'];
                $_SESSION['session_version'] = $row['session_version'] ?? null;
                $login_found = true;
            }
        }
    }

    // 3. CHECK GROUP TABLE
    if (!$login_found) {
        $stmt = $conn->prepare("SELECT * FROM `group` WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $group_result = $stmt->get_result();
        if ($group_result->num_rows > 0) {
            $row = $group_result->fetch_assoc();

            if (password_verify($pass, $row['password'])) {

                if (!empty($row['banned_until']) && strtotime($row['banned_until']) > time()) {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Your account has been suspended. Please contact the administrator.'
                    ]);
                    exit();
                }

                $allowed_statuses = ['active', 'approved'];
                if (!in_array(($row['status'] ?? ''), $allowed_statuses)) {
                    $msg = (($row['status'] ?? '') === 'pending')
                        ? 'Your account is pending. Please wait for admin approval.'
                        : 'Your account is not active. Please contact the administrator.';
                    echo json_encode(['status' => 'error', 'message' => $msg]);
                    exit();
                }

                $_SESSION['user_id'] = $row['id'];
                $_SESSION['role'] = 'group';
                $_SESSION['username'] = $row['username'];
                $_SESSION['session_version'] = $row['session_version'] ?? null;
                $login_found = true;
            }
        }
    }

    if ($login_found) {
        session_regenerate_id(true);
        $_SESSION['login_time'] = time();
        rotate_csrf_token();

        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $device = "Unknown Device";
        if (preg_match('/Mobile/i', $user_agent)) $device = "Mobile Device";
        elseif (preg_match('/Windows/i', $user_agent)) $device = "Windows PC";
        elseif (preg_match('/Mac/i', $user_agent)) $device = "Macintosh";

        $ip = $_SERVER['REMOTE_ADDR'];
        $role = $_SESSION['role'];
        $u_id = $_SESSION['user_id'];

    try {
        $stmt = $conn->prepare("INSERT INTO login_logs (user_id, role, device_info, ip_address) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isss", $u_id, $role, $device, $ip);
            $stmt->execute();
        }
    } catch (Throwable $e) {
        // Logging must never hide a successful login.
        error_log("CPMMIS login_logs write failed: " . $e->getMessage());
    }

    $username = $_SESSION['username'] ?? '';
    try {
        log_activity($conn, $u_id, $role, $username, "Logged in", "IP: $ip");
    } catch (Throwable $e) {
        error_log("CPMMIS activity_log write failed: " . $e->getMessage());
    }

        $redirect_url = "";
        if ($role === 'admin') {
            $redirect_url = "admin/admin_dashboard.php";
        } elseif ($role === 'adviser') {
            $redirect_url = "adviser/adviser_dashboard.php";
        } elseif ($role === 'group') {
            $redirect_url = "group/group_dashboard.php";
        }

        $_SESSION['login_attempts'] = ['count' => 0, 'first' => 0];
        ip_rate_limit_clear($conn, $client_ip);
        echo json_encode([
            'status' => 'success',
            'role' => $role,
            'redirect' => $redirect_url
        ]);
        exit();
    } else {
        $login_attempts['count']++;
        $_SESSION['login_attempts'] = $login_attempts;
        ip_rate_limit_fail($conn, $client_ip);
        echo json_encode(['status' => 'error', 'message' => 'The user or password you entered is incorrect.']);
        exit;
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}
?>