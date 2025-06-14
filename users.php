<?php
require_once 'config.php';

// Require Super Admin access
requireRole(['super_admin']);

$pdo = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($_POST['action'] === 'add_user') {
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $full_name = trim($_POST['full_name']);
            $role = $_POST['role'];
            
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $email, $password, $full_name, $role]);
            
            logActivity('user_created', "Created new user: $username ($role)");
            $success = "User berhasil ditambahkan!";
            
        } elseif ($_POST['action'] === 'edit_user') {
            $id = $_POST['user_id'];
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $full_name = trim($_POST['full_name']);
            $role = $_POST['role'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // Update password if provided
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ?, full_name = ?, role = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$username, $email, $password, $full_name, $role, $is_active, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, role = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$username, $email, $full_name, $role, $is_active, $id]);
            }
            
            logActivity('user_updated', "Updated user: $username");
            $success = "User berhasil diupdate!";
            
        } elseif ($_POST['action'] === 'toggle_status') {
            $id = $_POST['user_id'];
            $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$id]);
            
            logActivity('user_status_changed', "Toggled user status for ID: $id");
            $success = "Status user berhasil diubah!";
        }
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get all users
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

// Get user for edit if ID provided
$editUser = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editUser = $stmt->fetch();
}

// Get user statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
        SUM(CASE WHEN role = 'super_admin' THEN 1 ELSE 0 END) as super_admin_count,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
        SUM(CASE WHEN role = 'operator' THEN 1 ELSE 0 END) as operator_count,
        SUM(CASE WHEN role = 'viewer' THEN 1 ELSE 0 END) as viewer_count
    FROM users
