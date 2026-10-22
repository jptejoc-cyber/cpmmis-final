<?php
/**
 * Defensive bootstrap for CPMMIS.
 *
 * Included FIRST by every entry point so that no PHP warning, notice, or
 * uncaught exception can ever leak raw output - especially into a JSON API
 * response (which previously surfaced to clients as "Backend Connection Failed").
 */

// Suppress ALL PHP error output globally, from the very first included file.
error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Keep real problems in the PHP error log for diagnosis.
ini_set('log_errors', 1);

/**
 * Determine whether the current request expects a JSON response.
 *
 * Mirrors the convention used by the auth JSON endpoints (they set
 * Content-Type: application/json). Page endpoints render HTML and are
 * allowed to fall back to a plain-string message.
 */
function cpmmis_expects_json() {
    static $json = null;
    if ($json !== null) {
        return $json;
    }
    $headers = [];
    if (function_exists('headers_list')) {
        $headers = headers_list();
    }
    foreach ($headers as $h) {
        if (stripos($h, 'Content-Type:') !== false && stripos($h, 'application/json') !== false) {
            $json = true;
            return $json;
        }
    }
    $json = false;
    return $json;
}

function cpmmis_fatal_response($message) {
    // Do not emit a second handler if output was already produced/sent.
    if (headers_sent()) {
        return;
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

// Catch any uncaught Throwable so a fatal can never corrupt a JSON body.
set_exception_handler(function ($e) {
    error_log('CPMMIS uncaught: ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (cpmmis_expects_json()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Service temporarily unavailable. Please try again later.']);
        exit;
    }
    // For page endpoints there is no reliable way to render a full page here,
    // so emit a short generic message instead of raw stack traces.
    http_response_code(500);
    echo 'An unexpected error occurred. Please try again later.';
    exit;
});

// Turn warnings/notices into a recoverable error path for JSON endpoints only,
// so a lone notice cannot corrupt a response. Non-JSON pages ignore warnings
// (display_errors is already off) and continue normally.
set_error_handler(function ($severity, $message, $file, $line) {
    error_log("CPMMIS warning [$severity]: $message @ $file:$line");
    if (cpmmis_expects_json()) {
        // Suppress the error, but if headers were already emitted there is
        // nothing we can safely append - just log it and bail.
        if ($file === __FILE__) {
            return true;
        }
        // Never output raw error text into a JSON endpoint.
        return true;
    }
    return true;
});
