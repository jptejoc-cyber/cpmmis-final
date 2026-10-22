<?php
include('../backend/session_init.php');
include('../backend/db_config.php');
include('../backend/auth/csrf.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'adviser') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);

// --- AJAX HANDLERS ---

if (isset($_GET['action']) && $_GET['action'] === 'clear_adviser_notifications') {
    header('Content-Type: application/json');
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Security token validation failed']);
        exit();
    }
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND user_role = 'adviser' AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    echo json_encode(['status' => 'success']);
    exit();
}

function renderArchiveRows($conn, $search = '', $year = '', $adviser = '', $tab = 'all', $highlight = 0) {
    $titleCol = 'title';
    $adviserCol = 'adviser';
    $yearCol = 'year';
    $classCol = 'category';

    $conditions = [];
    $params = [];
    $types = '';

    if (!empty($search)) {
        $conditions[] = "$titleCol LIKE CONCAT(?, '%')";
        $params[] = $search;
        $types .= 's';
    }
    if (!empty($year)) {
        $conditions[] = "$yearCol = ?";
        $params[] = $year;
        $types .= 's';
    }
    if (!empty($adviser)) {
        $conditions[] = "$adviserCol = ?";
        $params[] = $adviser;
        $types .= 's';
    }
    if ($tab === 'best') {
        $conditions[] = "LOWER($classCol) LIKE '%best%'";
    }
    if ($tab === 'regular' || $tab === 'standard') {
        $conditions[] = "(LOWER($classCol) NOT LIKE '%best%' OR $classCol IS NULL OR $classCol = '')";
    }

    $query = "SELECT * FROM capstone_archive";
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }
    $query .= " ORDER BY id DESC LIMIT 500";

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $dbId = $row['id'];
            $dbTitle = $row[$titleCol] ?? 'Untitled';
            $dbAdviser = $row[$adviserCol] ?? 'N/A';
            $dbYear = $row[$yearCol] ?? 'N/A';
            $dbClass = strtolower(trim($row[$classCol] ?? ''));
            $dbFile = $row['file_path'] ?? $row['file'] ?? '';
            $isBest = (strpos($dbClass, 'best') !== false);

            $cat_html = $isBest
                ? '<span class="px-4 py-2 rounded-full bg-amber-500/10 text-amber-400 text-[9px] font-black border border-amber-500/20 uppercase tracking-wider inline-block">&#x1F3C6; Best Project</span>'
                : '<span class="px-4 py-2 rounded-full bg-blue-500/10 text-blue-400 text-[9px] font-black border border-blue-500/20 uppercase tracking-wider inline-block">Standard Project</span>';

            $row_json = htmlspecialchars(json_encode([
                'title' => $dbTitle,
                'adviser' => $dbAdviser,
                'year' => $dbYear,
                'category' => $isBest ? 'best' : 'regular',
                'abstract' => $row['abstract'] ?? '',
                'file_path' => $dbFile
            ]), ENT_QUOTES, 'UTF-8');

            $fileName = basename($dbFile);
            $serve_url = '../backend/serve_file.php?type=archive&file=' . rawurlencode($fileName);
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $view_url = $serve_url;
            $highlightClass = ($dbId == $highlight) ? 'bg-blue-900/40 border-l-4 border-blue-500' : '';

            echo "<tr id='row-$dbId' class='hover:bg-white/[0.02] group $highlightClass'>
                    <td class='px-4 sm:px-10 py-4 sm:py-6'>
                        <span class='font-extrabold text-slate-900 uppercase text-xs block mb-1 hover:text-blue-400 cursor-pointer' onclick=\"viewCapstone($row_json)\">" . htmlspecialchars($dbTitle) . "</span>
                    </td>
                    <td class='px-4 sm:px-10 py-4 sm:py-6 text-center text-xs font-bold text-slate-600 uppercase'>" . htmlspecialchars($dbAdviser) . "</td>
                    <td class='px-4 sm:px-10 py-4 sm:py-6 text-center text-xs font-bold text-slate-500'>$dbYear</td>
                    <td class='px-4 sm:px-10 py-4 sm:py-6 text-center'>$cat_html</td>
                    <td class='px-4 sm:px-10 py-4 sm:py-6 text-right pr-12'>
                        <div class='flex justify-end gap-2 relative z-20'>";

            if (!empty($dbFile)) {
                echo "<a href='$view_url' target='_blank' class='inline-flex items-center justify-center w-10 h-10 rounded-xl bg-slate-100 border border-gray-200 text-blue-400 hover:bg-blue-600 hover:text-white shadow-sm'>
                        <i class='fas fa-eye text-xs'></i>
                      </a>
                      <a href='$view_url' download class='inline-flex items-center justify-center w-10 h-10 rounded-xl bg-blue-500/10 border border-blue-500/10 text-blue-400 hover:bg-blue-600 hover:text-white shadow-sm'>
                        <i class='fas fa-download text-xs'></i>
                      </a>";
            } else {
                echo "<button disabled title='No file uploaded' class='inline-flex items-center justify-center w-10 h-10 rounded-xl bg-slate-100 border border-gray-200 text-slate-600 cursor-not-allowed'>
                        <i class='fas fa-file-excel text-xs'></i>
                      </button>";
            }

            echo "      </div>
                    </td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='5' class='py-24 text-center text-slate-500 uppercase text-[10px] font-black italic tracking-widest bg-transparent'>No matching records identified inside repository</td></tr>";
    }
}

