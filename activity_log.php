<?php
require_once 'config.php';

// Require Super Admin access
requireRole(['super_admin']);

$pdo = getDBConnection();

// Get filter parameters
$user_filter = $_GET['user_id'] ?? '';
$action_filter = $_GET['action'] ?? '';
$date_filter = $_GET['date'] ?? '';
$limit = $_GET['limit'] ?? 50;

// Build query
$where_conditions = ['1=1'];
$params = [];

if ($user_filter) {
    $where_conditions[] = "al.user_id = ?";
    $params[] = $user_filter;
}

if ($action_filter) {
    $where_conditions[] = "al.action LIKE ?";
    $params[] = "%$action_filter%";
}

if ($date_filter) {
    $where_conditions[] = "DATE(al.created_at) = ?";
    $params[] = $date_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Get activity logs with user info
$stmt = $pdo->prepare("
    SELECT al.*, u.username, u.full_name, u.role
    FROM user_activity_log al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE $where_clause
    ORDER BY al.created_at DESC
    LIMIT ?
");

$params[] = (int)$limit;
$stmt->execute($params);
$activities = $stmt->fetchAll();

// Get users for filter dropdown
$stmt = $pdo->query("SELECT id, username, full_name FROM users ORDER BY full_name");
$users = $stmt->fetchAll();

// Get activity statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_activities,
        COUNT(DISTINCT user_id) as active_users_today,
        COUNT(CASE WHEN action = 'login' THEN 1 END) as login_count,
        COUNT(CASE WHEN action = 'logout' THEN 1 END) as logout_count,
        COUNT(CASE WHEN action LIKE '%invoice%' THEN 1 END) as invoice_activities,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as last_hour_activities
    FROM user_activity_log 
    WHERE DATE(created_at) = CURDATE()
");
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alfina AC Mobil - Activity Log</title>
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
        .activity-item {
            border-left: 4px solid #dee2e6;
            transition: all 0.3s ease;
        }
        .activity-item:hover {
            border-left-color: #007bff;
            background-color: #f8f9fa;
        }
        .activity-login { border-left-color: #28a745; }
        .activity-logout { border-left-color: #6c757d; }
        .activity-invoice { border-left-color: #007bff; }
        .activity-user { border-left-color: #ffc107; }
        .activity-error { border-left-color: #dc3545; }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
    </style>
</head>
<body class="bg-light" data-user-role="<?= $_SESSION['user_role'] ?>">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'navbar_template.php'; renderSidebar('activity'); ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <?php renderPageHeader('System Activity Log', 'Monitor user activities and system events'); ?>

                    <!-- Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-2 mb-3">
                            <div class="stats-card p-3 text-center">
                                <i class="fas fa-chart-line fa-2x mb-2"></i>
                                <h4><?= $stats['total_activities'] ?></h4>
                                <small>Activities Today</small>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="stats-card p-3 text-center">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <h4><?= $stats['active_users_today'] ?></h4>
                                <small>Active Users</small>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="stats-card p-3 text-center">
                                <i class="fas fa-sign-in-alt fa-2x mb-2"></i>
                                <h4><?= $stats['login_count'] ?></h4>
                                <small>Logins</small>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="stats-card p-3 text-center">
                                <i class="fas fa-file-invoice fa-2x mb-2"></i>
                                <h4><?= $stats['invoice_activities'] ?></h4>
                                <small>Invoice Actions</small>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="stats-card p-3 text-center">
                                <i class="fas fa-clock fa-2x mb-2"></i>
                                <h4><?= $stats['last_hour_activities'] ?></h4>
                                <small>Last Hour</small>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="stats-card p-3 text-center">
                                <i class="fas fa-eye fa-2x mb-2"></i>
                                <h4><?= count($activities) ?></h4>
                                <small>Showing</small>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row align-items-center">
                                <div class="col-md-3">
                                    <select name="user_id" class="form-select">
                                        <option value="">Semua User</option>
                                        <?php foreach ($users as $user): ?>
                                        <option value="<?= $user['id'] ?>" <?= $user_filter == $user['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($user['full_name']) ?> (@<?= $user['username'] ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="text" name="action" class="form-control" placeholder="Action" 
                                           value="<?= htmlspecialchars($action_filter) ?>">
                                </div>
                                <div class="col-md-2">
                                    <input type="date" name="date" class="form-control" 
                                           value="<?= htmlspecialchars($date_filter) ?>">
                                </div>
                                <div class="col-md-2">
                                    <select name="limit" class="form-select">
                                        <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50 records</option>
                                        <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100 records</option>
                                        <option value="200" <?= $limit == 200 ? 'selected' : '' ?>>200 records</option>
                                        <option value="500" <?= $limit == 500 ? 'selected' : '' ?>>500 records</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-filter me-1"></i>Filter
                                    </button>
                                    <a href="activity_log.php" class="btn btn-outline-secondary">Reset</a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Activity Log -->
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2"></i>Activity Timeline
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($activities)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No activities found</h5>
                                <p class="text-muted">Try adjusting your filters</p>
                            </div>
                            <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($activities as $activity): 
                                    // Determine activity class
                                    $activityClass = 'activity-item';
                                    if (strpos($activity['action'], 'login') !== false) {
                                        $activityClass .= ' activity-login';
                                        $icon = 'fa-sign-in-alt text-success';
                                    } elseif (strpos($activity['action'], 'logout') !== false) {
                                        $activityClass .= ' activity-logout';
                                        $icon = 'fa-sign-out-alt text-secondary';
                                    } elseif (strpos($activity['action'], 'invoice') !== false) {
                                        $activityClass .= ' activity-invoice';
                                        $icon = 'fa-file-invoice text-primary';
                                    } elseif (strpos($activity['action'], 'user') !== false) {
                                        $activityClass .= ' activity-user';
                                        $icon = 'fa-user-cog text-warning';
                                    } elseif (strpos($activity['action'], 'error') !== false || strpos($activity['action'], 'failed') !== false) {
                                        $activityClass .= ' activity-error';
                                        $icon = 'fa-exclamation-triangle text-danger';
                                    } else {
                                        $icon = 'fa-info-circle text-info';
                                    }
                                ?>
                                <div class="<?= $activityClass ?> p-3 mb-3 rounded">
                                    <div class="row align-items-center">
                                        <div class="col-auto">
                                            <i class="fas <?= $icon ?> fa-lg"></i>
                                        </div>
                                        <div class="col">
                                            <div class="fw-bold">
                                                <?= htmlspecialchars($activity['action']) ?>
                                                <?php if ($activity['username']): ?>
                                                <span class="badge <?= getRoleBadgeClass($activity['role']) ?> ms-2">
                                                    <?= htmlspecialchars($activity['full_name']) ?>
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($activity['description']): ?>
                                            <div class="text-muted small mt-1">
                                                <?= htmlspecialchars($activity['description']) ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-auto text-end">
                                            <div class="small text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                <?= date('d/m/Y H:i:s', strtotime($activity['created_at'])) ?>
                                            </div>
                                            <?php if ($activity['ip_address']): ?>
                                            <div class="small text-muted">
                                                <i class="fas fa-globe me-1"></i>
                                                <?= htmlspecialchars($activity['ip_address']) ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if (count($activities) >= $limit): ?>
                            <div class="text-center mt-4">
                                <p class="text-muted">Showing <?= $limit ?> most recent activities</p>
                                <a href="?limit=<?= $limit * 2 ?><?= $user_filter ? '&user_id=' . $user_filter : '' ?><?= $action_filter ? '&action=' . $action_filter : '' ?><?= $date_filter ? '&date=' . $date_filter : '' ?>" 
                                   class="btn btn-outline-primary">
                                    <i class="fas fa-plus me-2"></i>Load More
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="auto_logout.js"></script>
    <script>
        // Auto refresh every 30 seconds if no filters applied
        <?php if (!$user_filter && !$action_filter && !$date_filter): ?>
        setTimeout(() => {
            window.location.reload();
        }, 30000);
        <?php endif; ?>
    </script>
</body>
</html>