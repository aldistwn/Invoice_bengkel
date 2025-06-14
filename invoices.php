<?php
require_once 'config.php';

// Require minimum operator role for invoices
requireMinimumRole('operator');

$pdo = getDBConnection();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update_status') {
    $invoice_id = $_POST['invoice_id'];
    $new_status = $_POST['status'];
    
    $stmt = $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $invoice_id]);
    
    // If changing from draft to posted, update stock
    if ($new_status === 'posted') {
        $stmt = $pdo->prepare("SELECT * FROM invoice_parts WHERE invoice_id = ?");
        $stmt->execute([$invoice_id]);
        $invoice_parts = $stmt->fetchAll();
        
        foreach ($invoice_parts as $part) {
            updateStock($part['part_id'], $part['qty'], 'out', $invoice_id, 'Digunakan dalam invoice');
        }
    }
    
    $success = "Status invoice berhasil diupdate!";
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$month_filter = $_GET['month'] ?? '';
$year_filter = $_GET['year'] ?? '';

// Build query based on filters
$where_conditions = ['1=1'];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "i.status = ?";
    $params[] = $status_filter;
}

if ($month_filter) {
    $where_conditions[] = "MONTH(i.invoice_date) = ?";
    $params[] = $month_filter;
}

if ($year_filter) {
    $where_conditions[] = "YEAR(i.invoice_date) = ?";
    $params[] = $year_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Get invoices with customer and vehicle info
$stmt = $pdo->prepare("
    SELECT i.*, c.nama_pelanggan, v.no_polisi, v.merek_kendaraan, v.tipe_kendaraan
    FROM invoices i 
    JOIN customers c ON i.customer_id = c.id 
    JOIN vehicles v ON i.vehicle_id = v.id
    WHERE $where_clause
    ORDER BY i.created_at DESC
");
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// Get summary statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_invoices,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count,
        SUM(CASE WHEN status = 'posted' THEN 1 ELSE 0 END) as posted_count,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
        SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END) as total_revenue,
        SUM(CASE WHEN status IN ('posted') THEN total ELSE 0 END) as pending_amount
    FROM invoices i
    WHERE $where_clause
");
$stmt->execute($params);
$summary = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alfina AC Mobil - Daftar Invoice</title>
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
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
        }
        .summary-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .summary-card:hover {
            transform: translateY(-2px);
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
                        
                        <?php if (hasMinimumRole('operator')): ?>
                        <li class="nav-item mb-2">
                            <a href="invoices.php" class="nav-link text-white active">
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
                        <h1 class="h3 mb-0">Daftar Invoice</h1>
                        <a href="invoice_create.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Buat Invoice Baru
                        </a>
                    </div>

                    <!-- Success Message -->
                    <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?= $success ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Summary Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card summary-card text-center">
                                <div class="card-body">
                                    <div class="text-primary mb-2">
                                        <i class="fas fa-file-invoice fa-2x"></i>
                                    </div>
                                    <h4 class="text-primary"><?= $summary['total_invoices'] ?></h4>
                                    <p class="text-muted mb-0">Total Invoice</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card summary-card text-center">
                                <div class="card-body">
                                    <div class="text-success mb-2">
                                        <i class="fas fa-check-circle fa-2x"></i>
                                    </div>
                                    <h4 class="text-success"><?= $summary['paid_count'] ?></h4>
                                    <p class="text-muted mb-0">Lunas</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card summary-card text-center">
                                <div class="card-body">
                                    <div class="text-info mb-2">
                                        <i class="fas fa-money-bill-wave fa-2x"></i>
                                    </div>
                                    <h6 class="text-info"><?= formatRupiah($summary['total_revenue']) ?></h6>
                                    <p class="text-muted mb-0">Total Revenue</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card summary-card text-center">
                                <div class="card-body">
                                    <div class="text-warning mb-2">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                    <h6 class="text-warning"><?= formatRupiah($summary['pending_amount']) ?></h6>
                                    <p class="text-muted mb-0">Belum Lunas</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row align-items-center">
                                <div class="col-md-3">
                                    <select name="status" class="form-select">
                                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Semua Status</option>
                                        <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft</option>
                                        <option value="posted" <?= $status_filter === 'posted' ? 'selected' : '' ?>>Posted</option>
                                        <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>Lunas</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select name="month" class="form-select">
                                        <option value="">Semua Bulan</option>
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?= $m ?>" <?= $month_filter == $m ? 'selected' : '' ?>>
                                            <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                        </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select name="year" class="form-select">
                                        <option value="">Semua Tahun</option>
                                        <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                        <option value="<?= $y ?>" <?= $year_filter == $y ? 'selected' : '' ?>><?= $y ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-filter me-1"></i>Filter
                                    </button>
                                    <a href="invoices.php" class="btn btn-outline-secondary">Reset</a>
                                </div>
                                <div class="col-md-2 text-end">
                                    <span class="text-muted">Total: <?= count($invoices) ?> invoice</span>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Invoices Table -->
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Daftar Invoice</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($invoices)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Belum ada invoice</h5>
                                <p class="text-muted">Buat invoice pertama dengan klik tombol "Buat Invoice Baru"</p>
                                <a href="invoice_create.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Buat Invoice Baru
                                </a>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>No. Invoice</th>
                                            <th>Tanggal</th>
                                            <th>Pelanggan</th>
                                            <th>Kendaraan</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invoices as $invoice): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($invoice['invoice_date'])) ?></td>
                                            <td><?= htmlspecialchars($invoice['nama_pelanggan']) ?></td>
                                            <td>
                                                <small class="text-muted"><?= htmlspecialchars($invoice['no_polisi']) ?></small><br>
                                                <?= htmlspecialchars($invoice['merek_kendaraan'] . ' ' . $invoice['tipe_kendaraan']) ?>
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
                                                        $statusClass = 'bg-warning text-dark'; 
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
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="invoice_detail.php?id=<?= $invoice['id'] ?>" class="btn btn-outline-primary" title="Lihat Detail">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="print_invoice.php?id=<?= $invoice['id'] ?>" target="_blank" class="btn btn-outline-success" title="Print">
                                                        <i class="fas fa-print"></i>
                                                    </a>
                                                    
                                                    <?php if (hasMinimumRole('operator')): ?>
                                                    <?php if ($invoice['status'] === 'draft'): ?>
                                                    <a href="invoice_edit.php?id=<?= $invoice['id'] ?>" class="btn btn-outline-warning" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-info dropdown-toggle" data-bs-toggle="dropdown" title="Update Status">
                                                            <i class="fas fa-cog"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <?php if ($invoice['status'] !== 'draft'): ?>
                                                            <li>
                                                                <form method="POST" style="display: inline;">
                                                                    <input type="hidden" name="action" value="update_status">
                                                                    <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                                                                    <input type="hidden" name="status" value="draft">
                                                                    <button type="submit" class="dropdown-item">
                                                                        <i class="fas fa-file me-2"></i>Set Draft
                                                                    </button>
                                                                </form>
                                                            </li>
                                                            <?php endif; ?>
                                                            <?php if ($invoice['status'] !== 'posted'): ?>
                                                            <li>
                                                                <form method="POST" style="display: inline;">
                                                                    <input type="hidden" name="action" value="update_status">
                                                                    <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                                                                    <input type="hidden" name="status" value="posted">
                                                                    <button type="submit" class="dropdown-item">
                                                                        <i class="fas fa-paper-plane me-2"></i>Set Posted
                                                                    </button>
                                                                </form>
                                                            </li>
                                                            <?php endif; ?>
                                                            <?php if ($invoice['status'] !== 'paid'): ?>
                                                            <li>
                                                                <form method="POST" style="display: inline;">
                                                                    <input type="hidden" name="action" value="update_status">
                                                                    <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                                                                    <input type="hidden" name="status" value="paid">
                                                                    <button type="submit" class="dropdown-item">
                                                                        <i class="fas fa-check me-2"></i>Set Lunas
                                                                    </button>
                                                                </form>
                                                            </li>
                                                            <?php endif; ?>
                                                        </ul>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
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