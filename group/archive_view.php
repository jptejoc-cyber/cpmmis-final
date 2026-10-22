<?php
ob_start();
include('../backend/session_init.php');
include('../backend/db_config.php');
include('../backend/auth/csrf.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'group') {
    header("Location: ../index.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

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
    echo json_encode(['status' => 'success']);
    ob_end_flush();
    exit();
}

function render_archive_rows($conn, $search, $year, $adviser, $tab) {
    $allowed = ['all', 'best', 'regular'];
    if (!in_array($tab, $allowed)) $tab = 'all';

    $sql = "SELECT id, title, adviser, year, category, file_path FROM capstone_archive WHERE 1=1";
    $params = [];
    $types = '';
    if (!empty($search)) { $sql .= " AND title LIKE CONCAT(?, '%')"; $params[] = $search; $types .= 's'; }
    if (!empty($year)) { $sql .= " AND year = ?"; $params[] = $year; $types .= 's'; }
    if (!empty($adviser)) { $sql .= " AND adviser = ?"; $params[] = $adviser; $types .= 's'; }
    if ($tab === 'best') $sql .= " AND category = 'best'";
    if ($tab === 'regular') $sql .= " AND category = 'regular'";
    $sql .= " ORDER BY id DESC LIMIT 500";

    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = mysqli_query($conn, $sql);
    }

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $cat_html_class = ($row['category'] === 'best')
                ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20'
                : 'bg-blue-500/10 text-blue-400 border border-blue-500/20';
            $cat_html_label = ($row['category'] === 'best')
                ? '🏆 Best Project'
                : 'Standard Project';

            $row_json = htmlspecialchars(json_encode([
                'title' => $row['title'],
                'adviser' => $row['adviser'],
                'year' => $row['year'],
                'category' => $row['category'],
                'abstract' => $row['abstract'] ?? '',
                'file_path' => $row['file_path']
            ], JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');

            $fn = basename($row['file_path']);
            $url = htmlspecialchars('../backend/serve_file.php?type=archive&file=' . rawurlencode($fn), ENT_QUOTES, 'UTF-8');
            $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
            $ext_js = htmlspecialchars(addslashes($ext), ENT_QUOTES, 'UTF-8');
            $t_html = htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8');
            $t_js = htmlspecialchars(addslashes($row['title']), ENT_QUOTES, 'UTF-8');

            echo "<tr id='row-{$row['id']}' class='hover:bg-white/[0.02] transition-all group'>
                    <td class='px-10 py-6'>
                        <span class='font-extrabold text-slate-900 uppercase text-xs block mb-1 group-hover:text-blue-400 transition-colors cursor-pointer' onclick=\"viewCapstone($row_json)\">$t_html</span>
                    </td>
                    <td class='px-10 py-6 text-center text-xs font-bold text-slate-600 uppercase'>".htmlspecialchars($row['adviser'] ?? 'NONE ASSIGNED', ENT_QUOTES, 'UTF-8')."</td>
                    <td class='px-10 py-6 text-center text-xs font-bold text-slate-500'>".htmlspecialchars($row['year'], ENT_QUOTES, 'UTF-8')."</td>
                    <td class='px-10 py-6 text-center'>
                        <span class='px-3 py-1.5 inline-block rounded-xl text-[8px] font-black uppercase tracking-wider $cat_html_class'>$cat_html_label</span>
                    </td>
                    <td class='px-10 py-6 text-center'>
                        <div class='inline-flex items-center justify-center gap-3'>
                            <button onclick=\"openFileViewer('$url', '$t_js', '$ext_js')\" class='icon-tooltip w-10 h-10 inline-flex items-center justify-center bg-slate-100 border border-gray-200 text-blue-400 hover:bg-blue-600 hover:text-white rounded-xl transition shadow-sm' data-tip='VIEW REPOSITORY FILE'>
                                <i class='fas fa-eye text-xs'></i>
                            </button>
                            <a href='$url' download class='w-10 h-10 inline-flex items-center justify-center bg-slate-100 border border-gray-200 text-blue-400 hover:bg-blue-600 hover:text-white rounded-xl transition shadow-sm' title='Download File'>
                                <i class='fas fa-download text-xs'></i>
                            </a>
                        </div>
                    </td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='5' class='px-10 py-20 text-center text-slate-500 uppercase text-[10px] font-black italic tracking-widest'>No matching records identified in repository.</td></tr>";
    }
}

if (isset($_GET['ajax_search'])) {
    $y = trim($_GET['year'] ?? '');
    $a = trim($_GET['adviser'] ?? '');
    render_archive_rows(
        $conn,
        trim($_GET['search'] ?? ''),
        ($y === 'all' || $y === '') ? '' : $y,
        ($a === 'all' || $a === '') ? '' : $a,
        trim($_GET['tab'] ?? 'all')
    );
    ob_end_flush();
    exit();
}

$grp_stmt = $conn->prepare("SELECT g.*, a.full_name as adviser_name, a.email as adviser_email FROM `group` g LEFT JOIN adviser a ON g.adviser_id = a.id WHERE g.id = ? LIMIT 1");
$grp_stmt->bind_param('i', $user_id);
$grp_stmt->execute();
$group_data = $grp_stmt->get_result()->fetch_assoc();
$grp_stmt->close();

$group_name = $group_data['username'] ?? 'Research Group';
$group_status = strtolower($group_data['status'] ?? 'pending');
$project_title = $group_data['project_title'] ?? 'Untitled Capstone Project';
$group_email = $group_data['leader_email'] ?? 'No email registered';

if ($group_status === 'pending') {
    header("Location: pending_screen.php");
    exit();
}



$metrics_stmt = $conn->prepare("SELECT (SELECT COUNT(*) FROM capstone_archive) as total, (SELECT COUNT(*) FROM capstone_archive WHERE category='best') as best_count, (SELECT COUNT(*) FROM capstone_archive WHERE category='regular') as standard_count");
$metrics_stmt->execute();
$m = $metrics_stmt->get_result()->fetch_assoc();
$metrics_stmt->close();
$total_archived = $m['total'] ?? 0;
$best_count = $m['best_count'] ?? 0;
$standard_count = $m['standard_count'] ?? 0;

$years_query = mysqli_query($conn, "SELECT DISTINCT year FROM capstone_archive ORDER BY year DESC");
$adv_q = mysqli_query($conn, "SELECT DISTINCT adviser FROM capstone_archive WHERE adviser IS NOT NULL AND adviser != '' ORDER BY adviser ASC");
$registered_advisers = [];
while ($a = mysqli_fetch_assoc($adv_q)) { $registered_advisers[] = $a['adviser']; }

$logins_q = mysqli_query($conn, "SELECT * FROM login_logs WHERE user_id = " . $user_id . " AND role = 'group' ORDER BY login_time DESC LIMIT 5");

$unread_stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE is_read = 0 AND ((user_id = ? AND user_role = 'group') OR (user_role = 'group' AND message LIKE '%registered%' AND message LIKE '%adviser%' AND SUBSTRING_INDEX(message, ': ', -1) IN (SELECT full_name FROM adviser)))");
$unread_stmt->bind_param('i', $user_id);
$unread_stmt->execute();
$unread_count = $unread_stmt->get_result()->fetch_assoc()['unread'] ?? 0;
$unread_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="color-scheme" content="light">
    <title>Archive Vault | CPMMIS</title>
    <link rel="icon" type="image/png" href="../chmsu.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>html,body{background-color:#F0F2F5;color-scheme:light}body{color:#1c1e21;overflow-x:hidden}</style>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        :root { --chmsu-blue: #1877F2; --chmsu-orange: #4267B2; --bg-deep: #F0F2F5; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-deep); background-image: none; background-size: 32px 32px; color: #1c1e21; overflow-x: hidden; }
        .ambient-glow { position: fixed; top: -10%; right: -10%; width: 600px; height: 600px; background: radial-gradient(circle, rgba(24,119,242,0.06) 0%, transparent 70%); z-index: -1; filter: blur(80px); pointer-events: none; }
        aside { width: 5.5rem; transition: width 0.3s cubic-bezier(0.4,0,0.2,1); background: #ffffff; backdrop-filter: blur(40px) saturate(180%); border-right: 1px solid #dadde1; height: 100vh; position: fixed; z-index: 150; display: flex; flex-direction: column; padding: 1.25rem 0; overflow: hidden; }
        aside:hover { width: 17rem; background: #ffffff; border-right-color: #dadde1; }
        .logo-section { display: flex; flex-direction: row; align-items: center; padding: 1.5rem 0; margin-bottom: 2.5rem; transition: all 0.3s ease; width: 100%; justify-content: flex-start; padding-left: 1.35rem; }
        .brand-text-container { max-width: 0; opacity: 0; overflow: hidden; white-space: nowrap; transition: all 0.3s ease; width: 100%; }
        aside:hover .brand-text-container { max-width: 200px; opacity: 1; margin-left: 1rem; }
        .brand-text { font-size: 1.25rem; font-weight: 800; color: #1877F2; letter-spacing: -0.025em; width: 100%; }
        .sidebar-link { display: flex; align-items: center; gap: 1.25rem; padding: 1rem 1.45rem; border-radius: 1rem; transition: background-color 0.3s ease, box-shadow 0.3s ease; color: #65676b; width: 100%; justify-content: flex-start; }
        .sidebar-text { opacity: 0; display: none; font-weight: 800; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; }
        aside:hover .sidebar-text { opacity: 1; display: inline; }
        .sidebar-link:hover { background-color: #f0f2f5; color: #1c1e21; }
        .sidebar-link.active { background: var(--chmsu-blue) !important; color: white !important; box-shadow: 0 10px 15px -3px rgba(24,119,242,0.25); }
        .premium-card { background: #ffffff; backdrop-filter: blur(40px) saturate(180%); border: 1px solid #dadde1; }
        .stat-card { border: 1px solid #e4e6eb; background: #ffffff; backdrop-filter: blur(20px); }
        #settings-drawer { transform: translateX(100%); transition: transform 0.4s cubic-bezier(0.4,0,0.2,1); background: #ffffff; backdrop-filter: blur(40px); border-left: 1px solid #dadde1; }
        #settings-drawer.open { transform: translateX(0); }
        .icon-tooltip { position: relative; }
        .icon-tooltip:hover::after { content: attr(data-tip); position: absolute; top: 110%; left: 50%; transform: translateX(-50%); background: #1e293b; color: white; padding: 6px 12px; border-radius: 8px; font-size: 9px; font-weight: 800; text-transform: uppercase; white-space: nowrap; z-index: 1000; display: block; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #d3d7de; border-radius: 10px; }
        @keyframes scale-in { from { opacity: 0; transform: scale(0.95) translateY(-10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .animate-scale-in { animation: scale-in 0.2s ease-out; }
        .sidebar-toggle-btn { display: none; position: fixed; top: 0.75rem; left: 0.75rem; z-index: 160; width: 2.5rem; height: 2.5rem; border-radius: 0.75rem; background: #ffffff; border: 1px solid #dadde1; color: #94a3b8; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease; }
        .sidebar-toggle-btn:hover { background: #f0f2f5; color: #1877F2; }
        @media (max-width: 767px) {
            .sidebar-toggle-btn { display: flex; }
            aside.sidebar { transform: translateX(-100%); width: 17rem !important; background: #ffffff !important; z-index: 155 !important; transition: transform 0.3s cubic-bezier(0.4,0,0.2,1) !important; border-right-color: #e4e6eb !important; }
            aside.sidebar:hover { width: 17rem !important; }
            aside.sidebar.open { transform: translateX(0); }
            aside.sidebar .brand-text-container { max-width: 200px !important; opacity: 1 !important; margin-left: 1rem !important; }
            aside.sidebar .sidebar-text { opacity: 1 !important; display: inline !important; }
            .sidebar-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 145; }
            .sidebar-backdrop.open { display: block; }
        }
    </style>
</head>
<?php ob_end_flush(); ?>
<body class="min-h-screen flex" style="background-color:#F0F2F5">

    <div class="ambient-glow"></div>
    <button class="sidebar-toggle-btn" id="sidebar-toggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">
        <i class="fas fa-bars text-sm"></i>
    </button>
    <div class="sidebar-backdrop" id="sidebar-backdrop" onclick="toggleSidebar()"></div>

    <div id="toast-container" style="display:none" class="fixed top-5 left-1/2 -translate-x-1/2 z-[999] transform -translate-y-20 transition-all duration-500 opacity-0 pointer-events-none">
        <div class="bg-white text-slate-800 px-8 py-4 rounded-2xl shadow-xl flex items-center gap-4 border border-blue-500/30">
            <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center"><i id="toast-icon" class="fas fa-check text-xs text-white"></i></div>
            <p id="toast-message" class="text-slate-900 font-black uppercase tracking-widest text-[10px]">Matrix System Status Refined</p>
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
            <a href="group_dashboard.php" class="sidebar-link"><i class="fas fa-th-large w-5 text-center text-sm"></i><span class="sidebar-text">Dashboard</span></a>
            <a href="archive_view.php" class="sidebar-link active"><i class="fas fa-archive w-5 text-center text-sm"></i><span class="sidebar-text">Capstone Archive</span></a>
            <a href="advisers.php" class="sidebar-link"><i class="fas fa-user-tie w-5 text-center text-sm"></i><span class="sidebar-text">Adviser</span></a>
            <a href="submissions.php" class="sidebar-link"><i class="fas fa-file-upload w-5 text-center text-sm"></i><span class="sidebar-text">Tasks & Submissions</span></a>
            <a href="letters.php" class="sidebar-link"><i class="fas fa-envelope w-5 text-center text-sm"></i><span class="sidebar-text">Letters</span></a>
            <a href="meetings.php" class="sidebar-link"><i class="fas fa-handshake w-5 text-center text-sm"></i><span class="sidebar-text">Consultation</span></a>
        </nav>
    </aside>

    <main class="flex-1 ml-0 md:ml-24 p-8 lg:p-12 space-y-10">
        <nav class="premium-card rounded-[2.5rem] px-4 sm:px-8 py-4 flex flex-wrap justify-between items-center gap-2 shadow-2xl relative z-[100]">
            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-black text-blue-400 uppercase tracking-[0.2em] mb-1">Authenticated Group Workspace</p>
                <h2 class="text-lg sm:text-xl font-black text-slate-900 uppercase tracking-tight truncate">Archive Repository</h2>
            </div>
            <div class="flex items-center gap-3 relative z-[250] shrink-0">
                <div class="relative nav-icon-container">
                    <button onclick="toggleGroupChatWidget()" data-tip="Messages" class="icon-tooltip w-12 h-12 rounded-2xl bg-slate-100 border border-gray-200 flex items-center justify-center hover:bg-slate-100 transition relative shadow-sm"><i class="fas fa-comment-dots text-blue-400"></i></button>
                </div>
                <div class="relative nav-icon-container">
                    <button onclick="handleNotifClick()" data-tip="Notifications" class="icon-tooltip w-12 h-12 rounded-2xl bg-slate-100 border border-gray-200 flex items-center justify-center hover:bg-slate-100 transition relative shadow-sm">
                        <i class="fas fa-bell text-blue-400"></i>
                        <?php if ($unread_count > 0): ?><span id="notif-badge" class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[9px] font-black rounded-full border-4 border-white flex items-center justify-center"><?php echo $unread_count; ?></span><?php endif; ?>
                    </button>
                    <div id="notif-popover" class="hidden absolute right-0 mt-3 w-80 bg-white backdrop-blur-2xl rounded-[2.2rem] shadow-2xl border border-gray-200 z-[300] overflow-hidden animate-scale-in">
                        <div class="p-6 bg-slate-100 border-b border-gray-200 flex justify-between items-center"><h4 class="text-[10px] font-black uppercase text-slate-900 tracking-widest leading-none">Notifications</h4></div>
                        <div class="max-h-80 overflow-y-auto p-4 space-y-3 custom-scrollbar">
                            <?php
                            $notif_q = mysqli_query($conn, "SELECT * FROM notifications WHERE (user_id = " . $user_id . " AND user_role = 'group') OR (user_role = 'group' AND message LIKE '%registered%' AND message LIKE '%adviser%' AND SUBSTRING_INDEX(message, ': ', -1) IN (SELECT full_name FROM adviser)) ORDER BY id DESC LIMIT 10");
                            if ($notif_q && mysqli_num_rows($notif_q) > 0):
                                $all_n = [];
                                while ($rw = mysqli_fetch_assoc($notif_q)) { $all_n[] = $rw; }
                                $new_n = array_filter($all_n, function($n) { return $n['is_read'] == 0; });
                                $old_n = array_filter($all_n, function($n) { return $n['is_read'] == 1; });
                                $groups = [['label'=>'New','items'=>$new_n,'color'=>'blue'],['label'=>'Recent','items'=>$old_n,'color'=>'slate']];
                                foreach ($groups as $g):
                                    if (count($g['items']) > 0):
                            ?>
                            <div class="px-2 py-1 mt-1 mb-2"><p class="text-[9px] font-black uppercase text-<?php echo $g['color']; ?>-400 tracking-widest"><?php echo $g['label']; ?></p></div>
                            <?php foreach ($g['items'] as $n):
                                $msg = $n['message'];
                                if ($n['is_read'] == 1) $msg = preg_replace('/^New /i', '', $msg);
                                $time_display = getRelativeTime($n['created_at']);
                                $is_unread = $n['is_read'] == 0;
                                $target_url = "submissions.php";
                                $is_archive_alert = false;
                                $status_badge = '';
                                $color = 'blue';
                                $action_label = "System Message";
                                $icon_markup = '<i class="fas fa-envelope-open-text"></i>';

                                if (stripos($n['message'], 'registered') !== false && stripos($n['message'], 'adviser') !== false) {
                                    $action_label = "Adviser Registered"; $icon_markup = '<i class="fas fa-user-tie"></i>'; $color = 'blue';
                                    $status_badge = '<span class="text-[7px] font-black text-blue-400 uppercase tracking-tight bg-blue-500/10 px-2 py-0.5 rounded-md">APPROVED</span>';
                                    $target_url = "advisers.php";
                                } elseif (stripos($n['message'], 'Urgent') !== false) {
                                    $action_label = "System Alert"; $icon_markup = '<i class="fas fa-exclamation-triangle"></i>'; $color = 'rose';
                                } elseif (stripos($n['message'], 'New Task') !== false) {
                                    $action_label = "Submission Status"; $icon_markup = '<i class="fas fa-clipboard-list"></i>'; $color = 'blue';
                                } elseif (stripos($n['message'], 'submission') !== false || stripos($n['message'], 'submitted') !== false || stripos($n['message'], 'chapter') !== false) {
                                    $action_label = "Submission Status"; $icon_markup = '<i class="fas fa-file-upload"></i>'; $color = 'blue';
                                } elseif (stripos($n['message'], 'meeting') !== false || stripos($n['message'], 'consultation') !== false) {
                                    $action_label = "Meeting Request"; $icon_markup = '<i class="fas fa-handshake"></i>'; $color = 'blue';
                                } elseif (stripos($n['message'], 'adviser') !== false || stripos($n['message'], 'assigned') !== false) {
                                    $action_label = "Adviser Update"; $icon_markup = '<i class="fas fa-user-tie"></i>'; $color = 'blue'; $target_url = "advisers.php";
                                } elseif (stripos($n['message'], 'archived') !== false || stripos($n['message'], 'archive') !== false) {
                                    $action_label = "Capstone Archive"; $icon_markup = '<i class="fas fa-archive"></i>'; $color = 'blue';
                                    $target_url = "archive_view.php"; $is_archive_alert = true;
                                }

                                $themes = [
                                    'blue' => ['text-blue-400','group-hover/item:text-blue-300','border-blue-500/30','bg-blue-500/5','bg-blue-500','bg-blue-500/10 text-blue-400'],
                                    'blue' => ['text-blue-400','group-hover/item:text-blue-300','border-blue-500/30','bg-blue-500/5','bg-blue-500','bg-blue-500/10 text-blue-400'],
                                    'rose' => ['text-rose-400','group-hover/item:text-rose-300','border-rose-500/30','bg-rose-500/5','bg-rose-500','bg-rose-500/10 text-rose-400'],
                                    'blue' => ['text-blue-400','group-hover/item:text-blue-300','border-blue-500/30','bg-blue-500/5','bg-blue-500','bg-blue-500/10 text-blue-400'],
                                    'blue' => ['text-blue-400','group-hover/item:text-blue-300','border-blue-500/30','bg-blue-500/5','bg-blue-500','bg-blue-500/10 text-blue-400'],
                                    'blue' => ['text-blue-400','group-hover/item:text-blue-300','border-blue-500/30','bg-blue-500/5','bg-blue-500','bg-blue-500/10 text-blue-400'],
                                    'blue' => ['text-blue-400','group-hover/item:text-blue-300','border-blue-500/30','bg-blue-500/5','bg-blue-500','bg-blue-500/10 text-blue-400']
                                ];
                                list($un_title,$un_hover,$un_border,$un_bg,$un_badge,$icon_theme) = $themes[$color];

                                if ($is_archive_alert) { $status_badge = ''; }
                                elseif ($status_badge === '') {
                                    if (stripos($n['message'],'approved')!==false||stripos($n['message'],'verified')!==false||stripos($n['message'],'accepted')!==false) {
                                        $status_badge = '<span class="text-[7px] font-black text-blue-400 uppercase tracking-tight bg-blue-500/10 px-2 py-0.5 rounded-md">Approved</span>';
                                    } elseif (stripos($n['message'],'declined')!==false||stripos($n['message'],'rejected')!==false||stripos($n['message'],'returned')!==false) {
                                        $status_badge = '<span class="text-[7px] font-black text-rose-400 uppercase tracking-tight bg-rose-500/10 px-2 py-0.5 rounded-md">Declined</span>';
                                    } elseif (empty($status_badge) && stripos($n['message'],'deadline')===false && stripos($n['message'],'task')===false && stripos($n['message'],'urgent')===false && stripos($n['message'],'chapter')===false) {
                                        $status_badge = '<span class="text-[7px] font-black text-amber-400 uppercase tracking-tight bg-amber-500/10 px-2 py-0.5 rounded-md">Pending</span>';
                                    }
                                }
                            ?>
                            <a href="<?php echo htmlspecialchars($target_url, ENT_QUOTES, 'UTF-8'); ?>" class="p-3 rounded-2xl bg-slate-100 border <?php echo $is_unread ? "$un_border $un_bg" : 'border-gray-200'; ?> flex items-center gap-4 hover:bg-slate-100 transition-all group/item">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-[10px] shrink-0 <?php echo $icon_theme; ?> transition-all shadow-sm"><?php echo $icon_markup; ?></div>
                                <div class="flex-1 text-left min-w-0">
                                    <div class="flex justify-between items-center w-full gap-2 mb-0.5">
                                        <p class="text-[10px] font-black <?php echo $is_unread ? "$un_title $un_hover" : 'text-slate-600 group-hover/item:text-blue-600'; ?> uppercase tracking-tight transition-colors truncate"><?php echo $action_label; ?></p>
                                        <div class="shrink-0 flex gap-2 items-center">
                                            <?php if (!$is_archive_alert && !empty($status_badge)): ?><?php echo $status_badge; ?><?php endif; ?>
                                            <?php if ($is_unread): ?><span class="text-[7px] font-black text-white uppercase tracking-tight <?php echo $un_badge; ?> px-1.5 py-0.5 rounded-md animate-pulse shadow-md">NEW</span><?php endif; ?>
                                        </div>
                                    </div>
                                    <p class="text-[9px] font-bold text-slate-500 mt-0.5 uppercase line-clamp-2"><?php echo preg_replace('/^(NEW )/i', '<span class="text-blue-400">$1</span>', htmlspecialchars($msg)); ?></p>
                                    <p class="text-[7px] text-slate-500 uppercase font-black mt-1.5 tracking-wider"><?php echo $time_display; ?></p>
                                </div>
                            </a>
                            <?php endforeach; endif; endforeach; else: ?>
                            <p class="text-center py-10 text-slate-500 text-[10px] font-bold uppercase italic">No tracking entries</p>
                            <?php endif; ?>
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

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
            <div class="stat-card p-4 sm:p-8 rounded-[2.5rem] flex items-center justify-between shadow-xl">
                <div class="min-w-0 flex-1"><p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1 truncate">Total Entries</p><h2 class="text-3xl font-black text-slate-900"><?php echo $total_archived; ?></h2></div>
                <div class="w-14 h-14 bg-blue-500/10 text-blue-400 rounded-3xl flex items-center justify-center text-xl shadow-inner shrink-0"><i class="fas fa-database"></i></div>
            </div>
            <div class="stat-card p-4 sm:p-8 rounded-[2.5rem] flex items-center justify-between shadow-xl border-amber-500/10">
                <div class="min-w-0 flex-1"><p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1 truncate">Best Projects</p><h2 class="text-3xl font-black text-slate-900"><?php echo $best_count; ?></h2></div>
                <div class="w-14 h-14 bg-amber-500/10 text-amber-400 rounded-3xl flex items-center justify-center text-xl shadow-inner shrink-0"><i class="fas fa-trophy"></i></div>
            </div>
            <div class="stat-card p-4 sm:p-8 rounded-[2.5rem] flex items-center justify-between shadow-xl border-blue-500/10">
                <div class="min-w-0 flex-1"><p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1 truncate">Standard</p><h2 class="text-3xl font-black text-slate-900"><?php echo $standard_count; ?></h2></div>
                <div class="w-14 h-14 bg-blue-500/10 text-blue-400 rounded-3xl flex items-center justify-center text-xl shadow-inner shrink-0"><i class="fas fa-file-alt"></i></div>
            </div>
        </div>

        <div class="sticky top-0 z-50 -mx-4 px-4 py-3 bg-gradient-to-b from-[#F0F2F5] via-[#F0F2F5]/90 to-transparent backdrop-blur-md">
            <nav class="premium-card rounded-[2.5rem] px-8 py-4 flex flex-wrap justify-between items-center shadow-2xl gap-4 bg-white border border-gray-200">
                <div class="flex items-center gap-2 p-1 bg-slate-100 rounded-2xl border border-gray-200">
                    <button onclick="filterTab('all')" id="tab-all" class="tab-btn px-6 py-2 rounded-xl text-[10px] font-black uppercase transition text-white bg-gradient-to-r from-blue-600 to-blue-500 shadow-sm active">All</button>
                    <button onclick="filterTab('best')" id="tab-best" class="tab-btn px-6 py-2 rounded-xl text-[10px] font-black uppercase transition text-slate-500 hover:text-blue-600">🏆 Best</button>
                    <button onclick="filterTab('regular')" id="tab-regular" class="tab-btn px-6 py-2 rounded-xl text-[10px] font-black uppercase transition text-slate-500 hover:text-blue-600">Standard</button>
                </div>
                <div class="flex items-center gap-3 flex-1 justify-center max-w-2xl">
                    <div class="relative flex-1 group min-w-[200px]">
                        <input type="text" id="searchInput" oninput="fetchResults()" placeholder="SEARCH VAULT..." class="w-full bg-slate-100 border border-gray-200 px-6 py-3 rounded-2xl text-[10px] font-bold uppercase text-slate-900 outline-none focus:border-blue-500/50 shadow-inner">
                        <i class="fas fa-search absolute right-5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                    </div>
                    <select id="yearSelect" onchange="fetchResults()" class="bg-white border border-gray-200 px-4 py-3 rounded-2xl text-[10px] font-black uppercase text-slate-600 outline-none cursor-pointer focus:border-blue-500/50 shadow-inner">
                        <option value="all" class="bg-white text-slate-900 font-bold uppercase">All Years</option>
                        <?php while ($yr = mysqli_fetch_assoc($years_query)): ?>
                            <option value="<?php echo htmlspecialchars($yr['year'], ENT_QUOTES, 'UTF-8'); ?>" class="bg-white text-slate-900 font-bold uppercase"><?php echo htmlspecialchars($yr['year'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endwhile; ?>
                    </select>
                    <select id="adviserSelect" onchange="fetchResults()" class="bg-white border border-gray-200 px-4 py-3 rounded-2xl text-[10px] font-black uppercase text-slate-600 outline-none cursor-pointer max-w-[150px] focus:border-blue-500/50 shadow-inner">
                        <option value="all" class="bg-white text-slate-900 font-bold uppercase">All Advisers</option>
                        <?php foreach ($registered_advisers as $adv_name): ?>
                            <option value="<?php echo htmlspecialchars($adv_name, ENT_QUOTES, 'UTF-8'); ?>" class="bg-white text-slate-900 font-bold uppercase"><?php echo htmlspecialchars(strtoupper($adv_name), ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </nav>
        </div>

        <section class="premium-card rounded-[3.5rem] shadow-2xl overflow-hidden">
            <div class="p-8 lg:p-10 border-b border-gray-200 flex flex-col md:flex-row justify-between items-center bg-slate-100">
                <div><h3 class="text-lg font-black uppercase text-slate-900 tracking-tight">Project Directory</h3><p class="text-[9px] font-bold text-blue-400 uppercase tracking-widest mt-1">Purged & Protected Capstone Archives</p></div>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left">
                    <thead class="bg-slate-100 text-[9px] font-black uppercase tracking-widest text-slate-500 border-b border-gray-200">
                        <tr>
                            <th class="px-4 sm:px-10 py-4 sm:py-6">Project Data</th>
                            <th class="px-4 sm:px-10 py-4 sm:py-6 text-center">Adviser</th>
                            <th class="px-4 sm:px-10 py-4 sm:py-6 text-center">Year</th>
                            <th class="px-4 sm:px-10 py-4 sm:py-6 text-center">Classification</th>
                            <th class="px-4 sm:px-10 py-4 sm:py-6 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="archiveTableBody" class="divide-y divide-gray-100">
                        <?php render_archive_rows($conn, '', '', '', 'all'); ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <div id="viewer-modal" class="fixed inset-0 bg-black/90 backdrop-blur-xl hidden z-[600] items-center justify-center p-4" onclick="if(event.target===this)closeFileViewer()">
        <div class="w-full max-w-5xl max-h-[90vh] rounded-[2rem] overflow-hidden bg-white border border-gray-200 shadow-2xl flex flex-col transform transition-all scale-95 opacity-0" id="viewer-content">
            <div class="flex items-center justify-between px-8 py-4 bg-slate-100 border-b border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-500/10 text-blue-400 rounded-xl flex items-center justify-center"><i class="fas fa-file-alt text-xs"></i></div>
                    <div>
                        <h3 class="text-sm font-black text-slate-900 uppercase tracking-tight" id="viewer-title">Document Viewer</h3>
                        <p class="text-[8px] font-bold text-slate-500 uppercase tracking-widest mt-0.5">CAPSTONE ARCHIVE</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a id="viewer-download-btn-group" href="#" download class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-400 hover:bg-blue-600 hover:text-white transition flex items-center justify-center"><i class="fas fa-download text-xs"></i></a>
                    <button onclick="closeFileViewer()" class="w-10 h-10 rounded-xl bg-slate-100 text-slate-500 hover:text-red-400 transition flex items-center justify-center"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div class="flex-1 bg-white relative min-h-[70vh]">
                <embed id="viewer-embed-group" type="application/pdf" class="w-full h-[75vh] border-0 hidden">
                <iframe id="viewer-iframe-group" src="" class="w-full h-[75vh] border-0 hidden" allowfullscreen></iframe>
                <div id="viewer-fallback-group" class="hidden absolute inset-0 flex items-center justify-center">
                    <div class="text-center">
                        <div class="w-20 h-20 bg-slate-100 rounded-3xl flex items-center justify-center mx-auto mb-6"><i class="fas fa-file text-3xl text-slate-500"></i></div>
                        <p class="text-sm font-black text-slate-500 uppercase tracking-widest">Preview not available</p>
                        <p class="text-[9px] text-slate-600 font-bold uppercase mt-2">Use the download button to view this file</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="view-modal" class="fixed inset-0 bg-black/80 backdrop-blur-xl hidden z-[500] items-center justify-center p-6">
        <div id="view-content" class="bg-gradient-to-b from-white to-slate-100 w-full max-w-2xl rounded-[3rem] border border-gray-200 shadow-2xl overflow-hidden transform scale-95 opacity-0 transition-all duration-300">
            <div class="p-8 border-b border-gray-200 flex justify-between items-center bg-slate-100">
                <div>
                    <span id="modal-cat" class="text-[8px] font-black uppercase px-3 py-1 rounded-full tracking-widest mb-3 inline-block"></span>
                    <h3 class="text-xl font-black uppercase text-slate-900 tracking-tight leading-tight pr-6" id="modal-title"></h3>
                </div>
                <button onclick="closeViewModal()" class="w-12 h-12 rounded-2xl bg-slate-100 border border-gray-200 text-slate-500 hover:text-red-400 transition flex items-center justify-center shrink-0"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-10 space-y-8 max-h-[60vh] overflow-y-auto custom-scrollbar">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-slate-100 p-5 rounded-2xl border border-gray-200">
                        <p class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Assigned Guide Node</p>
                        <p class="text-xs font-black text-blue-400 uppercase" id="modal-adviser"></p>
                    </div>
                    <div class="bg-slate-100 p-5 rounded-2xl border border-gray-200">
                        <p class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Timeline Matrix Deployment</p>
                        <p class="text-xs font-black text-slate-600 uppercase" id="modal-year"></p>
                    </div>
                </div>
                <div class="space-y-3">
                    <h4 class="text-[9px] font-black text-slate-900 uppercase tracking-widest">Metadata Abstract Summary Array</h4>
                    <div class="p-6 rounded-2xl bg-white border border-gray-200">
                        <p class="text-slate-500 text-xs leading-relaxed text-justify whitespace-pre-line uppercase" id="modal-abstract"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="drawer-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-[300]" onclick="closeSettingsDrawer()"></div>
    <div id="settings-drawer" class="fixed top-0 right-0 h-full w-80 bg-[#F0F2F5]/98 backdrop-blur-3xl z-[301] shadow-2xl border-l border-gray-200 flex flex-col transform translate-x-full transition-transform duration-300">
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
                <h3 class="text-xl font-black text-slate-900 uppercase tracking-tight"><?php echo htmlspecialchars($group_name, ENT_QUOTES, 'UTF-8'); ?></h3>
                <p class="text-[9px] font-black text-blue-400 uppercase tracking-widest mb-8">Research Group Workspace Node</p>
                <div class="space-y-3 text-left">
                    <div class="p-5 rounded-3xl bg-slate-100 border border-gray-200 flex items-center justify-between"><div><p class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Group Workspace Identity</p><p class="text-sm font-bold text-slate-600">@<?php echo htmlspecialchars($group_name, ENT_QUOTES, 'UTF-8'); ?></p></div><i class="fas fa-id-card text-slate-700"></i></div>
                    <div class="p-5 rounded-3xl bg-slate-100 border border-gray-200 flex items-center justify-between"><div><p class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Primary Portal Email Address</p><p class="text-sm font-bold text-slate-600"><?php echo htmlspecialchars($group_email, ENT_QUOTES, 'UTF-8'); ?></p></div><i class="fas fa-envelope text-slate-700"></i></div>
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
                <?php if ($logins_q && mysqli_num_rows($logins_q) > 0): mysqli_data_seek($logins_q, 0); while ($log = mysqli_fetch_assoc($logins_q)): ?>
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
        var CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';
        var searchInput = document.getElementById('searchInput');
        var yearSelect = document.getElementById('yearSelect');
        var adviserSelect = document.getElementById('adviserSelect');
        var tableBody = document.getElementById('archiveTableBody');
        var currentTab = 'all';

        function fetchResults() {
            fetch('archive_view.php?ajax_search=1&search=' + encodeURIComponent(searchInput.value) + '&year=' + encodeURIComponent(yearSelect.value) + '&adviser=' + encodeURIComponent(adviserSelect.value) + '&tab=' + encodeURIComponent(currentTab))
                .then(function(r) { return r.text(); })
                .then(function(d) { tableBody.innerHTML = d; });
        }

        function filterTab(tab) {
            currentTab = tab;
            document.querySelectorAll('.tab-btn').forEach(function(b) { b.className = 'tab-btn px-6 py-2 rounded-xl text-[10px] font-black uppercase transition text-slate-500 hover:text-blue-600'; });
            var a = document.getElementById('tab-' + tab);
            if (a) a.className = 'tab-btn px-6 py-2 rounded-xl text-[10px] font-black uppercase transition text-white bg-gradient-to-r from-blue-600 to-blue-500 shadow-sm active';
            fetchResults();
        }

        function openFileViewer(url, title, ext) {
            var modal = document.getElementById('viewer-modal');
            var content = document.getElementById('viewer-content');
            var embed = document.getElementById('viewer-embed-group');
            var iframe = document.getElementById('viewer-iframe-group');
            var fallback = document.getElementById('viewer-fallback-group');
            var dlBtn = document.getElementById('viewer-download-btn-group');
            document.getElementById('viewer-title').innerText = title || 'Document Viewer';
            dlBtn.href = url;
            embed.classList.add('hidden'); embed.src = '';
            iframe.classList.add('hidden'); iframe.src = '';
            fallback.classList.add('hidden');
            modal.classList.remove('hidden'); modal.classList.add('flex');
            setTimeout(function() {
                content.classList.remove('scale-95', 'opacity-0');
                content.classList.add('scale-100', 'opacity-100');
                if (ext === 'pdf') { embed.type = 'application/pdf'; embed.src = url; embed.classList.remove('hidden'); }
                else if (['jpg','jpeg','png','gif','txt','html','htm'].indexOf(ext) !== -1) { iframe.src = url; iframe.classList.remove('hidden'); }
                else { fallback.classList.remove('hidden'); }
            }, 200);
        }

        function closeFileViewer() {
            var c = document.getElementById('viewer-content');
            var m = document.getElementById('viewer-modal');
            var e = document.getElementById('viewer-embed-group');
            var i = document.getElementById('viewer-iframe-group');
            c.classList.add('scale-95', 'opacity-0');
            setTimeout(function() { m.classList.replace('flex', 'hidden'); e.src = ''; i.src = ''; }, 200);
        }

        function viewCapstone(d) {
            document.getElementById('modal-title').innerText = d.title;
            document.getElementById('modal-adviser').innerText = d.adviser || 'None Assigned';
            document.getElementById('modal-year').innerText = d.year;
            document.getElementById('modal-abstract').innerText = d.abstract || 'No abstract content registered within metadata entry.';
            var cb = document.getElementById('modal-cat');
            cb.innerText = d.category === 'best' ? "Best Capstone Award" : "Standard Research Node";
            cb.className = d.category === 'best'
                ? "bg-amber-500/10 text-amber-400 border border-amber-500/20 text-[8px] font-black uppercase px-3 py-1 rounded-full mb-3 inline-block tracking-widest"
                : "bg-blue-500/10 text-blue-400 border border-gray-200 text-[8px] font-black uppercase px-3 py-1 rounded-full mb-3 inline-block tracking-widest";
            var m = document.getElementById('view-modal');
            var c = document.getElementById('view-content');
            m.classList.remove('hidden'); m.classList.add('flex');
            setTimeout(function() { c.classList.remove('scale-95', 'opacity-0'); c.classList.add('scale-100', 'opacity-100'); }, 10);
        }

        function closeViewModal() {
            var c = document.getElementById('view-content');
            c.classList.replace('scale-100', 'scale-95');
            c.classList.replace('opacity-100', 'opacity-0');
            setTimeout(function() { document.getElementById('view-modal').classList.replace('flex', 'hidden'); }, 200);
        }

        function handleNotifClick() {
            var sp = document.getElementById('settings-popover');
            if (sp) sp.classList.add('hidden');
            document.getElementById('notif-popover').classList.toggle('hidden');
            var b = document.getElementById('notif-badge');
            if (b) b.style.display = 'none';
            fetch('archive_view.php?action=clear_student_notifications&csrf_token=' + encodeURIComponent(CSRF_TOKEN))
                .then(function(r) { return r.json(); })
                .then(function() { document.querySelectorAll('.animate-pulse').forEach(function(el) { el.style.display = 'none'; }); })
                .catch(function(e) { console.error("Notification sync aborted:", e); });
        }

        function toggleSettingsDropdown() {
            var np = document.getElementById('notif-popover');
            if (np) np.classList.add('hidden');
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
            var s = document.getElementById('security-submenu');
            var c = document.getElementById('security-chevron');
            if (s.style.maxHeight === "0px" || s.style.maxHeight === "") { s.style.maxHeight = "400px"; c.classList.add('rotate-90'); }
            else { s.style.maxHeight = "0px"; c.classList.remove('rotate-90'); }
        }

        function animateIn(id, cid) {
            var m = document.getElementById(id); var c = document.getElementById(cid);
            m.classList.remove('hidden'); m.classList.add('flex');
            setTimeout(function() { c.classList.remove('scale-95', 'opacity-0'); c.classList.add('scale-100', 'opacity-100'); }, 10);
        }
        function openLoginsModal() { animateIn('logins-modal', 'logins-content'); }
        function closeLoginsModal() { document.getElementById('logins-modal').classList.replace('flex', 'hidden'); }
        function openPasswordModal() { animateIn('password-modal', 'password-content'); }
        function closePasswordModal() { document.getElementById('password-modal').classList.replace('flex', 'hidden'); }
        function openProfileModal() { animateIn('profile-modal', 'profile-content'); }
        function closeProfileModal() { document.getElementById('profile-modal').classList.replace('flex', 'hidden'); }

        function togglePass(id, btn) {
            var i = document.getElementById(id); var ic = btn.querySelector('i');
            if (i.type === "password") { i.type = "text"; ic.classList.replace('fa-eye', 'fa-eye-slash'); }
            else { i.type = "password"; ic.classList.replace('fa-eye-slash', 'fa-eye'); }
        }

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebar-backdrop').classList.toggle('open');
        }

        document.getElementById('changePasswordForm').onsubmit = function(e) {
            e.preventDefault();
            var np = document.getElementById('new_pass').value;
            var cp = document.getElementById('conf_pass').value;
            var pe = document.getElementById('password-error');
            if (np !== cp) { pe.innerText = "New passwords do not match!"; pe.classList.remove('hidden'); return; }
            var fd = new FormData(this);
            fd.append('csrf_token', CSRF_TOKEN);
            fetch('../backend/auth/update_group_password.php', { method: 'POST', body: fd })
                .then(function(r) { return r.text(); })
                .then(function(d) {
                    var r = d.trim();
                    if (r === 'success') { closePasswordModal(); showToast("Password successfully changed"); this.reset(); pe.classList.add('hidden'); }
                    else if (r === 'incorrect_current') { pe.innerText = "Current password you entered is incorrect"; pe.classList.remove('hidden'); }
                    else { pe.innerText = r; pe.classList.remove('hidden'); }
                });
        };

        function showToast(msg) {
            var t = document.getElementById('toast-container');
            t.style.display = '';
            document.getElementById('toast-message').innerText = msg;
            t.classList.remove('-translate-y-20', 'opacity-0'); t.classList.add('translate-y-0', 'opacity-100');
            setTimeout(function() { t.classList.add('-translate-y-20', 'opacity-0'); t.classList.remove('translate-y-0', 'opacity-100'); }, 3000);
        }

        window.onclick = function(e) {
            if (e.target.closest('#archiveTableBody a') || e.target.closest('#view-content')) return;
            if (!e.target.closest('.nav-icon-container') && !e.target.closest('#settings-drawer') && !e.target.closest('#notif-popover')) {
                var sp = document.getElementById('settings-popover'); var np = document.getElementById('notif-popover');
                if (sp) sp.classList.add('hidden'); if (np) np.classList.add('hidden');
            }
        };
    </script>
<style>
@keyframes globalHighlightFade { 0% { background-color: rgba(59,130,246,0.2) !important; box-shadow: inset 0 0 0 2px #3b82f6 !important; border-radius: inherit; } 100% { background-color: transparent; box-shadow: inset 0 0 0 0px transparent; } }
.global-highlight-row { animation: globalHighlightFade 3s ease-in-out forwards !important; }
</style>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var p = new URLSearchParams(window.location.search);
    var h = p.get('highlight');
    if (h) { var el = document.getElementById('row-' + h); if (el) setTimeout(function() { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); el.classList.add('global-highlight-row'); }, 100); }
});
function updateLiveTimes() {
    document.querySelectorAll('.live-time').forEach(function(el) {
        var ts = parseInt(el.dataset.timestamp); if (!ts) return;
        var d = Math.floor((Date.now() / 1000) - ts); var t;
        if (d < 1) t = 'now'; else if (d < 60) t = d + ' second' + (d > 1 ? 's' : '');
        else if (d < 3600) t = Math.floor(d / 60) + ' minute' + (Math.floor(d / 60) > 1 ? 's' : '');
        else if (d < 86400) t = Math.floor(d / 3600) + ' hour' + (Math.floor(d / 3600) > 1 ? 's' : '');
        else if (d < 2592000) t = Math.floor(d / 86400) + ' day' + (Math.floor(d / 86400) > 1 ? 's' : '');
        else if (d < 31536000) t = Math.floor(d / 2592000) + ' month' + (Math.floor(d / 2592000) > 1 ? 's' : '');
        else t = Math.floor(d / 31536000) + ' year' + (Math.floor(d / 31536000) > 1 ? 's' : '');
        el.textContent = t + (el.closest('[data-suffix]') ? '' : ' ago');
    });
}
setInterval(updateLiveTimes, 30000);
updateLiveTimes();
</script>
<?php include('chat_widget.php'); ?>
</body>
</html>
