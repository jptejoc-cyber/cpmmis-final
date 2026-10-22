<?php
include('../backend/session_init.php');
include('../backend/db_config.php');
include('../backend/auth/csrf.php');

// Security Check: Only 'adviser' role allowed
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'adviser') {
    header("Location: ../index.php");
    exit();
}

$adviser_id = (int)$_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);

// Fetch Adviser Data for display
$adviser_q = mysqli_query($conn, "SELECT * FROM adviser WHERE id = $adviser_id");
$adviser_data = mysqli_fetch_assoc($adviser_q);

// FETCH UNREAD NOTIFICATIONS FOR THIS ADVISER
$unread_q = mysqli_query($conn, "SELECT COUNT(*) as unread FROM notifications WHERE user_id = $adviser_id AND user_role = 'adviser' AND is_read = 0");
$unread_count = mysqli_fetch_assoc($unread_q)['unread'] ?? 0;

// Fetch last 3 successful logins for setting drawer modal recovery matrix
$logins_q = mysqli_query($conn, "SELECT * FROM login_logs WHERE user_id = $adviser_id AND role = 'adviser' ORDER BY login_time DESC LIMIT 3");

/** * REFINED QUERY:
 * Pulls ONLY pending requests.
 */
$req_q = mysqli_query($conn, "SELECT r.*, g.username, g.project_title 
    FROM adviser_requests r 
    JOIN `group` g ON r.group_id = g.id 
    WHERE r.adviser_id = $adviser_id AND r.status = 'pending'
    ORDER BY r.created_at DESC");


?>
<!DOCTYPE html>
<html lang="en" style="background-color: #F0F2F5; color-scheme: light;">
<head>
    <meta charset="UTF-8">
    <meta name="color-scheme" content="light">
    <style>html, body { background-color: #F0F2F5 !important; color-scheme: light; }</style>
    <title>Group Requests | CPMMIS</title>
    <link rel="icon" type="image/png" href="../chmsu.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#F0F2F5">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
    </style>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
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
        .request-card { background: #ffffff; border: 1px solid #dadde1; border-radius: 2rem; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); }
        .request-card:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(0,0,0,0.12); border-color: rgba(24, 119, 242, 0.4); }

        #profile-content, #password-content, #logins-content { background: #ffffff; backdrop-filter: blur(40px); border: 1px solid #dadde1; color: #1c1e21; }
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
    </style>
</head>
<body class="min-h-screen flex" style="background-color: #F0F2F5; color-scheme: light;">

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
            <a href="adviser_dashboard.php" class="sidebar-link">
                <i class="fas fa-th-large w-5 text-center text-sm"></i>
                <span class="sidebar-text">Dashboard</span>
            </a>
            <a href="group_requests.php" class="sidebar-link active">
                <i class="fas fa-user-plus w-5 text-center text-sm"></i>
                <span class="sidebar-text">Group Requests</span>
            </a>
            <a href="archive_view.php" class="sidebar-link">
                <i class="fas fa-archive w-5 text-center text-sm"></i>
                <span class="sidebar-text">Capstone Archive</span>
            </a>
            <a href="groups.php" class="sidebar-link">
                <i class="fas fa-users w-5 text-center text-sm"></i>
                <span class="sidebar-text">Groups</span>
            </a>
            <a href="submissions.php" class="sidebar-link">
                <i class="fas fa-tasks w-5 text-center text-sm"></i>
                <span class="sidebar-text">Tasks & Submissions</span>
            </a>
            <a href="meetings.php" class="sidebar-link">
                <i class="fas fa-handshake w-5 text-center text-sm"></i>
                <span class="sidebar-text">Consultations</span>
            </a>
            
        </nav>
    </aside>

    <main class="flex-1 ml-0 md:ml-24 p-6 sm:p-8 lg:p-12 space-y-6 sm:space-y-10">
        
        <nav class="premium-card rounded-[2.5rem] px-4 sm:px-8 py-4 flex flex-wrap justify-between items-center gap-2 shadow-2xl relative z-[100]">
            <div class="min-w-0 flex-1">
                <h1 class="text-lg font-black text-slate-900 uppercase tracking-tight leading-none truncate">Mentorship Requests</h1>
                <p class="text-[8px] font-bold text-blue-400 uppercase tracking-widest block mt-2">Review and manage student applications</p>
            </div>
            
            <div class="flex items-center gap-4 shrink-0">
                <div class="relative nav-icon-container">
                    <button onclick="toggleAdviserChatWidget()" data-tip="Messages" class="icon-tooltip w-12 h-12 rounded-2xl bg-slate-100 border border-gray-200 flex items-center justify-center hover:bg-slate-100 transition relative shadow-sm">
                        <i class="fas fa-comment-dots text-blue-400"></i>
                    </button>
                </div>
                <div class="relative nav-icon-container">
                    <button onclick="handleNotifClick()" data-tip="Notifications" class="icon-tooltip w-12 h-12 rounded-2xl bg-slate-100 border border-gray-200 flex items-center justify-center hover:bg-slate-100 transition relative shadow-sm">
                        <i class="fas fa-bell text-blue-400"></i>
                        <?php if($unread_count > 0): ?>
                            <span id="notif-count" class="absolute -top-1 -right-1 min-w-[18px] h-[18px] bg-red-500 text-white text-[8px] font-black rounded-full border-4 border-white flex items-center justify-center"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </button>

                    <div id="notif-dropdown" class="hidden absolute right-0 mt-3 w-80 bg-white backdrop-blur-2xl rounded-[2.2rem] shadow-2xl border border-gray-200 z-[250] overflow-hidden animate-scale-in">
                        <div class="p-6 bg-slate-100 border-b border-gray-200 flex justify-between items-center"><h4 class="text-[10px] font-black uppercase text-slate-900 tracking-widest leading-none">Notifications</h4></div>
                        <div class="max-h-80 overflow-y-auto p-4 space-y-3 custom-scrollbar">
                            <?php
                            $notif_list = mysqli_query($conn, "SELECT n.* FROM notifications n WHERE n.user_id = $adviser_id AND n.user_role = 'adviser' ORDER BY n.created_at DESC LIMIT 15");
                            if(mysqli_num_rows($notif_list) > 0):
                                
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
                                    $is_unread = ($n['is_read'] == 0);
                                    if (!$is_unread) {
                                        $msg = preg_replace('/^New /i', '', $msg);
                                    }
                                    $raw_time = getRelativeTime($n['created_at']);
                                    $time_display = (strtolower($raw_time) === 'now' || strtolower($raw_time) === 'just now') ? "JUST NOW" : $raw_time . " ago";
                                    $link = getTargetPage($msg, $n['reference_id'] ?? 0);
                                    
                                    $action_label = "System Movement";
                                    $icon_markup = '<i class="fas fa-info-circle"></i>';
                                    $color = 'blue';
                                    $is_archive_alert = false;
                                    
                                    if (stripos($n['message'], 'registered') !== false || stripos($n['message'], 'group request') !== false || stripos($n['message'], 'advisership') !== false) {
                                        $action_label = "Group Advisership Request";
                                        $icon_markup = '<i class="fas fa-user-plus"></i>';
                                        $color = 'amber';
                                    } elseif (stripos($n['message'], 'submission') !== false || stripos($n['message'], 'submitted') !== false || stripos($n['message'], 'chapter') !== false) {
                                        $action_label = "Submission Status";
                                        $icon_markup = '<i class="fas fa-clipboard-list"></i>';
                                        $color = 'blue';
                                    } elseif (stripos($n['message'], 'meeting') !== false || stripos($n['message'], 'consultation') !== false) {
                                        $action_label = "Meeting Request";
                                        $icon_markup = '<i class="fas fa-handshake"></i>';
                                        $color = 'blue';
                                    } elseif (stripos($n['message'], 'archived') !== false || stripos($n['message'], 'archive') !== false) {
                                        $action_label = "Capstone Archive";
                                        $icon_markup = '<i class="fas fa-archive"></i>';
                                        $color = 'blue';
                                        $is_archive_alert = true;
                                    }

                                    $themes = [
                                        'blue' => ['text-blue-400', 'group-hover/item:text-blue-300', 'border-blue-500/30', 'bg-blue-500/5', 'bg-blue-500', 'bg-blue-500/10 text-blue-400'],
                                        'blue' => ['text-blue-400', 'group-hover/item:text-blue-300', 'border-blue-500/30', 'bg-blue-500/5', 'bg-blue-500', 'bg-blue-500/10 text-blue-400'],
                                        'rose' => ['text-rose-400', 'group-hover/item:text-rose-300', 'border-rose-500/30', 'bg-rose-500/5', 'bg-rose-500', 'bg-rose-500/10 text-rose-400'],
                                        'amber' => ['text-amber-400', 'group-hover/item:text-amber-300', 'border-amber-500/30', 'bg-amber-500/5', 'bg-amber-500', 'bg-amber-500/10 text-amber-400'],
                                        'blue' => ['text-blue-400', 'group-hover/item:text-blue-300', 'border-blue-500/30', 'bg-blue-500/5', 'bg-blue-500', 'bg-blue-500/10 text-blue-400'],
                                        'blue' => ['text-blue-400', 'group-hover/item:text-blue-300', 'border-blue-500/30', 'bg-blue-500/5', 'bg-blue-500', 'bg-blue-500/10 text-blue-400'],
                                        'blue' => ['text-blue-400', 'group-hover/item:text-blue-300', 'border-blue-500/30', 'bg-blue-500/5', 'bg-blue-500', 'bg-blue-500/10 text-blue-400'],
                                        'blue' => ['text-blue-400', 'group-hover/item:text-blue-300', 'border-blue-500/30', 'bg-blue-500/5', 'bg-blue-500', 'bg-blue-500/10 text-blue-400']
                                    ];
                                    list($un_title, $un_hover, $un_border, $un_bg, $un_badge, $icon_theme) = $themes[$color];

                                    // Dynamic Live Status Indicator Extraction Logic
                                    $status_badge = '';
                                    if (stripos($n['message'], 'registered') !== false || stripos($n['message'], 'advisership') !== false) {
                                        $msg_parts = explode(': ', $msg); $extracted_username = mysqli_real_escape_string($conn, trim(end($msg_parts)));
                                        $check_status_q = mysqli_query($conn, "SELECT ar.status FROM adviser_requests ar INNER JOIN `group` g ON ar.group_id = g.id WHERE g.username = '$extracted_username' AND ar.adviser_id = $adviser_id ORDER BY ar.created_at DESC LIMIT 1");
                                        $live_status = ($check_status_q && mysqli_num_rows($check_status_q) > 0) ? strtolower(mysqli_fetch_assoc($check_status_q)['status']) : 'pending';

                                        if ($live_status === 'active' || $live_status === 'approved' || $live_status === 'accepted') {
                                            $status_badge = '<span class="text-[7px] font-black text-blue-400 uppercase tracking-tight bg-blue-500/10 px-2 py-0.5 rounded-md">Approved</span>';
                                        } elseif ($live_status === 'declined' || $live_status === 'rejected') {
                                            $status_badge = '<span class="text-[7px] font-black text-rose-400 uppercase tracking-tight bg-rose-500/10 px-2 py-0.5 rounded-md">Declined</span>';
                                        } else {
                                            $status_badge = '<span class="text-[7px] font-black text-amber-400 uppercase tracking-tight bg-amber-500/10 px-2 py-0.5 rounded-md">Pending</span>';
                                        }
                                    }
                            ?>
                                <a href="<?php echo $link; ?>" class="p-3 rounded-2xl bg-slate-100 border <?php echo $is_unread ? "$un_border $un_bg" : 'border-gray-200'; ?> flex items-center gap-4 hover:bg-slate-100 block transition-all group/item">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-[10px] shrink-0 <?php echo $icon_theme; ?> transition-all shadow-sm">
                                        <?php echo $icon_markup; ?>
                                    </div>
                                    <div class="flex-1 text-left min-w-0">
                                        <div class="flex justify-between items-center w-full gap-2 mb-0.5">
                                            <p class="text-[10px] font-black <?php echo $is_unread ? "$un_title $un_hover" : 'text-slate-600 group-hover/item:text-blue-600'; ?> uppercase tracking-tight transition-colors truncate"><?php echo $action_label; ?></p>
                                            <div class="shrink-0 flex gap-2 items-center">
                                                <?php if(!empty($status_badge)): ?><?php echo $status_badge; ?><?php endif; ?>
                                                <?php if($is_unread): ?><span class="text-[7px] font-black text-white uppercase tracking-tight <?php echo $un_badge; ?> px-1.5 py-0.5 rounded-md animate-pulse shadow-md">NEW</span><?php endif; ?>
                                            </div>
                                        </div>
                                        <p class="text-[9px] font-bold text-slate-500 mt-0.5 uppercase tracking-tight line-clamp-2">
                                            <?php echo preg_replace('/^(NEW )/i', '<span class="text-blue-400">$1</span>', htmlspecialchars($msg)); ?>
                                        </p>
                                        <p class="text-[7px] text-slate-500 uppercase font-black mt-1.5 tracking-wider">
                                            <?php echo $time_display; ?>
                                        </p>
                                    </div>
                                </a>
                            <?php endforeach; endif; endforeach; else: ?>
                                <p class="text-center text-slate-500 text-[10px] py-10 font-black uppercase italic">No notifications detected</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="relative nav-icon-container">
                    <button onclick="toggleSettingsDropdown()" data-tip="Settings" class="icon-tooltip w-12 h-12 rounded-2xl bg-slate-100 border border-gray-200 flex items-center justify-center hover:bg-slate-100 transition relative shadow-sm">
                        <i class="fas fa-cog text-blue-400"></i>
                    </button>
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

        <section class="space-y-6">
            <?php if(mysqli_num_rows($req_q) > 0): while($req = mysqli_fetch_assoc($req_q)): ?>
                <div class="request-card p-6 border-l-4 border-l-amber-500 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6 shadow-2xl" id="row-<?php echo $req['id']; ?>">
                    <div class="flex-1 space-y-4 w-full">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="px-3 py-1 bg-amber-500/10 text-amber-400 text-[8px] font-black uppercase rounded-full border border-amber-500/20 tracking-wider">Pending Review</span>
                            <span class="text-[9px] font-extrabold text-slate-500 uppercase tracking-wider flex items-center gap-1.5"><i class="far fa-clock text-[10px]"></i> <?php echo date('M d, Y | g:i A', strtotime($req['created_at'])); ?></span>
                        </div>
                        
                        <div class="space-y-1">
                            <h2 class="text-base font-black text-slate-900 uppercase tracking-tight flex items-center gap-2">
                                <i class="fas fa-signature text-slate-500 text-xs"></i>
                                <?php echo htmlspecialchars($req['username']); ?>
                            </h2>
                            <p class="text-[11px] font-bold text-blue-400 uppercase tracking-wide flex items-center gap-2">
                                <i class="fas fa-folder-open text-blue-500 text-xs"></i>
                                Project: <?php echo htmlspecialchars($req['project_title']); ?>
                            </p>
                        </div>
                        
                        <div class="p-4 bg-slate-100 rounded-2xl border border-gray-200 shadow-inner">
                            <p class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Mentorship Request Letter:</p>
                            <a href="../<?php echo htmlspecialchars($req['message']); ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-500/10 text-blue-400 border border-blue-500/20 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-blue-500/20 transition-all">
                                <i class="fas fa-file-pdf text-sm"></i> View PDF Letter
                            </a>
                        </div>
                    </div>

                    <div class="flex flex-row lg:flex-col gap-2.5 w-full lg:w-44 shrink-0 pt-2 lg:pt-0">
                        <button onclick="handleDecision(<?php echo $req['id']; ?>, 'accept')" class="flex-1 py-3 bg-blue-500 text-white rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-blue-600 transition duration-200 active:scale-95 flex items-center justify-center gap-2 shadow-lg shadow-blue-500/10">
                            <i class="fas fa-check text-[10px]"></i> Accept Request
                        </button>
                        <button onclick="openDeclineModal(<?php echo $req['id']; ?>, this)" class="flex-1 py-3 bg-slate-100 text-rose-400 border border-gray-200 rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-rose-950/30 transition duration-200 active:scale-95 flex items-center justify-center gap-2">
                            <i class="fas fa-times text-[10px]"></i> Decline Group
                        </button>
                    </div>
                </div>
            <?php endwhile; else: ?>
                <div class="text-center py-20 bg-slate-100 rounded-[2rem] border border-dashed border-gray-200 shadow-2xl">
                    <div class="w-16 h-16 bg-slate-100 text-slate-500 rounded-full border border-gray-200 flex items-center justify-center text-2xl mx-auto mb-4"><i class="fas fa-inbox"></i></div>
                    <p class="text-slate-500 font-black uppercase tracking-widest text-[10px]">No pending mentorship requests at the moment</p>
                </div>
            <?php endif; ?>
        </section>
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

    <div id="decline-modal" class="fixed inset-0 bg-black/80 backdrop-blur-md hidden z-[500] items-center justify-center p-6">
        <div id="decline-content" class="bg-white w-full max-w-sm rounded-[2.5rem] shadow-2xl border border-gray-200 overflow-hidden transform transition-all scale-95 opacity-0 p-8 space-y-6">
            <div class="text-center space-y-2">
                <div class="w-14 h-14 bg-rose-500/10 text-rose-500 border border-rose-500/20 rounded-xl flex items-center justify-center text-xl mx-auto"><i class="fas fa-comment-slash"></i></div>
                <h3 class="text-lg font-black uppercase text-slate-900 tracking-tight">Decline Request</h3>
                <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">Provide a brief reason for the student group</p>
            </div>
            <textarea id="decline_reason" class="w-full p-4 bg-white border border-gray-200 rounded-2xl outline-none focus:border-rose-500 transition text-xs min-h-[100px] font-bold text-slate-600 uppercase" placeholder="E.g., SLOT ALREADY FILLED / CAPACITY EXCEEDED..."></textarea>
            <div class="flex gap-3">
                <button onclick="closeDeclineModal()" class="flex-1 py-3.5 bg-slate-100 text-slate-500 hover:bg-slate-100 rounded-xl text-[9px] font-black uppercase tracking-widest transition">Cancel</button>
                <button onclick="submitDecline()" class="flex-[2] py-3.5 bg-rose-600 text-white rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-rose-700 transition active:scale-95 shadow-lg shadow-rose-600/10">Confirm Decline</button>
            </div>
        </div>
    </div>

    <div id="logins-modal" class="fixed inset-0 bg-black/80 backdrop-blur-xl hidden z-[500] items-center justify-center p-6">
        <div id="logins-content" class="bg-gradient-to-b from-white to-slate-100 w-full max-w-md rounded-[2.5rem] border border-gray-200 shadow-2xl transform scale-95 opacity-0 overflow-hidden">
            <div class="p-8 border-b bg-slate-100 border-gray-200 flex justify-between items-center text-slate-900 font-black uppercase">
                <h3>Login Activity</h3>
                <button onclick="closeLoginsModal()" class="text-slate-500 hover:text-red-400 transition">X</button>
            </div>
            <div class="p-8 space-y-4 max-h-[400px] overflow-y-auto custom-scrollbar">
                <?php if ($logins_q && mysqli_num_rows($logins_q) > 0): ?>
                    <?php while($log = mysqli_fetch_assoc($logins_q)): ?>
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

    <div id="profile-modal" class="fixed inset-0 bg-black/80 backdrop-blur-xl hidden z-[500] items-center justify-center p-6">
        <div id="profile-content" class="bg-gradient-to-b from-white to-slate-100 w-full max-w-md rounded-[3rem] border border-gray-200 shadow-2xl overflow-hidden transform transition-all scale-95 opacity-0">
            <div class="h-32 bg-blue-600/20 relative"><button onclick="closeProfileModal()" class="absolute top-6 right-6 w-8 h-8 rounded-full bg-slate-100 text-slate-900 hover:bg-slate-100 transition"><i class="fas fa-times text-xs"></i></button></div>
            <div class="px-8 pb-10 -mt-12 relative text-center">
                <div class="w-24 h-24 bg-white rounded-[2rem] border-4 border-gray-200 shadow-xl mx-auto flex items-center justify-center text-3xl text-blue-400 mb-4"><i class="fas fa-user-shield"></i></div>
                <h3 class="text-xl font-black text-slate-900 uppercase tracking-tight"><?php echo htmlspecialchars($adviser_data['full_name'] ?? 'Faculty Mentor'); ?></h3>
                <p class="text-[9px] font-black text-blue-400 uppercase tracking-widest mb-8">Faculty Adviser Profile</p>
                <div class="space-y-3 text-left">
                    <div class="p-5 rounded-3xl bg-slate-100 border border-gray-200 flex items-center justify-between"><div><p class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Authenticated Username</p><p class="text-sm font-bold text-slate-600">@<?php echo htmlspecialchars($adviser_data['username']); ?></p></div><i class="fas fa-id-card text-slate-700"></i></div>
                    <div class="p-5 rounded-3xl bg-slate-100 border border-gray-200 flex items-center justify-between"><div><p class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Primary Email Address</p><p class="text-sm font-bold text-slate-600"><?php echo htmlspecialchars($adviser_data['email']); ?></p></div><i class="fas fa-envelope text-slate-700"></i></div>
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

        function handleNotifClick() {
            if(document.getElementById('settings-popover')) document.getElementById('settings-popover').classList.add('hidden');
            const dropdown = document.getElementById('notif-dropdown');
            dropdown.classList.toggle('hidden');
            const count = document.getElementById('notif-count');
            if (!dropdown.classList.contains('hidden') && count) {
                count.style.display = 'none';
                fetch('../backend/notifications/mark_read.php', { method: 'POST', body: JSON.stringify({ user_id: <?php echo $adviser_id; ?>, user_role: 'adviser', csrf_token: '<?php echo generate_csrf_token(); ?>' }), headers: { 'Content-Type': 'application/json' } });
            }
        }

        function toggleSettingsDropdown() { 
            if(document.getElementById('notif-dropdown')) document.getElementById('notif-dropdown').classList.add('hidden');
            document.getElementById('settings-popover').classList.toggle('hidden'); 
        }
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

        let currentRequestId = null;

        function handleDecision(id, action) {
            if(action === 'accept') {
                showCustomConfirm('Accept Mentorship', 'Are you sure you want to accept mentorship for this group?', 'success', 'ACCEPT', () => {
                    processAction(id, 'accept', '');
                });
            }
        }

        function openDeclineModal(id) {
            currentRequestId = id;
            animateIn('decline-modal', 'decline-content');
        }

        function closeDeclineModal() {
            const modal = document.getElementById('decline-modal');
            const content = document.getElementById('decline-content');
            content.classList.add('scale-95', 'opacity-0');
            setTimeout(() => { modal.classList.replace('flex', 'hidden'); }, 200);
        }

        function submitDecline() {
            const reason = document.getElementById('decline_reason').value;
            if(!reason.trim()) { alert("Please enter a clear decline reason."); return; }
            processAction(currentRequestId, 'decline', reason);
        }

        function processAction(id, action, reason) {
            const formData = new FormData();
            formData.append('request_id', id);
            formData.append('action', action);
            formData.append('reason', reason);
            formData.append('csrf_token', CSRF_TOKEN);

            fetch('../backend/advisers/process_adviser_request.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    showToast(action === 'accept' ? "Success: Mentorship request accepted!" : "Mentorship request declined successfully.", "success");
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast("System Notice: " + data.message, "error");
                }
            })
            .catch(err => showToast("System Error: Could not execute decision operation.", "error"));
        }

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
            fetch('../backend/auth/update_adviser_pass.php', { method: 'POST', body: formData })
            .then(res => res.text()).then(data => {
                const response = data.trim();
                if(response === 'success') { closePasswordModal(); showToast("Password successfully changed"); passForm.reset(); passError.classList.add('hidden'); }
                else if(response === 'incorrect_current') { passError.innerText = "Current password you entered is incorrect"; passError.classList.remove('hidden'); }
                else { passError.innerText = response; passError.classList.remove('hidden'); }
            });
        };

        function togglePass(id, btn) { const input = document.getElementById(id); const icon = btn.querySelector('i'); if (input.type === "password") { input.type = "text"; icon.classList.replace('fa-eye', 'fa-eye-slash'); } else { input.type = "password"; icon.classList.replace('fa-eye-slash', 'fa-eye'); } }
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebar-backdrop').classList.toggle('open');
        }
        window.onclick = function(e) { if (!e.target.closest('.nav-icon-container') && !e.target.closest('#settings-drawer') && !e.target.closest('#decline-content') && !e.target.closest('#notif-dropdown')) { if(document.getElementById('settings-popover')) document.getElementById('settings-popover').classList.add('hidden'); if(document.getElementById('notif-dropdown')) document.getElementById('notif-dropdown').classList.add('hidden'); } }
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
