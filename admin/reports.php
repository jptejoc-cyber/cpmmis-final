<?php
include('../backend/session_init.php');
include('../backend/db_config.php');
include('../backend/auth/csrf.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
$admin_id = (int)$_SESSION['user_id'];
$present_ay = "2026-2027";

// Fetch Admin Record
$stmt = $conn->prepare("SELECT * FROM admin WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin_data = $stmt->get_result()->fetch_assoc();

// Activity Logs
$stmt = $conn->prepare("SELECT * FROM login_logs WHERE user_id = ? AND role = 'admin' ORDER BY login_time DESC LIMIT 5");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$logins_q = $stmt->get_result();
$selected_year = isset($_GET['year']) ? strip_tags($_GET['year']) : $present_ay;

// --- INLINE NOTIFICATION UPDATER STATE TRIGGER ---
if (isset($_GET['action']) && $_GET['action'] === 'clear_reg_notifications') {
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Security token validation failed']);
        exit();
    }
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE message LIKE ? AND is_read = 0");
    $pattern = '%registered%';
    $stmt->bind_param("s", $pattern);
    $stmt->execute();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
    exit();
}

// --- DYNAMIC LIVE STATUS NOTIFICATIONS (MATCHES DASHBOARD) ---
$notif_query = "SELECT n.*, g.status as live_group_status 
                FROM notifications n
                LEFT JOIN `group` g ON SUBSTRING_INDEX(n.message, ': ', -1) = g.username
                WHERE n.message LIKE '%registered%'
                AND (
                    n.message NOT LIKE '%:%' 
                    OR SUBSTRING_INDEX(n.message, ': ', -1) IN (SELECT username FROM `group`)
                )
                ORDER BY n.id DESC LIMIT 10";
$notif_res = mysqli_query($conn, $notif_query);

$unread_count_q = mysqli_query($conn, "SELECT COUNT(*) as total FROM notifications n
                                      WHERE n.message LIKE '%registered%'
                                      AND n.is_read = 0
                                      AND (
                                          n.message NOT LIKE '%:%' 
                                          OR SUBSTRING_INDEX(n.message, ': ', -1) IN (SELECT username FROM `group`)
                                      )");
$unread_count = mysqli_fetch_assoc($unread_count_q)['total'] ?? 0;

// Academic years for the filter dropdown
$years_dropdown_query = mysqli_query($conn, "SELECT DISTINCT year FROM capstone_archive ORDER BY year DESC");

$months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
$start_year = (int)explode('-', $selected_year)[0];

// --- CORE METRIC: TOTAL MANUSCRIPTS (AY) ---
$year_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM capstone_archive WHERE year = ?");
mysqli_stmt_bind_param($year_stmt, "s", $selected_year);
mysqli_stmt_execute($year_stmt);
$total_capstones = mysqli_fetch_assoc(mysqli_stmt_get_result($year_stmt))['total'];

// --- SUBMISSION VELOCITY (MONTHLY TREND) ---
$trend_data = array_fill(0, 12, 0);
$trend_stmt = mysqli_prepare($conn, "SELECT MONTH(submitted_at) as m, COUNT(*) as count FROM group_submissions WHERE YEAR(submitted_at) = ? GROUP BY m");
mysqli_stmt_bind_param($trend_stmt, "i", $start_year);
mysqli_stmt_execute($trend_stmt);
$trend_q = mysqli_stmt_get_result($trend_stmt);
while($row = mysqli_fetch_assoc($trend_q)) {
    $trend_data[$row['m'] - 1] = (int)$row['count'];
}

// --- SUBMISSION STATUS BREAKDOWN (AY) ---
$status_map = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
$total_submissions = 0;
$status_stmt = mysqli_prepare($conn, "SELECT status, COUNT(*) as count FROM group_submissions WHERE YEAR(submitted_at) = ? GROUP BY status");
mysqli_stmt_bind_param($status_stmt, "i", $start_year);
mysqli_stmt_execute($status_stmt);
$status_q = mysqli_stmt_get_result($status_stmt);
while($row = mysqli_fetch_assoc($status_q)) {
    if (isset($status_map[$row['status']])) { $status_map[$row['status']] = (int)$row['count']; }
    $total_submissions += (int)$row['count'];
}

// --- ADVISERS REFERENCE ---
$adviser_names = [];
$adv_q = mysqli_query($conn, "SELECT id, full_name FROM adviser ORDER BY full_name ASC");
while ($row = mysqli_fetch_assoc($adv_q)) {
    $adviser_names[(int)$row['id']] = $row['full_name'];
}

// --- GROUPS & PROGRESS DATA ---
$group_rows = [];
$grp_q = mysqli_query($conn, "SELECT g.id, g.adviser_id, g.username, g.full_name, g.project_title, g.last_activity, g.status, g.created_at,
        a.full_name AS adviser_name
    FROM `group` g
    LEFT JOIN adviser a ON g.adviser_id = a.id
    ORDER BY g.status DESC, g.username ASC");
while ($row = mysqli_fetch_assoc($grp_q)) { $group_rows[] = $row; }

// Deadlines / captions map: adviser -> chapter -> value
$task_deadlines = [];
$task_q = mysqli_query($conn, "SELECT adviser_id, chapter_number, MIN(deadline) AS dl FROM adviser_tasks WHERE task_type = 'chapter' GROUP BY adviser_id, chapter_number");
while ($row = mysqli_fetch_assoc($task_q)) {
    $task_deadlines[(int)$row['adviser_id']][(int)$row['chapter_number']] = $row['dl'];
}

$task_caps = [];
$cap_q = mysqli_query($conn, "SELECT adviser_id, chapter_number, caption FROM adviser_tasks WHERE task_type = 'chapter'");
while ($row = mysqli_fetch_assoc($cap_q)) {
    $task_caps[(int)$row['adviser_id']][(int)$row['chapter_number']] = $row['caption'];
}

// Submission maps per group
$approved_chapters = [];
$submitted_chapters = [];
$on_time_approved = [];
$sub_totals = ['approved' => 0, 'pending' => 0, 'rejected' => 0];
$sub_q = mysqli_query($conn, "SELECT s.group_id, s.chapter_number, s.status, s.submitted_at, g.adviser_id
    FROM group_submissions s JOIN `group` g ON s.group_id = g.id ORDER BY s.submitted_at ASC");
while ($row = mysqli_fetch_assoc($sub_q)) {
    $gid = (int)$row['group_id'];
    $ch = (int)$row['chapter_number'];
    $sub_totals[$row['status']] = ($sub_totals[$row['status']] ?? 0) + 1;
    if (!isset($submitted_chapters[$gid])) $submitted_chapters[$gid] = [];
    $submitted_chapters[$gid][$ch] = true;
    if ($row['status'] === 'approved') {
        if (!isset($approved_chapters[$gid])) $approved_chapters[$gid] = [];
        $approved_chapters[$gid][$ch] = true;
        $aid = (int)$row['adviser_id'];
        $dl = $task_deadlines[$aid][$ch] ?? null;
        if ($dl && strtotime($row['submitted_at']) <= strtotime($dl)) {
            if (!isset($on_time_approved[$gid])) $on_time_approved[$gid] = [];
            $on_time_approved[$gid][$ch] = true;
        }
    }
}

// Active groups grouped by adviser
$active_groups_by_adviser = [];
foreach ($group_rows as $g) {
    if ($g['status'] === 'active' && !empty($g['adviser_id'])) {
        $active_groups_by_adviser[(int)$g['adviser_id']][] = $g;
    }
}

// Milestone / phase summary
$phase_labels = ['Phase 1: Proposal', 'Phase 2: Development', 'Phase 3: Final Defense'];
$phase_distribution = [0, 0, 0, 0];
$group_summary = [];
$total_approved_chapters = 0;
$total_on_time_approved = 0;
foreach ($group_rows as &$g) {
    $gid = (int)$g['id'];
    $sub_chs = $submitted_chapters[$gid] ?? [];
    $app_chs = $approved_chapters[$gid] ?? [];
    $max_ch = count($sub_chs) > 0 ? max(array_keys($sub_chs)) : 0;
    $phase = $max_ch >= 1 ? ($max_ch <= 3 ? 1 : ($max_ch <= 5 ? 2 : 3)) : 0;
    $phase_distribution[$phase]++;
    $total_approved_chapters += count($app_chs);
    $total_on_time_approved += count($on_time_approved[$gid] ?? []);
    $g['chapters_done'] = count($app_chs);
    $g['chapters_total'] = 6;
    $g['phase'] = $phase;
    $g['phase_label'] = $phase === 0 ? 'Not Started' : $phase_labels[$phase - 1];
    $g['progress_pct'] = $g['chapters_total'] > 0 ? (int)round((count($app_chs) / $g['chapters_total']) * 100) : 0;
    $g['days_inactive'] = empty($g['last_activity']) ? null : (int)floor((time() - strtotime($g['last_activity'])) / 86400);
    $group_summary[$gid] = $g;
}
unset($g);
$phase_chart_labels = array_merge(['Not Started'], $phase_labels);
$on_time_pct = $total_approved_chapters > 0 ? (int)round(($total_on_time_approved / $total_approved_chapters) * 100) : 0;

// --- OVERDUE TASKS ---
$overdue_rows = [];
foreach ($task_deadlines as $aid => $chapters) {
    $groups = $active_groups_by_adviser[$aid] ?? [];
    if (count($groups) === 0) continue;
    foreach ($chapters as $ch => $dl) {
        if (strtotime($dl) > time()) continue;
        $behind = [];
        foreach ($groups as $gr) {
            $gid = (int)$gr['id'];
            if (!isset($on_time_approved[$gid][$ch])) $behind[] = $gr['username'];
        }
        if (count($behind) === 0) continue;
        $overdue_rows[] = [
            'adviser_name' => $adviser_names[$aid] ?? 'Unassigned',
            'chapter_number' => $ch,
            'caption' => $task_caps[$aid][$ch] ?? ('Chapter ' . $ch . ' Submission'),
            'deadline' => $dl,
            'days_overdue' => (int)floor((time() - strtotime($dl)) / 86400),
            'behind_groups' => $behind,
            'assigned_count' => count($groups),
            'done_count' => count($groups) - count($behind)
        ];
    }
}
usort($overdue_rows, function($a, $b) { return $b['days_overdue'] <=> $a['days_overdue']; });

// --- FEEDBACK TURNAROUND ---
$review_stats = [];
$rev_q = mysqli_query($conn, "SELECT g.adviser_id, COUNT(*) AS reviewed,
        ROUND(AVG(TIMESTAMPDIFF(MINUTE, s.submitted_at, s.reviewed_at)) / 60, 1) AS avg_hours
    FROM group_submissions s JOIN `group` g ON s.group_id = g.id
    WHERE s.reviewed_at IS NOT NULL AND s.status IN ('approved','rejected')
    GROUP BY g.adviser_id");
while ($row = mysqli_fetch_assoc($rev_q)) {
    $review_stats[(int)$row['adviser_id']] = $row;
}
$turnaround_sum = 0;
$turnaround_n = 0;
foreach ($review_stats as $rs) {
    if ($rs['avg_hours'] !== null) {
        $turnaround_sum += (float)$rs['avg_hours'] * (int)$rs['reviewed'];
        $turnaround_n += (int)$rs['reviewed'];
    }
}
$avg_turnaround_hrs = $turnaround_n > 0 ? round($turnaround_sum / $turnaround_n, 1) : null;

// --- WORKLOAD CHART (GROUPS HANDLED PER ADVISER) ---
$workload_labels = [];
$workload_data = [];
foreach ($active_groups_by_adviser as $aid => $groups) {
    $workload_labels[] = $adviser_names[$aid] ?? ('Adviser #' . $aid);
    $workload_data[] = count($groups);
}
if (count($workload_labels) === 0) {
    $workload_labels = ['No Active Groups'];
    $workload_data = [0];
}

// --- EXECUTIVE KPIs ---
$kpi_active_groups = 0;
$kpi_pending_groups = 0;
foreach ($group_rows as $g) {
    if ($g['status'] === 'active') $kpi_active_groups++;
    elseif ($g['status'] === 'pending') $kpi_pending_groups++;
}
$meets_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM meeting_requests WHERE status = 'accepted' AND YEAR(meeting_time) = ?");
mysqli_stmt_bind_param($meets_stmt, "i", $start_year);
mysqli_stmt_execute($meets_stmt);
$kpi_meetings_held = mysqli_fetch_assoc(mysqli_stmt_get_result($meets_stmt))['total'];
$kpi_approved_submissions = $sub_totals['approved'] ?? 0;
$kpi_pending_submissions = $sub_totals['pending'] ?? 0;
$kpi_overdue_count = count($overdue_rows);

// --- CSV EXPORT ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="cpmmis_reports_' . $selected_year . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['CPMMIS Analytics Report', 'Academic Year: ' . $selected_year]);
    fputcsv($out, []);
    fputcsv($out, ['Core Metrics']);
    fputcsv($out, ['Total Manuscripts', $total_capstones]);
    fputcsv($out, ['Active Groups', $kpi_active_groups]);
    fputcsv($out, ['Pending Groups', $kpi_pending_groups]);
    fputcsv($out, ['Approved Submissions', $kpi_approved_submissions]);
    fputcsv($out, ['Pending Submissions', $kpi_pending_submissions]);
    fputcsv($out, ['Meetings Conducted', $kpi_meetings_held]);
    fputcsv($out, ['Overdue Tasks', $kpi_overdue_count]);
    fputcsv($out, ['On-Time Completion', $on_time_pct . '%']);
    fputcsv($out, ['Feedback Turnaround', $avg_turnaround_hrs !== null ? $avg_turnaround_hrs . ' hrs' : '']);
    fputcsv($out, []);
    fputcsv($out, ['Monthly Submissions']);
    foreach ($months as $i => $m) { fputcsv($out, [$m, $trend_data[$i]]); }
    fputcsv($out, []);
    fputcsv($out, ['Submission Status']);
    fputcsv($out, ['Approved', $status_map['approved']]);
    fputcsv($out, ['Rejected', $status_map['rejected']]);
    fputcsv($out, ['Pending', $status_map['pending']]);
    fputcsv($out, []);
    fputcsv($out, ['Adviser Workload (Groups Handled)']);
    foreach ($workload_labels as $i => $lbl) { fputcsv($out, [$lbl, $workload_data[$i]]); }
    fputcsv($out, []);
    fputcsv($out, ['Group Progress Report']);
    fputcsv($out, ['Group', 'Project Title', 'Adviser', 'Status', 'Milestone Phase', 'Chapters Approved', 'Total Chapters', 'Progress %', 'Last Activity']);
    foreach ($group_rows as $g) {
        $gi = $group_summary[(int)$g['id']] ?? [];
        fputcsv($out, [
            $g['username'],
            $g['project_title'] ?? '',
            $g['adviser_name'] ?? '',
            $g['status'],
            $gi['phase_label'] ?? 'Not Started',
            $gi['chapters_done'] ?? 0,
            $gi['chapters_total'] ?? 6,
            $gi['progress_pct'] ?? 0,
            $g['last_activity'] ?? ''
        ]);
    }
    fputcsv($out, []);
    fputcsv($out, ['Milestone Distribution']);
    foreach ($phase_chart_labels as $i => $lbl) { fputcsv($out, [$lbl, $phase_distribution[$i]]); }
    fputcsv($out, []);
    fputcsv($out, ['Overdue Tasks']);
    fputcsv($out, ['Adviser', 'Chapter', 'Caption', 'Deadline', 'Days Overdue', 'Assigned Groups', 'Completed', 'Behind Groups']);
    foreach ($overdue_rows as $od) {
        fputcsv($out, [
            $od['adviser_name'],
            $od['chapter_number'],
            $od['caption'],
            $od['deadline'],
            $od['days_overdue'],
            $od['assigned_count'],
            $od['done_count'],
            implode(', ', $od['behind_groups'])
        ]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Analytics Reports | CPMMIS</title>
    <link rel="icon" type="image/png" href="../chmsu.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        :root {
            --chmsu-blue: #1877F2;
            --chmsu-orange: #4267B2;
            --bg-deep: #F0F2F5;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-deep);
            background-image: none;
            background-size: 32px 32px;
            color: #1c1e21;
            overflow-x: hidden;
        }

        .ambient-glow {
            position: fixed; top: -10%; right: -10%; width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(24, 119, 242, 0.06) 0%, transparent 70%);
            z-index: -1; filter: blur(80px); pointer-events: none;
        }

        aside {
            width: 5.5rem; transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: #ffffff;
            border-right: 1px solid #dadde1; height: 100vh; position: fixed; z-index: 150;
            display: flex; flex-direction: column; padding: 1.25rem 0; overflow: hidden;
        }
        aside:hover { width: 17rem; background: #ffffff; border-right-color: #e4e6eb; }

        .logo-section { display: flex; flex-direction: row; align-items: center; padding: 1.5rem 0; margin-bottom: 2.5rem; transition: all 0.3s ease; width: 100%; justify-content: flex-start; padding-left: 1.35rem; }
        .brand-text-container { max-width: 0; opacity: 0; overflow: hidden; white-space: nowrap; transition: all 0.3s ease; width: 100%; }
        aside:hover .brand-text-container { max-width: 200px; opacity: 1; margin-left: 1rem; }
        .brand-text { font-size: 1.25rem; font-weight: 800; color: #1877F2; letter-spacing: -0.025em; width: 100%; }

        .sidebar-link { display: flex; align-items: center; gap: 1.25rem; padding: 1rem 1.45rem; border-radius: 1rem; transition: background-color 0.3s ease, box-shadow 0.3s ease; color: #65676b; width: 100%; justify-content: flex-start; }
        .sidebar-text { opacity: 0; display: none; font-weight: 800; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; }
        aside:hover .sidebar-text { opacity: 1; display: inline; }
        .sidebar-link:hover { background-color: #f0f2f5; color: #1c1e21; }
        .sidebar-link.active { background: var(--chmsu-blue) !important; color: white !important; box-shadow: 0 10px 15px -3px rgba(24, 119, 242, 0.25); }

        .premium-card { background: #ffffff; border: 1px solid #dadde1; }
        .stat-card { transition: all 0.3s ease; border: 1px solid #dadde1; background: #ffffff; box-shadow: 0 1px 2px rgba(0,0,0,0.06); }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(0,0,0,0.12); border-color: rgba(24, 119, 242, 0.4); }

        #settings-drawer { transform: translateX(100%); transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); background: #ffffff; backdrop-filter: blur(40px); border-left: 1px solid #dadde1; }
        #settings-drawer.open { transform: translateX(0); }
        #security-submenu { max-height: 0; overflow: hidden; transition: max-height 0.4s ease; }
        #security-submenu.open { max-height: 400px; }

        .icon-tooltip { position: relative; }
        .icon-tooltip:hover::after { content: attr(data-tip); position: absolute; top: 110%; left: 50%; transform: translateX(-50%); background: #1e293b; color: white; padding: 6px 12px; border-radius: 8px; font-size: 9px; font-weight: 800; text-transform: uppercase; tracking-spacing: 0.1em; white-space: nowrap; z-index: 1000; display: block; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0, 0, 0, 0.15); border-radius: 10px; }
        @keyframes scale-in { from { opacity: 0; transform: scale(0.95) translateY(-10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .animate-scale-in { animation: scale-in 0.2s ease-out; }
        .sidebar-toggle-btn {
            display: none;
            position: fixed; top: 0.75rem; left: 0.75rem; z-index: 160;
            width: 2.5rem; height: 2.5rem; border-radius: 0.75rem;
            background: #ffffff; border: 1px solid #dadde1;
            color: #94a3b8; align-items: center; justify-content: center; cursor: pointer;
            transition: all 0.2s ease;
        }
        .sidebar-toggle-btn:hover { background: #f0f2f5; color: #1877F2; }
        @media (max-width: 767px) {
            .sidebar-toggle-btn { display: flex; }
            aside.sidebar {
                transform: translateX(-100%); width: 17rem !important;
                background: #ffffff !important; z-index: 155 !important;
                transition: transform 0.3s cubic-bezier(0.4,0,0.2,1) !important;
                border-right-color: #e4e6eb !important;
            }
            aside.sidebar:hover { width: 17rem !important; }
            aside.sidebar.open { transform: translateX(0); }
            aside.sidebar .brand-text-container { max-width: 200px !important; opacity: 1 !important; margin-left: 1rem !important; }
            aside.sidebar .sidebar-text { opacity: 1 !important; display: inline !important; }
            .sidebar-backdrop {
                display: none; position: fixed; inset: 0;
                background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 145;
            }
            .sidebar-backdrop.open { display: block; }
        }

        @media print {
            aside, .sidebar-toggle-btn, .sidebar-backdrop, .no-print, #toast-container, .ambient-glow,
            .nav-icon-container, .chat-widget, #chat-widget-container { display: none !important; }
            main { margin-left: 0 !important; padding: 0 !important; }
            body { background: #fff !important; }
            .premium-card, .stat-card { box-shadow: none !important; border: 1px solid #dadde1 !important; break-inside: avoid; }
        }
    </style>
</head>
<body class="min-h-screen flex">

    <div class="ambient-glow"></div>
    <button class="sidebar-toggle-btn" id="sidebar-toggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">
        <i class="fas fa-bars text-sm"></i>
    </button>
    <div class="sidebar-backdrop" id="sidebar-backdrop" onclick="toggleSidebar()"></div>

    <div id="toast-container" class="fixed top-5 left-1/2 -translate-x-1/2 z-[999] transform -translate-y-20 transition-all duration-500 opacity-0 pointer-events-none">
        <div class="bg-white text-slate-800 px-8 py-4 rounded-2xl shadow-xl flex items-center gap-4 border border-blue-500/30">
            <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center"><i id="toast-icon" class="fas fa-check text-xs text-white"></i></div>
            <p id="toast-message" class="text-slate-900 font-black uppercase tracking-widest text-[10px]">Action Successful</p>
        </div>
    </div>

    <aside class="group sidebar" id="sidebar">
        <div class="logo-section transition-all duration-300">
            <div class="bg-white p-2 rounded-xl flex-none flex items-center justify-center w-12 h-12">
                <img src="../chmsu.png" class="w-10 h-10 object-contain shrink-0" alt="CHMSU Logo">
            </div>
            <div class="brand-text-container">
                <span class="brand-text">CPMMIS</span>
            </div>
        </div>

        <nav class="flex-1 space-y-2 px-3">
            <a href="admin_dashboard.php" class="sidebar-link <?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-th-large w-5 text-center text-sm"></i>
                <span class="sidebar-text">Dashboard</span>
            </a>
            <a href="archive.php" class="sidebar-link <?php echo ($current_page == 'archive.php') ? 'active' : ''; ?>">
                <i class="fas fa-archive w-5 text-center text-sm"></i>
                <span class="sidebar-text">Capstone Archive</span>
            </a>
            <a href="advisers.php" class="sidebar-link <?php echo ($current_page == 'advisers.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-tie w-5 text-center text-sm"></i>
                <span class="sidebar-text">Adviser</span>
            </a>
            <a href="groups.php" class="sidebar-link <?php echo ($current_page == 'groups.php') ? 'active' : ''; ?>">
                <i class="fas fa-users w-5 text-center text-sm"></i>
                <span class="sidebar-text">Groups</span>
            </a>
            <a href="submissions.php" class="sidebar-link <?php echo ($current_page == 'submissions.php') ? 'active' : ''; ?>">
                <i class="fas fa-tasks w-5 text-center text-sm"></i>
                <span class="sidebar-text">Submissions & Letters</span>
            </a>

            <div class="pt-4 mt-4 border-t border-gray-200 space-y-2">
                <a href="reports.php" class="sidebar-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?> active">
                    <i class="fas fa-chart-pie w-5 text-center text-sm"></i>
                    <span class="sidebar-text">Report Analytics</span>
                </a>
                <a href="maintenance.php" class="sidebar-link <?php echo ($current_page == 'maintenance.php') ? 'active' : ''; ?>">
                    <i class="fas fa-tools w-5 text-center text-sm"></i>
                    <span class="sidebar-text">System Maintenance</span>
                </a>
            </div>
        </nav>
    </aside>

    <main class="flex-1 ml-0 md:ml-24 p-6 sm:p-8 lg:p-12 space-y-6 sm:space-y-10">

        <nav class="premium-card rounded-[2.5rem] px-4 sm:px-8 py-4 flex flex-wrap justify-between items-center shadow-2xl relative z-[100] gap-2">
            <div class="min-w-0 flex-1">
                <h1 class="text-lg sm:text-xl font-black text-slate-900 uppercase tracking-tight truncate">Institutional Intelligence</h1>
                <p class="text-[10px] font-bold text-blue-400 uppercase tracking-[0.2em] mt-1 truncate">Report for Academic Year: <?php echo htmlspecialchars($selected_year, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <form method="GET" action="" id="year_filter_form" class="mr-2">
                    <select name="year" onchange="this.form.submit()" class="bg-white border border-gray-200 px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest text-slate-600 outline-none focus:border-blue-500/50 cursor-pointer shadow-inner">
                        <option value="<?php echo $present_ay; ?>" <?php echo ($selected_year == $present_ay) ? 'selected' : ''; ?>>AY <?php echo $present_ay; ?></option>
                        <?php mysqli_data_seek($years_dropdown_query, 0); while($yr = mysqli_fetch_assoc($years_dropdown_query)): ?>
                            <option value="<?php echo htmlspecialchars($yr['year'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($selected_year == $yr['year']) ? 'selected' : ''; ?>>AY <?php echo htmlspecialchars($yr['year'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endwhile; ?>
                    </select>
                </form>

                <form method="GET" action="" class="mr-2">
                    <input type="hidden" name="year" value="<?php echo htmlspecialchars($selected_year, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="export" value="csv">
                    <button type="submit" class="px-5 py-3 bg-blue-600 hover:bg-blue-500 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg flex items-center gap-2"><i class="fas fa-download"></i> Export</button>
                </form>
                <button type="button" onclick="window.print()" class="px-5 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all shadow-sm flex items-center gap-2 no-print"><i class="fas fa-print"></i> Print / PDF</button>

                <div class="relative nav-icon-container">
                    <button onclick="toggleAdminChatWidget()" data-tip="Messages" class="icon-tooltip w-12 h-12 rounded-2xl bg-slate-100 border border-gray-200 flex items-center justify-center hover:bg-slate-100 transition relative shadow-sm">
                        <i class="fas fa-comment-dots text-blue-400"></i>
                    </button>
                </div>
                <div class="relative nav-icon-container">
                    <button onclick="handleNotifClick()" data-tip="Notifications" class="icon-tooltip w-12 h-12 rounded-2xl bg-slate-100 border border-gray-200 flex items-center justify-center hover:bg-slate-100 transition relative shadow-sm">
                        <i class="fas fa-bell text-blue-400"></i>
                        <?php if($unread_count > 0): ?><span id="notif-badge" class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[9px] font-black rounded-full border-4 border-white flex items-center justify-center"><?php echo $unread_count; ?></span><?php endif; ?>
                    </button>
                    <div id="notif-popover" class="hidden absolute right-0 mt-3 w-80 bg-white backdrop-blur-2xl rounded-[2.2rem] shadow-2xl border border-gray-200 z-[250] overflow-hidden animate-scale-in">
                        <div class="p-6 bg-slate-100 border-b border-gray-200 flex justify-between items-center"><h4 class="text-[10px] font-black uppercase text-slate-900 tracking-widest leading-none">Notifications</h4></div>
                        <div class="max-h-80 overflow-y-auto p-4 space-y-3 custom-scrollbar">
                            <?php if($notif_res && mysqli_num_rows($notif_res) > 0):
                                $all_notifs = [];
                                while($row = mysqli_fetch_assoc($notif_res)) { $all_notifs[] = $row; }
                                $new_notifs = array_filter($all_notifs, function($n) { return $n['is_read'] == 0; });
                                $recent_notifs = array_filter($all_notifs, function($n) { return $n['is_read'] == 1; });
                                $notif_groups = [
                                    ['label' => 'New', 'items' => $new_notifs, 'color' => 'blue'],
                                    ['label' => 'Recent', 'items' => $recent_notifs, 'color' => 'slate']
                                ];
                                foreach($notif_groups as $group_data):
                                    if(count($group_data['items']) > 0):
                            ?>
                            <div class="px-2 py-1 mt-1 mb-2"><p class="text-[9px] font-black uppercase text-<?php echo $group_data['color']; ?>-400 tracking-widest"><?php echo $group_data['label']; ?></p></div>
                            <?php foreach($group_data['items'] as $n):
                                $msg = $n['message'];
                                if ($n['is_read'] == 1) {
                                    $msg = preg_replace('/^New /i', '', $msg);
                                }
                                $target_url = "groups.php?status=pending";
                                $time_display = getRelativeTime($n['created_at']);
                                $action_label = "Registration Alert";
                                $icon_markup = '<i class="fas fa-user-plus"></i>';
                                $icon_theme = 'bg-blue-500/10 text-blue-400';
                                $live_status = strtolower($n['live_group_status'] ?? 'pending');
                                if ($live_status === 'active' || $live_status === 'approved' || $live_status === 'accepted') {
                                    $status_badge = '<span class="text-[7px] font-black text-blue-400 uppercase tracking-tight bg-blue-500/10 px-2 py-0.5 rounded-md">Approved</span>';
                                } elseif ($live_status === 'declined' || $live_status === 'rejected') {
                                    $status_badge = '<span class="text-[7px] font-black text-rose-400 uppercase tracking-tight bg-rose-500/10 px-2 py-0.5 rounded-md">Declined</span>';
                                } else {
                                    $status_badge = '<span class="text-[7px] font-black text-amber-400 uppercase tracking-tight bg-amber-500/10 px-2 py-0.5 rounded-md">Pending</span>';
                                }
                            ?>
                            <a href="<?php echo $target_url; ?>" class="p-3 rounded-2xl bg-slate-100 border border-gray-200 flex items-center gap-4 hover:bg-slate-100 block transition-all group/item">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-[10px] shrink-0 <?php echo $icon_theme; ?> transition-all"><?php echo $icon_markup; ?></div>
                                <div class="flex-1 text-left min-w-0">
                                    <div class="flex justify-between items-center w-full gap-2 mb-0.5">
                                        <p class="text-[10px] font-black text-slate-900 uppercase tracking-tight group-hover/item:text-blue-400 transition-colors truncate"><?php echo $action_label; ?></p>
                                        <div class="shrink-0"><?php echo $status_badge; ?></div>
                                    </div>
                                    <p class="text-[9px] font-bold text-slate-500 mt-0.5 uppercase line-clamp-2"><?php echo preg_replace('/^(NEW )/i', '<span class="text-blue-400">$1</span>', htmlspecialchars($msg)); ?></p>
                                    <p class="text-[7px] text-slate-500 uppercase font-black mt-1.5 tracking-wider"><?php echo $time_display; ?></p>
                                </div>
                            </a>
                            <?php endforeach; endif; endforeach; else: ?><p class="text-center py-10 text-slate-500 text-[10px] font-bold uppercase italic">No new activity</p><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="relative nav-icon-container">
                    <button onclick="toggleSettingsDropdown()" data-tip="Settings" class="icon-tooltip w-12 h-12 rounded-2xl bg-slate-100 border border-gray-200 flex items-center justify-center hover:bg-slate-100 transition relative shadow-sm"><i class="fas fa-cog text-blue-400"></i></button>
                    <div id="settings-popover" class="hidden absolute right-0 mt-3 w-64 bg-white backdrop-blur-2xl rounded-[2rem] shadow-2xl border border-gray-200 z-[250] overflow-hidden animate-scale-in">
                        <div class="p-2">
                            <button onclick="openSettingsDrawer()" class="w-full flex items-center gap-4 p-5 hover:bg-slate-100 transition rounded-2xl group text-left"><div class="w-8 h-8 rounded-lg bg-slate-100 text-slate-500 flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition-all"><i class="fas fa-sliders-h text-xs"></i></div><p class="text-[11px] font-black uppercase text-slate-700 tracking-tight">Settings and Privacy</p></button>
                            <div class="h-px bg-slate-100 my-1 mx-4"></div>
                            <a href="../backend/auth/logout.php?csrf_token=<?php echo generate_csrf_token(); ?>" class="w-full flex items-center gap-4 p-5 hover:bg-rose-50 transition rounded-2xl group text-left text-rose-400 font-black uppercase text-[11px]"><div class="w-8 h-8 rounded-lg bg-rose-500/10 text-rose-400 flex items-center justify-center group-hover:bg-rose-600 group-hover:text-white transition-all"><i class="fas fa-sign-out-alt text-xs"></i></div>Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <div class="space-y-10 sm:space-y-12">

            <!-- SECTION 1: EXECUTIVE SUMMARY -->
            <section>
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-11 h-11 shrink-0 rounded-2xl bg-blue-500/10 text-blue-400 flex items-center justify-center text-base"><i class="fas fa-gauge-high"></i></div>
                    <div>
                        <h3 class="text-sm font-black uppercase text-slate-900 tracking-tight">Executive Summary</h3>
                        <p class="text-[9px] font-black uppercase tracking-widest text-blue-400 mt-0.5">Institutional KPIs — live operational snapshot</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                    <div class="stat-card p-4 sm:p-8 rounded-[2.5rem] flex items-center justify-between shadow-xl border-blue-500/10">
                        <div class="min-w-0 flex-1"><p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1 truncate">Total Manuscripts</p><h2 class="text-3xl font-black text-slate-900"><?php echo (int)$total_capstones; ?></h2></div>
                        <div class="w-14 h-14 shrink-0 bg-blue-500/10 text-blue-400 rounded-3xl flex items-center justify-center text-xl"><i class="fas fa-archive"></i></div>
                    </div>
                    <div class="stat-card p-4 sm:p-8 rounded-[2.5rem] flex items-center justify-between shadow-xl border-emerald-500/10">
                        <div class="min-w-0 flex-1"><p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1 truncate">Active Groups</p><h2 class="text-3xl font-black text-slate-900"><?php echo $kpi_active_groups; ?></h2></div>
                        <div class="w-14 h-14 shrink-0 bg-emerald-500/10 text-emerald-500 rounded-3xl flex items-center justify-center text-xl"><i class="fas fa-users"></i></div>
                    </div>
                    <div class="stat-card p-4 sm:p-8 rounded-[2.5rem] flex items-center justify-between shadow-xl border-violet-500/10">
                        <div class="min-w-0 flex-1"><p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1 truncate">Approved Submissions</p><h2 class="text-3xl font-black text-slate-900"><?php echo $kpi_approved_submissions; ?></h2></div>
                        <div class="w-14 h-14 shrink-0 bg-violet-500/10 text-violet-500 rounded-3xl flex items-center justify-center text-xl"><i class="fas fa-check-circle"></i></div>
                    </div>
                    <div class="stat-card p-4 sm:p-8 rounded-[2.5rem] flex items-center justify-between shadow-xl border-rose-500/10">
                        <div class="min-w-0 flex-1"><p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1 truncate">Overdue Tasks</p><h2 class="text-3xl font-black text-slate-900"><?php echo $kpi_overdue_count; ?></h2></div>
                        <div class="w-14 h-14 shrink-0 bg-rose-500/10 text-rose-500 rounded-3xl flex items-center justify-center text-xl"><i class="fas fa-exclamation-triangle"></i></div>
                    </div>
                </div>

                <div class="premium-card rounded-[2.5rem] p-6 sm:p-8 shadow-xl mt-6">
                    <div class="flex flex-wrap items-center gap-5 sm:gap-8">
                        <div class="flex items-center gap-3 min-w-[180px] flex-1">
                            <div class="w-12 h-12 shrink-0 rounded-2xl bg-amber-500/10 text-amber-500 flex items-center justify-center text-lg"><i class="fas fa-user-clock"></i></div>
                            <div class="min-w-0"><p class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Pending Groups</p><h4 class="text-base font-black text-slate-900 mt-0.5"><?php echo $kpi_pending_groups; ?></h4></div>
                        </div>
                        <div class="h-10 w-px bg-slate-100 hidden md:block"></div>
                        <div class="flex items-center gap-3 min-w-[170px] flex-1">
                            <div class="w-12 h-12 shrink-0 rounded-2xl bg-blue-500/10 text-blue-400 flex items-center justify-center text-lg"><i class="fas fa-hourglass-half"></i></div>
                            <div class="min-w-0"><p class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Pending Submissions</p><h4 class="text-base font-black text-slate-900 mt-0.5"><?php echo $kpi_pending_submissions; ?></h4></div>
                        </div>
                        <div class="h-10 w-px bg-slate-100 hidden md:block"></div>
                        <div class="flex items-center gap-3 min-w-[160px] flex-1">
                            <div class="w-12 h-12 shrink-0 rounded-2xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-lg"><i class="fas fa-stopwatch"></i></div>
                            <div class="min-w-0"><p class="text-[9px] font-black text-slate-500 uppercase tracking-widest">On-Time Completion</p><h4 class="text-base font-black text-slate-900 mt-0.5"><?php echo $on_time_pct; ?>%</h4></div>
                        </div>
                        <div class="h-10 w-px bg-slate-100 hidden md:block"></div>
                        <div class="flex items-center gap-3 min-w-[180px] flex-1">
                            <div class="w-12 h-12 shrink-0 rounded-2xl bg-violet-500/10 text-violet-500 flex items-center justify-center text-lg"><i class="fas fa-tachometer-alt"></i></div>
                            <div class="min-w-0"><p class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Feedback Turnaround</p><h4 class="text-base font-black text-slate-900 mt-0.5"><?php echo $avg_turnaround_hrs !== null ? $avg_turnaround_hrs . ' hrs' : '—'; ?> <span class="text-blue-400">avg</span></h4></div>
                        </div>
                        <div class="h-10 w-px bg-slate-100 hidden md:block"></div>
                        <div class="flex items-center gap-3 min-w-[180px] flex-1">
                            <div class="w-12 h-12 shrink-0 rounded-2xl bg-amber-500/10 text-amber-500 flex items-center justify-center text-lg"><i class="fas fa-calendar-check"></i></div>
                            <div class="min-w-0"><p class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Meetings Conducted</p><h4 class="text-base font-black text-slate-900 mt-0.5"><?php echo $kpi_meetings_held; ?></h4></div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- SECTION 2: ANALYTICS CHARTS -->
            <section>
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-11 h-11 shrink-0 rounded-2xl bg-blue-500/10 text-blue-400 flex items-center justify-center text-base"><i class="fas fa-chart-bar"></i></div>
                    <div>
                        <h3 class="text-sm font-black uppercase text-slate-900 tracking-tight">Analytics Trends</h3>
                        <p class="text-[9px] font-black uppercase tracking-widest text-blue-400 mt-0.5">Velocity · milestones · status · workload — AY <?php echo htmlspecialchars($selected_year, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="premium-card p-8 rounded-[2.5rem] shadow-xl">
                        <h4 class="text-[10px] font-black uppercase text-slate-500 tracking-widest mb-6">Submission Velocity (Monthly Trend)</h4>
                        <div class="h-[300px]"><canvas id="submissionTrendChart"></canvas></div>
                    </div>
                    <div class="premium-card p-8 rounded-[2.5rem] shadow-xl">
                        <h4 class="text-[10px] font-black uppercase text-slate-500 tracking-widest mb-6">Milestone Progress</h4>
                        <div class="h-[300px]"><canvas id="milestoneChart"></canvas></div>
                    </div>
                    <div class="premium-card p-8 rounded-[2.5rem] shadow-xl">
                        <h4 class="text-[10px] font-black uppercase text-slate-500 tracking-widest mb-6">Submission Status Distribution</h4>
                        <div class="h-[300px]"><canvas id="submissionStatusChart"></canvas></div>
                    </div>
                    <div class="premium-card p-8 rounded-[2.5rem] shadow-xl">
                        <h4 class="text-[10px] font-black uppercase text-slate-500 tracking-widest mb-6">Adviser Workload (Groups Handled)</h4>
                        <div class="h-[300px]"><canvas id="workloadChart"></canvas></div>
                    </div>
                </div>
            </section>

            <!-- SECTION 3: GROUP PROGRESS -->
            <section>
                <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 shrink-0 rounded-2xl bg-blue-500/10 text-blue-400 flex items-center justify-center text-base"><i class="fas fa-flag"></i></div>
                        <div>
                            <h3 class="text-sm font-black uppercase text-slate-900 tracking-tight">Group & Progress Reports</h3>
                            <p class="text-[9px] font-black uppercase tracking-widest text-blue-400 mt-0.5">Milestones · overdue tasks</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-500 text-[9px] font-black uppercase tracking-widest"><i class="fas fa-users mr-1.5"></i><?php echo count($group_rows); ?> groups</span>
                        <span class="px-3 py-1.5 rounded-lg bg-rose-500/10 text-rose-500 text-[9px] font-black uppercase tracking-widest"><i class="fas fa-exclamation-triangle mr-1.5"></i><?php echo $kpi_overdue_count; ?> overdue</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="premium-card rounded-[2.5rem] overflow-hidden shadow-xl">
                        <div class="p-6 sm:p-8 pb-5 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 shrink-0 rounded-2xl bg-blue-500/10 text-blue-400 flex items-center justify-center text-sm"><i class="fas fa-project-diagram"></i></div>
                                <div>
                                    <h4 class="text-[10px] font-black uppercase text-slate-500 tracking-widest">Capstone Group Progress</h4>
                                    <p class="text-[9px] font-black uppercase tracking-widest text-blue-400 mt-0.5">Approved chapters vs. 6-chapter track</p>
                                </div>
                            </div>
                        </div>
                        <div class="overflow-x-auto custom-scrollbar px-6 sm:px-8 pb-8">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="p-3 text-[9px] font-black uppercase tracking-widest text-slate-500">Group</th>
                                        <th class="p-3 text-[9px] font-black uppercase tracking-widest text-slate-500">Adviser</th>
                                        <th class="p-3 text-[9px] font-black uppercase tracking-widest text-slate-500">Milestone Phase</th>
                                        <th class="p-3 text-[9px] font-black uppercase tracking-widest text-slate-500">Chapters</th>
                                        <th class="p-3 text-[9px] font-black uppercase tracking-widest text-slate-500">Last Activity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($group_rows) > 0): ?>
                                        <?php foreach ($group_rows as $g):
                                            $gi = $group_summary[(int)$g['id']] ?? [];
                                            $phase_badge = $gi['phase'] === 0 ? '<span class="text-[8px] font-black text-slate-500 uppercase tracking-tight bg-slate-100 px-2 py-1 rounded-lg">Not Started</span>' : ($gi['phase'] === 1 ? '<span class="text-[8px] font-black text-blue-400 uppercase tracking-tight bg-blue-500/10 px-2 py-1 rounded-lg">Phase 1 · Proposal</span>' : ($gi['phase'] === 2 ? '<span class="text-[8px] font-black text-violet-500 uppercase tracking-tight bg-violet-500/10 px-2 py-1 rounded-lg">Phase 2 · Development</span>' : '<span class="text-[8px] font-black text-emerald-500 uppercase tracking-tight bg-emerald-500/10 px-2 py-1 rounded-lg">Phase 3 · Final</span>'));
                                            $status_badge = $g['status'] === 'active' ? '<span class="text-[8px] font-black text-emerald-500 uppercase tracking-tight bg-emerald-500/10 px-2 py-1 rounded-lg">Active</span>' : '<span class="text-[8px] font-black text-amber-500 uppercase tracking-tight bg-amber-500/10 px-2 py-1 rounded-lg">Pending</span>';
                                            $pct = (int)($gi['progress_pct'] ?? 0);
                                        ?>
                                        <tr class="border-b border-gray-200 hover:bg-slate-100 transition-all last:border-0">
                                            <td class="p-3">
                                                <p class="text-[11px] font-bold text-slate-900"><?php echo htmlspecialchars($g['username']); ?></p>
                                                <p class="text-[8px] text-slate-500 uppercase font-black tracking-wider truncate max-w-[180px]"><?php echo htmlspecialchars($g['project_title'] ?? ($g['full_name'] ?? '')); ?></p>
                                            </td>
                                            <td class="p-3">
                                                <p class="text-[11px] font-bold text-slate-600 truncate"><?php echo htmlspecialchars($g['adviser_name'] ?? '—'); ?></p>
                                                <?php echo $status_badge; ?>
                                            </td>
                                            <td class="p-3"><?php echo $phase_badge; ?></td>
                                            <td class="p-3 min-w-[150px]">
                                                <p class="text-[10px] font-bold text-slate-600 mb-1"><?php echo (int)($gi['chapters_done'] ?? 0); ?> / <?php echo (int)($gi['chapters_total'] ?? 6); ?> approved</p>
                                                <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-blue-500 rounded-full transition-all" style="width: <?php echo $pct; ?>%"></div></div>
                                            </td>
                                            <td class="p-3">
                                                <p class="text-[10px] font-bold text-slate-500 whitespace-nowrap"><?php echo $gi['days_inactive'] === null ? 'Never logged in' : $gi['days_inactive'] . 'd ago'; ?></p>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="p-10 text-center text-[10px] font-bold uppercase tracking-widest text-slate-500 italic">No groups registered yet</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="premium-card rounded-[2.5rem] overflow-hidden shadow-xl">
                        <div class="p-6 sm:p-8 pb-5 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 shrink-0 rounded-2xl bg-rose-500/10 text-rose-500 flex items-center justify-center text-sm"><i class="fas fa-exclamation-triangle"></i></div>
                                <div>
                                    <h4 class="text-[10px] font-black uppercase text-slate-500 tracking-widest">Overdue Tasks</h4>
                                    <p class="text-[9px] font-black uppercase tracking-widest text-blue-400 mt-0.5">Chapter deadlines missed by at least one group</p>
                                </div>
                            </div>
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-500"><?php echo count($overdue_rows); ?> overdue</span>
                        </div>
                        <div class="overflow-x-auto custom-scrollbar px-6 sm:px-8 pb-8">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="p-3.5 text-[9px] font-black uppercase tracking-widest text-slate-500">Task</th>
                                        <th class="p-3.5 text-[9px] font-black uppercase tracking-widest text-slate-500">Adviser</th>
                                        <th class="p-3.5 text-[9px] font-black uppercase tracking-widest text-slate-500">Deadline</th>
                                        <th class="p-3.5 text-[9px] font-black uppercase tracking-widest text-slate-500">Days Overdue</th>
                                        <th class="p-3.5 text-[9px] font-black uppercase tracking-widest text-slate-500">Behind</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($overdue_rows) > 0): ?>
                                        <?php foreach ($overdue_rows as $od): ?>
                                        <tr class="border-b border-gray-200 hover:bg-slate-100 transition-all last:border-0">
                                            <td class="p-3.5">
                                                <p class="text-[11px] font-bold text-slate-900">Chapter <?php echo (int)$od['chapter_number']; ?></p>
                                                <p class="text-[8px] text-slate-500 uppercase font-black tracking-wider truncate max-w-[220px]"><?php echo htmlspecialchars($od['caption']); ?></p>
                                            </td>
                                            <td class="p-3.5"><p class="text-[11px] font-bold text-slate-600 truncate"><?php echo htmlspecialchars($od['adviser_name']); ?></p></td>
                                            <td class="p-3.5 whitespace-nowrap">
                                                <p class="text-[11px] font-bold text-slate-600"><?php echo date('M j, Y', strtotime($od['deadline'])); ?></p>
                                            </td>
                                            <td class="p-3.5">
                                                <span class="text-[9px] font-black text-rose-500 uppercase tracking-tight bg-rose-500/10 px-2 py-1 rounded-lg"><?php echo (int)$od['days_overdue']; ?>d</span>
                                            </td>
                                            <td class="p-3.5">
                                                <?php foreach (array_slice($od['behind_groups'], 0, 3) as $bg): ?>
                                                    <span class="inline-block text-[8px] font-black text-rose-500 uppercase tracking-tight bg-rose-500/10 px-2 py-1 rounded-lg mr-1 mb-1"><?php echo htmlspecialchars($bg); ?></span>
                                                <?php endforeach; ?>
                                                <?php if (count($od['behind_groups']) > 3): ?><span class="text-[8px] font-black text-slate-500 uppercase tracking-tight">+<?php echo count($od['behind_groups']) - 3; ?> more</span><?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="p-10 text-center text-[10px] font-bold uppercase tracking-widest text-slate-500 italic">No overdue tasks — all deadlines met</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

        </div>
    </main>

    <div id="drawer-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-[300]" onclick="closeSettingsDrawer()"></div>

    <div id="settings-drawer" class="fixed top-0 right-0 h-full w-80 bg-[#F0F2F5]/98 backdrop-blur-3xl z-[301] shadow-2xl border-l border-gray-200 flex flex-col transform translate-x-full transition-transform duration-300">
        <div class="p-8 border-b border-gray-200 flex justify-between items-center bg-slate-100">
            <div><h2 class="text-lg font-black uppercase text-slate-900 leading-none tracking-tight">Settings</h2><p class="text-[9px] font-bold text-blue-400 uppercase tracking-widest mt-1">Privacy & Control</p></div>
            <button onclick="closeSettingsDrawer()" class="w-10 h-10 rounded-xl bg-slate-100 text-slate-500 hover:text-red-400 transition"><i class="fas fa-times"></i></button>
        </div>
        <div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-4">
            <div class="bg-slate-100 rounded-[1.5rem] border border-gray-200 overflow-hidden">
                <button onclick="toggleSecurityMenu()" class="w-full flex items-center justify-between p-5 hover:bg-slate-100 transition group text-left">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-slate-200 text-blue-400 flex items-center justify-center shadow-sm group-hover:bg-blue-600 group-hover:text-white transition-all"><i class="fas fa-history text-xs"></i></div>
                        <div><p class="text-[11px] font-black uppercase text-slate-700 leading-none">Security & Password</p><p class="text-[9px] font-bold text-slate-500 uppercase mt-1">Protection</p></div>
                    </div>
                    <i id="security-chevron" class="fas fa-chevron-right text-slate-500 text-xs transition-transform duration-300"></i>
                </button>
                <div id="security-submenu" class="max-h-0 overflow-hidden transition-all duration-300 px-5 space-y-1">
                    <button onclick="openLoginsModal()" class="w-full text-left py-3 px-4 rounded-xl text-[10px] font-black uppercase text-slate-500 hover:bg-slate-100 hover:text-blue-400 transition flex items-center gap-3"><i class="fas fa-history text-[8px]"></i> Login Activity</button>
                    <button onclick="openPasswordModal()" class="w-full text-left py-3 px-4 rounded-xl text-[10px] font-black uppercase text-slate-500 hover:bg-slate-100 hover:text-blue-400 transition flex items-center gap-3"><i class="fas fa-key text-[8px]"></i> Change Password</button>
                </div>
            </div>

            <button onclick="openProfileModal()" class="w-full flex items-center justify-between p-5 rounded-[1.5rem] bg-slate-100 border border-gray-200 hover:bg-blue-50 transition group text-left">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-slate-200 text-blue-400 flex items-center justify-center shadow-sm group-hover:bg-blue-600 group-hover:text-white transition-all"><i class="fas fa-user-circle"></i></div>
                    <div><p class="text-[11px] font-black uppercase text-slate-700 leading-none">Profile & Details</p><p class="text-[9px] font-bold text-slate-500 uppercase mt-1">Master Information</p></div>
                </div>
                <i class="fas fa-chevron-right text-slate-500 text-xs"></i>
            </button>
        </div>
    </div>

    <!-- Login Activity Modal -->
    <div id="logins-modal" class="fixed inset-0 bg-black/80 backdrop-blur-xl hidden z-[500] items-center justify-center p-6">
        <div id="logins-content" class="bg-gradient-to-b from-white to-slate-100 w-full max-w-md rounded-[2.5rem] border border-gray-200 shadow-2xl transform scale-95 opacity-0 overflow-hidden">
            <div class="p-8 border-b bg-slate-100 border-gray-200 flex justify-between items-center text-slate-900 font-black uppercase">
                <h3>Login Activity</h3>
                <button onclick="closeLoginsModal()" class="text-slate-500 hover:text-red-400 transition">X</button>
            </div>
            <div class="p-8 space-y-4 max-h-[400px] overflow-y-auto custom-scrollbar">
                <?php if ($logins_q && mysqli_num_rows($logins_q) > 0): ?>
                    <?php mysqli_data_seek($logins_q, 0); while($log = mysqli_fetch_assoc($logins_q)): ?>
                        <div class="p-4 bg-slate-100 border border-gray-200 rounded-2xl flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-blue-500/10 text-blue-400 flex items-center justify-center">
                                <i class="fas fa-sign-in-alt text-xs"></i>
                            </div>
                            <span class="text-[10px] font-bold text-slate-600">
                                <span class="live-time" data-timestamp="<?php echo strtotime($log['login_time']); ?>"><?php echo getRelativeTime(strtotime($log['login_time'])); ?> ago</span>
                            </span>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-center py-6 text-slate-500 text-[10px] font-bold uppercase italic">No history logs mapped.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Profile Modal -->
    <div id="profile-modal" class="fixed inset-0 bg-black/80 backdrop-blur-xl hidden z-[500] items-center justify-center p-6">
        <div id="profile-content" class="bg-gradient-to-b from-white to-slate-100 w-full max-w-md rounded-[3rem] border border-gray-200 shadow-2xl overflow-hidden transform transition-all scale-95 opacity-0">
            <div class="h-32 bg-blue-600/20 relative"><button onclick="closeProfileModal()" class="absolute top-6 right-6 w-8 h-8 rounded-full bg-slate-100 text-slate-900 hover:bg-slate-100 transition"><i class="fas fa-times text-xs"></i></button></div>
            <div class="px-8 pb-10 -mt-12 relative text-center">
                <div class="w-24 h-24 bg-white rounded-[2rem] border-4 border-gray-200 shadow-xl mx-auto flex items-center justify-center text-3xl text-blue-400 mb-4"><i class="fas fa-user-shield"></i></div>
                <h3 class="text-xl font-black text-slate-900 uppercase tracking-tight"><?php echo htmlspecialchars($admin_data['full_name'] ?? 'Admin'); ?></h3>
                <p class="text-[9px] font-black text-blue-400 uppercase tracking-widest mb-8">System Administrator</p>
                <div class="space-y-3 text-left">
                    <div class="p-5 rounded-3xl bg-slate-100 border border-gray-200 flex items-center justify-between"><div><p class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Authenticated Username</p><p class="text-sm font-bold text-slate-600">@<?php echo htmlspecialchars($admin_data['username']); ?></p></div><i class="fas fa-id-card text-slate-700"></i></div>
                    <div class="p-5 rounded-3xl bg-slate-100 border border-gray-200 flex items-center justify-between"><div><p class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Primary Email Address</p><p class="text-sm font-bold text-slate-600"><?php echo htmlspecialchars($admin_data['email']); ?></p></div><i class="fas fa-envelope text-slate-700"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Password Change Modal -->
    <div id="password-modal" class="fixed inset-0 bg-black/80 backdrop-blur-xl hidden z-[500] items-center justify-center p-6">
        <div id="password-content" class="bg-gradient-to-b from-white to-slate-100 w-full max-w-md rounded-[2.5rem] border border-gray-200 shadow-2xl overflow-hidden transform transition-all scale-95 opacity-0">
            <div class="p-8 border-b border-gray-200 bg-slate-100 flex justify-between items-center"><h3 class="text-lg font-black uppercase text-slate-900 tracking-tight leading-none">Security Gate</h3><button onclick="closePasswordModal()" class="text-slate-500 hover:text-red-400 transition-all"><i class="fas fa-times"></i></button></div>
            <form id="changePasswordForm" class="p-8 space-y-5">
                <div class="space-y-2"><label class="text-[9px] font-black uppercase text-slate-500 ml-2 tracking-widest">Current Password</label><div class="relative"><input type="password" id="curr_pass" name="current_password" required placeholder="Enter current password" class="w-full px-6 py-4 bg-white border-2 border-gray-200 text-slate-900 rounded-2xl outline-none focus:border-blue-500 transition font-bold text-sm pr-12"><button type="button" onclick="togglePass('curr_pass', this)" class="absolute right-5 top-1/2 -translate-y-1/2 text-slate-500 hover:text-blue-400 transition"><i class="fas fa-eye"></i></button></div></div>
                <div class="space-y-2"><label class="text-[9px] font-black uppercase text-slate-500 ml-2 tracking-widest">New Password</label><div class="relative"><input type="password" id="new_pass" name="new_password" required placeholder="Enter new password" class="w-full px-6 py-4 bg-white border-2 border-gray-200 text-slate-900 rounded-2xl outline-none focus:border-blue-500 transition font-bold text-sm pr-12"><button type="button" onclick="togglePass('new_pass', this)" class="absolute right-5 top-1/2 -translate-y-1/2 text-slate-500 hover:text-blue-400 transition"><i class="fas fa-eye"></i></button></div></div>
                <div class="space-y-2"><label class="text-[9px] font-black uppercase text-slate-500 ml-2 tracking-widest">Confirm New Password</label><div class="relative"><input type="password" id="conf_pass" name="confirm_password" required placeholder="Confirm new password" class="w-full px-6 py-4 bg-white border-2 border-gray-200 text-slate-900 rounded-2xl outline-none focus:border-blue-500 transition font-bold text-sm pr-12"><button type="button" onclick="togglePass('conf_pass', this)" class="absolute right-5 top-1/2 -translate-y-1/2 text-slate-600 hover:text-blue-400 transition"><i class="fas fa-eye"></i></button></div><p id="password-error" class="hidden text-[9px] font-black text-rose-500 uppercase ml-2 mt-1 tracking-widest"></p></div>
                <button type="submit" id="passSubmitBtn" class="w-full py-4 bg-gradient-to-r from-blue-600 to-blue-500 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-xl hover:brightness-110 transition-all active:scale-95">Authorize Change</button>
            </form>
        </div>
    </div>

    <script>
        function handleNotifClick() {
            document.getElementById('notif-popover').classList.toggle('hidden');
            const badge = document.getElementById('notif-badge');
            if(badge) {
                badge.style.display = 'none';
                fetch('?action=clear_reg_notifications&csrf_token=' + encodeURIComponent(CSRF_TOKEN))
                .then(res => res.json())
                .catch(err => console.error("Notification sync failure:", err));
            }
        }

        function toggleSettingsDropdown() {
            if(document.getElementById('notif-popover')) document.getElementById('notif-popover').classList.add('hidden'); document.getElementById('settings-popover').classList.toggle('hidden'); }
        function openSettingsDrawer() { document.getElementById('settings-popover').classList.add('hidden'); document.getElementById('drawer-overlay').classList.remove('hidden'); document.getElementById('settings-drawer').classList.add('open'); document.getElementById('settings-drawer').classList.remove('translate-x-full'); }
        function closeSettingsDrawer() { document.getElementById('drawer-overlay').classList.add('hidden'); document.getElementById('settings-drawer').classList.add('translate-x-full'); document.getElementById('settings-drawer').classList.remove('open'); }
        function toggleSecurityMenu() { const sub = document.getElementById('security-submenu'); const chev = document.getElementById('security-chevron'); if (sub.style.maxHeight === "0px" || sub.style.maxHeight === "") { sub.style.maxHeight = "400px"; chev.classList.add('rotate-90'); } else { sub.style.maxHeight = "0px"; chev.classList.remove('rotate-90'); } }

        function animateIn(id, contentId) { const m = document.getElementById(id); const c = document.getElementById(contentId); m.classList.remove('hidden'); m.classList.add('flex'); setTimeout(() => { c.classList.remove('scale-95', 'opacity-0'); c.classList.add('scale-100', 'opacity-100'); }, 10); }
        function openLoginsModal() { animateIn('logins-modal', 'logins-content'); }
        function closeLoginsModal() { document.getElementById('logins-modal').classList.replace('flex', 'hidden'); }
        function openPasswordModal() { animateIn('password-modal', 'password-content'); }
        function closePasswordModal() { document.getElementById('password-modal').classList.replace('flex', 'hidden'); }
        function openProfileModal() { animateIn('profile-modal', 'profile-content'); }
        function closeProfileModal() { document.getElementById('profile-modal').classList.replace('flex', 'hidden'); }

        function togglePass(id, btn) { const input = document.getElementById(id); const icon = btn.querySelector('i'); if (input.type === "password") { input.type = "text"; icon.classList.replace('fa-eye', 'fa-eye-slash'); } else { input.type = "password"; icon.classList.replace('fa-eye-slash', 'fa-eye'); } }

        function showToast(msg) {
            const toast = document.getElementById('toast-container');
            document.getElementById('toast-message').innerText = msg;
            toast.classList.remove('-translate-y-20', 'opacity-0'); toast.classList.add('translate-y-0', 'opacity-100');
            setTimeout(() => { toast.classList.add('-translate-y-20', 'opacity-0'); toast.classList.remove('translate-y-0', 'opacity-100'); }, 3000);
        }

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebar-backdrop').classList.toggle('open');
        }

        window.onclick = function(e) {
            if (!e.target.closest('.nav-icon-container') && !e.target.closest('#settings-drawer') && !e.target.closest('#notif-popover')) {
                document.getElementById('notif-popover').classList.add('hidden');
                document.getElementById('settings-popover').classList.add('hidden');
            }
        };

        const textMuted = '#94a3b8';
        const borderGrid = '#dadde1';
        const chartFontOptions = { family: 'Plus Jakarta Sans', weight: '700', size: 11 };

        // Submission Velocity (line)
        new Chart(document.getElementById('submissionTrendChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [{
                    label: 'Manuscripts Submitted',
                    data: <?php echo json_encode($trend_data); ?>,
                    borderColor: '#1877F2',
                    backgroundColor: 'rgba(24, 119, 242, 0.12)',
                    fill: true,
                    tension: 0.45,
                    borderWidth: 2.5,
                    pointRadius: 3,
                    pointBackgroundColor: '#1877F2',
                    pointBorderColor: '#F0F2F5',
                    pointBorderWidth: 2,
                    pointHoverRadius: 7,
                    pointHoverBackgroundColor: '#1877F2',
                    pointHoverBorderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: borderGrid }, ticks: { color: textMuted, font: chartFontOptions, stepSize: 1 }, border: { display: false } },
                    x: { grid: { display: false }, ticks: { color: textMuted, font: chartFontOptions }, border: { display: false } }
                },
                animation: { duration: 1600, easing: 'easeOutQuart' }
            }
        });

        // Milestone Progress (doughnut)
        new Chart(document.getElementById('milestoneChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($phase_chart_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($phase_distribution); ?>,
                    backgroundColor: ['#e4e6eb', '#93c5fd', '#1877F2', '#4267B2'],
                    borderWidth: 0,
                    hoverOffset: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { labels: { color: '#1c1e21', font: chartFontOptions } }
                }
            }
        });

        // Submission Status (doughnut)
        new Chart(document.getElementById('submissionStatusChart'), {
            type: 'doughnut',
            data: {
                labels: ['Approved', 'Pending', 'Rejected'],
                datasets: [{
                    data: [<?php echo $status_map['approved']; ?>, <?php echo $status_map['pending']; ?>, <?php echo $status_map['rejected']; ?>],
                    backgroundColor: ['#1877F2', 'rgba(148, 163, 184, 0.4)', '#ef4444'],
                    borderWidth: 0,
                    hoverOffset: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { labels: { color: '#1c1e21', font: chartFontOptions } }
                }
            }
        });

        // Adviser Workload (horizontal bar)
        new Chart(document.getElementById('workloadChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($workload_labels); ?>,
                datasets: [{
                    label: 'Handled Groups',
                    data: <?php echo json_encode($workload_data); ?>,
                    backgroundColor: '#3b82f6',
                    borderRadius: 8,
                    barThickness: 14
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { color: borderGrid }, ticks: { color: textMuted, font: chartFontOptions, stepSize: 1 } },
                    y: { grid: { display: false }, ticks: { color: textMuted, font: chartFontOptions } }
                }
            }
        });

        const CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';

        const passForm = document.getElementById('changePasswordForm');
        const passError = document.getElementById('password-error');
        passForm.onsubmit = function(e) {
            e.preventDefault();
            const newP = document.getElementById('new_pass').value;
            const confP = document.getElementById('conf_pass').value;
            if(newP !== confP) { passError.innerText = "New passwords do not match!"; passError.classList.remove('hidden'); return; }
            const formData = new FormData(this);
            formData.append('csrf_token', CSRF_TOKEN);
            fetch('../backend/auth/update_admin_password.php', { method: 'POST', body: formData })
            .then(res => res.text()).then(data => {
                const response = data.trim();
                if(response === 'success') { closePasswordModal(); showToast("Password successfully changed"); passForm.reset(); passError.classList.add('hidden'); }
                else if(response === 'incorrect_current') { passError.innerText = "Current password you entered is incorrect"; passError.classList.remove('hidden'); }
                else { passError.innerText = response; passError.classList.remove('hidden'); }
            });
        };
    </script>
<?php include('chat_widget.php'); ?>
</body>
</html>