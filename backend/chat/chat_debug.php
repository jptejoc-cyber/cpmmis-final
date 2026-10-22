<?php
header('Content-Type: application/json');

try {
    include(__DIR__ . '/../session_init.php');
    include(__DIR__ . '/../db_config.php');
    include(__DIR__ . '/chat_common.php');

    // Diagnostic endpoint: admin-only AND disabled outside the local/dev
    // environment so it can never leak internal state in production.
    if (defined('CPMMIS_ENV') && CPMMIS_ENV === 'live') {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
        exit;
    }
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }

    $results = [];

    // 1. Session check
    $has_session = isset($_SESSION['user_id']) && isset($_SESSION['role']);
    $results['session'] = [
        'pass' => $has_session,
        'user_id' => $has_session ? (int)$_SESSION['user_id'] : null,
        'role' => $has_session ? $_SESSION['role'] : null,
        'message' => $has_session ? 'Logged in as ' . $_SESSION['role'] . ' #' . $_SESSION['user_id'] : 'Not logged in or session expired'
    ];

    if (!$has_session) {
        echo json_encode(['status' => 'error', 'diagnostics' => $results], JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    $user_id = (int)$_SESSION['user_id'];
    $role = $_SESSION['role'];

    // 2. Database connection
    $results['database'] = [
        'pass' => isset($conn) && !$conn->connect_error,
        'message' => (isset($conn) && !$conn->connect_error) ? 'Connected' : 'Connection failed'
    ];

    // 3. Group record check
    if ($role === 'group') {
        $stmt = $conn->prepare("SELECT id, adviser_id, status, full_name FROM `group` WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $grp_q = $stmt->get_result();
        $grp_data = ($grp_q && mysqli_num_rows($grp_q) > 0) ? mysqli_fetch_assoc($grp_q) : null;

        $results['group_record'] = [
            'pass' => $grp_data !== null,
            'data' => $grp_data ? [
                'id' => (int)$grp_data['id'],
                'adviser_id' => $grp_data['adviser_id'] !== null ? (int)$grp_data['adviser_id'] : null,
                'status' => $grp_data['status'],
                'full_name' => $grp_data['full_name']
            ] : null,
            'message' => $grp_data ? 'Group record found' : 'Group record NOT found in database'
        ];

        if (!$grp_data) {
            echo json_encode(['status' => 'ok', 'diagnostics' => $results], JSON_INVALID_UTF8_SUBSTITUTE);
            exit;
        }

        // 4. Adviser assignment check
        $has_adviser = $grp_data['adviser_id'] !== null && (int)$grp_data['adviser_id'] > 0;
        $results['adviser_assigned'] = [
            'pass' => $has_adviser,
            'adviser_id' => $has_adviser ? (int)$grp_data['adviser_id'] : null,
            'message' => $has_adviser ? 'Adviser #' . $grp_data['adviser_id'] . ' assigned' : 'NO adviser assigned (adviser_id is NULL or 0)'
        ];

        if ($has_adviser) {
            $adv_id = (int)$grp_data['adviser_id'];

            // 5. Adviser existence check
            $stmt = $conn->prepare("SELECT id, full_name, status FROM adviser WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $adv_id);
            $stmt->execute();
            $adv_q = $stmt->get_result();
            $adv_data = ($adv_q && mysqli_num_rows($adv_q) > 0) ? mysqli_fetch_assoc($adv_q) : null;

            $results['adviser_exists'] = [
                'pass' => $adv_data !== null,
                'adviser_id' => $adv_id,
                'data' => $adv_data ? [
                    'id' => (int)$adv_data['id'],
                    'full_name' => $adv_data['full_name'],
                    'status' => $adv_data['status']
                ] : null,
                'message' => $adv_data ? 'Adviser "' . $adv_data['full_name'] . '" found' : 'Adviser #' . $adv_id . ' NOT found in adviser table'
            ];

            // 6. Partner authorization check
            $partner_ok = false;
            if ($adv_data) {
                $stmt = $conn->prepare("SELECT id FROM `group` WHERE id = ? AND adviser_id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("ii", $user_id, $adv_id);
                    $stmt->execute();
                    $partner_ok = (bool)$stmt->get_result()->fetch_assoc();
                }
            }

            $results['partner_allowed'] = [
                'pass' => $partner_ok,
                'message' => $partner_ok ? 'chat_partner_allowed() PASSED' : 'chat_partner_allowed() FAILED - group #' . $user_id . ' is NOT assigned to adviser #' . $adv_id
            ];
        } else {
            $results['adviser_exists'] = [
                'pass' => false,
                'message' => 'Skipped - no adviser assigned'
            ];
            $results['partner_allowed'] = [
                'pass' => false,
                'message' => 'Skipped - no adviser assigned'
            ];
        }
    } elseif ($role === 'adviser') {
        // Adviser diagnostics
        $stmt = $conn->prepare("SELECT id, full_name, status FROM adviser WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $adv_q = $stmt->get_result();
        $adv_data = ($adv_q && mysqli_num_rows($adv_q) > 0) ? mysqli_fetch_assoc($adv_q) : null;

        $results['adviser_record'] = [
            'pass' => $adv_data !== null,
            'data' => $adv_data ? [
                'id' => (int)$adv_data['id'],
                'full_name' => $adv_data['full_name'],
                'status' => $adv_data['status']
            ] : null,
            'message' => $adv_data ? 'Adviser record found' : 'Adviser record NOT found'
        ];

        // Count assigned groups
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM `group` WHERE adviser_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $grp_count = (int)$stmt->get_result()->fetch_assoc()['cnt'];
        $results['assigned_groups'] = [
            'pass' => $grp_count > 0,
            'count' => $grp_count,
            'message' => $grp_count > 0 ? $grp_count . ' group(s) assigned' : 'NO groups assigned to this adviser'
        ];
    } elseif ($role === 'admin') {
        $results['admin_check'] = [
            'pass' => true,
            'message' => 'Admin role - can message anyone'
        ];
    }

    // 7. Messages table check
    $msg_table_q = @mysqli_query($conn, "SELECT 1 FROM messages LIMIT 1");
    $results['messages_table'] = [
        'pass' => $msg_table_q !== false,
        'message' => $msg_table_q !== false ? 'messages table exists and accessible' : 'messages table MISSING or inaccessible'
    ];

    echo json_encode(['status' => 'ok', 'diagnostics' => $results], JSON_INVALID_UTF8_SUBSTITUTE);

} catch (Throwable $e) {
    error_log('chat_debug error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Debug endpoint error'
    ], JSON_INVALID_UTF8_SUBSTITUTE);
}
