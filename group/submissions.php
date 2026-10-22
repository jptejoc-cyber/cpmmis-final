<?php
include('../backend/session_init.php');

include('../backend/db_config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'group') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);

if (isset($_GET['action']) && $_GET['action'] === 'clear_student_notifications') {
    header('Content-Type: application/json');
    // Require a valid CSRF token to prevent cross-site notification clearing.
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Security token validation failed']);
        exit();
    }
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND user_role = 'group' AND is_read = 0");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_role = 'group' AND message LIKE ? AND is_read = 0");
    $pattern = '%registered%';
    $stmt->bind_param('s', $pattern);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['status' => 'success']);
    exit();
}

$group_query = mysqli_query($conn, "SELECT g.*, a.full_name as adviser_name FROM `group` g LEFT JOIN adviser a ON g.adviser_id = a.id WHERE g.id = " . (int)$user_id . " LIMIT 1");
$group_data = mysqli_fetch_assoc($group_query);

$group_name = $group_data['username'] ?? 'Research Group';
$adviser_id = $group_data['adviser_id'];
$adviser_name = $group_data['adviser_name'] ?? 'Unassigned';
$group_status = strtolower($group_data['status'] ?? 'pending');

// --- 3-DAY DEADLINE REMINDER TRIGGER LOGIC ---
if ($group_status === 'active' || $group_status === 'approved' || $group_status === 'accepted') {
    if ($adviser_id) {
        $tasks_check = mysqli_query($conn, "SELECT id, deadline, chapter_number FROM adviser_tasks WHERE adviser_id = " . (int)$adviser_id . " AND task_type = 'chapter'");
        if ($tasks_check && mysqli_num_rows($tasks_check) > 0) {
            $now = time();
            while($tc = mysqli_fetch_assoc($tasks_check)) {
                $diff_days = (strtotime($tc['deadline']) - $now) / 86400;
                if ($diff_days >= 0 && $diff_days <= 3) {
                    $tid = $tc['id'];
                    $sub_chk = mysqli_query($conn, "SELECT id FROM group_submissions WHERE task_id = " . (int)$tid . " AND group_id = " . (int)$user_id);
                    if (!$sub_chk || mysqli_num_rows($sub_chk) == 0) {
                        $rem_msg = "Urgent: 3 days remaining to submit Chapter {$tc['chapter_number']}";
                        $rem_msg_esc = mysqli_real_escape_string($conn, $rem_msg);
                        $rem_chk = mysqli_query($conn, "SELECT id FROM notifications WHERE user_id = " . (int)$user_id . " AND user_role = 'group' AND message = '$rem_msg_esc'");
                        if (!$rem_chk || mysqli_num_rows($rem_chk) == 0) {
                            mysqli_query($conn, "INSERT INTO notifications (user_id, user_role, message, is_read) VALUES (" . (int)$user_id . ", 'group', '$rem_msg_esc', 0)");
                        }
                    }
                }
            }
        }
    }
}

$project_title = $group_data['project_title'] ?? 'Untitled Capstone Project';
$group_email = $group_data['leader_email'] ?? 'No email registered';

