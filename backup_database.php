<?php
require_once 'config.php';

// Require Super Admin access
requireRole(['super_admin']);

// Create backups directory if not exists
$backup_dir = 'backups';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Handle backup request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'create_backup') {
    try {
        $filename = 'alfina_ac_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backup_dir . '/' . $filename;
        
        // Create database backup using mysqldump
        $command = sprintf(
            'mysqldump --host=%s --user=%s --password=%s %s > %s',
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME,
            $filepath
        );
        
        exec($command, $output, $return_code);
        
        if ($return_code === 0) {
            logActivity('database_backup', "Database backup created: $filename");
            $success = "Backup berhasil dibuat: $filename";
        } else {
            throw new Exception("Backup failed with return code: $return_code");
        }
        
    } catch (Exception $e) {
        $error = "Error creating backup: " . $e->getMessage();
        logActivity('database_backup_failed', $e->getMessage());
    }
}

// Handle backup download
if (isset($_GET['download'])) {
    $filename = basename($_GET['download']);
    $filepath = $backup_dir . '/' . $filename;
    
    if (file_exists($filepath)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        
        logActivity('database_backup_downloaded', "Downloaded backup: $filename");
        exit;
    } else {
        $error = "Backup file not found";
    }
}

// Handle backup deletion
if (isset($_GET['delete'])) {
    $filename = basename($_GET['delete']);
    $filepath = $backup_dir . '/' . $filename;
    
    if (file_exists($filepath)) {
        unlink($filepath);
        logActivity('database_backup_deleted', "Deleted backup: $filename");
        $success = "Backup berhasil dihapus: $filename";
    } else {
        $error = "Backup file not found";
    }
}

// Get existing backups
$backups = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $filepath = $backup_dir . '/' . $file;
            $backups[] = [
                'filename' => $file,
                'size' => filesize($filepath),
                'created' => filemtime($filepath)
            ];
        }
    }
    
    // Sort by creation time (newest first)
    usort($backups, function($a, $b) {
        return $b['created'] - $a['created'];
    });
}

// Get database statistics
$pdo = getDBConnection();
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_invoices,
        (SELECT COUNT(*) FROM customers) as total_customers,
        (SELECT COUNT(*) FROM vehicles) as total_vehicles,
        (SELECT COUNT(*) FROM services) as total_services,
        (SELECT COUNT(*) FROM parts) as total_parts,
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM user_activity_log) as total_activities
    FROM invoices
");
$db_stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alfina AC Mobil - Database Backup</title>
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
        .backup-item {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        .backup-item:hover {
            border-color: #007bff;
            box-shadow: 0 2px 8px rgba(0,123,255,0.1);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
        }
    </style>
</head>
<body class="bg-light" data-user-role="<?= $_SESSION['user_role'] ?>">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'navbar_template.php'; renderSidebar('backup'); ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <?php 
                    renderPageHeader(
                        'Database Backup & Restore', 
                        'Backup and manage your database',
                        '<form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="create_backup">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-download me-2"></i>Create Backup
                            </button>
                        </form>'
                    ); 
                    ?>

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

                    <!-- Database Statistics -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="fas fa-database me-2"></i>Database Statistics
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <i class="fas fa-file-invoice fa-2x mb-2"></i>
                                    <h4><?= number_format($db_stats['total_invoices']) ?></h4>
                                    <small>Invoices</small>
                                </div>
                                <div class="stat-card">
                                    <i class="fas fa-users fa-2x mb-2"></i>
                                    <h4><?= number_format($db_stats['total_customers']) ?></h4>
                                    <small>Customers</small>
                                </div>
                                <div class="stat-card">
                                    <i class="fas fa-car fa-2x mb-2"></i>
                                    <h4><?= number_format($db_stats['total_vehicles']) ?></h4>
                                    <small>Vehicles</small>
                                </div>
                                <div class="stat-card">
                                    <i class="fas fa-tools fa-2x mb-2"></i>
                                    <h4><?= number_format($db_stats['total_services']) ?></h4>
                                    <small>Services</small>
                                </div>
                                <div class="stat-card">
                                    <i class="fas fa-cogs fa-2x mb-2"></i>
                                    <h4><?= number_format($db_stats['total_parts']) ?></h4>
                                    <small>Parts</small>
                                </div>
                                <div class="stat-card">
                                    <i class="fas fa-user-cog fa-2x mb-2"></i>
                                    <h4><?= number_format($db_stats['total_users']) ?></h4>
                                    <small>Users</small>
                                </div>
                                <div class="stat-card">
                                    <i class="fas fa-history fa-2x mb-2"></i>
                                    <h4><?= number_format($db_stats['total_activities']) ?></h4>
                                    <small>Activities</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Backup List -->
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="fas fa-archive me-2"></i>Backup Files (<?= count($backups) ?>)
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($backups)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-archive fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No backup files found</h5>
                                <p class="text-muted">Create your first backup to get started</p>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="create_backup">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-download me-2"></i>Create First Backup
                                    </button>
                                </form>
                            </div>
                            <?php else: ?>
                            <?php foreach ($backups as $backup): ?>
                            <div class="backup-item">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-file-archive fa-2x text-primary me-3"></i>
                                            <div>
                                                <h6 class="mb-0"><?= htmlspecialchars($backup['filename']) ?></h6>
                                                <small class="text-muted">
                                                    Created: <?= date('d/m/Y H:i:s', $backup['created']) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <span class="badge bg-info fs-6">
                                            <?= number_format($backup['size'] / 1024, 1) ?> KB
                                        </span>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <div class="btn-group">
                                            <a href="?download=<?= urlencode($backup['filename']) ?>" 
                                               class="btn btn-outline-success btn-sm">
                                                <i class="fas fa-download me-1"></i>Download
                                            </a>
                                            <button class="btn btn-outline-danger btn-sm" 
                                                    onclick="confirmDelete('<?= htmlspecialchars($backup['filename']) ?>')">
                                                <i class="fas fa-trash me-1"></i>Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Backup Instructions -->
                    <div class="card mt-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>Backup Instructions
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Creating Backups:</h6>
                                    <ul>
                                        <li>Click "Create Backup" to generate a new backup file</li>
                                        <li>Backup files are stored in the <code>backups/</code> directory</li>
                                        <li>Each backup includes all tables and data</li>
                                        <li>Regular backups are recommended (daily/weekly)</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6>Restoring Backups:</h6>
                                    <ul>
                                        <li>Download the backup file you want to restore</li>
                                        <li>Use phpMyAdmin to import the SQL file</li>
                                        <li>Or use command line: <code>mysql -u root -p alfina_ac_mobil < backup.sql</code></li>
                                        <li>Always test restore process on a separate environment first</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Important:</strong> Store backup files in a secure location outside of the web directory for production use.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="auto_logout.js"></script>
    <script>
        function confirmDelete(filename) {
            if (confirm(`Are you sure you want to delete backup: ${filename}?`)) {
                window.location.href = `?delete=${encodeURIComponent(filename)}`;
            }
        }
    </script>
</body>
</html>