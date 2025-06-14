<?php
// Database configuration untuk Alfina AC Mobil
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Default XAMPP password kosong
define('DB_NAME', 'alfina_ac_mobil');

// Set session timeout BEFORE session_start()
ini_set('session.gc_maxlifetime', 120); // 2 minutes for testing
ini_set('session.cookie_lifetime', 120);

// Start session AFTER ini_set
session_start();

// Make session config global
$GLOBALS['session_config'] = $session_config;

// Create database connection
function getDBConnection() {
    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Fungsi untuk format currency Indonesia
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Fungsi untuk generate invoice number
function generateInvoiceNumber() {
    return 'INV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

// Fungsi untuk format tanggal Indonesia
function formatDateIndo($date) {
    $months = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $timestamp = strtotime($date);
    $day = date('d', $timestamp);
    $month = $months[(int)date('m', $timestamp)];
    $year = date('Y', $timestamp);
    
    return $day . ' ' . $month . ' ' . $year;
}

// Fungsi untuk update stok parts
function updateStock($part_id, $qty, $movement_type, $invoice_id = null, $keterangan = '') {
    $pdo = getDBConnection();
    
    // Insert ke stock_movements
    $stmt = $pdo->prepare("
        INSERT INTO stock_movements (part_id, invoice_id, movement_type, qty, keterangan) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$part_id, $invoice_id, $movement_type, $qty, $keterangan]);
    
    // Update stok di tabel parts
    if ($movement_type == 'out') {
        $stmt = $pdo->prepare("UPDATE parts SET stok_gudang = stok_gudang - ? WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("UPDATE parts SET stok_gudang = stok_gudang + ? WHERE id = ?");
    }
    $stmt->execute([$qty, $part_id]);
}

// Test koneksi database
function testConnection() {
    try {
        $pdo = getDBConnection();
        return "Koneksi database berhasil!";
    } catch(Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

// USER MANAGEMENT FUNCTIONS
// Session already started above

// Check if user is logged in and session is valid
function isLoggedIn() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        return false;
    }
    
    // Check session timeout (configurable)
    if (isset($_SESSION['last_activity'])) {
        $inactive_time = time() - $_SESSION['last_activity'];
        
        // Get session config safely
        $session_timeout = 3600; // default 60 minutes
        if (isset($GLOBALS['session_config']) && is_array($GLOBALS['session_config'])) {
            $session_timeout = $GLOBALS['session_config']['timeout'];
        }
        
        if ($inactive_time > $session_timeout) {
            // Session expired, destroy it
            logActivity('session_expired', 'Session expired due to inactivity');
            session_destroy();
            return false;
        }
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    
    // Check if session token exists in database (if user_sessions table exists)
    if (isset($_SESSION['session_token'])) {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("
                SELECT * FROM user_sessions 
                WHERE session_token = ? AND expires_at > NOW()
            ");
            $stmt->execute([$_SESSION['session_token']]);
            
            if (!$stmt->fetch()) {
                // Session token not found or expired in database
                session_destroy();
                return false;
            }
        } catch (Exception $e) {
            // If user_sessions table doesn't exist, just skip this check
            // This allows basic functionality even without full user management
        }
    }
    
    return true;
}

// Update session activity
function updateSessionActivity() {
    if (isset($_SESSION['session_token'])) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            UPDATE user_sessions 
            SET expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR)
            WHERE session_token = ?
        ");
        $stmt->execute([$_SESSION['session_token']]);
    }
}

// Get current user info
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Check user role/permission
function hasRole($required_roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $user_role = $_SESSION['user_role'];
    
    if (is_string($required_roles)) {
        $required_roles = [$required_roles];
    }
    
    return in_array($user_role, $required_roles);
}

// Role hierarchy check
function hasMinimumRole($minimum_role) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $role_hierarchy = [
        'viewer' => 1,
        'operator' => 2,
        'admin' => 3,
        'super_admin' => 4
    ];
    
    $user_level = $role_hierarchy[$_SESSION['user_role']] ?? 0;
    $required_level = $role_hierarchy[$minimum_role] ?? 0;
    
    return $user_level >= $required_level;
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

// Redirect if doesn't have required role
function requireRole($required_roles) {
    requireLogin();
    
    if (!hasRole($required_roles)) {
        header("Location: index.php?error=Akses ditolak");
        exit;
    }
}

// Redirect if doesn't have minimum role
function requireMinimumRole($minimum_role) {
    requireLogin();
    
    if (!hasMinimumRole($minimum_role)) {
        header("Location: index.php?error=Akses ditolak");
        exit;
    }
}

// Log user activity
function logActivity($action, $description = '', $user_id = null) {
    if (!$user_id && isLoggedIn()) {
        $user_id = $_SESSION['user_id'];
    }
    
    if (!$user_id) return;
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT INTO user_activity_log (user_id, action, description, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $action, $description, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {
        // Silently fail to avoid breaking the application
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// Generate secure session token
function generateSessionToken() {
    return bin2hex(random_bytes(32));
}

// Clean expired sessions
function cleanExpiredSessions() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE expires_at < NOW()");
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Failed to clean expired sessions: " . $e->getMessage());
    }
}

// Get role display name
function getRoleDisplayName($role) {
    $roles = [
        'super_admin' => 'Super Admin',
        'admin' => 'Administrator',
        'operator' => 'Operator',
        'viewer' => 'Viewer'
    ];
    
    return $roles[$role] ?? $role;
}

// Get role badge class
function getRoleBadgeClass($role) {
    $classes = [
        'super_admin' => 'bg-danger',
        'admin' => 'bg-primary',
        'operator' => 'bg-success',
        'viewer' => 'bg-secondary'
    ];
    
    return $classes[$role] ?? 'bg-secondary';
}

// Get session configuration for JavaScript
function getSessionConfig() {
    // Default config
    $default_config = [
        'timeout' => 60 * 60 * 1000,    // 60 minutes in milliseconds
        'warning' => 5 * 60 * 1000      // 5 minutes in milliseconds
    ];
    
    // Try to get from global config
    if (isset($GLOBALS['session_config']) && is_array($GLOBALS['session_config'])) {
        return [
            'timeout' => $GLOBALS['session_config']['timeout'] * 1000,    // convert to milliseconds for JS
            'warning' => $GLOBALS['session_config']['warning'] * 1000     // convert to milliseconds for JS
        ];
    }
    
    return $default_config;
}
?>