<?php
// Suppress PHP output from the very first line so a later JSON response can
// never be corrupted by a notice/warning emitted before db_config.php runs.
if ((getenv('CPMMIS_ENV') ?: 'local') === 'live') {
    error_reporting(0);
} else {
    error_reporting(E_ALL);
}
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    // Cookie lifetime (8h) and absolute session ceiling (12h). The absolute
    // ceiling is enforced independently of the idle timeout so that a chat
    // heartbeat (which touches last_activity) cannot keep a session alive
    // forever.
    $lifespan = 8 * 60 * 60;
    define('CPMMIS_ABSOLUTE_SESSION_MAX', 12 * 60 * 60);
    $port = $_SERVER['SERVER_PORT'] ?? 0;
    $is_secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int)$port === 443;
    ini_set('session.cookie_lifetime', $lifespan);
    ini_set('session.gc_maxlifetime', $lifespan);
    // Reject attacker-supplied session ids and only accept cookies (fixation
    // and injection defense in depth, on top of session_regenerate_id on login).
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    session_set_cookie_params([
        'lifetime' => $lifespan,
        'path' => '/',
        'secure' => $is_secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Idle session timeout: destroy the session after 30 minutes of inactivity.
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
    $idle_limit = 30 * 60; // 30 minutes
    $now = time();
    $last = $_SESSION['last_activity'] ?? 0;
    $login_time = $_SESSION['login_time'] ?? $now;
    $expired = false;

    // Absolute session ceiling: regardless of activity, force re-login after
    // CPMMIS_ABSOLUTE_SESSION_MAX. This prevents heartbeat-kept sessions from
    // living indefinitely.
    if (($now - $login_time) > CPMMIS_ABSOLUTE_SESSION_MAX) {
        $expired = true;
    }

    if ($expired || ($last > 0 && ($now - $last) > $idle_limit)) {
        $_SESSION = [];
        @session_destroy();
        @setcookie(session_name(), '', time() - 42000, '/');
        // The session is gone; do not keep executing the request with a dead
        // identity. Redirect to the landing page if this was a page request.
        if (!headers_sent()) {
            header("Location: index.php");
        }
        exit();
    } else {
        $_SESSION['last_activity'] = $now;
    }
}

if (!headers_sent()) {
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-src 'none'; form-action 'self'; frame-ancestors 'none';");
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    header("X-XSS-Protection: 0");
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
    if ($is_https) {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    }
}
