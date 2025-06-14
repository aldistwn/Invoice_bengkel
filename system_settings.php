<?php
require_once 'config.php';

// Require Super Admin access
requireRole(['super_admin']);

// System information
$system_info = [
    'php_version' => phpversion(),
    'mysql_version' => '',
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'session_timeout' => ini_get('session.gc_maxlifetime'),
    'disk_space' => disk_free_space('.'),
    'total_space' => disk_total_space('.')
];

// Get MySQL version
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT VERSION() as version");
    $system_info['mysql_version'] = $stmt->fetch()['version'];
} catch (Exception $e) {
    $system_info['mysql_version'] = 'Unable to connect';
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'update_session_settings') {
        // Update session timeout settings
        $timeout_minutes = (int)$_POST['session_timeout'];
        $warning_minutes = (int)$_POST['warning_time'];
        
        // Validate input
        if ($timeout_minutes < 5 || $timeout_minutes > 480) { // 5 minutes to 8 hours
            $error = "Session timeout must be between 5 and 480 minutes";
        } elseif ($warning_minutes < 1 || $warning_minutes >= $timeout_minutes) {
            $error = "Warning time must be between 1 minute and less than session timeout";
        } else {
            // Save settings to a simple config file
            $settings = [
                'session_timeout' => $timeout_minutes,
                'warning_time' => $warning_minutes,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $_SESSION['full_name']
            ];
            
            file_put_contents('session_config.json', json_encode($settings, JSON_PRETTY_PRINT));
            
            logActivity('session_settings_updated', "Session timeout set to {$timeout_minutes} minutes, warning at {$warning_minutes} minutes");
            $success = "Session settings updated successfully. Changes will take effect on next login.";
        }
        
    } elseif ($_POST['action'] === 'clear_sessions') {
        // Clear all user sessions
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE expires_at < NOW()");
        $stmt->execute();
        
        logActivity('system_maintenance', 'Expired sessions cleared');
        $success = "Expired sessions cleared successfully";
        
    } elseif ($_POST['action'] === 'clear_activity_log') {
        // Clear old activity logs (older than 30 days)
        $stmt = $pdo->prepare("DELETE FROM user_activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        
        logActivity('system_maintenance', 'Old activity logs cleared (30+ days)');
        $success = "Old activity logs cleared successfully";
        
    } elseif ($_POST['action'] === 'optimize_database') {
        // Optimize database tables
        $tables = ['users', 'user_sessions', 'user_activity_log', 'customers', 'vehicles', 'services', 'parts', 'invoices', 'invoice_services', 'invoice_parts', 'stock_movements'];
        
        foreach ($tables as $table) {
            $stmt = $pdo->prepare("OPTIMIZE TABLE $table");
            $stmt->execute();
        }
        
        logActivity('system_maintenance', 'Database tables optimized');
        $success = "Database optimization completed successfully";
    }
}

