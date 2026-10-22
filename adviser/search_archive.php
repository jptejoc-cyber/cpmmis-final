<?php
include('../backend/session_init.php');
include('../backend/db_config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'adviser') {
    header('HTTP/1.0 401 Unauthorized');
    exit;
}

$keyword = trim($_GET['keyword'] ?? '');
$filter = $_GET['filter'] ?? 'all';
$highlight = isset($_GET['highlight']) ? intval($_GET['highlight']) : 0;

// 1. AUTO-SYNC WITH DATABASE STRUCTURE
$columns_query = mysqli_query($conn, "SHOW COLUMNS FROM capstone_archive");
$cols = [];
while($c = mysqli_fetch_assoc($columns_query)) { $cols[] = $c['Field']; }

$titleCol   = in_array('project_title', $cols) ? 'project_title' : 'title';
$adviserCol = in_array('adviser_name', $cols) ? 'adviser_name' : 'adviser';
$yearCol    = in_array('year', $cols) ? 'year' : 'year_published';
$classCol   = in_array('classification', $cols) ? 'classification' : 'category';

// 2. FETCH DATA WITH SEQUENTIAL SEARCH LOGIC
$sql = "SELECT * FROM capstone_archive WHERE 1=1";
$params = [];
$types = '';

if (!empty($keyword)) {
    $sql .= " AND $titleCol LIKE CONCAT(?, '%')";
    $params[] = $keyword;
    $types .= 's';
}

if ($filter === 'best') {
    $sql .= " AND LOWER($classCol) LIKE '%best%'";
} elseif ($filter === 'standard') {
    $sql .= " AND (LOWER($classCol) NOT LIKE '%best%' OR $classCol IS NULL OR $classCol = '')";
}

$sql .= " ORDER BY $titleCol ASC LIMIT 500";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = mysqli_query($conn, $sql);
}

// 3. OUTPUT ROWS
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $dbId      = $row['id'];
        $dbTitle   = $row[$titleCol] ?? 'Untitled';
        $dbAdviser = $row[$adviserCol] ?? 'N/A';
        $dbYear    = $row[$yearCol] ?? 'N/A';
        $dbClass   = strtolower(trim($row[$classCol] ?? ''));
        $dbFile    = $row['file_path'] ?? $row['file'] ?? '';

        $highlightClass = ($dbId == $highlight) ? 'highlight-row' : '';

        echo '<tr id="row-'.$dbId.'" class="hover:bg-slate-50/50 transition duration-300 '.$highlightClass.'">
                <td class="px-12 py-10">
                    <span class="text-[13px] font-black text-slate-800 uppercase tracking-tight">'.htmlspecialchars($dbTitle).'</span>
                </td>
                <td class="px-12 py-10 text-slate-500 font-bold uppercase text-[10px] tracking-wide">
                    '.htmlspecialchars($dbAdviser).'
                </td>
                <td class="px-12 py-10 text-slate-500 font-bold text-[10px] tracking-widest">
                    '.htmlspecialchars($dbYear).'
                </td>
                <td class="px-12 py-10 text-center">';

        if (strpos($dbClass, 'best') !== false) {
            echo '<span class="inline-flex items-center gap-2 text-amber-600 text-[8px] font-black uppercase tracking-[0.2em]">🏆 BEST CAPSTONE</span>';
        } else {
            echo '<span class="text-slate-500 text-[8px] font-black uppercase tracking-[0.2em]">STANDARD</span>';
        }

        echo '</td>
              <td class="px-12 py-10 text-right">
                <div class="flex justify-end gap-2">';

        if (!empty($dbFile)) {
            echo '<a href="../backend/serve_file.php?type=archive&file='.rawurlencode(basename($dbFile)).'" download
                   class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-white transition shadow-sm"
                   title="Download File">
                    <i class="fas fa-download text-sm"></i>
                </a>';
        } else {
            echo '<button disabled title="No file uploaded" class="w-12 h-12 rounded-2xl bg-slate-50 text-slate-700 cursor-not-allowed">
                    <i class="fas fa-file-excel text-sm"></i>
                </button>';
        }

        echo '</div></td></tr>';
    }
} else {
    echo '<tr><td colspan="5" class="py-24 text-center text-slate-600 font-black text-[11px] uppercase tracking-widest italic">No matching records found in archive</td></tr>';
}
?>
