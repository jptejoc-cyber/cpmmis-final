<?php
include(__DIR__ . '/../session_init.php');
include(__DIR__ . '/../db_config.php');

$allowed_roles = ['admin', 'adviser', 'group'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $allowed_roles, true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Unauthorized access.');
}

$archive_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($archive_id <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Invalid archive entry.');
}

$stmt = $conn->prepare("SELECT id, title, file_path FROM capstone_archive WHERE id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Unable to read the archive entry.');
}
$stmt->bind_param('i', $archive_id);
$stmt->execute();
$archive = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$archive) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Archive entry not found.');
}

$base = realpath(__DIR__ . '/../../uploads/archive');
$stored_name = basename((string)($archive['file_path'] ?? ''));
$path = ($base !== false && $stored_name !== '') ? realpath($base . DIRECTORY_SEPARATOR . $stored_name) : false;

if ($base === false || $path === false || !is_file($path) || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('The archived file could not be found.');
}

function archive_pdf_safe_name(string $name, string $fallback = 'archive-document.pdf'): string
{
    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[\x00-\x1F\x7F"\\\\;]+/', '_', $name);
    $name = trim((string)$name);
    if ($name === '' || $name === '.' || $name === '..') {
        $name = $fallback;
    }
    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'pdf') {
        $name .= '.pdf';
    }
    return $name;
}

$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$download = isset($_GET['download']) && $_GET['download'] === '1';
$stream = null;
$zip = null;
$display_name = '';
$content_length = 0;

if ($extension === 'pdf') {
    $display_name = archive_pdf_safe_name((string)$archive['title'], 'project-manuscript.pdf');
    $stream = fopen($path, 'rb');
    $content_length = (int)filesize($path);
} elseif ($extension === 'zip') {
    $entry_value = $_GET['entry'] ?? '';
    if ($entry_value === '' || !ctype_digit((string)$entry_value)) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        exit('A PDF entry was not specified.');
    }

    $entry_index = (int)$entry_value;
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        http_response_code(422);
        header('Content-Type: text/plain; charset=utf-8');
        exit('The archive could not be opened.');
    }

    $stat = $zip->statIndex($entry_index);
    $entry_name = $zip->getNameIndex($entry_index);
    $normalized_name = $entry_name !== false ? str_replace('\\', '/', $entry_name) : '';

    if ($stat === false || $normalized_name === '' || substr($normalized_name, -1) === '/'
        || strtolower(pathinfo($normalized_name, PATHINFO_EXTENSION)) !== 'pdf') {
        $zip->close();
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        exit('The requested PDF was not found in this archive.');
    }

    $display_name = archive_pdf_safe_name($normalized_name);
    $content_length = (int)($stat['size'] ?? 0);
    $stream = $zip->getStream($entry_name);
    if ($stream === false) {
        $zip->close();
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        exit('The PDF could not be opened.');
    }
} else {
    http_response_code(415);
    header('Content-Type: text/plain; charset=utf-8');
    exit('This archive does not contain a viewable PDF.');
}

if ($stream === false || $stream === null) {
    if ($zip instanceof ZipArchive) {
        $zip->close();
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('The PDF could not be opened.');
}

// Release the session lock before streaming the file so other requests are not
// held up while the browser reads a large PDF.
session_write_close();

$disposition = $download ? 'attachment' : 'inline';
header('Content-Type: application/pdf');
header('Content-Disposition: ' . $disposition . '; filename="' . $display_name . '"; filename*=UTF-8\'\'' . rawurlencode($display_name));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
if ($content_length > 0) {
    header('Content-Length: ' . $content_length);
}

if ($extension === 'pdf') {
    readfile($path);
} else {
    fpassthru($stream);
    fclose($stream);
    $zip->close();
}
exit;