if (isset($_GET['ajax_search'])) {
    header('Content-Type: text/html; charset=utf-8');
    $search = trim($_GET['search'] ?? '');
    $year = trim($_GET['year'] ?? '');
    $adviser = trim($_GET['adviser'] ?? '');
    $tab = trim($_GET['tab'] ?? 'all');
    $highlight = isset($_GET['highlight']) ? intval($_GET['highlight']) : 0;
    renderArchiveRows($conn, $search, $year, $adviser, $tab, $highlight);
    exit();
}

// --- PHP DATA LAYER ---

$adviser_stmt = $conn->prepare("SELECT * FROM adviser WHERE id = ? LIMIT 1");
$adviser_stmt->bind_param("i", $user_id);
$adviser_stmt->execute();
$adviser_query = $adviser_stmt->get_result();
$adviser_data = mysqli_fetch_assoc($adviser_query);
$adviser_name = $adviser_data['full_name'] ?? 'Adviser';
$adviser_email = $adviser_data['email'] ?? 'No email registered';

$total_archived = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM capstone_archive"))['total'];
$best_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM capstone_archive WHERE category = 'best'"))['total'];
$standard_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM capstone_archive WHERE category = 'regular' OR category = 'standard' OR category IS NULL OR category = ''"))['total'];

$years_query = mysqli_query($conn, "SELECT DISTINCT year FROM capstone_archive ORDER BY year DESC");
$advisers_list_query = mysqli_query($conn, "SELECT DISTINCT adviser FROM capstone_archive WHERE adviser IS NOT NULL AND adviser != '' ORDER BY adviser ASC");
$registered_advisers = [];
while ($adv = mysqli_fetch_assoc($advisers_list_query)) { $registered_advisers[] = $adv['adviser']; }

$logins_stmt = $conn->prepare("SELECT * FROM login_logs WHERE user_id = ? AND role = 'adviser' ORDER BY login_time DESC LIMIT 5");
$logins_stmt->bind_param("i", $user_id);
$logins_stmt->execute();
$logins_q = $logins_stmt->get_result();

$unread_stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE is_read = 0 AND user_id = ? AND user_role = 'adviser'");
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_count = $unread_stmt->get_result()->fetch_assoc()['unread'] ?? 0;


