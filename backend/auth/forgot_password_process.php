<?php
// REMOVED FOR SECURITY — This endpoint was a duplicate of reset_password_final.php
// without rate limiting. Use reset_password_final.php instead.
http_response_code(410);
header('Content-Type: application/json');
echo json_encode(['status' => 'error', 'message' => 'This endpoint has been removed. Please use the standard password recovery flow.']);
?>