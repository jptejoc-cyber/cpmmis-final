<?php
include('../backend/session_init.php');
include('../backend/db_config.php');
include('../backend/auth/csrf.php');

// Security Gate - Verifies alignment to the student group role array mapping
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'group') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);

// --- FIXED: INLINE NOTIFICATION UPDATER STATE TRIGGER FOR GROUP SIDE DIRECTORY ---
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
    // Also clear general adviser broadcast rows read state triggers
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_role = 'group' AND message LIKE ? AND is_read = 0");
    $pattern = '%registered%';
    $stmt->bind_param('s', $pattern);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['status' => 'success']);
    exit();
}

// Fetch primary group information
$group_query = mysqli_query($conn, "SELECT g.*, a.full_name as adviser_name, a.email as adviser_email FROM `group` g LEFT JOIN adviser a ON g.adviser_id = a.id WHERE g.id = " . (int)$user_id . " LIMIT 1");
$group_data = mysqli_fetch_assoc($group_query);

$group_name = $group_data['username'] ?? 'Research Group';
$group_status = strtolower($group_data['status'] ?? 'pending');
$project_title = $group_data['project_title'] ?? 'Untitled Capstone Project';
$group_email = $group_data['leader_email'] ?? 'No email registered';

// Progress Logic (Counting Approved Chapters)
$approved_chapters_q = mysqli_query($conn, "SELECT COUNT(DISTINCT document_name) as completed FROM group_submissions WHERE group_id = " . (int)$user_id . " AND status = 'approved'");
$completed_chapters = mysqli_fetch_assoc($approved_chapters_q)['completed'] ?? 0;
$progress_percent = ($completed_chapters / 5) * 100;

// Activity Logs (For the Login Activity modal)
$logins_q = mysqli_query($conn, "SELECT * FROM login_logs WHERE user_id = " . (int)$user_id . " AND role = 'group' ORDER BY login_time DESC LIMIT 5");

