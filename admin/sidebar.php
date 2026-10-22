<?php
// Get the current filename (e.g., 'admin_dashboard.php') to dynamically set active classes
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- REUSABLE SIDEBAR COMPONENT -->
<aside class="w-64 glass-panel h-screen flex flex-col justify-between border-r border-gray-200 z-20 shrink-0">
    <div>
        <!-- Institutional Branding Area -->
        <div class="p-6 border-b border-gray-200 flex items-center gap-3">
            <div class="bg-white p-1.5 rounded-xl shadow-md">
                <img src="../chmsu.png" class="w-7 h-7 object-contain" alt="CHMSU Logo" onerror="this.src='https://placehold.co/40'">
            </div>
            <div>
                <h2 class="text-lg font-black tracking-tighter text-slate-900">CPMMIS</h2>
                <span class="text-[9px] font-bold text-blue-400 tracking-widest block uppercase">Management Hub</span>
            </div>
        </div>

        <!-- Dynamic Menu List Links -->
        <nav class="mt-6 space-y-1 px-3">
            <a href="admin_dashboard.php" class="sidebar-link flex items-center gap-4 px-4 py-3 rounded-xl text-sm font-semibold <?= ($current_page == 'admin_dashboard.php') ? 'active text-blue-400' : 'text-slate-500' ?>">
                <i class="fas fa-chart-pie text-base w-5 text-center"></i> Dashboard
            </a>
            <a href="archive.php" class="sidebar-link flex items-center gap-4 px-4 py-3 rounded-xl text-sm font-semibold <?= ($current_page == 'archive.php') ? 'active text-blue-400' : 'text-slate-500' ?>">
                <i class="fas fa-archive text-base w-5 text-center"></i> Capstone Archive
            </a>
            <a href="advisers.php" class="sidebar-link flex items-center gap-4 px-4 py-3 rounded-xl text-sm font-semibold <?= ($current_page == 'advisers.php') ? 'active text-blue-400' : 'text-slate-500' ?>">
                <i class="fas fa-chalkboard-user text-base w-5 text-center"></i> Adviser
            </a>
            <a href="groups.php" class="sidebar-link flex items-center gap-4 px-4 py-3 rounded-xl text-sm font-semibold <?= ($current_page == 'groups.php') ? 'active text-blue-400' : 'text-slate-500' ?>">
                <i class="fas fa-users text-base w-5 text-center"></i> Groups
            </a>
            <a href="group_submissions.php" class="sidebar-link flex items-center gap-4 px-4 py-3 rounded-xl text-sm font-semibold <?= ($current_page == 'group_submissions.php') ? 'active text-blue-400' : 'text-slate-500' ?>">
                <i class="fas fa-file-upload text-base w-5 text-center"></i> Submission
            </a>
            <a href="submissions.php" class="sidebar-link flex items-center gap-4 px-4 py-3 rounded-xl text-sm font-semibold <?= ($current_page == 'submissions.php') ? 'active text-blue-400' : 'text-slate-500' ?>">
                <i class="fas fa-tasks text-base w-5 text-center"></i> Submissions & Letters
            </a>
            <a href="reports.php" class="sidebar-link flex items-center gap-4 px-4 py-3 rounded-xl text-sm font-semibold <?= ($current_page == 'reports.php') ? 'active text-blue-400' : 'text-slate-500' ?>">
                <i class="fas fa-chart-line text-base w-5 text-center"></i> Reports & Analytics
            </a>
            <a href="maintenance.php" class="sidebar-link flex items-center gap-4 px-4 py-3 rounded-xl text-sm font-semibold <?= ($current_page == 'maintenance.php') ? 'active text-blue-400' : 'text-slate-500' ?>">
                <i class="fas fa-screwdriver-wrench text-base w-5 text-center"></i> System Maintenance
            </a>
        </nav>
    </div>

    <!-- Console Version Footer Label -->
    <div class="p-4 border-t border-gray-200 bg-slate-100 text-center text-[10px] text-slate-500 font-semibold tracking-wider uppercase">
        CPMMIS Console v3.0
    </div>
</aside>