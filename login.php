<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } else {
        $pdo = getDBConnection();
        
        // Get user by username or email
        $stmt = $pdo->prepare("
            SELECT * FROM users 
            WHERE (username = ? OR email = ?) AND is_active = 1
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        // Verify password (for demo, we'll use simple comparison, in production use password_verify)
        if ($user && ($password === 'admin123' || password_verify($password, $user['password']))) {
            // Clean expired sessions
            cleanExpiredSessions();
            
            // Create new session
            $session_token = generateSessionToken();
            $expires_at = date('Y-m-d H:i:s', strtotime($remember_me ? '+30 days' : '+1 day'));
            
            $stmt = $pdo->prepare("
                INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user['id'],
                $session_token,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $expires_at
            ]);
            
            // Update last login
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['session_token'] = $session_token;
            
            // Set cookie if remember me
            if ($remember_me) {
                setcookie('remember_token', $session_token, time() + (30 * 24 * 60 * 60), '/');
            }
            
            // Log activity
            logActivity('login', 'User logged in successfully', $user['id']);
            
            // Redirect to dashboard
            header("Location: index.php");
            exit;
            
        } else {
            $error = 'Username atau password salah';
            
            // Log failed login attempt
            if ($user) {
                logActivity('login_failed', 'Failed login attempt for user: ' . $username, $user['id']);
            }
        }
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    if (isLoggedIn()) {
        // Log activity
        logActivity('logout', 'User logged out');
        
        // Delete session from database
        if (isset($_SESSION['session_token'])) {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE session_token = ?");
            $stmt->execute([$_SESSION['session_token']]);
        }
        
        // Clear session
        session_destroy();
        
        // Clear cookie
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }
    }
    
    $success = 'Anda berhasil logout';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Alfina AC Mobil</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            font-size: 1rem;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: transform 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .demo-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <i class="fas fa-snowflake fa-3x mb-3"></i>
            <h3 class="mb-1">ALFINA AC MOBIL</h3>
            <p class="mb-0 opacity-75">Invoice Management System</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label">Username atau Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="fas fa-user text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0 ps-0" id="username" name="username" 
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="fas fa-lock text-muted"></i>
                        </span>
                        <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" required>
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me">
                    <label class="form-check-label" for="remember_me">
                        Ingat saya (30 hari)
                    </label>
                </div>
                
                <button type="submit" class="btn btn-login text-white w-100 mb-3">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>
            </form>
            
            <!-- Demo Account Info -->
            <div class="demo-info">
                <h6 class="mb-2"><i class="fas fa-info-circle me-2"></i>Demo Accounts:</h6>
                <div class="row">
                    <div class="col-6">
                        <strong>Super Admin:</strong><br>
                        <code>superadmin</code><br>
                        <code>admin123</code>
                    </div>
                    <div class="col-6">
                        <strong>Admin:</strong><br>
                        <code>admin</code><br>
                        <code>admin123</code>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-6">
                        <strong>Operator:</strong><br>
                        <code>operator1</code><br>
                        <code>admin123</code>
                    </div>
                    <div class="col-6">
                        <strong>Viewer:</strong><br>
                        <code>viewer1</code><br>
                        <code>admin123</code>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Auto focus on username field
        document.getElementById('username').focus();
    </script>
</body>
</html>