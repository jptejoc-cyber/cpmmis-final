<?php

if (!headers_sent()) {
    header('Content-Type: application/json');
}

function chat_respond($data, $code = 200) {
    http_response_code($code);
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    $json = json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        $json = json_encode(['status' => 'error', 'message' => 'Encoding error'], JSON_INVALID_UTF8_SUBSTITUTE);
    }
    echo $json;
    exit;
}

function chat_guard($allowed = ['admin', 'adviser', 'group']) {
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed, true)) {
        chat_respond(['status' => 'error', 'message' => 'Unauthorized access'], 401);
    }
    return ['id' => (int)$_SESSION['user_id'], 'role' => $_SESSION['role']];
}

function chat_partner_allowed(mysqli $conn, $me, $other_id, $other_role) {
    if ($me['role'] === 'admin' || $other_role === 'admin') {
        return true;
    }
    try {
        if ($me['role'] === 'adviser' && $other_role === 'group') {
            $stmt = $conn->prepare("SELECT id FROM `group` WHERE id = ? AND adviser_id = ? LIMIT 1");
            if (!$stmt) return false;
            $stmt->bind_param("ii", $other_id, $me['id']);
            $stmt->execute();
            return (bool)$stmt->get_result()->fetch_assoc();
        }
        if ($me['role'] === 'group' && $other_role === 'adviser') {
            $stmt = $conn->prepare("SELECT id FROM `group` WHERE id = ? AND adviser_id = ? LIMIT 1");
            if (!$stmt) return false;
            $stmt->bind_param("ii", $me['id'], $other_id);
            $stmt->execute();
            return (bool)$stmt->get_result()->fetch_assoc();
        }
    } catch (Exception $e) {
        error_log('chat_partner_allowed error: ' . $e->getMessage());
        return false;
    }
    return false;
}

function chat_stmt(mysqli $conn, $sql, $bind = [], $errorMsg = 'Database error') {
    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            chat_respond(['status' => 'error', 'message' => $errorMsg], 500);
        }
        if ($bind) {
            $types = array_shift($bind);
            $args = [$types];
            foreach ($bind as $i => &$value) {
                $args[] = &$value;
            }
            unset($value);
            call_user_func_array([$stmt, 'bind_param'], $args);
        }
        $stmt->execute();
        return $stmt;
    } catch (mysqli_sql_exception $e) {
        chat_respond(['status' => 'error', 'message' => $errorMsg], 500);
    }
}
