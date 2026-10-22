<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_SESSION['role'] === 'admin') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        header("Location: ../../admin/advisers.php?error=csrf");
        exit();
    }

    try {
        $full_name = trim($_POST['full_name']);
        $username  = trim($_POST['username']);
        $email     = trim($_POST['email']);
        $password  = $_POST['password'];

        // Validate required fields
        if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
            throw new Exception('All fields are required.');
        }

        // Email format validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please provide a valid email address.');
        }

        // Password policy
        $pw_err = validate_password($password);
        if ($pw_err !== '') {
            throw new Exception($pw_err);
        }

        // Validate username format (alphanumeric, underscore, period, 3-32 chars)
        if (!preg_match('/^[a-zA-Z0-9_.]{3,32}$/', $username)) {
            throw new Exception('Username must be 3-32 characters using letters, numbers, underscore, or period.');
        }

        // 1. Check for Duplication
        $stmt = $conn->prepare("SELECT id FROM adviser WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            header("Location: ../../admin/advisers.php?error=duplicate");
            exit();
        }

        // 2. Hash password and insert
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO adviser (full_name, username, email, password) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $full_name, $username, $email, $hashed_password);

        if ($stmt->execute()) {
            $admin_stmt = $conn->prepare("SELECT id FROM admin LIMIT 1");
            $admin_stmt->execute();
            $admin_row = $admin_stmt->get_result()->fetch_assoc();
            $admin_id = $admin_row['id'] ?? 1;
            $broadcast_msg = "New faculty adviser account has been registered: " . $full_name;
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, message, is_read, created_at) VALUES (?, 'admin', ?, 0, NOW())");
            $stmt->bind_param("is", $admin_id, $broadcast_msg);
            $stmt->execute();

            // Fan out one row per active group so group dashboards ("Recovery Engine" queries
            // filtering user_role='group') actually receive the notice.
            $groups_res = $conn->query("SELECT id FROM `group` WHERE status IS NULL OR status NOT IN ('inactive','archived','pending')");
            if ($groups_res) {
                $grp_stmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, message, is_read, created_at) VALUES (?, 'group', ?, 0, NOW())");
                while ($g = $groups_res->fetch_assoc()) {
                    $gid = (int)$g['id'];
                    $grp_stmt->bind_param("is", $gid, $broadcast_msg);
                    $grp_stmt->execute();
                }
            }

            log_activity($conn, $_SESSION['user_id'], 'admin', $_SESSION['username'] ?? '', "Added adviser", "$full_name ($username)");

            header("Location: ../../admin/advisers.php?status=added");
            exit();
        }
    } catch (Exception $e) {
        die("An error occurred while creating the adviser account.");
    }
}
?>