<?php
// Template navbar untuk konsistensi di semua halaman
// Usage: include 'navbar_template.php';

function renderSidebar($active_page = '') {
    $current_user = getCurrentUser();
    
    echo '
    <div class="col-md-3 col-lg-2 px-0">
        <div class="sidebar d-flex flex-column p-3">
            <div class="text-white mb-4 text-center">
                <i class="fas fa-snowflake fa-2x mb-2"></i>
                <div class="brand-title">ALFINA AC MOBIL</div>
                <small>Invoice System</small>
            </div>
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item mb-2">
                    <a href="index.php" class="nav-link text-white ' . ($active_page === 'dashboard' ? 'active' : '') . '">
                        <i class="fas fa-tachometer-alt me-2"></i>
                        Dashboard
                    </a>
                </li>';
    
    // Menu untuk Operator+
    if (hasMinimumRole('operator')) {
        echo '
                <li class="nav-item mb-2">
                    <a href="invoices.php" class="nav-link text-white ' . ($active_page === 'invoices' ? 'active' : '') . '">
                        <i class="fas fa-file-invoice me-2"></i>
                        Invoice
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a href="customers.php" class="nav-link text-white ' . ($active_page === 'customers' ? 'active' : '') . '">
                        <i class="fas fa-users me-2"></i>
                        Pelanggan
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a href="vehicles.php" class="nav-link text-white ' . ($active_page === 'vehicles' ? 'active' : '') . '">
                        <i class="fas fa-car me-2"></i>
                        Kendaraan
                    </a>
                </li>';
    }
    
    // Menu untuk Admin+
    if (hasMinimumRole('admin')) {
        echo '
                <li class="nav-item mb-2">
                    <a href="services.php" class="nav-link text-white ' . ($active_page === 'services' ? 'active' : '') . '">
                        <i class="fas fa-tools me-2"></i>
                        Layanan
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a href="parts.php" class="nav-link text-white ' . ($active_page === 'parts' ? 'active' : '') . '">
                        <i class="fas fa-cogs me-2"></i>
                        Parts & Stok
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a href="reports.php" class="nav-link text-white ' . ($active_page === 'reports' ? 'active' : '') . '">
                        <i class="fas fa-chart-bar me-2"></i>
                        Laporan
                    </a>
                </li>';
    }
    
    // Menu untuk Super Admin
    if (hasRole(['super_admin'])) {
        echo '
                <li class="nav-item mb-2">
                    <a href="users.php" class="nav-link text-white ' . ($active_page === 'users' ? 'active' : '') . '">
                        <i class="fas fa-user-cog me-2"></i>
                        User Management
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a href="activity_log.php" class="nav-link text-white ' . ($active_page === 'activity' ? 'active' : '') . '">
                        <i class="fas fa-history me-2"></i>
                        Activity Log
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a href="backup_database.php" class="nav-link text-white ' . ($active_page === 'backup' ? 'active' : '') . '">
                        <i class="fas fa-database me-2"></i>
                        Database Backup
                    </a>
                </li>';
    }
    
    echo '
            </ul>
            
            <!-- User Info & Logout -->
            <div class="text-white-50 small text-center mt-auto">
                <div class="mb-2">
                    <i class="fas fa-user me-1"></i>
                    ' . htmlspecialchars($_SESSION['full_name']) . '
                </div>
                <div class="mb-2">
                    <span class="badge ' . getRoleBadgeClass($_SESSION['user_role']) . '">
                        ' . getRoleDisplayName($_SESSION['user_role']) . '
                    </span>
                </div>
                <a href="login.php?logout=1" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </div>';
}

function renderPageHeader($title, $subtitle = '', $actions = '') {
    echo '
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">' . htmlspecialchars($title) . '</h1>';
    
    if ($subtitle) {
        echo '<small class="text-muted">' . htmlspecialchars($subtitle) . '</small>';
    }
    
    echo '
        </div>
        <div>' . $actions . '</div>
    </div>';
}

function renderAccessDenied() {
    echo '
    <div class="container-fluid">
        <div class="row justify-content-center align-items-center" style="min-height: 100vh;">
            <div class="col-md-6 text-center">
                <div class="card">
                    <div class="card-body p-5">
                        <i class="fas fa-ban fa-5x text-danger mb-4"></i>
                        <h2 class="text-danger mb-3">Akses Ditolak</h2>
                        <p class="text-muted mb-4">
                            Anda tidak memiliki izin untuk mengakses halaman ini.<br>
                            Silakan hubungi administrator untuk mendapatkan akses.
                        </p>
                        <div class="mb-3">
                            <span class="badge ' . getRoleBadgeClass($_SESSION['user_role']) . ' fs-6">
                                Role Anda: ' . getRoleDisplayName($_SESSION['user_role']) . '
                            </span>
                        </div>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-home me-2"></i>Kembali ke Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>';
    exit;
}
?>