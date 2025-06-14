<?php
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Return session configuration for JavaScript
echo json_encode(getSessionConfig());
?>