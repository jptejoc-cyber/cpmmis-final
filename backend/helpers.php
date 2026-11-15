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

if (!function_exists('calculate_group_health')) {
    /**
     * Derive a group's health badge from its adviser's chapter tasks.
     *
     * Rules (see docs/cpmmis-dfd-level1.md §3.7):
     *  - completed:        all five chapters (1-5) are approved.
     *  - at_risk:          an applicable task has no submission and is overdue.
     *  - needs_attention:  an applicable task has no submission and is due
     *                      within the next 3 days.
     *  - on_track:         otherwise.
     *
     * Only tasks posted after the group was created are considered. Without
     * that guard a newly registered group inherits every historical task of
     * its adviser and is instantly "At Risk", so the 3-day notification can
     * never line up with a yellow "Needs Attention" badge.
     *
     * @return array{status:string,label:string,color:string,icon:string}
     */
    function calculate_group_health($conn, $group_id) {
        $on_track = [
            'status' => 'on_track',
            'label'  => 'On Track',
            'color'  => 'blue',
            'icon'   => 'fa-check-circle',
        ];

        $group_id = (int)$group_id;
        if (!$conn || $group_id <= 0) {
            return $on_track;
        }

        // All five chapters approved = completed, ahead of any task state.
        $stmt = $conn->prepare("SELECT COUNT(DISTINCT chapter_number) AS c
                                FROM group_submissions
                                WHERE group_id = ?
                                  AND status = 'approved'
                                  AND chapter_number BETWEEN 1 AND 5");
        if (!$stmt) {
            return $on_track;
        }
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        $fin = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($fin && (int)$fin['c'] >= 5) {
            return [
                'status' => 'completed',
                'label'  => 'Completed',
                'color'  => 'blue',
                'icon'   => 'fa-check-double',
            ];
        }

        // One query fetches the applicable tasks and whether the group has
        // already submitted each one.
        $stmt = $conn->prepare("SELECT t.id, t.deadline, s.id AS submission_id
                                FROM adviser_tasks t
                                JOIN `group` g ON g.adviser_id = t.adviser_id
                                LEFT JOIN group_submissions s
                                       ON s.task_id = t.id AND s.group_id = g.id
                                WHERE g.id = ?
                                  AND t.task_type = 'chapter'
                                  AND t.deadline IS NOT NULL
                                  AND t.deadline > '1970-01-01 00:00:00'
                                  AND t.created_at >= g.created_at
                                ORDER BY t.deadline ASC");
        if (!$stmt) {
            return $on_track;
        }
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        $tasks = $stmt->get_result();

        $health = $on_track;
        $now = time();
        while ($task = $tasks->fetch_assoc()) {
            if ($task['submission_id'] !== null) {
                continue;
            }

            $diff_days = (strtotime($task['deadline']) - $now) / 86400;
            if ($diff_days < 0) {
                // Worst status wins; no need to inspect the remaining tasks.
                $health = [
                    'status' => 'at_risk',
                    'label'  => 'At Risk',
                    'color'  => 'rose',
                    'icon'   => 'fa-exclamation-triangle',
                ];
                break;
            }

            if ($diff_days <= 3) {
                $health = [
                    'status' => 'needs_attention',
                    'label'  => 'Needs Attention',
                    'color'  => 'amber',
                    'icon'   => 'fa-exclamation-circle',
                ];
            }
        }
        $stmt->close();

        return $health;
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
