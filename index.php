<?php
require_once 'config.php';

// Require login
requireLogin();

// Update session activity
updateSessionActivity();

// Get statistics
$pdo = getDBConnection();

// Total invoices
$stmt = $pdo->query("SELECT COUNT(*) as total FROM invoices");
$totalInvoices = $stmt->fetch()['total'];

// Total customers
$stmt = $pdo->query("SELECT COUNT(*) as total FROM customers");
$totalCustomers = $stmt->fetch()['total'];

// Total revenue (paid invoices)
$stmt = $pdo->query("SELECT SUM(total) as revenue FROM invoices WHERE status = 'paid'");
$totalRevenue = $stmt->fetch()['revenue'] ?? 0;

// Pending invoices (posted but not paid)
$stmt = $pdo->query("SELECT SUM(total) as pending FROM invoices WHERE status = 'posted'");
$totalPending = $stmt->fetch()['pending'] ?? 0;

// Recent invoices
$stmt = $pdo->query("
    SELECT i.*, c.nama_pelanggan, v.no_polisi, v.merek_kendaraan, v.tipe_kendaraan
    FROM invoices i 
    JOIN customers c ON i.customer_id = c.id 
    JOIN vehicles v ON i.vehicle_id = v.id
    ORDER BY i.created_at DESC 
    LIMIT 5
");
$recentInvoices = $stmt->fetchAll();

// Parts dengan stok rendah
$stmt = $pdo->query("
    SELECT * FROM parts 
    WHERE stok_gudang <= minimum_stok AND is_active = 1
    ORDER BY stok_gudang ASC
    LIMIT 5
");
$lowStockParts = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alfina AC Mobil - Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        }
        .card-stats {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .card-stats:hover {
            transform: translateY(-5px);
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
        }
        .brand-title {
            font-size: 1.1rem;
            font-weight: bold;
        }
        .low-stock {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
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
                            <a href="index.php" class="nav-link text-white active">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        
                        <?php if (hasMinimumRole('operator')): ?>
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
                        <?php endif; ?>
                        
                        <?php if (hasMinimumRole('admin')): ?>
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
                        <?php endif; ?>
                        
                        <?php if (hasRole(['super_admin'])): ?>
                        <li class="nav-item mb-2">
                            <a href="users.php" class="nav-link text-white">
                                <i class="fas fa-user-cog me-2"></i>
                                User Management
                            </a>
                        </li>
                        <?php endif; ?>
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
                        <h1 class="h3 mb-0">Dashboard</h1>
                        <div class="text-muted">
                            <i class="fas fa-calendar me-1"></i>
                            <?= formatDateIndo(date('Y-m-d')) ?>
                        </div>
                    </div>
                    
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card card-stats text-center">
                                <div class="card-body">
                                    <div class="text-primary mb-2">
                                        <i class="fas fa-file-invoice fa-2x"></i>
                                    </div>
                                    <h3 class="text-primary"><?= $totalInvoices ?></h3>
                                    <p class="text-muted mb-0">Total Invoice</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card card-stats text-center">
                                <div class="card-body">
                                    <div class="text-success mb-2">
                                        <i class="fas fa-users fa-2x"></i>
                                    </div>
                                    <h3 class="text-success"><?= $totalCustomers ?></h3>
                                    <p class="text-muted mb-0">Total Pelanggan</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card card-stats text-center">
                                <div class="card-body">
                                    <div class="text-info mb-2">
                                        <i class="fas fa-money-bill-wave fa-2x"></i>
                                    </div>
                                    <h6 class="text-info"><?= formatRupiah($totalRevenue) ?></h6>
                                    <p class="text-muted mb-0">Revenue Lunas</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card card-stats text-center">
                                <div class="card-body">
                                    <div class="text-warning mb-2">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                    <h6 class="text-warning"><?= formatRupiah($totalPending) ?></h6>
                                    <p class="text-muted mb-0">Belum Lunas</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Recent Invoices -->
                        <div class="col-lg-8 mb-4">
                            <div class="card">
                                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Invoice Terbaru</h5>
                                    <a href="invoice_create.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus me-1"></i>Buat Invoice
                                    </a>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>No. Invoice</th>
                                                    <th>Pelanggan</th>
                                                    <th>Kendaraan</th>
                                                    <th>Total</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($recentInvoices)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted">
                                                        Belum ada invoice
                                                    </td>
                                                </tr>
                                                <?php else: ?>
                                                <?php foreach ($recentInvoices as $invoice): ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong></td>
                                                    <td><?= htmlspecialchars($invoice['nama_pelanggan']) ?></td>
                                                    <td>
                                                        <small class="text-muted"><?= htmlspecialchars($invoice['no_polisi']) ?></small><br>
                                                        <?= htmlspecialchars($invoice['merek_kendaraan']) ?> <?= htmlspecialchars($invoice['tipe_kendaraan']) ?>
                                                    </td>
                                                    <td><?= formatRupiah($invoice['total']) ?></td>
                                                    <td>
                                                        <?php
                                                        $statusClass = '';
                                                        $statusText = '';
                                                        switch($invoice['status']) {
                                                            case 'draft': 
                                                                $statusClass = 'bg-secondary'; 
                                                                $statusText = 'Draft';
                                                                break;
                                                            case 'posted': 
                                                                $statusClass = 'bg-warning'; 
                                                                $statusText = 'Posted';
                                                                break;
                                                            case 'paid': 
                                                                $statusClass = 'bg-success'; 
                                                                $statusText = 'Lunas';
                                                                break;
                                                        }
                                                        ?>
                                                        <span class="badge <?= $statusClass ?> status-badge">
                                                            <?= $statusText ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Low Stock Alert -->
                        <div class="col-lg-4 mb-4">
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                        Stok Rendah
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($lowStockParts)): ?>
                                    <div class="text-center text-muted">
                                        <i class="fas fa-check-circle text-success mb-2"></i>
                                        <p>Semua stok aman</p>
                                    </div>
                                    <?php else: ?>
                                    <?php foreach ($lowStockParts as $part): ?>
                                    <div class="alert alert-warning low-stock py-2 mb-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?= htmlspecialchars($part['nama_part']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($part['kode_part']) ?></small>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-warning text-dark"><?= $part['stok_gudang'] ?> <?= $part['satuan'] ?></span><br>
                                                <small class="text-muted">Min: <?= $part['minimum_stok'] ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <div class="text-center mt-3">
                                        <a href="parts.php" class="btn btn-warning btn-sm">
                                            <i class="fas fa-cogs me-1"></i>Kelola Stok
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
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