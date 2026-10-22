<?php
header('Content-Type: application/json');

include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');

class RegistrationValidationException extends Exception {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid security token. Please refresh the page.']);
        exit();
    }

    // Registration abuse control: limit to 3 registrations per IP per hour.
    // Uses the DB-backed login_rate_limits table so clearing cookies cannot
    // bypass it. This blocks mass sign-up spam / storage DoS.
    $reg_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    try {
        $rl_key = 'reg_' . date('YmdH') . '_' . $reg_ip;
        $rl_stmt = $conn->prepare("SELECT attempts FROM login_rate_limits WHERE ip_address = ? LIMIT 1");
        $rl_stmt->bind_param("s", $rl_key);
        $rl_stmt->execute();
        $rl_row = $rl_stmt->get_result()->fetch_assoc();
        if ($rl_row && (int)$rl_row['attempts'] >= 3) {
            echo json_encode(['status' => 'error', 'message' => 'Too many registration attempts. Please try again later.']);
            exit();
        }
    } catch (Throwable $e) {
        // Rate limiting is best-effort; never block legitimate registration
        // because the helper table is unavailable.
        error_log('CPMMIS registration rate-limit check failed: ' . $e->getMessage());
    }

    try {
        $group_name    = trim($_POST['group_name'] ?? '');
        $leader_name   = trim($_POST['leader_name'] ?? '');
        $leader_email  = trim($_POST['leader_email'] ?? '');
        $password      = $_POST['password'] ?? '';
        $project_title = trim($_POST['project_title'] ?? '');

        if (empty($group_name) || empty($leader_name) || empty($leader_email) || empty($password) || empty($project_title)) {
            throw new RegistrationValidationException('All registration fields are required.');
        }

        // Group name is stored in a varchar(50) UNIQUE column, so the max
        // length here must never exceed the column width or the insert fails.
        if (!preg_match('/^[a-zA-Z0-9_.\s-]{3,50}$/', $group_name)) {
            throw new RegistrationValidationException('Group ID/Name must be 3-50 characters using letters, numbers, space, underscore, period, or hyphen.');
        }

        if (strlen($leader_name) > 255) {
            throw new RegistrationValidationException('Leader name must be 255 characters or fewer.');
        }

        if (strlen($project_title) > 255) {
            throw new RegistrationValidationException('Project title must be 255 characters or fewer.');
        }

        // Enforce password policy
        $pw_err = validate_password($password);
        if ($pw_err !== '') {
            throw new RegistrationValidationException($pw_err);
        }

        if (!filter_var($leader_email, FILTER_VALIDATE_EMAIL)) {
            throw new RegistrationValidationException('Please provide a valid email address structure.');
        }

        $stmt = $conn->prepare("SELECT id FROM `group` WHERE LOWER(project_title) = LOWER(?) LIMIT 1");
        $stmt->bind_param("s", $project_title);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new RegistrationValidationException('This Capstone Title is already registered.');
        }

        $stmt = $conn->prepare("SELECT id FROM `group` WHERE LOWER(username) = LOWER(?) LIMIT 1");
        $stmt->bind_param("s", $group_name);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new RegistrationValidationException('Group ID/Name is already taken, even with different capitalization.');
        }

        $stmt = $conn->prepare("SELECT id FROM `group` WHERE leader_email = ? LIMIT 1");
        $stmt->bind_param("s", $leader_email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new RegistrationValidationException('This email address is already linked to a registered group.');
        }

        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $conn->prepare("INSERT INTO `group` (username, full_name, leader_email, password, project_title, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("sssss", $group_name, $leader_name, $leader_email, $hashed_password, $project_title);

        if ($stmt->execute()) {
            $new_group_id = $conn->insert_id;
            $notif_msg = "Group account registered: " . $group_name;

            $admin_stmt = $conn->prepare("SELECT id FROM admin LIMIT 1");
            $admin_stmt->execute();
            $admin_row = $admin_stmt->get_result()->fetch_assoc();
            $admin_id = $admin_row['id'] ?? 1;

            $check_fields = $conn->query("SHOW COLUMNS FROM `notifications` LIKE 'group_id'");
            $has_group_id = $check_fields && $check_fields->num_rows > 0;

            if ($has_group_id) {
                $stmt2 = $conn->prepare("INSERT INTO notifications (user_id, user_role, message, is_read, group_id, created_at) VALUES (?, 'admin', ?, 0, ?, NOW())");
                $stmt2->bind_param("isi", $admin_id, $notif_msg, $new_group_id);
            } else {
                $stmt2 = $conn->prepare("INSERT INTO notifications (user_id, user_role, message, is_read, created_at) VALUES (?, 'admin', ?, 0, NOW())");
                $stmt2->bind_param("is", $admin_id, $notif_msg);
            }
            $stmt2->execute();

            log_activity($conn, $new_group_id, 'group', $group_name, "Registered group account", $group_name);

            // Increment the per-IP-per-hour registration counter.
            try {
                $rl_key = 'reg_' . date('YmdH') . '_' . $reg_ip;
                $rl_upd = $conn->prepare("INSERT INTO login_rate_limits (ip_address, attempts, first_attempt) VALUES (?, 1, NOW())
                                          ON DUPLICATE KEY UPDATE attempts = attempts + 1");
                $rl_upd->bind_param("s", $rl_key);
                $rl_upd->execute();
            } catch (Throwable $e) {
                error_log('CPMMIS registration rate-limit increment failed: ' . $e->getMessage());
            }

            echo json_encode(['status' => 'success', 'message' => 'Registration submitted for approval!']);
        } else {
            throw new Exception('Registration failed. Please try again later.');
        }

    } catch (RegistrationValidationException $e) {
        // User input problems are safe to tell the user about; misreporting
        // them as a server failure makes the form unusable.
        error_log('CPMMIS group registration validation: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } catch (Exception $e) {
        // Never surface the raw exception (it can embed SQL/connection detail).
        error_log('CPMMIS group registration failed: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Registration failed. Please try again later.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>