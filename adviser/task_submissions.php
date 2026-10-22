<?php
include('../backend/session_init.php');
include('../backend/db_config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'adviser') {
    header("Location: ../index.php");
    exit();
}

$adviser_id = (int)$_SESSION['user_id'];
$task_id = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;

$task_q = mysqli_query($conn, "SELECT * FROM adviser_tasks WHERE id = $task_id AND adviser_id = $adviser_id");
if(mysqli_num_rows($task_q) == 0) {
    echo "Task not found or unauthorized.";
    exit;
}
$task = mysqli_fetch_assoc($task_q);

// Submissions for this task
$subs_q = mysqli_query($conn, "
    SELECT s.*, g.username as group_name 
    FROM group_submissions s 
    JOIN `group` g ON s.group_id = g.id 
    WHERE s.task_id = $task_id 
    ORDER BY s.submitted_at DESC
");

?>
<!DOCTYPE html>
<html lang="en" style="background-color: #F0F2F5; color-scheme: light;">
<head>
    <meta charset="UTF-8">
    <meta name="color-scheme" content="light">
    <style>html, body { background-color: #F0F2F5 !important; color-scheme: light; }</style>
    <title>Task Submissions | Adviser</title>
    <link rel="icon" type="image/png" href="../chmsu.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#F0F2F5">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --chmsu-blue: #1877F2; --bg-deep: #F0F2F5; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-deep); background-image: none; background-size: 32px 32px; color: #1c1e21; overflow-x: hidden; }
        .premium-card { background: #ffffff; border: 1px solid #dadde1; }
    </style>
</head>
<body class="min-h-screen p-8 lg:p-12" style="background-color: #F0F2F5; color-scheme: light;">
    <div class="max-w-6xl mx-auto space-y-8">
        
        <div class="flex items-center gap-4">
            <a href="submissions.php" class="w-10 h-10 rounded-xl bg-slate-100 border border-gray-200 text-slate-500 hover:text-blue-400 hover:bg-blue-950/30 transition flex items-center justify-center">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-xl font-black uppercase text-slate-900 tracking-tight leading-none"><?php echo htmlspecialchars($task['caption']); ?></h1>
                <p class="text-[9px] font-bold text-blue-400 uppercase tracking-widest mt-1">Submissions Overview</p>
            </div>
        </div>

        <section class="premium-card rounded-[3rem] shadow-2xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-100 text-[9px] font-black uppercase tracking-widest text-slate-500 border-b border-gray-200">
                        <tr>
                            <th class="px-10 py-5">Research Group</th>
                            <th class="px-10 py-5">Submission Date</th>
                            <th class="px-10 py-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-[11px] font-bold text-slate-600">
                        <?php if(mysqli_num_rows($subs_q) > 0): while($row = mysqli_fetch_assoc($subs_q)): ?>
                            <tr class="hover:bg-slate-100 transition-all duration-300">
                                <td class="px-10 py-6 font-extrabold text-slate-900 uppercase text-sm leading-tight">
                                    <?php echo htmlspecialchars($row['group_name']); ?>
                                </td>
                                <td class="px-10 py-6 text-[11px] font-bold text-slate-500 uppercase">
                                    <?php echo date('M d, Y g:i A', strtotime($row['submitted_at'])); ?>
                                </td>
                                <td class="px-10 py-6 text-right flex items-center justify-end gap-2">
                                    <a href="../backend/serve_file.php?type=submissions&file=<?php echo urlencode($row['file_path']); ?>" target="_blank" title="View File" class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-400 hover:bg-blue-600 hover:text-white transition shadow-sm">
                                        <i class="fas fa-eye text-xs"></i>
                                    </a>
                                    <a href="../backend/serve_file.php?type=submissions&file=<?php echo urlencode($row['file_path']); ?>" download class="px-4 py-2 bg-blue-500/10 text-blue-400 rounded-xl text-[9px] font-black uppercase hover:bg-blue-600 hover:text-white transition">Download File</a>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="3" class="px-10 py-20 text-center text-slate-500 font-black text-[10px] uppercase italic tracking-widest">No submissions yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
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
