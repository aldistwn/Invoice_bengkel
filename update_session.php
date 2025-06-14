<?php
require_once 'config.php';

header('Content-Type: application/json');

// Check if request is POST and user is logged in
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

$response = ['success' => false];

try {
    switch ($action) {
        case 'update_activity':
            // Update last activity time
            $_SESSION['last_activity'] = time();
            
            // Update session in database
            updateSessionActivity();
            
            $response['success'] = true;
            $response['message'] = 'Activity updated';
            
            // Log activity
            logActivity('session_activity', 'Session activity updated');
            break;
            
        case 'extend_session':
            // Reset session timeout
            $_SESSION['last_activity'] = time();
            
            // Extend session in database by 1 hour
            if (isset($_SESSION['session_token'])) {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("
                    UPDATE user_sessions 
                    SET expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR)
                    WHERE session_token = ?
                ");
                $stmt->execute([$_SESSION['session_token']]);
            }
            
            $response['success'] = true;
            $response['message'] = 'Session extended';
            $response['new_expiry'] = date('Y-m-d H:i:s', time() + 3600);
            
            // Log activity
            logActivity('session_extended', 'Session extended by user');
            break;
            
        case 'check_session':
            // Check if session is still valid
            if (isLoggedIn()) {
                $remaining_time = 3600 - (time() - ($_SESSION['last_activity'] ?? time()));
                
                $response['success'] = true;
                $response['session_valid'] = true;
                $response['remaining_time'] = max(0, $remaining_time);
                $response['remaining_formatted'] = gmdate('i:s', max(0, $remaining_time));
            } else {
                $response['success'] = true;
                $response['session_valid'] = false;
                $response['remaining_time'] = 0;
            }
            break;
            
        default:
            http_response_code(400);
            $response['error'] = 'Invalid action';
    }
    
} catch (Exception $e) {
    http_response_code(500);
    $response['error'] = 'Server error: ' . $e->getMessage();
}

echo json_encode($response);
?>