");
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alfina AC Mobil - User Management</title>
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
        .user-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .user-card:hover {
            transform: translateY(-2px);
        }
        .role-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
        }
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
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar d-flex flex-column p-3">
                    <div class="text-white mb-4 text-center">
                        <i class="fas fa-snowflake fa-2x mb-2"></i>
                        <div class="brand-title">ALFINA AC MOBIL</div>
                        <small>Invoice System</small>
                    </div>
                    <ul class="nav nav-pills flex-column mb-auto">
                        <li class="nav-item mb-2">
                            <a href="index.php" class="nav-link text-white">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a href="invoices.php" class="nav-link text-white">
                                <i class="fas fa-file-invoice me-2"></i>
                                Invoice
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a href="customers.php" class="nav-link text-white">
                                <i class="fas fa-users me-2"></i>
                                Pelanggan
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a href="vehicles.php" class="nav-link text-white">
                                <i class="fas fa-car me-2"></i>
                                Kendaraan
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a href="services.php" class="nav-link text-white">
                                <i class="fas fa-tools me-2"></i>
                                Layanan
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a href="parts.php" class="nav-link text-white">
                                <i class="fas fa-cogs me-2"></i>
                                Parts & Stok
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a href="reports.php" class="nav-link text-white">
                                <i class="fas fa-chart-bar me-2"></i>
                                Laporan
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a href="users.php" class="nav-link text-white active">
                                <i class="fas fa-user-cog me-2"></i>
                                User Management
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a href="activity_log.php" class="nav-link text-white">
                                <i class="fas fa-history me-2"></i>
                                Activity Log
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a href="backup_database.php" class="nav-link text-white">
                                <i class="fas fa-database me-2"></i>
                                Database Backup
                            </a>
                        </li>
                    </ul>
                    
                    <!-- User Info & Logout -->
                    <div class="text-white-50 small text-center mt-auto">
                        <div class="mb-2">
                            <i class="fas fa-user me-1"></i>
                            <?= htmlspecialchars($_SESSION['full_name']) ?>
                        </div>
                        <div class="mb-2">
                            <span class="badge <?= getRoleBadgeClass($_SESSION['user_role']) ?>">
                                <?= getRoleDisplayName($_SESSION['user_role']) ?>
                            </span>
                        </div>
                        <a href="login.php?logout=1" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0">User Management</h1>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">
                            <i class="fas fa-plus me-2"></i>Tambah User
                        </button>
                    </div>

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

                    <!-- Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-2 mb-3">
                            <div class="stats-card p-3 text-center">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <h4><?= $stats['total_users'] ?></h4>
                                <small>Total Users</small>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="stats-card p-3 text-center">
                                <i class="fas fa-user-check fa-2x mb-2"></i>
                                <h4><?= $stats['active_users'] ?></h4>
                                <small>Active</small>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="stats-card p-3 text-center">
                                <i class="fas fa-crown fa-2x mb-2"></i>
                                <h4><?= $stats['super_admin_count'] ?></h4>
                                <small>Super Admin</small>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="stats-card p-3 text-center">
                                <i class="fas fa-user-tie fa-2x mb-2"></i>
                                <h4><?= $stats['admin_count'] ?></h4>
                                <small>Admin</small>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="stats-card p-3 text-center">
                                <i class="fas fa-user-cog fa-2x mb-2"></i>
                                <h4><?= $stats['operator_count'] ?></h4>
                                <small>Operator</small>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="stats-card p-3 text-center">
                                <i class="fas fa-eye fa-2x mb-2"></i>
                                <h4><?= $stats['viewer_count'] ?></h4>
                                <small>Viewer</small>
                            </div>
                        </div>
                    </div>

                    <!-- Users List -->
                    <div class="row">
                        <?php foreach ($users as $user): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card user-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <i class="fas fa-user-circle fa-2x text-primary"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0"><?= htmlspecialchars($user['full_name']) ?></h6>
                                                <small class="text-muted">@<?= htmlspecialchars($user['username']) ?></small>
                                            </div>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="?edit=<?= $user['id'] ?>">
                                                    <i class="fas fa-edit me-2"></i>Edit
                                                </a></li>
                                                <li>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="fas fa-power-off me-2"></i>
                                                            <?= $user['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <small class="text-muted">Email:</small><br>
                                        <span><?= htmlspecialchars($user['email']) ?></span>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge <?= getRoleBadgeClass($user['role']) ?> role-badge">
                                            <?= getRoleDisplayName($user['role']) ?>
                                        </span>
                                        <span class="badge <?= $user['is_active'] ? 'bg-success' : 'bg-secondary' ?> role-badge">
                                            <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </div>
                                    
                                    <div class="mt-3 text-muted small">
                                        <div>Dibuat: <?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></div>
                                        <?php if ($user['last_login']): ?>
                                        <div>Login terakhir: <?= date('d/m/Y H:i', strtotime($user['last_login'])) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?= $editUser ? 'Edit User' : 'Tambah User Baru' ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="<?= $editUser ? 'edit_user' : 'add_user' ?>">
                        <?php if ($editUser): ?>
                        <input type="hidden" name="user_id" value="<?= $editUser['id'] ?>">
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="username" 
                                       value="<?= $editUser ? htmlspecialchars($editUser['username']) : '' ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?= $editUser ? htmlspecialchars($editUser['email']) : '' ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" 
                                   value="<?= $editUser ? htmlspecialchars($editUser['full_name']) : '' ?>" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password <?= $editUser ? '(kosongkan jika tidak diubah)' : '<span class="text-danger">*</span>' ?></label>
                                <input type="password" class="form-control" name="password" 
                                       <?= !$editUser ? 'required' : '' ?> minlength="6">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Role <span class="text-danger">*</span></label>
                                <select class="form-select" name="role" required>
                                    <option value="">Pilih Role</option>
                                    <option value="super_admin" <?= $editUser && $editUser['role'] === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                                    <option value="admin" <?= $editUser && $editUser['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                                    <option value="operator" <?= $editUser && $editUser['role'] === 'operator' ? 'selected' : '' ?>>Operator</option>
                                    <option value="viewer" <?= $editUser && $editUser['role'] === 'viewer' ? 'selected' : '' ?>>Viewer</option>
                                </select>
                            </div>
                        </div>

                        <?php if ($editUser): ?>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" 
                                       <?= $editUser['is_active'] ? 'checked' : '' ?>>
                                <label class="form-check-label">User Aktif</label>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="alert alert-info">
                            <h6>Role Permissions:</h6>
                            <ul class="mb-0">
                                <li><strong>Super Admin:</strong> Akses semua fitur + user management</li>
                                <li><strong>Admin:</strong> Akses semua fitur bisnis (invoice, customer, parts, reports)</li>
                                <li><strong>Operator:</strong> Bisa buat/edit invoice, lihat customer & kendaraan</li>
                                <li><strong>Viewer:</strong> Hanya bisa lihat data, tidak bisa edit</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            <?= $editUser ? 'Update' : 'Simpan' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="auto_logout.js"></script>
    <script>
        // Auto show modal if editing
        <?php if ($editUser): ?>
        var userModal = new bootstrap.Modal(document.getElementById('userModal'));
        userModal.show();
        <?php endif; ?>
    </script>
</body>
</html>