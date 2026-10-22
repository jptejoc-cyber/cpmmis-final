<?php
include('../backend/session_init.php');
include('../backend/db_config.php');
include('../backend/auth/csrf.php');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$admin_id = (int)$_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);

// CLEAR REGISTRATION NOTIFICATIONS (AJAX)
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

// FETCH REGISTRATION NOTIFICATIONS WITH LIVE GROUP STATUS
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

// REAL-TIME UNREAD COUNT FOR THE BADGE
$unread_count_q = mysqli_query($conn, "SELECT COUNT(*) as total FROM notifications n
                                      WHERE n.message LIKE '%registered%'
                                      AND n.is_read = 0
                                      AND (
                                          n.message NOT LIKE '%:%'
                                          OR SUBSTRING_INDEX(n.message, ': ', -1) IN (SELECT username FROM `group`)
                                      )");
$unread_count = mysqli_fetch_assoc($unread_count_q)['total'] ?? 0;

// Fetch Admin Data
$stmt = $conn->prepare("SELECT * FROM admin WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin_data = $stmt->get_result()->fetch_assoc();

// Fetch last 5 successful logins
$stmt = $conn->prepare("SELECT * FROM login_logs WHERE user_id = ? AND role = 'admin' ORDER BY login_time DESC LIMIT 5");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$logins_q = $stmt->get_result();

// Fetch all posted letters with group info
$letters_q = mysqli_query($conn, "SELECT al.*, g.username as group_name FROM admin_letters al JOIN `group` g ON al.group_id = g.id ORDER BY al.created_at DESC LIMIT 200");

// Fetch all active groups for letter upload
$groups_q = mysqli_query($conn, "SELECT g.*, u.full_name as adviser_name FROM `group` g LEFT JOIN adviser u ON g.adviser_id = u.id WHERE g.status = 'active' ORDER BY g.username ASC");


// Handling session messages for toast
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
    <title>Submissions & Letters | Admin</title>
    <link rel="icon" type="image/png" href="../chmsu.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        
        :root {
            --chmsu-blue: #1877F2;
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
            <a href="submissions.php" class="sidebar-link <?php echo ($current_page == 'submissions.php') ? 'active' : ''; ?> active">
                <i class="fas fa-tasks w-5 text-center text-sm"></i>
                <span class="sidebar-text">Submissions & Letters</span>
            </a>
            
            <div class="pt-4 mt-4 border-t border-gray-200 space-y-2">
                <a href="reports.php" class="sidebar-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
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

    <main class="flex-1 ml-0 md:ml-24 p-6 sm:p-8 lg:p-12 space-y-6 sm:space-y-10 relative z-10">
        
        <nav class="premium-card rounded-[2.5rem] px-4 sm:px-8 py-4 flex flex-wrap justify-between items-center gap-2 shadow-2xl relative z-[100]">
            <div class="min-w-0 flex-1">
                <span class="block font-black uppercase text-lg text-slate-900 tracking-tight truncate">Submissions & Letters</span>
                <span class="text-[9px] font-bold text-blue-400 uppercase tracking-widest">Letter Management & Documentation</span>
            </div>
            
            <div class="flex items-center gap-4 shrink-0">
                <button onclick="openLetterModal()" class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-500 text-white rounded-2xl text-[10px] font-black uppercase tracking-wider hover:brightness-110 transition shadow-lg">
                    <i class="fas fa-plus mr-2"></i> Post Letter
                </button>
                <div class="h-6 w-[1px] bg-slate-100 mx-1"></div>
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
                                $target_url = "submissions.php";
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
                                        <p class="text-[9px] font-bold text-slate-500 mt-0.5 uppercase line-clamp-2">
                                            <?php echo preg_replace('/^(NEW )/i', '<span class="text-blue-400">$1</span>', htmlspecialchars($msg)); ?>
                                        </p>
                                        <p class="text-[7px] text-slate-500 uppercase font-black mt-1.5 tracking-wider"><?php echo $time_display; ?></p>
                                    </div>
                                </a>
                            <?php endforeach; endif; endforeach; else: ?><p class="text-center py-10 text-slate-500 text-[10px] font-bold uppercase italic">No new activity</p><?php endif; ?>
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

        <section class="premium-card rounded-[3rem] shadow-2xl border border-gray-200 overflow-hidden">
            <div class="px-10 py-8 border-b border-gray-200 bg-slate-100 flex items-center">
                <i class="fas fa-file-pen text-blue-400 mr-3 text-sm"></i>
                <h3 class="text-sm font-black uppercase tracking-widest text-slate-900">Posted Letters & Documents</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-100 text-[9px] font-black uppercase tracking-widest text-slate-500 border-b border-gray-200">
                        <tr>
                            <th class="px-4 sm:px-10 py-4 sm:py-6">Letter Details</th>
                            <th class="px-4 sm:px-10 py-4 sm:py-6">Group</th>
                            <th class="px-4 sm:px-10 py-4 sm:py-6">File</th>
                            <th class="px-4 sm:px-10 py-4 sm:py-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-[11px] font-bold text-slate-600">
                        <?php if($letters_q && mysqli_num_rows($letters_q) > 0): while($row = mysqli_fetch_assoc($letters_q)): ?>
                            <tr class="hover:bg-slate-100 transition-all duration-300" id="row-<?php echo $row['id']; ?>">
                                <td class="px-4 sm:px-10 py-4 sm:py-6">
                                    <p class="font-extrabold text-slate-900 uppercase text-sm leading-tight mb-1"><?php echo htmlspecialchars($row['title']); ?></p>
                                    <p class="text-[8px] font-bold text-slate-500 uppercase tracking-widest">
                                        <i class="far fa-calendar mr-1"></i> <?php echo date('M d, Y g:i A', strtotime($row['created_at'])); ?>
                                    </p>
                                </td>
                                <td class="px-4 sm:px-10 py-4 sm:py-6">
                                    <span class="text-slate-900 font-extrabold uppercase"><?php echo htmlspecialchars($row['group_name']); ?></span>
                                </td>
                                <td class="px-4 sm:px-10 py-4 sm:py-6 text-[11px] font-bold text-slate-500 uppercase max-w-xs truncate">
                                    <a href="../backend/admin/download_letter.php?id=<?php echo $row['id']; ?>" target="_blank" class="text-blue-400 hover:text-blue-300 transition underline underline-offset-2">
                                        <i class="fas fa-paperclip mr-1"></i> <?php echo htmlspecialchars($row['file_name']); ?>
                                    </a>
                                </td>
                                <td class="px-4 sm:px-10 py-4 sm:py-6 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="../backend/admin/download_letter.php?id=<?php echo $row['id']; ?>" class="w-9 h-9 bg-slate-100 border border-gray-200 text-blue-400 rounded-xl hover:bg-blue-500 hover:text-white flex items-center justify-center text-xs transition shadow-sm" data-tip="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr>
                                <td colspan="4" class="px-4 sm:px-10 py-4 sm:py-20 text-center text-slate-500 font-black text-[10px] uppercase italic tracking-widest">
                                    No letters have been posted yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- Upload Letter Modal -->
    <div id="letter-modal" class="fixed inset-0 bg-black/80 backdrop-blur-xl hidden z-[500] items-center justify-center p-6">
        <div id="letter-content" class="bg-gradient-to-b from-white to-slate-100 w-full max-w-lg max-h-[90vh] overflow-y-auto custom-scrollbar rounded-[2.5rem] border border-gray-200 shadow-2xl transform transition-all scale-95 opacity-0">
            <div class="p-8 border-b border-gray-200 bg-slate-100 flex justify-between items-center sticky top-0 z-10 backdrop-blur-md">
                <h3 class="text-lg font-black uppercase text-slate-900 tracking-tight leading-none">Post New Letter</h3>
                <button onclick="closeLetterModal()" class="text-slate-500 hover:text-red-400 transition-all"><i class="fas fa-times"></i></button>
            </div>
            <form id="letterForm" enctype="multipart/form-data" class="p-8 space-y-5">
                <div class="space-y-2">
                    <label class="text-[9px] font-black uppercase text-slate-500 ml-2 tracking-widest">Recipient Group</label>
                    <select name="group_id" id="letter_group_id" class="w-full px-6 py-4 bg-white border-2 border-gray-200 text-slate-900 rounded-2xl outline-none focus:border-blue-500 transition font-bold text-sm appearance-none" onchange="document.getElementById('letter-group-error').classList.add('hidden'); this.classList.remove('border-rose-500')">
                        <option value="">Select a group...</option>
                        <?php if ($groups_q && mysqli_num_rows($groups_q) > 0): mysqli_data_seek($groups_q, 0); while($g = mysqli_fetch_assoc($groups_q)): ?>
                            <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['username']); ?> (<?php echo htmlspecialchars($g['adviser_name'] ?? 'Unassigned'); ?>)</option>
                        <?php endwhile; endif; ?>
                    </select>
                    <p id="letter-group-error" class="hidden text-[9px] font-black text-rose-500 uppercase ml-2 mt-1 tracking-widest"><i class="fas fa-exclamation-circle mr-1"></i>Select a recipient group or check "Post to All Groups"</p>
                </div>
                <div class="flex items-center gap-3">
                    <input type="checkbox" name="broadcast_all" id="broadcast_all" value="1" class="w-5 h-5 rounded-lg bg-white border-2 border-gray-200 text-blue-500 focus:ring-blue-500 focus:ring-2 outline-none cursor-pointer accent-blue-500" onchange="document.getElementById('letter_group_id').disabled = this.checked; if(this.checked) document.getElementById('letter_group_id').value = ''; document.getElementById('letter-group-error').classList.add('hidden'); document.getElementById('letter_group_id').classList.remove('border-rose-500')">
                    <label for="broadcast_all" class="text-[10px] font-black uppercase text-slate-500 tracking-widest cursor-pointer select-none">Post to All Groups</label>
                </div>

                <div class="space-y-2">
                    <label class="text-[9px] font-black uppercase text-slate-500 ml-2 tracking-widest">Letter Title</label>
                    <input type="text" name="letter_title" id="letter_title" class="w-full px-6 py-4 bg-white border-2 border-gray-200 text-slate-900 rounded-2xl outline-none focus:border-blue-500 transition font-bold text-sm" placeholder="e.g., Approval Letter, Completion Certificate" oninput="document.getElementById('letter-title-error').classList.add('hidden'); this.classList.remove('border-rose-500')">
                    <p id="letter-title-error" class="hidden text-[9px] font-black text-rose-500 uppercase ml-2 mt-1 tracking-widest"><i class="fas fa-exclamation-circle mr-1"></i>Letter title is required</p>
                </div>

                <div class="space-y-2">
                    <label class="text-[9px] font-black uppercase text-slate-500 ml-2 tracking-widest">Document (PDF only)</label>
                    <div class="relative w-full h-24 border-2 border-dashed border-gray-200 rounded-2xl bg-slate-200 hover:bg-slate-100 hover:border-blue-500/50 transition-all flex flex-col items-center justify-center group/file cursor-pointer" onclick="document.getElementById('letter_file').click()">
                        <i class="fas fa-cloud-upload-alt text-2xl text-slate-500 group-hover/file:text-blue-400 mb-2 transition-colors"></i>
                        <span id="file-name" class="text-[10px] font-black uppercase text-slate-500 tracking-widest">Browse PDF File</span>
                        <input type="file" name="letter_file" id="letter_file" accept=".pdf" class="hidden" onchange="document.getElementById('file-name').innerText = this.files[0] ? this.files[0].name : 'Browse PDF File'; document.getElementById('letter-file-error').classList.add('hidden'); this.closest('.border-dashed').classList.remove('border-rose-500', 'bg-rose-500/5')">
                    </div>
                    <p id="letter-file-error" class="hidden text-[9px] font-black text-rose-500 uppercase ml-2 mt-1 tracking-widest"><i class="fas fa-exclamation-circle mr-1"></i>Please attach a PDF document</p>
                    <p class="text-[8px] text-slate-500 mt-2">Supported: PDF only (Max 10MB)</p>
                </div>

                <div class="space-y-2">
                    <label class="text-[9px] font-black uppercase text-slate-500 ml-2 tracking-widest">Description (Optional)</label>
                    <textarea name="letter_description" class="w-full px-6 py-4 bg-white border-2 border-gray-200 text-slate-900 rounded-2xl outline-none focus:border-blue-500 transition font-bold text-sm" rows="3" placeholder="Add any notes or details..."></textarea>
                </div>

                <button type="submit" class="w-full py-4 bg-gradient-to-r from-blue-600 to-blue-500 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-xl hover:brightness-110 transition-all active:scale-95">Post Letter & Notify Group</button>
            </form>
        </div>
    </div>

    <!-- Settings Drawer -->
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
            if(document.getElementById('notif-popover')) document.getElementById('notif-popover').classList.add('hidden');
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

        function openLetterModal() {
            animateIn('letter-modal', 'letter-content');
        }

        function closeLetterModal() {
            document.getElementById('letter-modal').classList.replace('flex', 'hidden');
        }

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast-container');
            const toastBox = document.getElementById('toast-box');
            const toastIconBg = document.getElementById('toast-icon-bg');
            const toastIcon = document.getElementById('toast-icon');
            
            document.getElementById('toast-message').innerText = message;
            
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

        document.getElementById('letterForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();

            const fileInput = document.getElementById('letter_file');
            const fileError = document.getElementById('letter-file-error');
            const dropzone = fileInput.closest('.border-dashed');

            const broadcastChecked = document.getElementById('broadcast_all').checked;
            const groupSelect = document.getElementById('letter_group_id');
            const groupError = document.getElementById('letter-group-error');

            if (!broadcastChecked && !groupSelect.value) {
                groupError.classList.remove('hidden');
                groupSelect.classList.add('border-rose-500');
                showToast('Please select a recipient group or check "Post to All Groups"', 'error');
                return;
            }
            groupError.classList.add('hidden');
            groupSelect.classList.remove('border-rose-500');

            const titleInput = document.getElementById('letter_title');
            const titleError = document.getElementById('letter-title-error');

            if (!titleInput.value.trim()) {
                titleError.classList.remove('hidden');
                titleInput.classList.add('border-rose-500');
                showToast('Please enter a letter title', 'error');
                return;
            }
            titleError.classList.add('hidden');
            titleInput.classList.remove('border-rose-500');

            if (!fileInput.files || fileInput.files.length === 0) {
                fileError.classList.remove('hidden');
                if (dropzone) {
                    dropzone.classList.add('border-rose-500', 'bg-rose-500/5');
                    setTimeout(() => dropzone.classList.remove('border-rose-500', 'bg-rose-500/5'), 3000);
                }
                showToast('Please attach a PDF document before posting the letter', 'error');
                return;
            }
            fileError.classList.add('hidden');
            if (dropzone) dropzone.classList.remove('border-rose-500', 'bg-rose-500/5');

            const formData = new FormData(e.target);
            formData.append('csrf_token', CSRF_TOKEN);
            
            try {
                const response = await fetch('../backend/admin/upload_letter.php', {
                    method: 'POST',
                    body: formData
                });
                
                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    console.error('Raw response:', text);
                    showToast('Server error: ' + text.substring(0, 100), 'error');
                    return;
                }
                if (result.success) {
                    showToast('Letter posted and group notified!');
                    closeLetterModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(result.message || 'Upload failed', 'error');
                }
            } catch (error) {
                console.error(error);
                showToast('Error uploading letter', 'error');
            }
        });

        const CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';
        const passForm = document.getElementById('changePasswordForm');
        const passError = document.getElementById('password-error');
        if(passForm) {
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

        function togglePass(id, btn) { const input = document.getElementById(id); const icon = btn.querySelector('i'); if (input.type === "password") { input.type = "text"; icon.classList.replace('fa-eye', 'fa-eye-slash'); } else { input.type = "password"; icon.classList.replace('fa-eye-slash', 'fa-eye'); } }

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
