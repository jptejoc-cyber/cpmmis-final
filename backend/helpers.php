<?php
/**
 * Shared helper functions for CPMMIS.
 * Included once via db_config.php — all page files get these automatically.
 */

if (!function_exists('getRelativeTime')) {
    function getRelativeTime($timestamp) {
        if (!$timestamp) return "";
        $time = is_numeric($timestamp) ? $timestamp : strtotime($timestamp);
        $current_time = time();
        $diff = $current_time - $time;
        if ($diff < 1) return 'now';
        $intervals = [31536000 => ' year', 2592000 => ' month', 604800 => ' week', 86400 => ' day', 3600 => ' hour', 60 => ' minute'];
        foreach ($intervals as $secs => $str) {
            $d = $diff / $secs;
            if ($d >= 1) { $r = round($d); return $r . $str . ($r > 1 ? 's' : ''); }
        }
        return $diff . ' second' . ($diff > 1 ? 's' : '');
    }
}

if (!function_exists('getTargetPage')) {
    /**
     * Resolve a notification message to the page (relative to the current
     * role directory) the user should be taken to when clicking it.
     * $reference_id is included for context but is NOT embedded in URLs here
     * because each notification source uses different id semantics (submission
     * id, meeting id, group id, archive id) and the safest target is always
     * the appropriate list page.
     */
    function getTargetPage($msg, $reference_id = 0) {
        if (!is_string($msg)) {
            $msg = '';
        }
        $low = strtolower($msg);
        if (strpos($low, 'submission') !== false || strpos($low, 'submitted') !== false || strpos($low, 'chapter') !== false || strpos($low, 'task') !== false || strpos($low, 'revision') !== false) {
            return 'groups.php';
        }
        if (strpos($low, 'meeting') !== false || strpos($low, 'consultation') !== false) {
            return 'meetings.php';
        }
        if (strpos($low, 'advisership') !== false || strpos($low, 'group request') !== false || strpos($low, 'registered') !== false) {
            return 'group_requests.php';
        }
        if (strpos($low, 'assigned') !== false || strpos($low, 'adviser') !== false) {
            return 'groups.php';
        }
        if (strpos($low, 'archive') !== false || strpos($low, 'archived') !== false) {
            return 'archive_view.php';
        }
        return 'adviser_dashboard.php';
    }
}