// --- 3-DAY DEADLINE REMINDER TRIGGER LOGIC ---
if ($group_status === 'active' || $group_status === 'approved' || $group_status === 'accepted') {
    $g_adviser = $group_data['adviser_id'];
    if ($g_adviser) {
        $tasks_check = mysqli_query($conn, "SELECT id, deadline, chapter_number FROM adviser_tasks WHERE adviser_id = " . (int)$g_adviser . " AND task_type = 'chapter'");
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



// --- FIXED: METRICS UNREAD BASELINE RECOVERY ENGINE EXCLUDES DELETED ADVISERS ---
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

$present_ay = "2026-2027";

if ($group_status === 'pending'):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pending Access | CPMMIS</title>
    <link rel="icon" type="image/png" href="../chmsu.png">
    <meta name="color-scheme" content="light">
    <style>html{background:#F0F2F5;color-scheme:light}</style>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: #F0F2F5; 
            background-image: none;
            background-size: 32px 32px;
            color: #1c1e21; 
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <div class="bg-slate-100 border border-gray-200 backdrop-blur-3xl p-12 rounded-[3rem] shadow-2xl text-center max-w-md w-full relative overflow-hidden">
        <div class="absolute -top-10 -right-10 w-40 h-40 bg-amber-500/10 rounded-full blur-3xl"></div>
        <div class="w-20 h-20 bg-amber-500/10 text-amber-400 border border-amber-500/20 rounded-3xl flex items-center justify-center text-3xl mx-auto mb-6"><i class="fas fa-clock"></i></div>
        <h1 class="text-xl font-black uppercase text-slate-900 tracking-tight">Account Registration Pending</h1>
        <p class="text-[11px] font-bold text-slate-500 uppercase mt-3 leading-relaxed">Your group profile credentials are awaiting admin system approval.</p>
        <a href="../backend/auth/logout.php?csrf_token=<?php echo generate_csrf_token(); ?>" class="mt-8 block py-4 bg-gradient-to-r from-amber-500 to-orange-500 hover:brightness-110 text-slate-900 rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-lg transition-all transform active:scale-95">Disconnect Session</a>
    </div>
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
</script>
</body>
</html>
<?php exit(); endif; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Consultations | CPMMIS</title>
    <link rel="icon" type="image/png" href="../chmsu.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <style>html{background:#F0F2F5;color-scheme:light}</style>
    <script src="https://cdn.tailwindcss.com"></script>
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
        
        #settings-drawer { transform: translateX(100%); transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); background: #ffffff; backdrop-filter: blur(40px); border-left: 1px solid #dadde1; }
        #settings-drawer.open { transform: translateX(0); }
        #security-submenu { max-height: 0; overflow: hidden; transition: max-height 0.4s ease; }
        #security-submenu.open { max-height: 400px; }

        .icon-tooltip { position: relative; }
        .icon-tooltip:hover::after { content: attr(data-tip); position: absolute; top: 110%; left: 50%; transform: translateX(-50%); background: #1e293b; color: white; padding: 6px 12px; border-radius: 8px; font-size: 9px; font-weight: 800; text-transform: uppercase; white-space: nowrap; z-index: 1000; display: block; }
        
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0, 0, 0, 0.15); border-radius: 10px; }
        
        .spinner-arrow { width: 100%; padding: 0.45rem 0; display: flex; align-items: center; justify-content: center; color: #94a3b8; background: #f0f2f5; border: 1px solid #dadde1; border-radius: 0.75rem; cursor: pointer; transition: all 0.15s ease; -webkit-user-select: none; user-select: none; }
        .spinner-arrow:hover { color: #1877F2; background: rgba(24, 119, 242, 0.1); border-color: rgba(24, 119, 242, 0.35); }
        .spinner-arrow:active { transform: scale(0.92); }
        .spinner-value { min-height: 2.75rem; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #1c1e21; font-weight: 800; font-size: 0.85rem; line-height: 1.2; padding: 0.25rem 0; }
        .spinner-value small { font-size: 0.55rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; margin-top: 0.1rem; }
        
        .progress-bar-fill { transition: stroke-dashoffset 1s cubic-bezier(0.4, 0, 0.2, 1); }
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
        <div class="bg-white text-slate-800 px-8 py-4 rounded-2xl shadow-xl flex items-center gap-4 border border-blue-500/30">
            <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center"><i class="fas fa-check text-xs text-white"></i></div>
            <p id="toast-message" class="text-slate-900 font-black uppercase tracking-widest text-[10px]">Matrix Security Restructured</p>
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
            <a href="submissions.php" class="sidebar-link">
                <i class="fas fa-file-upload w-5 text-center text-sm"></i>
                <span class="sidebar-text">Tasks & Submissions</span>
            </a>
            <a href="letters.php" class="sidebar-link">
                <i class="fas fa-envelope w-5 text-center text-sm"></i>
                <span class="sidebar-text">Letters</span>
            </a>
            <a href="meetings.php" class="sidebar-link active">
                <i class="fas fa-handshake w-5 text-center text-sm"></i>
                <span class="sidebar-text">Consultation</span>
            </a>
        </nav>
    </aside>

    <!-- FIXED Layer Mapping: Added explicit relative layout layer metrics -->
    <main class="flex-1 ml-0 md:ml-24 p-6 sm:p-8 lg:p-12 space-y-6 sm:space-y-10 relative z-10">
        
        <div class="relative z-[200]">
            <nav class="premium-card rounded-[2.5rem] px-4 sm:px-8 py-4 flex flex-wrap justify-between items-center gap-2 shadow-2xl relative z-[200]">
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] font-black text-blue-400 uppercase tracking-[0.2em] mb-1">Adviser Collaboration</p>
                    <h2 class="text-lg sm:text-xl font-black text-slate-900 uppercase tracking-tight truncate">Meeting Consultations</h2>
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
                                    if ($n['is_read'] == 1) {
                                        $msg = preg_replace('/^New /i', '', $msg);
                                    }
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

                <section class="premium-card rounded-[3.5rem] p-10 shadow-2xl border border-gray-200 relative overflow-hidden relative z-10 mb-8 flex justify-between items-center">
            <div class="absolute -bottom-32 -left-32 w-96 h-96 bg-blue-500/5 rounded-full blur-3xl pointer-events-none"></div>
            <div class="relative z-10">
                <span class="text-[9px] font-black text-blue-400 bg-blue-500/10 border border-blue-500/20 px-4 py-1.5 rounded-full uppercase tracking-widest">Consultations</span>
                <h1 class="text-2xl font-black text-slate-900 uppercase tracking-tight opacity-90 leading-tight mt-6 max-w-2xl">Meeting Schedule</h1>
                <p class="text-[10px] font-bold text-slate-500 mt-2 uppercase tracking-wide">Manage your consultations with your assigned adviser</p>
            </div>
            
            <div class="relative z-10">
                <?php if(!empty($group_data['adviser_id'])): ?>
                    <button onclick="openMeetingModal()" class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-8 py-4 rounded-2xl text-[11px] font-black uppercase tracking-widest shadow-xl hover:brightness-110 active:scale-95 transition-all flex items-center gap-3">
                        <i class="fas fa-calendar-plus text-sm"></i> Request Consultation
                    </button>
                <?php else: ?>
                    <button disabled class="bg-slate-100 border border-gray-200 text-slate-500 px-8 py-4 rounded-2xl text-[11px] font-black uppercase tracking-widest cursor-not-allowed flex items-center gap-3">
                        <i class="fas fa-ban text-sm"></i> No Adviser Assigned
                    </button>
                <?php endif; ?>
            </div>
        </section>

        <section class="premium-card rounded-[3rem] p-10 shadow-2xl border border-gray-200 relative z-10">
            <h3 class="text-[10px] font-black uppercase text-slate-500 tracking-[0.2em] mb-8">Consultation History</h3>
            
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="py-4 px-6 text-[9px] font-black uppercase text-slate-500 tracking-widest">Date & Time</th>
                            <th class="py-4 px-6 text-[9px] font-black uppercase text-slate-500 tracking-widest">Agenda / Topic</th>
                            <th class="py-4 px-6 text-[9px] font-black uppercase text-slate-500 tracking-widest">Status</th>
                            <th class="py-4 px-6 text-[9px] font-black uppercase text-slate-500 tracking-widest">Adviser Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-[11px] font-bold text-slate-600">
                        <?php 
                        $meetings_q = mysqli_query($conn, "SELECT * FROM meeting_requests WHERE group_id = " . (int)$user_id . " ORDER BY created_at DESC LIMIT 500");
                        if($meetings_q && mysqli_num_rows($meetings_q) > 0): 
                            while($m = mysqli_fetch_assoc($meetings_q)): 
                        ?>
                            <tr class="hover:bg-slate-100 transition-all">
                                <td class="py-6 px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center"><i class="fas fa-clock"></i></div>
                                        <div>
                                            <p class="text-slate-900"><?php echo date('M d, Y', strtotime($m['meeting_time'])); ?></p>
                                            <p class="text-[9px] text-slate-500 mt-0.5"><?php echo date('g:i A', strtotime($m['meeting_time'])); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-6 px-6 max-w-xs truncate" title="<?php echo htmlspecialchars($m['agenda']); ?>">
                                    <?php echo htmlspecialchars($m['agenda']); ?>
                                </td>
                                <td class="py-6 px-6">
                                    <?php if($m['status'] == 'pending'): ?>
                                        <span class="px-3 py-1 bg-amber-500/10 text-amber-400 text-[8px] font-black uppercase rounded-md border border-amber-500/20 tracking-widest animate-pulse">Pending</span>
                                    <?php elseif($m['status'] == 'accepted'): ?>
                                        <span class="px-3 py-1 bg-blue-500/10 text-blue-400 text-[8px] font-black uppercase rounded-md border border-blue-500/20 tracking-widest">Approved</span>
                                    <?php elseif($m['status'] == 'declined'): ?>
                                        <span class="px-3 py-1 bg-rose-500/10 text-rose-400 text-[8px] font-black uppercase rounded-md border border-rose-500/20 tracking-widest">Declined</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-6 px-6 text-[10px] text-slate-500 italic">
                                    <?php echo !empty($m['admin_remarks']) ? htmlspecialchars($m['admin_remarks']) : 'None'; ?>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="4" class="py-12 text-center text-slate-500 font-bold uppercase text-[10px] italic tracking-widest">No consultations requested yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Meeting Request Modal -->
        <div id="meeting-modal" class="fixed inset-0 backdrop-blur-xl hidden z-[500] items-center justify-center p-6">
            <div id="meeting-content" class="w-full max-w-lg rounded-[2.5rem] border border-gray-200 shadow-2xl overflow-hidden transform transition-all scale-95 opacity-0">
                <div class="p-8 border-b border-gray-200 bg-slate-100 flex justify-between items-center">
                    <h3 class="text-lg font-black uppercase text-slate-900 tracking-tight leading-none">Schedule Consultation</h3>
                    <button onclick="closeMeetingModal()" class="text-slate-500 hover:text-red-400 transition-all"><i class="fas fa-times"></i></button>
                </div>
                <form action="../backend/groups/submit_meeting.php" method="POST" class="p-8 space-y-6" onsubmit="return validateMeetingForm(event)">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="space-y-2">
                        <label class="text-[9px] font-black uppercase text-slate-500 ml-2 tracking-widest">Proposed Date & Time</label>
                        <div class="bg-white border-2 border-gray-200 rounded-2xl p-5">
                            <div class="grid grid-cols-3 gap-3">
                                <div class="flex flex-col items-center gap-1.5">
                                    <span class="text-[8px] font-black uppercase text-slate-500 tracking-widest">Month</span>
                                    <button type="button" onclick="stepMonth(1)" class="spinner-arrow" title="Next month"><i class="fas fa-chevron-up text-xs"></i></button>
                                    <div id="spinner-month" class="spinner-value"></div>
                                    <button type="button" onclick="stepMonth(-1)" class="spinner-arrow" title="Previous month"><i class="fas fa-chevron-down text-xs"></i></button>
                                </div>
                                <div class="flex flex-col items-center gap-1.5">
                                    <span class="text-[8px] font-black uppercase text-slate-500 tracking-widest">Day</span>
                                    <button type="button" onclick="stepDay(1)" class="spinner-arrow" title="Next day"><i class="fas fa-chevron-up text-xs"></i></button>
                                    <div id="spinner-day" class="spinner-value"></div>
                                    <button type="button" onclick="stepDay(-1)" class="spinner-arrow" title="Previous day"><i class="fas fa-chevron-down text-xs"></i></button>
                                </div>
                                <div class="flex flex-col items-center gap-1.5">
                                    <span class="text-[8px] font-black uppercase text-slate-500 tracking-widest">Time</span>
                                    <button type="button" onclick="stepTime(1)" class="spinner-arrow" title="Later"><i class="fas fa-chevron-up text-xs"></i></button>
                                    <div id="spinner-time" class="spinner-value"></div>
                                    <button type="button" onclick="stepTime(-1)" class="spinner-arrow" title="Earlier"><i class="fas fa-chevron-down text-xs"></i></button>
                                </div>
                            </div>
                            <input type="hidden" name="meeting_time" id="meeting_time" required>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="text-[9px] font-black uppercase text-slate-500 ml-2 tracking-widest">Agenda / Topic</label>
                        <textarea name="agenda" required rows="4" placeholder="Briefly describe what you want to discuss with your adviser..." class="w-full px-6 py-4 bg-white border-2 border-gray-200 text-slate-900 rounded-2xl outline-none focus:border-blue-500 transition font-bold text-sm custom-scrollbar"></textarea>
                    </div>
                    <div id="meeting-modal-error" class="hidden text-[9px] font-black text-rose-400 uppercase tracking-widest text-center"></div>
                    <button type="submit" class="w-full py-4 bg-gradient-to-r from-blue-600 to-blue-500 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-xl hover:brightness-110 transition-all active:scale-95">Submit Request</button>
                </form>
            </div>
        </div>
</div>
    </main>

    <div id="drawer-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-[300]" onclick="closeSettingsDrawer()"></div>
    <div id="settings-drawer" class="fixed top-0 right-0 h-full w-80 z-[301] shadow-2xl flex flex-col transform translate-x-full transition-transform duration-300">
        <div class="p-8 border-b border-gray-200 flex justify-between items-center bg-slate-100">
            <div><h2 class="text-lg font-black uppercase text-slate-900 leading-none tracking-tight">Settings</h2><p class="text-[9px] font-bold text-blue-400 uppercase tracking-widest mt-1">Privacy & Control</p></div>
            <button onclick="closeSettingsDrawer()" class="w-10 h-10 rounded-xl bg-slate-100 text-slate-500 hover:text-red-400 transition flex items-center justify-center"><i class="fas fa-times"></i></button>
        </div>
        <div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-4">
            
            <div class="bg-slate-100 rounded-[1.5rem] border border-gray-200 overflow-hidden">
                <button onclick="toggleSecurityMenu()" class="w-full flex items-center justify-between p-5 hover:bg-slate-100 transition group text-left">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-slate-200 text-blue-400 flex items-center justify-center shadow-sm group-hover:bg-blue-600 group-hover:text-white transition-all"><i class="fas fa-shield-alt"></i></div>
                        <div><p class="text-[11px] font-black uppercase text-slate-700 leading-none">Settings & Privacy</p><p class="text-[9px] font-bold text-slate-500 uppercase mt-1">Protection</p></div>
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

    <div id="profile-modal" class="fixed inset-0 bg-black/80 backdrop-blur-xl hidden z-[500] items-center justify-center p-6">
        <div id="profile-content" class="bg-gradient-to-b from-white to-slate-100 w-full max-w-md rounded-[3rem] border border-gray-200 shadow-2xl overflow-hidden transform transition-all scale-95 opacity-0">
            <div class="h-32 bg-blue-600/20 relative"><button onclick="closeProfileModal()" class="absolute top-6 right-6 w-8 h-8 rounded-full bg-slate-100 text-slate-900 hover:bg-slate-100 transition"><i class="fas fa-times text-xs"></i></button></div>
            <div class="px-8 pb-10 -mt-12 relative text-center">
                <div class="w-24 h-24 bg-white rounded-[2rem] border-4 border-gray-200 shadow-xl mx-auto flex items-center justify-center text-3xl text-blue-400 mb-4"><i class="fas fa-users"></i></div>
                <h3 class="text-xl font-black text-slate-900 uppercase tracking-tight"><?php echo htmlspecialchars($group_name); ?></h3>
                <p class="text-[9px] font-black text-blue-400 uppercase tracking-widest mb-8">Research Group Workspace Node</p>
                <div class="space-y-3 text-left">
                    <div class="p-5 rounded-3xl bg-slate-100 border border-gray-200 flex items-center justify-between"><div><p class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Group Workspace Identity</p><p class="text-sm font-bold text-slate-600">@<?php echo htmlspecialchars($group_name); ?></p></div><i class="fas fa-id-card text-slate-700"></i></div>
                    <div class="p-5 rounded-3xl bg-slate-100 border border-gray-200 flex items-center justify-between"><div><p class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Primary Portal Email Address</p><p class="text-sm font-bold text-slate-600"><?php echo htmlspecialchars($group_email); ?></p></div><i class="fas fa-envelope text-slate-700"></i></div>
                </div>
            </div>
        </div>
    </div>

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

    <div id="logins-modal" class="fixed inset-0 bg-black/80 backdrop-blur-xl hidden z-[500] items-center justify-center p-6">
        <div id="logins-content" class="bg-gradient-to-b from-white to-slate-100 w-full max-w-md rounded-[2.5rem] border border-gray-200 shadow-2xl transform scale-95 opacity-0 overflow-hidden">
            <div class="p-8 border-b bg-slate-100 border-gray-200 flex justify-between items-center text-slate-900 font-black uppercase"><h3>Login Activity</h3><button onclick="closeLoginsModal()" class="text-slate-500 hover:text-red-400 transition">X</button></div>
            <div class="p-8 space-y-4 max-h-[400px] overflow-y-auto custom-scrollbar">
                <?php if($logins_q && mysqli_num_rows($logins_q) > 0): mysqli_data_seek($logins_q, 0); while($log = mysqli_fetch_assoc($logins_q)): ?>
                    <div class="p-4 bg-slate-100 border border-gray-200 rounded-2xl flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-blue-500/10 text-blue-400 flex items-center justify-center"><i class="fas fa-sign-in-alt text-xs"></i></div>
                        <span class="live-time" data-timestamp="<?php echo strtotime($log['login_time']); ?>"><?php echo getRelativeTime(strtotime($log['login_time'])); ?> ago</span>
                    </div>
                <?php endwhile; else: ?>
                    <p class="text-center text-[10px] text-slate-500 font-bold uppercase italic py-4">No logged interaction loops found</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function handleNotifClick() {
            if(document.getElementById('settings-popover')) document.getElementById('settings-popover').classList.add('hidden');
            document.getElementById('notif-popover').classList.toggle('hidden');
            
            const badge = document.getElementById('notif-badge');
            if (badge) badge.style.display = 'none';

            fetch('group_dashboard.php?action=clear_student_notifications&csrf_token=' + encodeURIComponent(CSRF_TOKEN))
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

        function openSettingsDrawer() { 
            document.getElementById('settings-popover').classList.add('hidden'); 
            document.getElementById('drawer-overlay').classList.remove('hidden'); 
            document.getElementById('settings-drawer').classList.add('open'); 
            document.getElementById('settings-drawer').classList.remove('translate-x-full'); 
        }
        
        function closeSettingsDrawer() { 
            document.getElementById('drawer-overlay').classList.add('hidden'); 
            document.getElementById('settings-drawer').classList.add('translate-x-full'); 
            document.getElementById('settings-drawer').classList.remove('open'); 
        }

        function toggleSecurityMenu() {
            const sub = document.getElementById('security-submenu');
            const chev = document.getElementById('security-chevron');
            if (sub.style.maxHeight === "0px" || sub.style.maxHeight === "") {
                sub.style.maxHeight = "400px";
                chev.classList.add('rotate-90');
            } else {
                sub.style.maxHeight = "0px";
                chev.classList.remove('rotate-90');
            }
        }

        // Animated Modal Framework Operations
        function animateIn(id, contentId) { 
            const m = document.getElementById(id); 
            const c = document.getElementById(contentId); 
            m.classList.remove('hidden'); 
            m.classList.add('flex'); 
            setTimeout(() => { c.classList.remove('scale-95', 'opacity-0'); c.classList.add('scale-100', 'opacity-100'); }, 10); 
        }
        function openMeetingModal() { animateIn('meeting-modal', 'meeting-content'); }
        function closeMeetingModal() { document.getElementById('meeting-modal').classList.replace('flex', 'hidden'); }
        function openLoginsModal() { animateIn('logins-modal', 'logins-content'); }
        function closeLoginsModal() { document.getElementById('logins-modal').classList.replace('flex', 'hidden'); }
        function openPasswordModal() { animateIn('password-modal', 'password-content'); }
        function closePasswordModal() { document.getElementById('password-modal').classList.replace('flex', 'hidden'); }
        function openProfileModal() { animateIn('profile-modal', 'profile-content'); }
        function closeProfileModal() { document.getElementById('profile-modal').classList.replace('flex', 'hidden'); }

        function togglePass(id, btn) { 
            const input = document.getElementById(id); 
            const icon = btn.querySelector('i'); 
            if (input.type === "password") { input.type = "text"; icon.classList.replace('fa-eye', 'fa-eye-slash'); } 
            else { input.type = "password"; icon.classList.replace('fa-eye-slash', 'fa-eye'); } 
        }

        const MEETING_MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        let meetingDateTime = new Date();
        meetingDateTime.setSeconds(0, 0);
        meetingDateTime.setMinutes(Math.ceil((meetingDateTime.getMinutes() + 1) / 30) * 30);
        const meetingMinTime = meetingDateTime.getTime();

        function clampMeetingDateTime() {
            if (meetingDateTime.getTime() < meetingMinTime) {
                meetingDateTime = new Date(meetingMinTime);
            }
        }

        function validateMeetingForm(e) {
            const errEl = document.getElementById('meeting-modal-error');
            const val = document.getElementById('meeting_time').value;
            if (errEl) errEl.classList.add('hidden');
            if (!val) {
                if (errEl) { errEl.textContent = 'Please choose a proposed date and time.'; errEl.classList.remove('hidden'); }
                e.preventDefault();
                return false;
            }
            const ts = new Date(val).getTime();
            if (isNaN(ts) || ts <= Date.now()) {
                if (errEl) { errEl.textContent = 'Please choose a future date and time.'; errEl.classList.remove('hidden'); }
                e.preventDefault();
                return false;
            }
            return true;
        }

        function renderMeetingSpinners() {
            const y = meetingDateTime.getFullYear();
            const m = meetingDateTime.getMonth();
            const d = meetingDateTime.getDate();
            let h = meetingDateTime.getHours();
            const ampm = h >= 12 ? 'PM' : 'AM';
            let h12 = h % 12; if (h12 === 0) h12 = 12;
            const pad = n => String(n).padStart(2, '0');
            document.getElementById('spinner-month').innerHTML = MEETING_MONTHS[m] + '<small>' + y + '</small>';
            document.getElementById('spinner-day').textContent = pad(d);
            document.getElementById('spinner-time').textContent = pad(h12) + ':' + pad(meetingDateTime.getMinutes()) + ' ' + ampm;
            document.getElementById('meeting_time').value = y + '-' + pad(m + 1) + '-' + pad(d) + 'T' + pad(h) + ':' + pad(meetingDateTime.getMinutes());
        }

        function stepMonth(dir) {
            const y = meetingDateTime.getFullYear();
            const m = meetingDateTime.getMonth() + dir;
            const maxDay = new Date(y, m + 1, 0).getDate();
            const newDay = Math.min(meetingDateTime.getDate(), maxDay);
            meetingDateTime = new Date(y, m, newDay, meetingDateTime.getHours(), meetingDateTime.getMinutes(), 0, 0);
            clampMeetingDateTime();
            renderMeetingSpinners();
        }

        function stepDay(dir) {
            meetingDateTime.setDate(meetingDateTime.getDate() + dir);
            clampMeetingDateTime();
            renderMeetingSpinners();
        }

        function stepTime(dir) {
            meetingDateTime.setMinutes(meetingDateTime.getMinutes() + dir * 30);
            clampMeetingDateTime();
            renderMeetingSpinners();
        }

        renderMeetingSpinners();

        const CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebar-backdrop').classList.toggle('open');
        }

        // Change Password API Submission Handle
        const passForm = document.getElementById('changePasswordForm');
        const passError = document.getElementById('password-error');
        passForm.onsubmit = function(e) {
            e.preventDefault();
            const newP = document.getElementById('new_pass').value;
            const confP = document.getElementById('conf_pass').value;
            if(newP !== confP) { passError.innerText = "New passwords do not match!"; passError.classList.remove('hidden'); return; }
            
            const formData = new FormData(this);
            formData.append('csrf_token', CSRF_TOKEN);
            fetch('../backend/auth/update_group_password.php', { method: 'POST', body: formData })
            .then(res => res.text()).then(data => {
                const response = data.trim();
                if(response === 'success') { 
                    closePasswordModal(); 
                    showToast("Password successfully changed"); 
                    passForm.reset(); 
                    passError.classList.add('hidden'); 
                }
                else if(response === 'incorrect_current') { 
                    passError.innerText = "Current password you entered is incorrect"; 
                    passError.classList.remove('hidden'); 
                }
                else { 
                    passError.innerText = response; 
                    passError.classList.remove('hidden'); 
                }
            });
        };

        function showToast(msg) {
            const toast = document.getElementById('toast-container');
            document.getElementById('toast-message').innerText = msg;
            toast.classList.remove('-translate-y-20', 'opacity-0'); toast.classList.add('translate-y-0', 'opacity-100');
            setTimeout(() => { toast.classList.add('-translate-y-20', 'opacity-0'); toast.classList.remove('translate-y-0', 'opacity-100'); }, 3000);
        }

        window.onclick = function(e) {
            if (!e.target.closest('.nav-icon-container') && !e.target.closest('#settings-drawer') && !e.target.closest('#notif-popover')) {
                if(document.getElementById('settings-popover')) document.getElementById('settings-popover').classList.add('hidden');
                if(document.getElementById('notif-popover')) document.getElementById('notif-popover').classList.add('hidden');
            }
        }
    </script>
<style>
@keyframes globalHighlightFade {
    0% { background-color: rgba(59, 130, 246, 0.2) !important; box-shadow: inset 0 0 0 2px #3b82f6 !important; border-radius: inherit; }
    100% { background-color: transparent; box-shadow: inset 0 0 0 0px transparent; }
}
.global-highlight-row { animation: globalHighlightFade 3s ease-in-out forwards !important; }
</style>
<script>
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
document.addEventListener("DOMContentLoaded", function() {
    const urlParams = new URLSearchParams(window.location.search);
    
    // Toast notification check
    if (urlParams.get('status') === 'success') {
        showToast("Consultation requested successfully!");
        // Clean URL to prevent re-triggering on refresh
        window.history.replaceState(null, null, window.location.pathname);
    }

    const errorMap = {
        invalid_time: 'Invalid date or time format.',
        past_time: 'The proposed time is already in the past. Pick a future slot.',
        conflict: 'That time overlaps an existing meeting. Please pick another slot.',
        duplicate: 'You already requested a meeting at this exact time.',
        no_adviser: 'No adviser is assigned to your group yet.',
        invalid_agenda: 'Please include a brief agenda for the meeting.',
        failed: 'Could not save the meeting request. Please try again.',
        csrf: 'Your session expired. Please try again.'
    };
    const errParam = urlParams.get('error');
    if (errParam && errorMap[errParam]) {
        showToast(errorMap[errParam]);
        window.history.replaceState(null, null, window.location.pathname);
    }

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
</script>
<?php include('chat_widget.php'); ?>
</body>
</html>