// Fetch Adviser Tasks for this group's adviser
$tasks_q = mysqli_query($conn, "SELECT t.*, 
    (SELECT id FROM group_submissions WHERE task_id = t.id AND group_id = " . (int)$user_id . " LIMIT 1) as submission_id,
    (SELECT file_path FROM group_submissions WHERE task_id = t.id AND group_id = " . (int)$user_id . " LIMIT 1) as submitted_file,
    (SELECT status FROM group_submissions WHERE task_id = t.id AND group_id = " . (int)$user_id . " LIMIT 1) as submission_status
    FROM adviser_tasks t 
    WHERE t.adviser_id = " . (int)$adviser_id . " 
    ORDER BY t.created_at DESC");

$logins_q = mysqli_query($conn, "SELECT * FROM login_logs WHERE user_id = " . (int)$user_id . " AND role = 'group' ORDER BY login_time DESC LIMIT 5");

$unread_q = mysqli_query($conn, "SELECT COUNT(*) as unread FROM notifications 
    WHERE is_read = 0 
      AND (
          (user_id = " . (int)$user_id . " AND user_role = 'group') 
          OR (
              user_role = 'group' 
              AND message LIKE '%registered%' 
              AND message LIKE '%adviser%'
              AND SUBSTRING_INDEX(message, ': ', -1) IN (SELECT full_name FROM adviser)
          )
      )");
$unread_count = mysqli_fetch_assoc($unread_q)['unread'] ?? 0;



$toast_msg = "";
$toast_type = "success";
if (isset($_SESSION['success'])) {
    $toast_msg = $_SESSION['success'];
    unset($_SESSION['success']);
} elseif (isset($_SESSION['error'])) {
    $toast_msg = $_SESSION['error'];
    $toast_type = "error";
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tasks & Submissions | CPMMIS</title>
    <link rel="icon" type="image/png" href="../chmsu.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <style>html{background:#F0F2F5;color-scheme:light}</style>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        :root { --chmsu-blue: #1877F2; --bg-deep: #F0F2F5; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-deep); background-image: none; background-size: 32px 32px; color: #1c1e21; overflow-x: hidden; }
        .ambient-glow { position: fixed; top: -10%; right: -10%; width: 600px; height: 600px; background: radial-gradient(circle, rgba(24, 119, 242, 0.06) 0%, transparent 70%); z-index: -1; filter: blur(80px); pointer-events: none; }
        aside { width: 5.5rem; transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); background: #ffffff; border-right: 1px solid #dadde1; height: 100vh; position: fixed; z-index: 150; display: flex; flex-direction: column; padding: 1.25rem 0; overflow: hidden; }
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
        
        #settings-drawer { transform: translateX(100%); transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); background: #ffffff; backdrop-filter: blur(40px); border-left: 1px solid #dadde1; }
        #settings-drawer.open { transform: translateX(0); }
        #security-submenu { max-height: 0; overflow: hidden; transition: max-height 0.4s ease; }
        #security-submenu.open { max-height: 400px; }
        .icon-tooltip { position: relative; }
        .icon-tooltip:hover::after { content: attr(data-tip); position: absolute; top: 110%; left: 50%; transform: translateX(-50%); background: #1e293b; color: white; padding: 6px 12px; border-radius: 8px; font-size: 9px; font-weight: 800; text-transform: uppercase; white-space: nowrap; z-index: 1000; display: block; }
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
    </style>
</head>
<body class="min-h-screen flex">

    <div class="ambient-glow"></div>
    <button class="sidebar-toggle-btn" id="sidebar-toggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">
        <i class="fas fa-bars text-sm"></i>
    </button>
    <div class="sidebar-backdrop" id="sidebar-backdrop" onclick="toggleSidebar()"></div>

    <div id="toast-container" class="fixed top-5 left-1/2 -translate-x-1/2 z-[999] transform -translate-y-20 transition-all duration-500 opacity-0 pointer-events-none">
        <div class="bg-white text-slate-800 px-8 py-4 rounded-2xl shadow-xl flex items-center gap-4 border border-blue-500/30" id="toast-box">
            <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center" id="toast-icon-bg"><i id="toast-icon" class="fas fa-check text-xs text-white"></i></div>
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
            <a href="group_dashboard.php" class="sidebar-link">
                <i class="fas fa-th-large w-5 text-center text-sm"></i>
                <span class="sidebar-text">Dashboard</span>
            </a>
            <a href="archive_view.php" class="sidebar-link">
                <i class="fas fa-archive w-5 text-center text-sm"></i>
                <span class="sidebar-text">Capstone Archive</span>
            </a>
            <a href="advisers.php" class="sidebar-link">
                <i class="fas fa-user-tie w-5 text-center text-sm"></i>
                <span class="sidebar-text">Adviser</span>
            </a>
            <a href="submissions.php" class="sidebar-link active">
                <i class="fas fa-file-upload w-5 text-center text-sm"></i>
                <span class="sidebar-text">Tasks & Submissions</span>
            </a>
            <a href="letters.php" class="sidebar-link">
                <i class="fas fa-envelope w-5 text-center text-sm"></i>
                <span class="sidebar-text">Letters</span>
            </a>
            <a href="meetings.php" class="sidebar-link">
                <i class="fas fa-handshake w-5 text-center text-sm"></i>
                <span class="sidebar-text">Consultation</span>
            </a>
        </nav>
    </aside>

    <main class="flex-1 ml-0 md:ml-24 p-6 sm:p-8 lg:p-12 space-y-6 sm:space-y-10 relative z-10">
        
        <nav class="premium-card rounded-[2.5rem] px-4 sm:px-8 py-4 flex flex-wrap justify-between items-center gap-2 shadow-2xl relative z-[100]">
            <div class="flex items-center gap-6 min-w-0 flex-1">
                <div>
                    <h2 class="text-lg font-black text-slate-900 uppercase tracking-tight leading-none truncate">Task Feed</h2>
                    <p class="text-[9px] font-bold text-blue-400 uppercase tracking-widest mt-1">Adviser Submissions</p>
                </div>
                <div class="h-10 w-[1px] bg-slate-100 mx-2"></div>
                <div class="flex flex-col">
                    <span class="text-[7px] font-black text-slate-500 uppercase tracking-widest">Assigned Adviser</span>
                    <span class="text-[11px] font-extrabold text-slate-600 uppercase flex items-center gap-2 mt-0.5">
                        <i class="fas fa-user-tie text-blue-400 text-[10px]"></i> <?php echo htmlspecialchars($adviser_name); ?>
                    </span>
                </div>
            </div>
            
            <div class="flex items-center gap-3 relative z-[250] shrink-0">
                    <div class="relative nav-icon-container">
                        <button onclick="toggleGroupChatWidget()" data-tip="Messages" class="icon-tooltip w-12 h-12 rounded-2xl bg-slate-100 border border-gray-200 flex items-center justify-center hover:bg-slate-100 transition relative shadow-sm">
                            <i class="fas fa-comment-dots text-blue-400"></i>
                        </button>
                    </div>
                    <div class="relative nav-icon-container">
                        <button onclick="handleNotifClick()" data-tip="Notifications" class="icon-tooltip w-12 h-12 rounded-2xl bg-slate-100 border border-gray-200 flex items-center justify-center hover:bg-slate-100 transition relative shadow-sm">
                            <i class="fas fa-bell text-blue-400"></i>
                            <?php if($unread_count > 0): ?><span id="notif-badge" class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[9px] font-black rounded-full border-4 border-white flex items-center justify-center"><?php echo $unread_count; ?></span><?php endif; ?>
                        </button>
                        <div id="notif-popover" class="hidden absolute right-0 mt-3 w-80 bg-white backdrop-blur-2xl rounded-[2.2rem] shadow-2xl border border-gray-200 z-[300] overflow-hidden animate-scale-in">
                            <div class="p-6 bg-slate-100 border-b border-gray-200 flex justify-between items-center"><h4 class="text-[10px] font-black uppercase text-slate-900 tracking-widest leading-none">Notifications</h4></div>
                            <div class="max-h-80 overflow-y-auto p-4 space-y-3 custom-scrollbar">
                                <?php 
                                // --- FIXED: RECOVERY ENGINE QUERY EXCLUDES DELETED ADVISER ACCOUNT LOGS DYNAMICALLY ---
                                $notif_list_query = "
                                    SELECT * FROM notifications 
                                    WHERE (user_id = " . (int)$user_id . " AND user_role = 'group') 
                                       OR (
                                           user_role = 'group' 
                                           AND message LIKE '%registered%' 
                                           AND message LIKE '%adviser%'
                                           AND SUBSTRING_INDEX(message, ': ', -1) IN (SELECT full_name FROM adviser)
                                       ) 
                                    ORDER BY id DESC LIMIT 10";

                                $notif_list = mysqli_query($conn, $notif_list_query);
                                if($notif_list && mysqli_num_rows($notif_list) > 0): 
                                    
    $all_notifs = [];
    while($row = mysqli_fetch_assoc($notif_list)) { $all_notifs[] = $row; }
    $new_notifs = array_filter($all_notifs, function($n) { return $n['is_read'] == 0; });
    $recent_notifs = array_filter($all_notifs, function($n) { return $n['is_read'] == 1; });
    $notif_groups = [
        ['label' => 'New', 'items' => $new_notifs, 'color' => 'blue'],
        ['label' => 'Recent', 'items' => $recent_notifs, 'color' => 'slate']
    ];
    foreach($notif_groups as $notif_group_data):
        if(count($notif_group_data['items']) > 0):
?>
<div class="px-2 py-1 mt-1 mb-2"><p class="text-[9px] font-black uppercase text-<?php echo $notif_group_data['color']; ?>-400 tracking-widest"><?php echo $notif_group_data['label']; ?></p></div>
<?php foreach($notif_group_data['items'] as $n): 
                                        $msg = $n['message'];
                                        $msg = preg_replace('/^New /i', '', $msg);
                                        $time_display = getRelativeTime($n['created_at']);
                                        $is_unread = $n['is_read'] == 0;
                                        $target_url = "submissions.php";
                                        $is_archive_alert = false;
                                        $status_badge = '';
                                        
                                        $color = 'blue';
                                        $action_label = "System Message";
                                        $icon_markup = '<i class="fas fa-envelope-open-text"></i>';
                                        
                                        if (stripos($n['message'], 'registered') !== false && stripos($n['message'], 'adviser') !== false) {
                                            $action_label = "Adviser Registered";
                                            $icon_markup = '<i class="fas fa-user-tie"></i>';
                                            $color = 'blue';
                                            $status_badge = '<span class="text-[7px] font-black text-blue-400 uppercase tracking-tight bg-blue-500/10 px-2 py-0.5 rounded-md">APPROVED</span>';
                                            $target_url = "advisers.php";
                                        } elseif (stripos($n['message'], 'Urgent') !== false) {
                                            $action_label = "System Alert";
                                            $icon_markup = '<i class="fas fa-exclamation-triangle"></i>';
                                            $color = 'rose';
                                            $target_url = "submissions.php";
                                        } elseif (stripos($n['message'], 'New Task') !== false) {
                                            $action_label = "Submission Status";
                                            $icon_markup = '<i class="fas fa-clipboard-list"></i>';
                                            $color = 'blue';
                                            $target_url = "submissions.php";
                                        } elseif (stripos($n['message'], 'submission') !== false || stripos($n['message'], 'submitted') !== false || stripos($n['message'], 'chapter') !== false) {
                                            $action_label = "Submission Status";
                                            $icon_markup = '<i class="fas fa-file-upload"></i>';
                                            $color = 'blue';
                                            $target_url = "submissions.php";
                                        } elseif (stripos($n['message'], 'meeting') !== false || stripos($n['message'], 'consultation') !== false) {
                                            $action_label = "Meeting Request";
                                            $icon_markup = '<i class="fas fa-handshake"></i>';
                                            $color = 'blue';
                                            $target_url = "submissions.php"; 
                                        } elseif (stripos($n['message'], 'adviser') !== false || stripos($n['message'], 'assigned') !== false) {
                                            $action_label = "Adviser Update";
                                            $icon_markup = '<i class="fas fa-user-tie"></i>';
                                            $color = 'blue';
                                            $target_url = "advisers.php";
                                        } elseif (stripos($n['message'], 'archived') !== false || stripos($n['message'], 'archive') !== false) {
                                            $action_label = "Capstone Archive";
                                            $icon_markup = '<i class="fas fa-archive"></i>';
                                            $color = 'blue';
                                            $target_url = "archive_view.php";
                                            $is_archive_alert = true;
                                        }

                                        $themes = [
                                            'blue' => ['text-blue-400', 'group-hover/item:text-blue-300', 'border-blue-500/30', 'bg-blue-500/5', 'bg-blue-500', 'bg-blue-500/10 text-blue-400'],
                                            'blue' => ['text-blue-400', 'group-hover/item:text-blue-300', 'border-blue-500/30', 'bg-blue-500/5', 'bg-blue-500', 'bg-blue-500/10 text-blue-400'],
                                            'rose' => ['text-rose-400', 'group-hover/item:text-rose-300', 'border-rose-500/30', 'bg-rose-500/5', 'bg-rose-500', 'bg-rose-500/10 text-rose-400'],
                                            'blue' => ['text-blue-400', 'group-hover/item:text-blue-300', 'border-blue-500/30', 'bg-blue-500/5', 'bg-blue-500', 'bg-blue-500/10 text-blue-400'],
                                            'blue' => ['text-blue-400', 'group-hover/item:text-blue-300', 'border-blue-500/30', 'bg-blue-500/5', 'bg-blue-500', 'bg-blue-500/10 text-blue-400'],
                                            'blue' => ['text-blue-400', 'group-hover/item:text-blue-300', 'border-blue-500/30', 'bg-blue-500/5', 'bg-blue-500', 'bg-blue-500/10 text-blue-400'],
                                            'blue' => ['text-blue-400', 'group-hover/item:text-blue-300', 'border-blue-500/30', 'bg-blue-500/5', 'bg-blue-500', 'bg-blue-500/10 text-blue-400']
                                        ];
                                        list($un_title, $un_hover, $un_border, $un_bg, $un_badge, $icon_theme) = $themes[$color];


                                        if ($is_archive_alert) {
                                            $status_badge = '';
                                        } elseif ($status_badge === '') {
                                            if (stripos($n['message'], 'approved') !== false || stripos($n['message'], 'verified') !== false || stripos($n['message'], 'accepted') !== false) {
                                                $status_badge = '<span class="text-[7px] font-black text-blue-400 uppercase tracking-tight bg-blue-500/10 px-2 py-0.5 rounded-md">Approved</span>';
                                            } elseif (stripos($n['message'], 'declined') !== false || stripos($n['message'], 'rejected') !== false || stripos($n['message'], 'returned') !== false) {
                                                $status_badge = '<span class="text-[7px] font-black text-rose-400 uppercase tracking-tight bg-rose-500/10 px-2 py-0.5 rounded-md">Declined</span>';
                                            } else {
                                                if(empty($status_badge) && stripos($n['message'], 'deadline') === false && stripos($n['message'], 'task') === false && stripos($n['message'], 'urgent') === false && stripos($n['message'], 'chapter') === false) {
                                                    $status_badge = '<span class="text-[7px] font-black text-amber-400 uppercase tracking-tight bg-amber-500/10 px-2 py-0.5 rounded-md">Pending</span>';
                                                }
                                            }
                                        }
                                ?>
                                        <a href="<?php echo $target_url; ?>" class="p-3 rounded-2xl bg-slate-100 border <?php echo $is_unread ? "$un_border $un_bg" : 'border-gray-200'; ?> flex items-center gap-4 hover:bg-slate-100 transition-all group/item">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-[10px] shrink-0 <?php echo $icon_theme; ?> transition-all shadow-sm"><?php echo $icon_markup; ?></div>
                                            <div class="flex-1 text-left min-w-0">
                                                <div class="flex justify-between items-center w-full gap-2 mb-0.5">
                                                    <p class="text-[10px] font-black <?php echo $is_unread ? "$un_title $un_hover" : 'text-slate-600 group-hover/item:text-blue-600'; ?> uppercase tracking-tight transition-colors truncate"><?php echo $action_label; ?></p>
                                                    <div class="shrink-0 flex gap-2 items-center">
                                                        <?php if(!$is_archive_alert && !empty($status_badge)): ?><?php echo $status_badge; ?><?php endif; ?>
                                                        <?php if($is_unread): ?><span class="text-[7px] font-black text-white uppercase tracking-tight <?php echo $un_badge; ?> px-1.5 py-0.5 rounded-md animate-pulse shadow-md">NEW</span><?php endif; ?>
                                                    </div>
                                                </div>
                                                <p class="text-[9px] font-bold text-slate-500 mt-0.5 uppercase line-clamp-2"><?php echo preg_replace('/^(NEW )/i', '<span class="text-blue-400">$1</span>', htmlspecialchars($msg)); ?></p>
                                                <p class="text-[7px] text-slate-500 uppercase font-black mt-1.5 tracking-wider"><?php echo $time_display; ?></p>
                                            </div>
                                        </a>
                                <?php endforeach; endif; endforeach; else: ?><p class="text-center py-10 text-slate-500 text-[10px] font-bold uppercase italic">No tracking entries</p><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="relative nav-icon-container">
                        <button onclick="toggleSettingsDropdown()" data-tip="Settings" class="icon-tooltip w-12 h-12 rounded-2xl bg-slate-100 border border-gray-200 flex items-center justify-center hover:bg-slate-100 transition relative shadow-sm"><i class="fas fa-cog text-blue-400"></i></button>
                        <div id="settings-popover" class="hidden absolute right-0 mt-3 w-64 bg-white backdrop-blur-2xl rounded-[2rem] shadow-2xl border border-gray-200 z-[300] overflow-hidden animate-scale-in">
                            <div class="p-2">
                                <button onclick="openSettingsDrawer()" class="w-full flex items-center gap-4 p-5 hover:bg-slate-100 transition rounded-2xl group text-left">
                                    <div class="w-8 h-8 rounded-lg bg-slate-100 text-slate-500 flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition-all"><i class="fas fa-sliders-h text-xs"></i></div>
                                    <p class="text-[11px] font-black uppercase text-slate-700 tracking-tight">Settings and Privacy</p>
                                </button>
                                <div class="h-px bg-slate-100 my-1 mx-4"></div>
                                <a href="../backend/auth/logout.php?csrf_token=<?php echo generate_csrf_token(); ?>" class="w-full flex items-center gap-4 p-5 hover:bg-rose-50 transition rounded-2xl group text-left text-rose-400 font-black uppercase text-[11px]"><div class="w-8 h-8 rounded-lg bg-rose-500/10 text-rose-400 flex items-center justify-center group-hover:bg-rose-600 group-hover:text-white transition-all"><i class="fas fa-sign-out-alt text-xs"></i></div>Logout</a>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>
        </div>


        <section class="space-y-6">
            <?php if(!$adviser_id): ?>
                <div class="text-center py-20 bg-slate-100 rounded-[2rem] border border-dashed border-gray-200 shadow-2xl" id="row-<?php echo $sub['id'] ?? $row['id'] ?? 0; ?>">
                    <div class="w-16 h-16 bg-slate-100 text-slate-500 rounded-full border border-gray-200 flex items-center justify-center text-2xl mx-auto mb-4"><i class="fas fa-user-slash"></i></div>
                    <p class="text-slate-500 font-black uppercase tracking-widest text-[10px]">No adviser assigned. Request an adviser first.</p>
                </div>
            <?php elseif(mysqli_num_rows($tasks_q) > 0): while($task = mysqli_fetch_assoc($tasks_q)): 
                $is_past_deadline = !empty($task['deadline']) ? (strtotime($task['deadline']) < time()) : false;
            ?>
                <div class="premium-card p-6 border-l-4 <?php echo $task['task_type'] === 'chapter' ? 'border-l-blue-500' : 'border-l-blue-500'; ?> flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6 shadow-2xl rounded-2xl">
                    <div class="flex-1 space-y-4 w-full">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="px-3 py-1 <?php echo $task['task_type'] === 'chapter' ? 'bg-blue-500/10 text-blue-400 border-blue-500/20' : 'bg-blue-500/10 text-blue-400 border-blue-500/20'; ?> text-[8px] font-black uppercase rounded-full border tracking-wider">
                                <?php echo $task['task_type'] === 'chapter' ? 'Chapter ' . $task['chapter_number'] : 'Other / Letter'; ?>
                            </span>
                            <span class="text-[9px] font-extrabold <?php echo (!empty($task['deadline']) && $is_past_deadline) ? 'text-rose-500' : 'text-blue-500'; ?> uppercase tracking-wider flex items-center gap-1.5">
                                <?php if (!empty($task['deadline'])): ?>
                                    <i class="far fa-clock text-[10px]"></i> Due: <?php echo date('M d, Y | g:i A', strtotime($task['deadline'])); ?>
                                <?php else: ?>
                                    <i class="fas fa-infinity text-slate-500 text-[10px]"></i> <span class="text-slate-500">No Deadline</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="space-y-1">
                            <h2 class="text-base font-black text-slate-900 uppercase tracking-tight"><?php echo htmlspecialchars($task['caption']); ?></h2>
                        </div>
                        
                        <?php if($task['file_path']): ?>
                            <div class="p-4 bg-slate-100 rounded-2xl border border-gray-200 shadow-inner inline-block">
                                <p class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Attached Resource:</p>
                                <a href="../backend/serve_file.php?type=tasks&file=<?php echo urlencode(htmlspecialchars($task['file_path'])); ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-500/10 text-slate-500 border border-slate-500/20 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-500/20 hover:text-blue-600 transition-all">
                                    <i class="fas fa-download text-sm"></i> <?php echo htmlspecialchars($task['original_filename']); ?>
                                </a>
                            </div>
                        <?php endif; ?>

                            <?php if($task['submission_id']): ?>
                                <div class="mt-4 p-4 bg-blue-950/20 border border-blue-500/20 rounded-2xl">
                                    <p class="text-[8px] font-black text-blue-500 uppercase tracking-widest mb-1.5">Your Submission:</p>
                                    <?php if (($task['submitted_file'] ?? '') === 'auto_combined'): ?>
                                        <span class="text-[10px] font-bold text-blue-400/70 italic">All 5 chapters approved &mdash; compiled final project available to your adviser</span>
                                    <?php else: ?>
                                        <a href="../backend/serve_file.php?type=submissions&file=<?php echo urlencode(htmlspecialchars($task['submitted_file'])); ?>" target="_blank" class="text-[10px] font-bold text-blue-400 underline underline-offset-2 hover:text-blue-300">View Submitted File</a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                    </div>

                    <div class="flex flex-row lg:flex-col gap-2.5 w-full lg:w-44 shrink-0 pt-2 lg:pt-0">
                        <?php if($task['task_type'] === 'chapter'): ?>
                            <?php if(!$task['submission_id']): ?>
                                <?php if(!$is_past_deadline): ?>
                                    <button onclick="openUploadModal(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars(addslashes($task['caption'])); ?>')" class="flex-1 py-3 bg-blue-500 text-white rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-blue-600 transition active:scale-95 flex items-center justify-center gap-2 shadow-lg shadow-blue-500/10">
                                        <i class="fas fa-upload text-[10px]"></i> Upload Turn In
                                    </button>
                                <?php else: ?>
                                    <button disabled class="flex-1 py-3 bg-rose-500/20 text-rose-400 rounded-xl text-[9px] font-black uppercase tracking-widest flex items-center justify-center gap-2 cursor-not-allowed">
                                        <i class="fas fa-times text-[10px]"></i> Deadline Passed
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if(!$is_past_deadline): ?>
                                    <button onclick="unsubmitTask(<?php echo $task['submission_id']; ?>)" class="flex-1 py-3 bg-amber-500/20 text-amber-500 border border-amber-500/20 rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-amber-500 hover:text-blue-600 transition active:scale-95 flex items-center justify-center gap-2">
                                        <i class="fas fa-undo text-[10px]"></i> Unsubmit
                                    </button>
                                <?php else: ?>
                                    <button disabled class="flex-1 py-3 bg-slate-100 text-slate-500 rounded-xl text-[9px] font-black uppercase tracking-widest flex items-center justify-center gap-2 cursor-not-allowed border border-gray-200">
                                        <i class="fas fa-lock text-[10px]"></i> Locked
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; else: ?>
                <div class="text-center py-20 bg-slate-100 rounded-[2rem] border border-dashed border-gray-200 shadow-2xl" id="row-<?php echo $sub['id'] ?? $row['id'] ?? 0; ?>">
                    <div class="w-16 h-16 bg-slate-100 text-slate-500 rounded-full border border-gray-200 flex items-center justify-center text-2xl mx-auto mb-4"><i class="fas fa-inbox"></i></div>
                    <p class="text-slate-500 font-black uppercase tracking-widest text-[10px]">No tasks assigned by adviser yet</p>
                </div>
            <?php endif; ?>
        </section>


    </main>

    <!-- Upload Task Modal -->
    <div id="upload-modal" class="fixed inset-0 bg-black/80 backdrop-blur-xl hidden z-[500] items-center justify-center p-6">
        <div id="upload-content" class="bg-gradient-to-b from-white to-slate-100 w-full max-w-lg rounded-[2.5rem] border border-gray-200 shadow-2xl overflow-hidden transform transition-all scale-95 opacity-0">
            <div class="p-8 border-b border-gray-200 bg-slate-100 flex justify-between items-center">
                <h3 class="text-lg font-black uppercase text-slate-900 tracking-tight leading-none">Submit Task</h3>
                <button onclick="closeUploadModal()" class="text-slate-500 hover:text-red-400 transition-all"><i class="fas fa-times"></i></button>
            </div>
            <form action="../backend/groups/group_upload_task.php" method="POST" enctype="multipart/form-data" class="p-8 space-y-5">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="task_id" id="upload_task_id">
                <p id="upload_task_name" class="text-[10px] font-black text-blue-400 uppercase tracking-widest mb-4"></p>
                
                <div class="space-y-2">
                    <label class="text-[9px] font-black uppercase text-slate-500 ml-2 tracking-widest">Attach PDF</label>
                    <div class="relative w-full h-32 border-2 border-dashed border-gray-200 rounded-2xl bg-slate-200 hover:bg-slate-100 hover:border-blue-500/50 transition-all flex flex-col items-center justify-center group/file cursor-pointer" onclick="document.getElementById('task_submission_file').click()">
                        <i class="fas fa-cloud-upload-alt text-3xl text-slate-500 group-hover/file:text-blue-400 mb-2 transition-colors"></i>
                        <span id="sub-file-name" class="text-[10px] font-black uppercase text-slate-500 tracking-widest">Browse PDF File</span>
                        <input type="file" name="submission_file" id="task_submission_file" required accept=".pdf" class="hidden" onchange="document.getElementById('sub-file-name').innerText = this.files[0] ? this.files[0].name : 'Browse PDF File'">
                    </div>
                </div>

                <button type="submit" class="w-full py-4 mt-2 bg-gradient-to-r from-blue-600 to-blue-500 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-xl hover:brightness-110 transition-all active:scale-95">Turn In</button>
            </form>
        </div>
    </div>



    <!-- Hidden Drawer/Settings Logic Preserved (Skipped full HTML for brevity, using scripts to toggle) -->
    <div id="drawer-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-[300]" onclick="closeSettingsDrawer()"></div>
    <div id="settings-drawer" class="fixed top-0 right-0 h-full w-80 z-[301] shadow-2xl flex flex-col transform translate-x-full transition-transform duration-300 bg-[#F0F2F5]/98 border-l border-gray-200">
        <div class="p-8 border-b border-gray-200 flex justify-between items-center bg-slate-100">
            <div><h2 class="text-lg font-black uppercase text-slate-900 leading-none tracking-tight">Settings</h2></div>
            <button onclick="closeSettingsDrawer()" class="w-10 h-10 rounded-xl bg-slate-100 text-slate-500 hover:text-red-400 transition flex items-center justify-center"><i class="fas fa-times"></i></button>
        </div>
        <!-- Settings Content Minimalised -->
    </div>

    <script>
        function showToast(msg, type='success') {
            const toast = document.getElementById('toast-container');
            const toastBox = document.getElementById('toast-box');
            const toastIconBg = document.getElementById('toast-icon-bg');
            const toastIcon = document.getElementById('toast-icon');
            
            document.getElementById('toast-message').innerText = msg;
            
            if(type === 'error') {
                toastBox.classList.replace('border-blue-500/20', 'border-rose-500/20');
                toastIconBg.classList.replace('bg-blue-500', 'bg-rose-500');
                toastIcon.classList.replace('fa-check', 'fa-times');
            } else {
                toastBox.classList.replace('border-rose-500/20', 'border-blue-500/20');
                toastIconBg.classList.replace('bg-rose-500', 'bg-blue-500');
                toastIcon.classList.replace('fa-times', 'fa-check');
            }

            toast.classList.remove('-translate-y-20', 'opacity-0'); toast.classList.add('translate-y-0', 'opacity-100');
            setTimeout(() => { toast.classList.add('-translate-y-20', 'opacity-0'); toast.classList.remove('translate-y-0', 'opacity-100'); }, 3000);
        }

        <?php if($toast_msg): ?>
            window.onload = () => showToast(<?php echo json_encode($toast_msg); ?>, <?php echo json_encode($toast_type); ?>);
        <?php endif; ?>

        function openUploadModal(taskId, taskName) {
            document.getElementById('upload_task_id').value = taskId;
            document.getElementById('upload_task_name').innerText = "Task: " + taskName;
            
            const m = document.getElementById('upload-modal');
            const c = document.getElementById('upload-content');
            m.classList.remove('hidden'); m.classList.add('flex');
            setTimeout(() => { c.classList.remove('scale-95', 'opacity-0'); c.classList.add('scale-100', 'opacity-100'); }, 10);
        }

        function closeUploadModal() {
            document.getElementById('upload-modal').classList.replace('flex', 'hidden');
        }

        const CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';
        function unsubmitTask(submissionId) {
            showCustomConfirm('Unsubmit File', 'Are you sure you want to unsubmit this file? You will need to upload a new one before the deadline.', 'danger', 'UNSUBMIT', () => {
                fetch('../backend/groups/group_unsubmit_task.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + encodeURIComponent(submissionId) + '&csrf_token=' + encodeURIComponent(CSRF_TOKEN)
                }).then(() => { window.location.href = '../group/submissions.php'; })
                  .catch(() => { window.location.href = '../group/submissions.php'; });
            });
        }
        function handleNotifClick() {
            if(document.getElementById('settings-popover')) document.getElementById('settings-popover').classList.add('hidden');
            document.getElementById('notif-popover').classList.toggle('hidden');
            
            const badge = document.getElementById('notif-badge');
            if (badge) badge.style.display = 'none';

            fetch('submissions.php?action=clear_student_notifications&csrf_token=' + encodeURIComponent(CSRF_TOKEN))
            .then(res => res.json())
            .then(data => { 
                document.querySelectorAll('.animate-pulse').forEach(el => el.style.display = 'none');
                document.querySelectorAll('.border-blue-500\\/30').forEach(el => {
                    el.classList.remove('border-blue-500/30', 'bg-blue-500/5');
                    el.classList.add('border-gray-200');
                });
            })
            .catch(err => console.error("Notification synchronization aborted:", err));
        }

        function toggleSettingsDropdown() {
            if(document.getElementById('notif-popover')) document.getElementById('notif-popover').classList.add('hidden');
            document.getElementById('settings-popover').classList.toggle('hidden'); 
        }
        function openSettingsDrawer() { document.getElementById('settings-popover').classList.add('hidden'); document.getElementById('drawer-overlay').classList.remove('hidden'); document.getElementById('settings-drawer').classList.add('open'); document.getElementById('settings-drawer').classList.remove('translate-x-full'); }
        function closeSettingsDrawer() { document.getElementById('drawer-overlay').classList.add('hidden'); document.getElementById('settings-drawer').classList.add('translate-x-full'); document.getElementById('settings-drawer').classList.remove('open'); }

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebar-backdrop').classList.toggle('open');
        }

        window.onclick = function(e) {
            if (!e.target.closest('.nav-icon-container') && !e.target.closest('#settings-drawer') && !e.target.closest('#notif-popover')) {
                if(document.getElementById('settings-popover')) document.getElementById('settings-popover').classList.add('hidden');
                if(document.getElementById('notif-popover')) document.getElementById('notif-popover').classList.add('hidden');
            }
        }
    </script>
    <?php include '../components/custom_confirm.php'; ?>
<style>
@keyframes globalHighlightFade {
    0% { background-color: rgba(59, 130, 246, 0.2) !important; box-shadow: inset 0 0 0 2px #3b82f6 !important; border-radius: inherit; }
    100% { background-color: transparent; box-shadow: inset 0 0 0 0px transparent; }
}
.global-highlight-row { animation: globalHighlightFade 3s ease-in-out forwards !important; }
</style>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const urlParams = new URLSearchParams(window.location.search);
    const highlightId = urlParams.get('highlight');
    if (highlightId) {
        const el = document.getElementById('row-' + highlightId);
        if (el) {
            setTimeout(() => {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                el.classList.add('global-highlight-row');
            }, 100);
        }
    }
});

function updateLiveTimes() {
    document.querySelectorAll('.live-time').forEach(el => {
        const ts = parseInt(el.dataset.timestamp);
        if (!ts) return;
        const diff = Math.floor((Date.now() / 1000) - ts);
        let text;
        if (diff < 1) text = 'now';
        else if (diff < 60) text = diff + ' second' + (diff > 1 ? 's' : '');
        else if (diff < 3600) text = Math.floor(diff / 60) + ' minute' + (Math.floor(diff / 60) > 1 ? 's' : '');
        else if (diff < 86400) text = Math.floor(diff / 3600) + ' hour' + (Math.floor(diff / 3600) > 1 ? 's' : '');
        else if (diff < 2592000) text = Math.floor(diff / 86400) + ' day' + (Math.floor(diff / 86400) > 1 ? 's' : '');
        else if (diff < 31536000) text = Math.floor(diff / 2592000) + ' month' + (Math.floor(diff / 2592000) > 1 ? 's' : '');
        else text = Math.floor(diff / 31536000) + ' year' + (Math.floor(diff / 31536000) > 1 ? 's' : '');
        el.textContent = text + (el.closest('[data-suffix]') ? '' : ' ago');
    });
}
setInterval(updateLiveTimes, 30000);
updateLiveTimes();
</script>
<?php include('chat_widget.php'); ?>
</body>
</html>

