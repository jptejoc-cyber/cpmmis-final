<?php
include('../backend/session_init.php');
include('../backend/db_config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit;
}

$search = $_GET['search'] ?? '';
$tab = $_GET['tab'] ?? 'all';
$year = $_GET['year'] ?? 'all';
$adviser = $_GET['adviser'] ?? 'all';

$highlight_id = isset($_GET['highlight']) ? (int)$_GET['highlight'] : null;

$conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $conditions[] = "title LIKE CONCAT(?, '%')";
    $params[] = $search;
    $types .= 's';
}

if ($tab === 'best') {
    $conditions[] = "category = 'best'";
} elseif ($tab === 'regular') {
    $conditions[] = "category = 'regular'";
}

if ($year !== 'all') {
    $conditions[] = "year = ?";
    $params[] = $year;
    $types .= 's';
}

if ($adviser !== 'all') {
    $conditions[] = "adviser = ?";
    $params[] = $adviser;
    $types .= 's';
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
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $row_id = $row['id'];
        $displayYear = htmlspecialchars($row['year']); 
        
        // Highlight logic matching your premium system setup
        $highlight_class = ($highlight_id == $row_id) ? 'bg-blue-500/10 border-l-4 border-blue-500' : 'hover:bg-slate-100';

        // Category Badge styling synced with the system colors
        $catStyle = "bg-blue-500/10 text-blue-400 border border-blue-500/20";
        $catLabel = "Standard Project";
        if ($row['category'] == 'best') {
            $catStyle = "bg-amber-500/10 text-amber-400 border border-amber-500/20"; 
            $catLabel = "🏆 Best Project";
        }

        // --- USE AUTHENTICATED PROXY TO SERVE FILES ---
        $fileName = basename($row['file_path']);
        $filePath = '../backend/serve_file.php?type=archive&file=' . rawurlencode($fileName);

        echo '
        <tr id="row-' . $row_id . '" class="transition-all duration-300 border-b border-gray-200 ' . $highlight_class . '">
            <td class="px-10 py-6">
                <span class="font-extrabold text-slate-900 uppercase text-xs block mb-1">' . htmlspecialchars($row['title']) . '</span>
            </td>
            
            <td class="px-10 py-6 text-center text-xs font-bold text-slate-600 uppercase">' . (!empty($row['adviser']) ? strtoupper(htmlspecialchars($row['adviser'])) : 'NO ADVISER ASSIGNED') . '</td>
            
            <td class="px-10 py-6 text-center text-xs font-bold text-slate-500">' . $displayYear . '</td>
            
            <td class="px-10 py-6 text-center">
                <span class="px-3 py-1.5 inline-block rounded-xl text-[8px] font-black uppercase tracking-wider ' . $catStyle . '">
                    ' . $catLabel . '
                </span>
            </td>
            
            <td class="px-10 py-6 text-center">
                <div class="inline-flex items-center justify-center gap-3">
                    <a href="' . $filePath . '" 
                       download="' . htmlspecialchars($row['title']) . '" 
                       class="w-10 h-10 inline-flex items-center justify-center bg-slate-100 border border-gray-200 text-blue-400 hover:bg-blue-600 hover:text-white rounded-xl transition shadow-sm"
                       title="Download File">
                        <i class="fas fa-download text-xs"></i>
                    </a>
                    
                    <button onclick="confirmDelete(' . $row_id . ')" 
                            class="w-10 h-10 inline-flex items-center justify-center bg-slate-100 border border-gray-200 text-red-400 hover:bg-red-600 hover:text-white rounded-xl transition shadow-sm"
                            title="Remove Entry">
                        <i class="fas fa-trash text-xs"></i>
                    </button>
                </div>
            </td>
        </tr>';
    }
} else {
    echo '<tr><td colspan="5" class="px-10 py-20 text-center text-slate-500 uppercase text-[10px] font-black italic tracking-widest">No matching assets logged in terminal node.</td></tr>';
}
?>