?>
<!DOCTYPE html>
<html lang="en" style="background-color: #F0F2F5; color-scheme: light;">
<head>
    <meta charset="UTF-8">
    <meta name="color-scheme" content="light">
    <style>
        html, body { background-color: #F0F2F5 !important; color-scheme: light; }
    </style>
    <title>Archive Vault | CPMMIS</title>
    <link rel="icon" type="image/png" href="../chmsu.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#F0F2F5">
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preload" as="style" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        .logo-section { display: flex; flex-direction: row; align-items: center; padding: 1.5rem 0; margin-bottom: 2.5rem; width: 100%; justify-content: flex-start; padding-left: 1.35rem; outline: none !important; border: none !important; box-shadow: none !important; }
        .logo-section * { outline: none !important; }
        .brand-text-container { max-width: 0; opacity: 0; overflow: hidden; white-space: nowrap; transition: max-width 0.3s ease, opacity 0.3s ease; width: 100%; }
        aside:hover .brand-text-container { max-width: 200px; opacity: 1; margin-left: 1rem; }
        .brand-text { font-size: 1.25rem; font-weight: 800; color: #1877F2; letter-spacing: -0.025em; width: 100%; }
        .sidebar-link { display: flex; align-items: center; gap: 1.25rem; padding: 1rem 1.45rem; border-radius: 1rem; transition: background-color 0.2s ease, color 0.2s ease; color: #65676b; width: 100%; justify-content: flex-start; }
        .sidebar-text { opacity: 0; display: none; font-weight: 800; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; }
        aside:hover .sidebar-text { opacity: 1; display: inline; }
        .sidebar-link:hover { background-color: #f0f2f5; color: #1c1e21; }
        .sidebar-link.active { background: var(--chmsu-blue) !important; color: white !important; box-shadow: 0 10px 15px -3px rgba(24, 119, 242, 0.25); }
        .premium-card { background: #ffffff; border: 1px solid #dadde1; }
        .stat-card { transition: box-shadow 0.2s ease, border-color 0.2s ease; border: 1px solid #e4e6eb; background: #ffffff; }
        .stat-card:hover { transform: none !important; box-shadow: 0 10px 24px rgba(0,0,0,0.12); border-color: rgba(24, 119, 242, 0.4); }
        #settings-drawer { transform: translateX(100%); transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); background: #ffffff; backdrop-filter: blur(40px); border-left: 1px solid #dadde1; }
        #settings-drawer.open { transform: translateX(0); }
        .icon-tooltip { position: relative; }
        .icon-tooltip:hover::after { content: attr(data-tip); position: absolute; top: 110%; left: 50%; transform: translateX(-50%); background: #1e293b; color: white; padding: 6px 12px; border-radius: 8px; font-size: 9px; font-weight: 800; text-transform: uppercase; white-space: nowrap; z-index: 1000; display: block; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0, 0, 0, 0.15); border-radius: 10px; }
        @keyframes scale-in { from { opacity: 0; transform: scale(0.95) translateY(-10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .animate-scale-in { animation: scale-in 0.2s ease-out; }
        .sidebar-toggle-btn {
            display: none; position: fixed; top: 0.75rem; left: 0.75rem; z-index: 160;
            width: 2.5rem; height: 2.5rem; border-radius: 0.75rem;
            background: #ffffff; border: 1px solid #dadde1;
            color: #94a3b8; align-items: center; justify-content: center; cursor: pointer;
            transition: background-color 0.2s ease, color 0.2s ease;
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
            .sidebar-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 145; }
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
            <a href="group_requests.php" class="sidebar-link">
                <i class="fas fa-user-plus w-5 text-center text-sm"></i>
                <span class="sidebar-text">Group Requests</span>
            </a>
            <a href="archive_view.php" class="sidebar-link active">
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

        <!-- Navbar -->
        <nav class="premium-card rounded-[2.5rem] px-4 sm:px-8 py-4 flex flex-wrap justify-between items-center gap-2 shadow-2xl relative z-[100]">
            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-black text-blue-400 uppercase tracking-[0.2em] mb-1">Authenticated Adviser Workspace</p>
                <h2 class="text-lg sm:text-xl font-black text-slate-900 uppercase tracking-tight truncate">Archive Repository</h2>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <div class="relative nav-icon-container">
                    <button onclick="toggleAdviserChatWidget()" data-tip="Messages" class="icon-tooltip w-12 h-12 rounded-2xl bg-slate-100 border border-gray-200 flex items-center justify-center hover:bg-slate-100 relative shadow-sm">
                        <i class="fas fa-comment-dots text-blue-400"></i>
                    </button>
                </div>
                <!-- Notifications -->
                <div class="relative nav-icon-container">
                    <button onclick="handleNotifClick()" data-tip="Notifications" class="icon-tooltip w-12 h-12 rounded-2xl bg-slate-100 border border-gray-200 flex items-center justify-center hover:bg-slate-100 relative shadow-sm">
                        <i class="fas fa-bell text-blue-400"></i>
                        <?php if ($unread_count > 0): ?>
                        <span id="notif-badge" class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[9px] font-black rounded-full border-4 border-white flex items-center justify-center"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </button>
                    <div id="notif-popover" class="hidden absolute right-0 mt-3 w-80 bg-white backdrop-blur-2xl rounded-[2.2rem] shadow-2xl border border-gray-200 z-[250] overflow-hidden animate-scale-in">
                        <div class="p-6 bg-slate-100 border-b border-gray-200 flex justify-between items-center"><h4 class="text-[10px] font-black uppercase text-slate-900 tracking-widest leading-none">Notifications</h4></div>
                        <div class="max-h-80 overflow-y-auto p-4 space-y-3 custom-scrollbar">
                            <?php
                            $notif_list_stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND user_role = 'adviser' ORDER BY id DESC LIMIT 10");
                            $notif_list_stmt->bind_param("i", $user_id);
                            $notif_list_stmt->execute();
                            $notif_list = $notif_list_stmt->get_result();
                            if ($notif_list && mysqli_num_rows($notif_list) > 0):
                                $all_notifs = [];
                                while ($row = mysqli_fetch_assoc($notif_list)) { $all_notifs[] = $row; }
                                $new_notifs = array_filter($all_notifs, function ($n) { return $n['is_read'] == 0; });
                                $recent_notifs = array_filter($all_notifs, function ($n) { return $n['is_read'] == 1; });
                                $notif_groups = [
                                    ['label' => 'New', 'items' => $new_notifs, 'color' => 'blue'],
                                    ['label' => 'Recent', 'items' => $recent_notifs, 'color' => 'slate']
                                ];
                                foreach ($notif_groups as $group):
                                    if (count($group['items']) > 0):
                            ?>
                            <div class="px-2 py-1 mt-1 mb-2"><p class="text-[9px] font-black uppercase text-<?php echo $group['color']; ?>-400 tracking-widest"><?php echo $group['label']; ?></p></div>
                            <?php foreach ($group['items'] as $n):
                                $msg = $n['message'];
                                $is_unread = ($n['is_read'] == 0);
                                if (!$is_unread) {
                                    $msg = preg_replace('/^New /i', '', $msg);
                                }
                                $raw_time = getRelativeTime($n['created_at']);
                                $time_display = (strtolower($raw_time) === 'now' || strtolower($raw_time) === 'just now') ? "JUST NOW" : $raw_time . " ago";
                                $link = getTargetPage($msg, $n['reference_id'] ?? 0);;
                                $action_label = "System Movement";
                                $icon_markup = '<i class="fas fa-info-circle"></i>';
                                $color = 'blue';
                                $status_badge = '';

                                if (stripos($n['message'], 'registered') !== false || stripos($n['message'], 'group request') !== false || stripos($n['message'], 'advisership') !== false) {
                                    $action_label = "Group Advisership Request";
                                    $icon_markup = '<i class="fas fa-user-plus"></i>';
                                    $color = 'amber';
                                    $msg_parts = explode(': ', $msg);
                                    $extracted_username = trim(end($msg_parts));
                                    $check_status_stmt = $conn->prepare("SELECT status FROM `group` WHERE username = ? LIMIT 1");
                                    $check_status_stmt->bind_param("s", $extracted_username);
                                    $check_status_stmt->execute();
                                    $check_status_q = $check_status_stmt->get_result();
                                    $live_status = ($check_status_q && $check_status_q->num_rows > 0) ? strtolower($check_status_q->fetch_assoc()['status']) : 'pending';
                                    if ($live_status === 'active' || $live_status === 'approved' || $live_status === 'accepted') {
                                        $status_badge = '<span class="text-[7px] font-black text-blue-400 uppercase tracking-tight bg-blue-500/10 px-2 py-0.5 rounded-md">Approved</span>';
                                    } elseif ($live_status === 'declined' || $live_status === 'rejected') {
                                        $status_badge = '<span class="text-[7px] font-black text-rose-400 uppercase tracking-tight bg-rose-500/10 px-2 py-0.5 rounded-md">Declined</span>';
                                    } else {
                                        $status_badge = '<span class="text-[7px] font-black text-amber-400 uppercase tracking-tight bg-amber-500/10 px-2 py-0.5 rounded-md">Pending</span>';
                                    }
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
                                }

                                $themes = [
                                    'blue' => ['text-blue-400', 'group-hover/item:text-blue-300', 'border-blue-500/30', 'bg-blue-500/5', 'bg-blue-500', 'bg-blue-500/10 text-blue-400'],
                                    'blue' => ['text-blue-400', 'group-hover/item:text-blue-300', 'border-blue-500/30', 'bg-blue-500/5', 'bg-blue-500', 'bg-blue-500/10 text-blue-400'],
                                    'amber' => ['text-amber-400', 'group-hover/item:text-amber-300', 'border-amber-500/30', 'bg-amber-500/5', 'bg-amber-500', 'bg-amber-500/10 text-amber-400'],
                                    'blue' => ['text-blue-400', 'group-hover/item:text-blue-300', 'border-blue-500/30', 'bg-blue-500/5', 'bg-blue-500', 'bg-blue-500/10 text-blue-400'],
                                    'blue' => ['text-blue-400', 'group-hover/item:text-blue-300', 'border-blue-500/30', 'bg-blue-500/5', 'bg-blue-500', 'bg-blue-500/10 text-blue-400'],
                                    'blue' => ['text-blue-400', 'group-hover/item:text-blue-300', 'border-blue-500/30', 'bg-blue-500/5', 'bg-blue-500', 'bg-blue-500/10 text-blue-400']
                                ];
                                list($un_title, $un_hover, $un_border, $un_bg, $un_badge, $icon_theme) = $themes[$color];
                            ?>
                                <a href="<?php echo $link; ?>" class="p-3 rounded-2xl bg-slate-100 border <?php echo $is_unread ? "$un_border $un_bg" : 'border-gray-200'; ?> flex items-center gap-4 hover:bg-slate-100 block transition-all group/item">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-[10px] shrink-0 <?php echo $icon_theme; ?> transition-all shadow-sm"><?php echo $icon_markup; ?></div>
                                    <div class="flex-1 text-left min-w-0">
                                        <div class="flex justify-between items-center w-full gap-2 mb-0.5">
                                            <p class="text-[10px] font-black <?php echo $is_unread ? "$un_title $un_hover" : 'text-slate-600 group-hover/item:text-blue-600'; ?> uppercase tracking-tight transition-colors truncate"><?php echo $action_label; ?></p>
                                            <div class="shrink-0 flex gap-2 items-center">
                                                <?php if (!empty($status_badge)) echo $status_badge; ?>
                                                <?php if ($is_unread): ?><span class="text-[7px] font-black text-white uppercase tracking-tight <?php echo $un_badge; ?> px-1.5 py-0.5 rounded-md animate-pulse shadow-md">NEW</span><?php endif; ?>
                                            </div>
                                        </div>
                                        <p class="text-[9px] font-bold text-slate-500 mt-0.5 uppercase tracking-tight line-clamp-2"><?php echo preg_replace('/^(NEW )/i', '<span class="text-blue-400">$1</span>', htmlspecialchars($msg)); ?></p>
                                        <p class="text-[7px] text-slate-500 uppercase font-black mt-1.5 tracking-wider"><?php echo $time_display; ?></p>
                                    </div>
                                </a>
                            <?php endforeach; endif; endforeach; else: ?>
                            <p class="text-center py-10 text-slate-500 text-[10px] font-bold uppercase italic">No tracking entries</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- Settings -->
                <div class="relative nav-icon-container">
                    <button onclick="toggleSettingsDropdown()" data-tip="Settings" class="icon-tooltip w-12 h-12 rounded-2xl bg-slate-100 border border-gray-200 flex items-center justify-center hover:bg-slate-100 relative shadow-sm"><i class="fas fa-cog text-blue-400"></i></button>
                    <div id="settings-popover" class="hidden absolute right-0 mt-3 w-64 bg-white backdrop-blur-2xl rounded-[2rem] shadow-2xl border border-gray-200 z-[250] overflow-hidden animate-scale-in">
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

        <!-- Stat Cards -->
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

        <!-- Filters -->
        <div class="sticky top-0 z-50 -mx-4 px-4 py-3 bg-gradient-to-b from-[#F0F2F5] via-[#F0F2F5]/90 to-transparent backdrop-blur-md">
            <nav class="premium-card rounded-[2.5rem] px-8 py-4 flex flex-wrap justify-between items-center shadow-2xl gap-4 bg-white border border-gray-200">
                <div class="flex items-center gap-2 p-1 bg-slate-100 rounded-2xl border border-gray-200">
                    <button onclick="filterTab('all')" id="tab-all" class="tab-btn px-6 py-2 rounded-xl text-[10px] font-black uppercase transition text-white bg-gradient-to-r from-blue-600 to-blue-500 shadow-sm active">All</button>
                    <button onclick="filterTab('best')" id="tab-best" class="tab-btn px-6 py-2 rounded-xl text-[10px] font-black uppercase transition text-slate-500 hover:text-blue-600">&#127942; Best</button>
                    <button onclick="filterTab('regular')" id="tab-regular" class="tab-btn px-6 py-2 rounded-xl text-[10px] font-black uppercase transition text-slate-500 hover:text-blue-600">Standard</button>
                </div>
                <div class="flex items-center gap-3 flex-1 justify-end max-w-2xl">
                    <div class="relative flex-1 group min-w-[200px]">
                        <input type="text" id="searchInput" oninput="fetchResults()" placeholder="SEARCH VAULT..." class="w-full bg-slate-100 border border-gray-200 px-6 py-3 rounded-2xl text-[10px] font-bold uppercase text-slate-900 outline-none focus:border-blue-500/50 shadow-inner">
                        <i class="fas fa-search absolute right-5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                    </div>
                    <select id="yearSelect" class="bg-white border border-gray-200 px-4 py-3 rounded-2xl text-[10px] font-black uppercase text-slate-600 outline-none cursor-pointer focus:border-blue-500/50 shadow-inner" onchange="fetchResults()">
                        <option value="" class="bg-white text-slate-500 font-bold uppercase">All Years</option>
                        <?php mysqli_data_seek($years_query, 0); while ($yr = mysqli_fetch_assoc($years_query)): ?>
                        <option value="<?php echo $yr['year']; ?>" class="bg-white text-slate-900 font-bold uppercase"><?php echo $yr['year']; ?></option>
                        <?php endwhile; ?>
                    </select>
                    <select id="adviserSelect" class="bg-white border border-gray-200 px-4 py-3 rounded-2xl text-[10px] font-black uppercase text-slate-600 outline-none cursor-pointer max-w-[150px] focus:border-blue-500/50 shadow-inner" onchange="fetchResults()">
                        <option value="" class="bg-white text-slate-500 font-bold uppercase">All Advisers</option>
                        <?php foreach ($registered_advisers as $adv_name): ?>
                        <option value="<?php echo htmlspecialchars($adv_name); ?>" class="bg-white text-slate-900 font-bold uppercase"><?php echo htmlspecialchars(strtoupper($adv_name)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </nav>
        </div>

        <!-- Table -->
        <section class="premium-card rounded-[3.5rem] shadow-2xl overflow-hidden">
            <div class="p-4 sm:p-8 lg:p-10 border-b border-gray-200 flex flex-col md:flex-row justify-between items-center bg-slate-100">
                <div><h3 class="text-lg font-black uppercase text-slate-900 tracking-tight">Manuscript Directory</h3><p class="text-[9px] font-bold text-blue-400 uppercase tracking-widest mt-1">Purged & Protected Capstone Archives</p></div>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left">
                    <thead class="bg-slate-100 text-[9px] font-black uppercase tracking-widest text-slate-500 border-b border-gray-200">
                        <tr>
                            <th class="px-4 sm:px-10 py-4 sm:py-6">Project Data</th>
                            <th class="px-4 sm:px-10 py-4 sm:py-6 text-center">Adviser</th>
                            <th class="px-4 sm:px-10 py-4 sm:py-6 text-center">Year</th>
                            <th class="px-4 sm:px-10 py-4 sm:py-6 text-center">Classification</th>
                            <th class="px-4 sm:px-10 py-4 sm:py-6 text-right pr-12">Action</th>
                        </tr>
                    </thead>
                    <tbody id="archiveTableBody" class="divide-y divide-gray-100">
                        <?php
                        $initHighlight = isset($_GET['highlight']) ? intval($_GET['highlight']) : 0;
                        renderArchiveRows($conn, '', '', '', 'all', $initHighlight);
                        ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- File Viewer Modal -->
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
                    <a id="viewer-download-btn-adviser" href="#" download class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-400 hover:bg-blue-600 hover:text-white transition flex items-center justify-center"><i class="fas fa-download text-xs"></i></a>
                    <button onclick="closeFileViewer()" class="w-10 h-10 rounded-xl bg-slate-100 text-slate-500 hover:text-red-400 transition flex items-center justify-center"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div class="flex-1 bg-white relative min-h-[70vh]">
                <embed id="viewer-embed-adviser" type="application/pdf" class="w-full h-[75vh] border-0 hidden">
                <iframe id="viewer-iframe-adviser" src="" class="w-full h-[75vh] border-0 hidden" allowfullscreen></iframe>
                <div id="viewer-fallback-adviser" class="hidden absolute inset-0 flex items-center justify-center">
                    <div class="text-center">
                        <div class="w-20 h-20 bg-slate-100 rounded-3xl flex items-center justify-center mx-auto mb-6"><i class="fas fa-file text-3xl text-slate-500"></i></div>
                        <p class="text-sm font-black text-slate-500 uppercase tracking-widest">Preview not available</p>
                        <p class="text-[9px] text-slate-600 font-bold uppercase mt-2">Use the download button to view this file</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Capstone View Modal -->
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

    <!-- Settings Drawer -->
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

    <!-- Profile Modal -->
    <div id="profile-modal" class="fixed inset-0 bg-black/80 backdrop-blur-xl hidden z-[500] items-center justify-center p-6">
        <div id="profile-content" class="bg-gradient-to-b from-white to-slate-100 w-full max-w-md rounded-[3rem] border border-gray-200 shadow-2xl overflow-hidden transform transition-all scale-95 opacity-0">
            <div class="h-32 bg-blue-600/20 relative"><button onclick="closeProfileModal()" class="absolute top-6 right-6 w-8 h-8 rounded-full bg-slate-100 text-slate-900 hover:bg-slate-100 transition"><i class="fas fa-times text-xs"></i></button></div>
            <div class="px-8 pb-10 -mt-12 relative text-center">
                <div class="w-24 h-24 bg-white rounded-[2rem] border-4 border-gray-200 shadow-xl mx-auto flex items-center justify-center text-3xl text-blue-400 mb-4"><i class="fas fa-user-tie"></i></div>
                <h3 class="text-xl font-black text-slate-900 uppercase tracking-tight"><?php echo htmlspecialchars($adviser_name); ?></h3>
                <p class="text-[9px] font-black text-blue-400 uppercase tracking-widest mb-8">Adviser Node</p>
                <div class="space-y-3 text-left">
                    <div class="p-5 rounded-3xl bg-slate-100 border border-gray-200 flex items-center justify-between"><div><p class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Identity</p><p class="text-sm font-bold text-slate-600">@<?php echo htmlspecialchars(str_replace(' ', '_', strtolower($adviser_name))); ?></p></div><i class="fas fa-id-card text-slate-700"></i></div>
                    <div class="p-5 rounded-3xl bg-slate-100 border border-gray-200 flex items-center justify-between"><div><p class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Primary Portal Email Address</p><p class="text-sm font-bold text-slate-600"><?php echo htmlspecialchars($adviser_email); ?></p></div><i class="fas fa-envelope text-slate-700"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Password Modal -->
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

    <!-- Logins Modal -->
    <div id="logins-modal" class="fixed inset-0 bg-black/80 backdrop-blur-xl hidden z-[500] items-center justify-center p-6">
        <div id="logins-content" class="bg-gradient-to-b from-white to-slate-100 w-full max-w-md rounded-[2.5rem] border border-gray-200 shadow-2xl transform scale-95 opacity-0 overflow-hidden">
            <div class="p-8 border-b bg-slate-100 border-gray-200 flex justify-between items-center text-slate-900 font-black uppercase"><h3>Login Activity</h3><button onclick="closeLoginsModal()" class="text-slate-500 hover:text-red-400 transition">X</button></div>
            <div class="p-8 space-y-4 max-h-[400px] overflow-y-auto custom-scrollbar">
                <?php if ($logins_q && mysqli_num_rows($logins_q) > 0): mysqli_data_seek($logins_q, 0); while ($log = mysqli_fetch_assoc($logins_q)): ?>
                <div class="p-4 bg-slate-100 border border-gray-200 rounded-2xl flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-blue-500/10 text-blue-400 flex items-center justify-center"><i class="fas fa-sign-in-alt text-xs"></i></div>
                    <span class="text-[10px] font-bold text-slate-600"><span class="live-time" data-timestamp="<?php echo strtotime($log['login_time']); ?>"><?php echo getRelativeTime(strtotime($log['login_time'])); ?> ago</span></span>
                </div>
                <?php endwhile; else: ?>
                <p class="text-center text-[10px] text-slate-500 font-bold uppercase italic py-4">No logged interaction loops found</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div id="toast-container" class="fixed top-20 left-1/2 -translate-x-1/2 z-[999] transition-all duration-500 -translate-y-20 opacity-0 pointer-events-none">
        <div class="bg-white text-slate-800 px-8 py-4 rounded-2xl shadow-xl flex items-center gap-4 border border-blue-500/30">
            <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center"><i class="fas fa-check text-white text-xs"></i></div>
            <span id="toast-message" class="text-[10px] font-black uppercase tracking-widest"></span>
        </div>
    </div>

    <style>
        @keyframes globalHighlightFade {
            0% { background-color: rgba(59, 130, 246, 0.2) !important; box-shadow: inset 0 0 0 2px #3b82f6 !important; border-radius: inherit; }
            100% { background-color: transparent; box-shadow: inset 0 0 0 0px transparent; }
        }
        .global-highlight-row { animation: globalHighlightFade 3s ease-in-out forwards !important; }
    </style>

    <script>
        const searchInput = document.getElementById('searchInput');
        const yearSelect = document.getElementById('yearSelect');
        const adviserSelect = document.getElementById('adviserSelect');
        const tableBody = document.getElementById('archiveTableBody');
        let currentTab = 'all';

        function fetchResults() {
            const search = searchInput.value;
            const year = yearSelect.value;
            const adviser = adviserSelect.value;
            const urlParams = new URLSearchParams(window.location.search);
            const highlight = urlParams.get('highlight') || '0';

            fetch(`archive_view.php?ajax_search=1&search=${encodeURIComponent(search)}&year=${year}&adviser=${encodeURIComponent(adviser)}&tab=${currentTab}&highlight=${highlight}`)
                .then(res => res.text())
                .then(html => {
                    tableBody.innerHTML = html;
                    if (highlight !== '0') {
                        const row = document.getElementById('row-' + highlight);
                        if (row) {
                            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            row.classList.add('global-highlight-row');
                        }
                    }
                })
                .catch(err => console.error("Error fetching repository:", err));
        }

        function filterTab(tab) {
            currentTab = tab;
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.className = "tab-btn px-6 py-2 rounded-xl text-[10px] font-black uppercase transition text-slate-500 hover:text-blue-600";
            });
            const activeBtn = document.getElementById('tab-' + tab);
            activeBtn.className = "tab-btn px-6 py-2 rounded-xl text-[10px] font-black uppercase transition text-white bg-gradient-to-r from-blue-600 to-blue-500 shadow-sm active";
            fetchResults();
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('highlight')) {
            const highlight = urlParams.get('highlight');
            const row = document.getElementById('row-' + highlight);
            if (row) {
                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                row.classList.add('global-highlight-row');
            }
        }

        function openFileViewer(fileUrl, title, ext) {
            const modal = document.getElementById('viewer-modal');
            const content = document.getElementById('viewer-content');
            const embed = document.getElementById('viewer-embed-adviser');
            const iframe = document.getElementById('viewer-iframe-adviser');
            const fallback = document.getElementById('viewer-fallback-adviser');
            const downloadBtn = document.getElementById('viewer-download-btn-adviser');

            document.getElementById('viewer-title').innerText = title || 'Document Viewer';
            downloadBtn.href = fileUrl;

            embed.classList.add('hidden');
            embed.src = '';
            iframe.classList.add('hidden');
            iframe.src = '';
            fallback.classList.add('hidden');

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => {
                content.classList.remove('scale-95', 'opacity-0');
                content.classList.add('scale-100', 'opacity-100');

                if (ext === 'pdf') {
                    embed.type = 'application/pdf';
                    embed.src = fileUrl;
                    embed.classList.remove('hidden');
                } else if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
                    iframe.src = fileUrl;
                    iframe.classList.remove('hidden');
                } else if (['txt', 'html', 'htm'].includes(ext)) {
                    iframe.src = fileUrl;
                    iframe.classList.remove('hidden');
                } else {
                    fallback.classList.remove('hidden');
                }
            }, 200);
        }

        function closeFileViewer() {
            const modal = document.getElementById('viewer-modal');
            const content = document.getElementById('viewer-content');
            const embed = document.getElementById('viewer-embed-adviser');
            const iframe = document.getElementById('viewer-iframe-adviser');
            content.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.replace('flex', 'hidden');
                embed.src = '';
                iframe.src = '';
            }, 200);
        }

        function viewCapstone(data) {
            document.getElementById('modal-title').innerText = data.title;
            document.getElementById('modal-adviser').innerText = data.adviser || 'None Assigned';
            document.getElementById('modal-year').innerText = data.year;
            document.getElementById('modal-abstract').innerText = data.abstract || 'No abstract content registered within metadata entry.';

            const catBadge = document.getElementById('modal-cat');
            catBadge.innerText = data.category === 'best' ? "Best Capstone Award" : "Standard Research Node";
            catBadge.className = data.category === 'best'
                ? "bg-amber-500/10 text-amber-400 border border-amber-500/20 text-[8px] font-black uppercase px-3 py-1 rounded-full mb-3 inline-block tracking-widest"
                : "bg-blue-500/10 text-blue-400 border border-gray-200 text-[8px] font-black uppercase px-3 py-1 rounded-full mb-3 inline-block tracking-widest";

            const modal = document.getElementById('view-modal');
            const content = document.getElementById('view-content');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => {
                content.classList.remove('scale-95', 'opacity-0');
                content.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function closeViewModal() {
            const content = document.getElementById('view-content');
            content.classList.replace('scale-100', 'scale-95');
            content.classList.replace('opacity-100', 'opacity-0');
            setTimeout(() => { document.getElementById('view-modal').classList.replace('flex', 'hidden'); }, 200);
        }

        function handleNotifClick() {
            if (document.getElementById('settings-popover')) document.getElementById('settings-popover').classList.add('hidden');
            document.getElementById('notif-popover').classList.toggle('hidden');
            document.getElementById('settings-popover').classList.add('hidden');

            const badge = document.getElementById('notif-badge');
            if (badge) {
                badge.style.display = 'none';
                fetch('archive_view.php?action=clear_adviser_notifications&csrf_token=' + encodeURIComponent(CSRF_TOKEN))
                    .then(res => res.json())
                    .then(data => { console.log("Database alignment synced successfully."); })
                    .catch(err => console.error("Notification synchronization aborted:", err));
            }
        }

        function toggleSettingsDropdown() {
            if (document.getElementById('notif-dropdown')) document.getElementById('notif-dropdown').classList.add('hidden');
            document.getElementById('settings-popover').classList.toggle('hidden');
            document.getElementById('notif-popover').classList.add('hidden');
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

        function animateIn(id, contentId) {
            const m = document.getElementById(id);
            const c = document.getElementById(contentId);
            m.classList.remove('hidden');
            m.classList.add('flex');
            setTimeout(() => { c.classList.remove('scale-95', 'opacity-0'); c.classList.add('scale-100', 'opacity-100'); }, 10);
        }
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

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebar-backdrop').classList.toggle('open');
        }

        const CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';
        const passForm = document.getElementById('changePasswordForm');
        const passError = document.getElementById('password-error');
        if (passForm) {
            passForm.onsubmit = function(e) {
                e.preventDefault();
                const newP = document.getElementById('new_pass').value;
                const confP = document.getElementById('conf_pass').value;
                if (newP !== confP) { passError.innerText = "New passwords do not match!"; passError.classList.remove('hidden'); return; }

                const formData = new FormData(this);
                formData.append('csrf_token', CSRF_TOKEN);
                fetch('../backend/auth/update_adviser_pass.php', { method: 'POST', body: formData })
                    .then(res => res.text()).then(data => {
                        const response = data.trim();
                        if (response === 'success') {
                            closePasswordModal();
                            showToast("Password successfully changed", "success");
                            passForm.reset();
                            passError.classList.add('hidden');
                        } else if (response === 'incorrect_current') {
                            passError.innerText = "Current password you entered is incorrect";
                            passError.classList.remove('hidden');
                        } else {
                            passError.innerText = response;
                            passError.classList.remove('hidden');
                        }
                    });
            };
        }

        function showToast(msg, type = 'success') {
            const toast = document.getElementById('toast-container');
            const card = toast.querySelector('div');
            const iconBg = toast.querySelector('.rounded-full');
            const icon = toast.querySelector('i');

            document.getElementById('toast-message').innerText = msg;

            if (type === 'error') {
                card.className = "bg-white text-slate-800 px-8 py-4 rounded-2xl shadow-xl flex items-center gap-4 border border-rose-500/30";
                iconBg.className = "w-8 h-8 rounded-full bg-rose-500 flex items-center justify-center";
                icon.className = "fas fa-exclamation-triangle text-white text-xs";
            } else {
                card.className = "bg-white text-slate-800 px-8 py-4 rounded-2xl shadow-xl flex items-center gap-4 border border-blue-500/30";
                iconBg.className = "w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center";
                icon.className = "fas fa-check text-white text-xs";
            }

            toast.classList.remove('-translate-y-20', 'opacity-0');
            toast.classList.add('translate-y-0', 'opacity-100');
            setTimeout(() => { toast.classList.add('-translate-y-20', 'opacity-0'); toast.classList.remove('translate-y-0', 'opacity-100'); }, 3000);
        }

        window.onclick = function(e) {
            if (e.target.closest('#archiveTableBody a')) return;
            if (!e.target.closest('.nav-icon-container') && !e.target.closest('#settings-drawer') && !e.target.closest('#notif-popover') && !e.target.closest('#view-content')) {
                document.getElementById('settings-popover').classList.add('hidden');
                document.getElementById('notif-popover').classList.add('hidden');
            }
        };

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
