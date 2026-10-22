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
$group_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// --- FIXED: INLINE NOTIFICATION UPDATER STATE TRIGGER ---
if (isset($_GET['action']) && $_GET['action'] === 'clear_reg_notifications') {
    // Require a valid CSRF token to prevent cross-site notification clearing.
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

// Fetch Admin Record
$admin_data_q = mysqli_query($conn, "SELECT * FROM admin WHERE id = " . (int)$admin_id);
$admin_data = mysqli_fetch_assoc($admin_data_q);

// Fetch Targeted Research Group Metadata
$group_q = mysqli_query($conn, "SELECT g.*, u.full_name as adviser_name 
                                FROM `group` g 
                                LEFT JOIN adviser u ON g.adviser_id = u.id 
                                WHERE g.id = " . (int)$group_id);
$group = mysqli_fetch_assoc($group_q);

if (!$group) { die("System Error: Research Group not found."); }


// --- EXACT CONTEXT MATCH: DYNAMIC AUTO-VANISH NOTIFICATIONS ENGINE ---
$notif_query = "SELECT n.* FROM notifications n
                WHERE n.message LIKE '%registered%'
                AND (
                    n.message NOT LIKE '%:%' 
                    OR SUBSTRING_INDEX(n.message, ': ', -1) IN (SELECT username FROM `group`)
                )
                ORDER BY n.id DESC LIMIT 10";
$notif_res = mysqli_query($conn, $notif_query);

// REAL-TIME BASELINE READ RECOVERY CONTEXT MATCH EXTRACTION MAP WITH THE SAME FILTERS
$unread_count_q = mysqli_query($conn, "SELECT COUNT(*) as total FROM notifications n
                                      WHERE n.message LIKE '%registered%'
                                      AND n.is_read = 0
                                      AND (
                                          n.message NOT LIKE '%:%' 
                                          OR SUBSTRING_INDEX(n.message, ': ', -1) IN (SELECT username FROM `group`)
                                      )");
$unread_count = mysqli_fetch_assoc($unread_count_q)['total'] ?? 0;

// Milestone checklist logic maps
$chapters = [1 => 'Pending', 2 => 'Pending', 3 => 'Pending', 4 => 'Pending', 5 => 'Pending'];
$approved_files = []; // stores file_path per chapter for final project download
$sub_q = mysqli_query($conn, "SELECT chapter_number, file_path FROM group_submissions WHERE group_id = " . (int)$group_id . " AND status = 'approved'");
while ($row = mysqli_fetch_assoc($sub_q)) {
    $ch = $row['chapter_number'];
    if (isset($chapters[$ch])) { 
        $chapters[$ch] = 'Completed';
        $approved_files[$ch] = $row['file_path'];
    }
}
$completed_count = count(array_filter($chapters, fn($s) => $s === 'Completed'));
$percent = $completed_count * 20;
$all_chapters_complete = ($completed_count === 5); // Final Project unlocks when all 5 chapters approved

// Compute Health Status dynamically (mirrors adviser_dashboard.php)
$health_status = 'on_track';
$tasks_q = mysqli_query($conn, "SELECT adviser_id FROM `group` WHERE id = " . (int)$group_id);
$group_adviser = mysqli_fetch_assoc($tasks_q);
if ($group_adviser && $group_adviser['adviser_id']) {
    $adviser_id = $group_adviser['adviser_id'];
    $tasks_q = mysqli_query($conn, "SELECT id, deadline FROM adviser_tasks WHERE adviser_id = " . (int)$adviser_id . " AND task_type = 'chapter'");
    if ($tasks_q && mysqli_num_rows($tasks_q) > 0) {
        $now = time();
        while ($t = mysqli_fetch_assoc($tasks_q)) {
            $task_id = $t['id'];
            if (empty($t['deadline'])) continue;
            
            $deadline_time = strtotime($t['deadline']);
            $diff_days = ($deadline_time - $now) / 86400;

            $sub_q = mysqli_query($conn, "SELECT id FROM group_submissions WHERE task_id = " . (int)$task_id . " AND group_id = " . (int)$group_id);
            $has_submitted = ($sub_q && mysqli_num_rows($sub_q) > 0);

            if (!$has_submitted) {
                if ($diff_days < 0) {
                    $health_status = 'at_risk';
                    break; // Worst status wins
                } elseif ($diff_days <= 3) {
                    if ($health_status !== 'at_risk') {
                        $health_status = 'needs_attention';
                    }
                }
            }
        }
    }
}

// A group with ALL FIVE chapters (1-5) approved is fully complete, overriding task health.
$fin_q = mysqli_query($conn, "SELECT COUNT(DISTINCT chapter_number) AS c FROM group_submissions WHERE group_id = " . (int)$group_id . " AND status = 'approved' AND chapter_number BETWEEN 1 AND 5");
$fin_row = $fin_q ? mysqli_fetch_assoc($fin_q) : null;
if ($fin_row && (int)$fin_row['c'] >= 5) {
    $health_status = 'completed';
}

$submissions_result = mysqli_query($conn, "SELECT * FROM group_submissions WHERE group_id = " . (int)$group_id . " ORDER BY submitted_at DESC LIMIT 500");
$meetings_result = mysqli_query($conn, "SELECT * FROM meeting_requests WHERE group_id = " . (int)$group_id . " ORDER BY created_at DESC LIMIT 500");
$logins_q = mysqli_query($conn, "SELECT * FROM login_logs WHERE user_id = " . (int)$admin_id . " AND role = 'admin' ORDER BY login_time DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($group['username']); ?> | Admin Monitoring</title>
    <link rel="icon" type="image/png" href="../chmsu.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        .brand-text-container { max-width: 0; opacity: 0; overflow: hidden; white-space: nowrap; transition: all 0.3s ease; }
        aside:hover .brand-text-container { max-width: 200px; opacity: 1; margin-left: 1rem; }
        .brand-text { font-size: 1.25rem; font-weight: 800; color: white; letter-spacing: -0.025em; }

        .sidebar-link { display: flex; align-items: center; gap: 1.25rem; padding: 1rem 1.45rem; border-radius: 1rem; transition: background-color 0.3s ease, box-shadow 0.3s ease; color: #65676b; width: 100%; justify-content: flex-start; }
        .sidebar-text { opacity: 0; display: none; font-weight: 800; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; }
        aside:hover .sidebar-text { opacity: 1; display: inline; }
        .sidebar-link:hover { background-color: #f0f2f5; color: #1c1e21; }
        .sidebar-link.active { background: var(--chmsu-blue) !important; color: white !important; box-shadow: 0 10px 15px -3px rgba(24, 119, 242, 0.25); }

        .premium-card { background: #ffffff; border: 1px solid #dadde1; }
        .stat-card { transition: all 0.3s ease; border: 1px solid #dadde1; background: #ffffff; box-shadow: 0 1px 2px rgba(0,0,0,0.06); }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(0,0,0,0.12); border-color: rgba(24, 119, 242, 0.4); }

        .milestone-item {
            background: #ffffff;
            border: 1px solid #e4e6eb;
            border-radius: 1.25rem;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
        }
        .milestone-item:hover {
            border-color: rgba(24, 119, 242, 0.2);
            background: #e4e6eb;
        }
        .milestone-number {
            width: 32px;
            height: 32px;
            background: #dadde1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 11px;
            color: #64748b;
            margin-right: 1rem;
        }
        .milestone-item.completed .milestone-number {
            background: var(--chmsu-blue);
            color: white;
            box-shadow: 0 0 15px rgba(24, 119, 242, 0.4);
        }

        #profile-content, #password-content, #logins-content { background: #ffffff; backdrop-filter: blur(40px); border: 1px solid #dadde1; color: #1c1e21; }
        #settings-drawer { transform: translateX(100%); transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); background: #ffffff; backdrop-filter: blur(40px); border-left: 1px solid #dadde1; }
        #settings-drawer.open { transform: translateX(0); }
        #security-submenu { max-height: 0; overflow: hidden; transition: max-height 0.4s ease; }
        #security-submenu.open { max-height: 400px; }

        .icon-tooltip { position: relative; }
        .icon-tooltip:hover::after { content: attr(data-tip); position: absolute; top: 110%; left: 50%; transform: translateX(-50%); background: #1e293b; color: white; padding: 6px 12px; border-radius: 8px; font-size: 9px; font-weight: 800; text-transform: uppercase; z-index: 1000; display: block; }
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
        
        th { font-size: 9px !important; letter-spacing: 0.1em; }
        td { padding: 1rem 0.75rem !important; }
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
            <p id="toast-message" class="text-slate-900 font-black uppercase tracking-widest text-[10px]">Action Successful</p>
        </div>
    </div>

    

    <main class="flex-1 w-full max-w-7xl mx-auto p-6 sm:p-8 lg:p-12 space-y-6 sm:space-y-10">
        
        <nav class="premium-card rounded-[2.5rem] px-4 sm:px-8 py-4 flex flex-wrap justify-between items-center shadow-2xl relative z-[100] gap-2">
            <div class="min-w-0 flex-1">
                <a href="admin_dashboard.php" class="text-[9px] font-black text-blue-400 uppercase tracking-widest hover:text-blue-600 transition block mb-1 truncate">
                    <i class="fas fa-arrow-left mr-1"></i> BACK TO ADMIN DASHBOARD
                </a>
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-lg sm:text-xl font-black text-slate-900 uppercase tracking-tight truncate"><?php echo htmlspecialchars($group['username']); ?></h1>
                    <?php
                        $hs_label = 'On Track';
                        $hs_icon  = 'fa-check-circle';
                        $hs_color = 'blue';
                        if ($health_status === 'completed') {
                            $hs_label = 'Completed'; $hs_icon = 'fa-check-double'; $hs_color = 'blue';
                        } elseif ($health_status === 'need_attention' || $health_status === 'needs_attention' || $health_status === 'need attention') {
                            $hs_label = 'Need Attention'; $hs_icon = 'fa-exclamation-triangle'; $hs_color = 'amber';
                        } elseif ($health_status === 'at_risk' || $health_status === 'at risk') {
                            $hs_label = 'At Risk'; $hs_icon = 'fa-times-circle'; $hs_color = 'rose';
                        }
                    ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[8px] font-black uppercase tracking-widest bg-<?php echo $hs_color; ?>-500/10 text-<?php echo $hs_color; ?>-400 border border-<?php echo $hs_color; ?>-500/20" id="health-badge">
                        <i class="fas <?php echo $hs_icon; ?> text-[8px]"></i>
                        <?php echo $hs_label; ?>
                    </span>
                </div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider line-clamp-1 max-w-xl truncate"><?php echo htmlspecialchars($group['project_title'] ?: 'UNTITLED RESEARCH SPACE'); ?></p>
            </div>
            
            <div class="flex items-center gap-4 shrink-0">
                <div class="bg-slate-100 rounded-2xl px-5 py-2.5 flex items-center shadow-sm border border-gray-200 gap-4">
                    <div class="text-right">
                        <p class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-0.5">COMPLETION RATE</p>
                        <p class="text-lg font-black text-slate-900 leading-none"><?php echo $percent; ?>%</p>
                    </div>
                    <div class="w-10 h-10 bg-blue-500/10 text-blue-400 border border-blue-500/20 rounded-xl flex items-center justify-center text-xs font-black">
                        <?php echo $completed_count; ?>/5
                    </div>
                </div>

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
                                $target_url = "groups.php?status=pending";
                                $time_display = getRelativeTime($n['created_at']);
                            ?>
                                <a href="<?php echo $target_url; ?>" class="p-3 rounded-2xl bg-slate-100 border border-gray-200 flex items-center gap-4 hover:bg-slate-100 block transition-all group/item">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-[10px] bg-blue-500/10 text-blue-400 transition-all"><i class="fas fa-user-plus"></i></div>
                                    <div class="flex-1 text-left">
                                        <div class="flex justify-between items-center w-full">
                                            <p class="text-[10px] font-black text-slate-900 uppercase tracking-tight group-hover/item:text-blue-400 transition-colors">Registration Alert</p>
                                            <span class="text-[8px] font-black text-amber-400 uppercase tracking-tight bg-amber-500/10 px-2 py-0.5 rounded-md transition-colors"><?php echo $time_display; ?></span>
                                        </div>
                                        <p class="text-[9px] font-bold text-slate-500 mt-0.5 uppercase">
                                            <?php echo htmlspecialchars($n['message']); ?>
                                        </p>
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

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <div class="lg:col-span-4 premium-card p-8 rounded-[2.5rem] shadow-xl border border-gray-200">
                <h4 class="text-[10px] font-black uppercase text-slate-500 tracking-widest mb-6">Milestone Checklist</h4>
                
                <div class="space-y-3">
                    <?php foreach ($chapters as $num => $status): ?>
                    <div class="milestone-item <?php echo $status === 'Completed' ? 'completed' : ''; ?>">
                        <div class="flex items-center">
                            <div class="milestone-number"><?php echo $num; ?></div>
                            <span class="text-[10px] font-black uppercase tracking-widest <?php echo $status === 'Completed' ? 'text-slate-900' : 'text-slate-500'; ?>">
                                CHAPTER <?php echo $num; ?>
                            </span>
                        </div>
                        <div>
                            <?php if ($status === 'Completed'): ?>
                                <i class="fas fa-check-circle text-blue-400 text-base"></i>
                            <?php else: ?>
                                <i class="fa-regular fa-circle text-slate-800 text-base"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <!-- FINAL PROJECT MILESTONE -->
                    <div class="milestone-item <?php echo $all_chapters_complete ? 'completed' : ''; ?> mt-6 border-t border-gray-200 pt-6" style="background: <?php echo $all_chapters_complete ? 'rgba(24,119,242,0.05)' : '#ffffff'; ?>; border-color: <?php echo $all_chapters_complete ? 'rgba(24,119,242,0.2)' : '#e4e6eb'; ?>;">
                        <div class="flex items-center">
                            <div class="milestone-number shadow-inner border border-gray-200" style="background: <?php echo $all_chapters_complete ? 'linear-gradient(135deg,#1877F2,#3b82f6)' : ''; ?>; color: <?php echo $all_chapters_complete ? 'white' : ''; ?>; box-shadow: <?php echo $all_chapters_complete ? '0 0 20px rgba(24,119,242,0.5)' : ''; ?>;">
                                <i class="fas fa-graduation-cap text-[9px]"></i>
                            </div>
                            <div>
                                <span class="text-[10px] font-black uppercase tracking-widest <?php echo $all_chapters_complete ? 'text-slate-900' : 'text-slate-500'; ?>">
                                    Final Project
                                </span>
                                <p class="text-[8px] font-bold text-slate-500 uppercase tracking-wider"><?php echo $all_chapters_complete ? 'All Chapters Combined' : 'Unlocks when Ch.1-5 done'; ?></p>
                            </div>
                        </div>
                        <?php if ($all_chapters_complete): ?>
                            <div class="flex items-center gap-2">
                                <a href="../backend/advisers/download_final_project.php?group_id=<?php echo $group_id; ?>" 
                                   class="w-8 h-8 inline-flex items-center justify-center bg-blue-500/10 border border-blue-500/20 text-blue-400 hover:bg-blue-500 hover:text-white rounded-xl transition shadow-sm" 
                                   title="Download Final Project (All Chapters)">
                                    <i class="fas fa-download text-xs"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            <i class="fas fa-lock text-slate-700 text-sm"></i>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-8 space-y-8">
                
                <div class="premium-card rounded-[2.5rem] shadow-xl overflow-hidden border border-gray-200">
                    <div class="p-6 border-b border-gray-200 flex items-center bg-slate-100">
                        <i class="fas fa-file-medical text-blue-400 mr-3 text-xs"></i>
                        <h4 class="text-[10px] font-black uppercase text-slate-600 tracking-widest">Submission History</h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-100 text-[9px] font-black uppercase tracking-widest text-slate-500 border-b border-gray-200">
                                <tr>
                                    <th class="px-6 py-4">CHAPTER</th>
                                    <th class="px-6 py-4">DATE</th>
                                    <th class="px-6 py-4">STATUS</th>
                                    <th class="px-6 py-4 text-right">REVIEW</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-[11px] font-bold text-slate-600">
                                <?php if (mysqli_num_rows($submissions_result) > 0): ?>
                                    <?php while($sub = mysqli_fetch_assoc($submissions_result)): ?>
                                    <tr class="hover:bg-slate-100 transition" id="row-<?php echo $sub['id']; ?>">
                                        <td class="px-6 py-4 uppercase text-slate-900 font-extrabold">Chapter <?php echo $sub['chapter_number']; ?></td>
                                        <td class="px-6 py-4 text-slate-500 font-medium"><?php echo date('M d, Y', strtotime($sub['submitted_at'])); ?></td>
                                        <td class="px-6 py-4">
                                            <span class="px-3 py-1 rounded-full text-[8px] font-black uppercase <?php echo $sub['status'] === 'approved' ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' : ($sub['status'] === 'rejected' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20'); ?>">
                                                <?php echo $sub['status']; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <a href="../backend/serve_file.php?type=submissions&file=<?php echo urlencode($sub['file_path']); ?>" target="_blank" class="w-8 h-8 inline-flex items-center justify-center bg-slate-100 border border-gray-200 text-blue-400 hover:bg-blue-600 hover:text-white rounded-xl transition shadow-sm" title="View Document">
                                                <i class="fas fa-eye text-xs"></i>
                                            </a>
                                            <a href="../backend/serve_file.php?type=submissions&file=<?php echo urlencode($sub['file_path']); ?>" download class="w-8 h-8 inline-flex items-center justify-center bg-slate-100 border border-gray-200 text-blue-400 hover:bg-blue-600 hover:text-white rounded-xl transition shadow-sm ml-1" title="Download Document">
                                                <i class="fas fa-download text-xs"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="p-12 text-center text-slate-500 font-black uppercase tracking-widest italic text-[10px]">
                                            No manuscripts submitted yet
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="premium-card rounded-[2.5rem] shadow-xl overflow-hidden border border-gray-200">
                    <div class="p-6 border-b border-gray-200 flex items-center bg-slate-100">
                        <i class="fas fa-handshake text-blue-400 mr-3 text-xs"></i>
                        <h4 class="text-[10px] font-black uppercase text-slate-600 tracking-widest">Meeting History</h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-100 text-[9px] font-black uppercase tracking-widest text-slate-500 border-b border-gray-200">
                                <tr>
                                    <th class="px-6 py-4">PURPOSE</th>
                                    <th class="px-6 py-4">DATE</th>
                                    <th class="px-6 py-4">OUTCOME</th>
                                    <th class="px-6 py-4 text-right">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-[11px] font-bold text-slate-600">
                                <?php if (mysqli_num_rows($meetings_result) > 0): ?>
                                    <?php while($meet = mysqli_fetch_assoc($meetings_result)): ?>
                                    <tr class="hover:bg-slate-100 transition" id="row-<?php echo $meet['id']; ?>">
                                        <td class="px-6 py-4">
                                            <p class="font-extrabold text-slate-900 uppercase text-[11px] leading-tight break-words max-w-[220px]">
                                                <?php echo htmlspecialchars($meet['agenda'] ?: 'CONSULTATION'); ?>
                                            </p>
                                        </td>
                                        <td class="px-6 py-4 text-slate-500 font-medium"><?php echo date('M d, Y', strtotime($meet['created_at'])); ?></td>
                                        <td class="px-6 py-4">
                                            <?php 
                                                $status = strtolower($meet['status']);
                                                if ($status === 'accepted' || $status === 'approved') {
                                                    $pill_class = 'bg-blue-500/10 text-blue-400 border border-blue-500/20';
                                                } elseif ($status === 'declined' || $status === 'rejected') {
                                                    $pill_class = 'bg-rose-500/10 text-rose-400 border border-rose-500/20';
                                                } else {
                                                    $pill_class = 'bg-amber-500/10 text-amber-400 border border-amber-500/20';
                                                }
                                            ?>
                                            <span class="px-3 py-1 rounded-full text-[8px] font-black uppercase <?php echo $pill_class; ?>">
                                                <?php echo htmlspecialchars($meet['status'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <?php if ($status === 'declined' || $status === 'rejected'): ?>
                                                <button onclick="openReasonModal('<?php echo htmlspecialchars(addslashes($meet['admin_remarks'] ?: 'No reason provided')); ?>')" class="px-3 py-1 bg-slate-100 border border-gray-200 text-rose-400 hover:bg-rose-600 hover:text-white rounded-full text-[8px] font-black uppercase tracking-wider transition shadow-sm">
                                                    View Reason
                                                </button>
                                            <?php else: ?>
                                                <i class="fas fa-ellipsis-h text-slate-600 cursor-pointer hover:text-blue-600 transition"></i>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="p-12 text-center text-slate-500 font-black uppercase tracking-widest italic text-[10px]">
                                            No meetings recorded
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <div id="drawer-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-[300]" onclick="closeSettingsDrawer()"></div>
    <div id="settings-drawer" class="fixed top-0 right-0 h-full w-80 z-[301] shadow-2xl flex flex-col transform translate-x-full transition-transform duration-300">
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
                <div class="flex items-center gap-4"><div class="w-10 h-10 rounded-xl bg-slate-200 text-blue-400 flex items-center justify-center shadow-sm group-hover:bg-blue-600 group-hover:text-white transition-all"><i class="fas fa-user-circle"></i></div><div><p class="text-[11px] font-black uppercase text-slate-700 leading-none">Profile & Details</p><p class="text-[9px] font-bold text-slate-500 uppercase mt-1">Master Information</p></div></div><i class="fas fa-chevron-right text-slate-500 text-xs"></i>
            </button>
        </div>
    </div>

    <div id="profile-modal" class="fixed inset-0 bg-black/80 backdrop-blur-xl hidden z-[500] items-center justify-center p-6">
        <div id="profile-content" class="bg-gradient-to-b from-white to-slate-100 w-full max-w-md rounded-[3rem] border border-gray-200 shadow-2xl overflow-hidden transform transition-all scale-95 opacity-0">
            <div class="h-32 bg-blue-600/20 relative"><button onclick="closeProfileModal()" class="absolute top-6 right-6 w-8 h-8 rounded-full bg-slate-100 text-slate-900 hover:bg-slate-100 transition"><i class="fas fa-times text-xs"></i></button></div>
            <div class="px-8 pb-10 -mt-12 relative text-center">
                <div class="w-24 h-24 bg-white rounded-[2rem] border-4 border-gray-200 shadow-xl mx-auto flex items-center justify-center text-3xl text-blue-400 mb-4"><i class="fas fa-user-shield"></i></div>
                <h3 class="text-xl font-black text-slate-900 uppercase tracking-tight"><?php echo htmlspecialchars($admin_data['full_name']); ?></h3>
                <p class="text-[9px] font-black text-blue-400 uppercase tracking-widest mb-8">Administrator Profile</p>
                <div class="space-y-3 text-left">
                    <div class="p-5 rounded-3xl bg-slate-100 border border-gray-200 flex items-center justify-between"><div><p class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Authenticated Username</p><p class="text-sm font-bold text-slate-600">@<?php echo htmlspecialchars($admin_data['username']); ?></p></div><i class="fas fa-id-card text-slate-700"></i></div>
                    <div class="p-5 rounded-3xl bg-slate-100 border border-gray-200 flex items-center justify-between"><div><p class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Primary Email Address</p><p class="text-sm font-bold text-slate-600"><?php echo htmlspecialchars($admin_data['email']); ?></p></div><i class="fas fa-envelope text-slate-700"></i></div>
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
            <div class="p-8 border-b bg-slate-100 border-gray-200 flex justify-between items-center text-slate-900 font-black uppercase">
                <h3>Login Activity</h3>
                <button onclick="closeLoginsModal()" class="text-slate-500 hover:text-red-400 transition"><i class="fas fa-times"></i></button>
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

    <!-- Reason Modal -->
    <div id="reason-modal" class="fixed inset-0 bg-black/80 backdrop-blur-xl hidden z-[500] items-center justify-center p-6">
        <div id="reason-content" class="bg-gradient-to-b from-white to-slate-100 w-full max-w-md rounded-[2.5rem] border border-gray-200 shadow-2xl transform scale-95 opacity-0 overflow-hidden">
            <div class="p-8 border-b bg-slate-100 border-gray-200 flex justify-between items-center text-slate-900 font-black uppercase">
                <h3 class="flex items-center gap-2"><i class="fas fa-info-circle text-rose-500"></i> Decline Reason</h3>
                <button onclick="closeReasonModal()" class="text-slate-500 hover:text-red-400 transition"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-8">
                <p id="reason-text" class="text-sm font-medium text-slate-600 leading-relaxed"></p>
            </div>
        </div>
    </div>

    <script>
        function handleNotifClick() { 
            if(document.getElementById('settings-popover')) document.getElementById('settings-popover').classList.add('hidden');
            document.getElementById('notif-popover').classList.toggle('hidden'); 
            const badge = document.getElementById('notif-badge'); 
            if(badge) {
                badge.style.display = 'none'; 
                fetch('?action=clear_reg_notifications&csrf_token=' + encodeURIComponent(CSRF_TOKEN))
                .then(res => res.json())
                .catch(err => console.error("Notification sync error:", err));
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
        
        function openReasonModal(reason) {
            document.getElementById('reason-text').innerText = reason;
            animateIn('reason-modal', 'reason-content');
        }
        function closeReasonModal() {
            document.getElementById('reason-modal').classList.replace('flex', 'hidden');
        }

        function togglePass(id, btn) { const input = document.getElementById(id); const icon = btn.querySelector('i'); if (input.type === "password") { input.type = "text"; icon.classList.replace('fa-eye', 'fa-eye-slash'); } else { input.type = "password"; icon.classList.replace('fa-eye-slash', 'fa-eye'); } }
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebar-backdrop').classList.toggle('open');
        }

        // Health status update function
        function updateHealthStatus() {
            const group_id = new URLSearchParams(window.location.search).get('id');
            fetch(`../backend/advisers/calculate_health_status.php?group_id=${group_id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status && data.health_status) {
                        const badge = document.getElementById('health-badge');
                        if (badge) {
                            const status = data.health_status;
                            let label = 'On Track', icon = 'fa-check-circle', color = 'blue';
                            
                            if (status === 'completed') {
                                label = 'Completed'; icon = 'fa-check-double'; color = 'blue';
                            } else if (status === 'needs_attention') {
                                label = 'Need Attention'; icon = 'fa-exclamation-triangle'; color = 'amber';
                            } else if (status === 'at_risk') {
                                label = 'At Risk'; icon = 'fa-times-circle'; color = 'rose';
                            }
                            
                            badge.innerHTML = `<i class="fas ${icon} text-[8px]"></i> ${label}`;
                            badge.className = `inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[8px] font-black uppercase tracking-widest bg-${color}-500/10 text-${color}-400 border border-${color}-500/20`;
                        }
                    }
                })
                .catch(err => console.error("Health status update error:", err));
        }

        // Auto-update health status every 10 seconds
        setInterval(updateHealthStatus, 10000);

        const CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';
        const passForm = document.getElementById('changePasswordForm');
        const passError = document.getElementById('password-error');
        if (passForm) {
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
        }

        function showToast(msg) {
            const toast = document.getElementById('toast-container');
            if(toast) {
                document.getElementById('toast-message').innerText = msg;
                toast.classList.remove('-translate-y-20', 'opacity-0'); toast.classList.add('translate-y-0', 'opacity-100');
                setTimeout(() => { toast.classList.add('-translate-y-20', 'opacity-0'); toast.classList.remove('translate-y-0', 'opacity-100'); }, 3000);
            }
        }

        window.onclick = function(e) { if (!e.target.closest('.nav-icon-container') && !e.target.closest('#settings-drawer') && !e.target.closest('#notif-popover')) { if(document.getElementById('settings-popover')) document.getElementById('settings-popover').classList.add('hidden'); if(document.getElementById('notif-popover')) document.getElementById('notif-popover').classList.add('hidden'); } }
    </script>
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
    const archiveStatus = urlParams.get('archive');
    const archiveError = urlParams.get('error');
    if (archiveStatus === 'success') {
        showToast("Project archived to the Capstone Vault");
    } else if (archiveStatus || archiveError) {
        const reason = archiveError === 'duplicate' ? "A project with this title is already archived."
                       : archiveError === 'not_complete' ? "All 5 chapters must be approved before archiving."
                       : archiveError === 'zip' ? "Could not generate the final project file."
                       : archiveError === 'copy' ? "Could not move the file into the vault."
                       : "Archive failed. Please try again.";
        showToast(reason);
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