// Get system statistics
$stmt = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE is_active = 1) as active_users,
        (SELECT COUNT(*) FROM user_sessions WHERE expires_at > NOW()) as active_sessions,
        (SELECT COUNT(*) FROM user_activity_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as today_activities,
        (SELECT COUNT(*) FROM invoices WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as monthly_invoices
");
$stats = $stmt->fetch();

// Get current session settings
$session_settings = [
    'session_timeout' => 60, // default 60 minutes
    'warning_time' => 5,     // default 5 minutes
    'updated_at' => null,
    'updated_by' => null
];

if (file_exists('session_config.json')) {
    $saved_settings = json_decode(file_get_contents('session_config.json'), true);
    if ($saved_settings) {
        $session_settings = array_merge($session_settings, $saved_settings);
    }
}

// Get recent system activities
$stmt = $pdo->query("
    SELECT al.*, u.full_name 
    FROM user_activity_log al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE al.action LIKE '%system%' OR al.action LIKE '%backup%' OR al.action LIKE '%user_%'
    ORDER BY al.created_at DESC 
    LIMIT 10
");
$recent_activities = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alfina AC Mobil - System Settings</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        }
        .brand-title {
            font-size: 1.1rem;
            font-weight: bold;
        }
        .info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .maintenance-btn {
            transition: all 0.3s ease;
        }
        .maintenance-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body class="bg-light" data-user-role="<?= $_SESSION['user_role'] ?>">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'navbar_template.php'; renderSidebar('settings'); ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <?php renderPageHeader('System Settings', 'System configuration and maintenance tools'); ?>

                    <!-- Messages -->
                    <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?= $success ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Session Settings -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="fas fa-clock me-2"></i>Session Settings
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_session_settings">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Session Timeout (Minutes)</label>
                                            <input type="number" class="form-control" name="session_timeout" 
                                                   value="<?= $session_settings['session_timeout'] ?>" 
                                                   min="5" max="480" required>
                                            <div class="form-text">Auto-logout after this many minutes of inactivity (5-480 minutes)</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Warning Time (Minutes)</label>
                                            <input type="number" class="form-control" name="warning_time" 
                                                   value="<?= $session_settings['warning_time'] ?>" 
                                                   min="1" max="60" required>
                                            <div class="form-text">Show warning this many minutes before logout</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">&nbsp;</label>
                                            <div>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-save me-2"></i>Update Settings
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($session_settings['updated_at']): ?>
                                <div class="alert alert-info">
                                    <small>
                                        <i class="fas fa-info-circle me-1"></i>
                                        Last updated: <?= date('d/m/Y H:i', strtotime($session_settings['updated_at'])) ?>
                                        by <?= htmlspecialchars($session_settings['updated_by']) ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Current Settings:</h6>
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-clock text-primary me-2"></i>Timeout: <strong><?= $session_settings['session_timeout'] ?> minutes</strong></li>
                                            <li><i class="fas fa-exclamation-triangle text-warning me-2"></i>Warning: <strong><?= $session_settings['warning_time'] ?> minutes before logout</strong></li>
                                            <li><i class="fas fa-sign-out-alt text-danger me-2"></i>Auto-logout: <strong><?= $session_settings['session_timeout'] - $session_settings['warning_time'] ?> minutes after warning</strong></li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Recommended Settings:</h6>
                                        <ul class="list-unstyled small text-muted">
                                            <li><strong>Office use:</strong> 60-120 minutes timeout</li>
                                            <li><strong>Public computer:</strong> 15-30 minutes timeout</li>
                                            <li><strong>High security:</strong> 30-60 minutes timeout</li>
                                            <li><strong>Warning time:</strong> 5-10 minutes before logout</li>
                                        </ul>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- System Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="info-card p-3 text-center">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <h4><?= $stats['active_users'] ?></h4>
                                <small>Active Users</small>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="info-card p-3 text-center">
                                <i class="fas fa-plug fa-2x mb-2"></i>
                                <h4><?= $stats['active_sessions'] ?></h4>
                                <small>Active Sessions</small>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="info-card p-3 text-center">
                                <i class="fas fa-chart-line fa-2x mb-2"></i>
                                <h4><?= $stats['today_activities'] ?></h4>
                                <small>Today's Activities</small>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="info-card p-3 text-center">
                                <i class="fas fa-file-invoice fa-2x mb-2"></i>
                                <h4><?= $stats['monthly_invoices'] ?></h4>
                                <small>Monthly Invoices</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- System Information -->
                        <div class="col-lg-8 mb-4">
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-info-circle me-2"></i>System Information
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <table class="table table-sm">
                                                <tr>
                                                    <td><strong>PHP Version:</strong></td>
                                                    <td><?= $system_info['php_version'] ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>MySQL Version:</strong></td>
                                                    <td><?= $system_info['mysql_version'] ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Server Software:</strong></td>
                                                    <td><?= $system_info['server_software'] ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Max Execution Time:</strong></td>
                                                    <td><?= $system_info['max_execution_time'] ?> seconds</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Memory Limit:</strong></td>
                                                    <td><?= $system_info['memory_limit'] ?></td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            <table class="table table-sm">
                                                <tr>
                                                    <td><strong>Upload Max Size:</strong></td>
                                                    <td><?= $system_info['upload_max_filesize'] ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Session Timeout:</strong></td>
                                                    <td><?= round($system_info['session_timeout'] / 60) ?> minutes</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Free Disk Space:</strong></td>
                                                    <td><?= number_format($system_info['disk_space'] / 1024 / 1024 / 1024, 2) ?> GB</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Total Disk Space:</strong></td>
                                                    <td><?= number_format($system_info['total_space'] / 1024 / 1024 / 1024, 2) ?> GB</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Disk Usage:</strong></td>
                                                    <td>
                                                        <?php 
                                                        $used_percent = (($system_info['total_space'] - $system_info['disk_space']) / $system_info['total_space']) * 100;
                                                        ?>
                                                        <div class="progress" style="height: 20px;">
                                                            <div class="progress-bar <?= $used_percent > 80 ? 'bg-danger' : ($used_percent > 60 ? 'bg-warning' : 'bg-success') ?>" 
                                                                 style="width: <?= $used_percent ?>%">
                                                                <?= number_format($used_percent, 1) ?>%
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Maintenance Tools -->
                        <div class="col-lg-4 mb-4">
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-tools me-2"></i>Maintenance Tools
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-3">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="clear_sessions">
                                            <button type="submit" class="btn btn-warning maintenance-btn w-100" 
                                                    onclick="return confirm('Clear all expired sessions?')">
                                                <i class="fas fa-broom me-2"></i>Clear Expired Sessions
                                            </button>
                                        </form>

                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="clear_activity_log">
                                            <button type="submit" class="btn btn-info maintenance-btn w-100" 
                                                    onclick="return confirm('Clear activity logs older than 30 days?')">
                                                <i class="fas fa-history me-2"></i>Clear Old Activity Logs
                                            </button>
                                        </form>

                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="optimize_database">
                                            <button type="submit" class="btn btn-success maintenance-btn w-100" 
                                                    onclick="return confirm('Optimize database tables?')">
                                                <i class="fas fa-database me-2"></i>Optimize Database
                                            </button>
                                        </form>

                                        <a href="backup_database.php" class="btn btn-primary maintenance-btn">
                                            <i class="fas fa-download me-2"></i>Database Backup
                                        </a>

                                        <a href="activity_log.php" class="btn btn-secondary maintenance-btn">
                                            <i class="fas fa-list me-2"></i>View Activity Log
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent System Activities -->
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="fas fa-cog me-2"></i>Recent System Activities
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_activities)): ?>
                            <div class="text-center py-3">
                                <i class="fas fa-history fa-2x text-muted mb-2"></i>
                                <p class="text-muted">No recent system activities</p>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Action</th>
                                            <th>User</th>
                                            <th>Description</th>
                                            <th>Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_activities as $activity): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?= htmlspecialchars($activity['action']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($activity['full_name'] ?? 'System') ?></td>
                                            <td><?= htmlspecialchars($activity['description']) ?></td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= date('d/m/Y H:i', strtotime($activity['created_at'])) ?>
                                                </small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="auto_logout.js"></script>
</body>
</html>