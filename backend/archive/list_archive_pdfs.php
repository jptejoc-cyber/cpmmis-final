<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$allowed_roles = ['admin', 'adviser', 'group'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $allowed_roles, true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

$archive_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($archive_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid archive entry.']);
    exit;
}

$stmt = $conn->prepare("SELECT id, title, file_path FROM capstone_archive WHERE id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to read the archive entry.']);
    exit;
}
$stmt->bind_param('i', $archive_id);
$stmt->execute();
$archive = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$archive) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Archive entry not found.']);
    exit;
}

$base = realpath(__DIR__ . '/../../uploads/archive');
$stored_name = basename((string)($archive['file_path'] ?? ''));
$path = ($base !== false && $stored_name !== '') ? realpath($base . DIRECTORY_SEPARATOR . $stored_name) : false;

if ($base === false || $path === false || !is_file($path) || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'The archived file could not be found.']);
    exit;
}

$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$entries = [];
$kind = $extension;
$message = '';
$truncated = false;

if ($extension === 'pdf') {
    $entries[] = [
        'entry' => null,
        'name' => 'Full project PDF',
        'label' => 'Project Manuscript',
        'chapter' => null,
        'size' => (int)filesize($path),
    ];
} elseif ($extension === 'zip') {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'The archive could not be opened.']);
        exit;
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        if (count($entries) >= 200) {
            $truncated = true;
            break;
        }

        $entry_name = $zip->getNameIndex($i);
        if ($entry_name === false || $entry_name === '' || substr($entry_name, -1) === '/') {
            continue;
        }

        $normalized_name = str_replace('\\', '/', $entry_name);
        $entry_file = basename($normalized_name);
        if (strtolower(pathinfo($entry_file, PATHINFO_EXTENSION)) !== 'pdf') {
            continue;
        }

        $stat = $zip->statIndex($i);
        $stem = pathinfo($entry_file, PATHINFO_FILENAME);
        $chapter = null;
        $label = $stem !== '' ? $stem : 'PDF Document';

        if (preg_match('/chapter[\s._-]*([0-9]{1,2})/i', $stem, $matches)) {
            $chapter = (int)$matches[1];
            $label = 'Chapter ' . $chapter;
        }

        $entries[] = [
            'entry' => $i,
            'name' => $entry_file,
            'label' => $label,
            'chapter' => $chapter,
            'size' => (int)($stat['size'] ?? 0),
        ];
    }
    $zip->close();

    usort($entries, function ($a, $b) {
        $a_chapter = $a['chapter'] === null ? PHP_INT_MAX : (int)$a['chapter'];
        $b_chapter = $b['chapter'] === null ? PHP_INT_MAX : (int)$b['chapter'];
        if ($a_chapter !== $b_chapter) {
            return $a_chapter <=> $b_chapter;
        }
        return strnatcasecmp($a['name'], $b['name']);
    });

    if (count($entries) === 0) {
        $message = 'No PDF documents were found inside this archive.';
    }
} else {
    http_response_code(415);
    echo json_encode(['status' => 'error', 'message' => 'This archive does not contain a viewable PDF.']);
    exit;
}

echo json_encode([
    'status' => 'success',
    'archive' => [
        'id' => (int)$archive['id'],
        'title' => $archive['title'],
    ],
    'kind' => $kind,
    'entries' => $entries,
    'message' => $message,
    'truncated' => $truncated,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
