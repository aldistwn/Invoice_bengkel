<?php
require_once 'config.php';

$pdo = getDBConnection();

// Get date range parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month

// Revenue Summary
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_invoices,
        SUM(total) as total_revenue,
        SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END) as paid_revenue,
        SUM(CASE WHEN status = 'posted' THEN total ELSE 0 END) as pending_revenue,
        AVG(total) as avg_invoice_value
    FROM invoices 
    WHERE invoice_date BETWEEN ? AND ?
");
$stmt->execute([$start_date, $end_date]);
$revenue_summary = $stmt->fetch();

// Monthly Revenue Trend (last 6 months)
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(invoice_date, '%Y-%m') as month,
        COUNT(*) as invoice_count,
        SUM(total) as monthly_revenue,
        SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END) as paid_revenue
    FROM invoices 
    WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(invoice_date, '%Y-%m')
    ORDER BY month ASC
");
$monthly_trend = $stmt->fetchAll();

// Top Services
$stmt = $pdo->prepare("
    SELECT 
        s.nama_service,
        COUNT(*) as usage_count,
        SUM(ins.total) as total_revenue,
        AVG(ins.harga) as avg_price
    FROM invoice_services ins
    JOIN services s ON ins.service_id = s.id
    JOIN invoices i ON ins.invoice_id = i.id
    WHERE i.invoice_date BETWEEN ? AND ?
    GROUP BY s.id, s.nama_service
    ORDER BY total_revenue DESC
    LIMIT 5
");
$stmt->execute([$start_date, $end_date]);
$top_services = $stmt->fetchAll();

// Top Parts
$stmt = $pdo->prepare("
    SELECT 
        p.nama_part,
        SUM(inp.qty) as total_qty,
        SUM(inp.total) as total_revenue,
        COUNT(DISTINCT inp.invoice_id) as usage_count
    FROM invoice_parts inp
    JOIN parts p ON inp.part_id = p.id
    JOIN invoices i ON inp.invoice_id = i.id
    WHERE i.invoice_date BETWEEN ? AND ?
    GROUP BY p.id, p.nama_part
    ORDER BY total_revenue DESC
    LIMIT 5
");
$stmt->execute([$start_date, $end_date]);
$top_parts = $stmt->fetchAll();

// Customer Analysis
$stmt = $pdo->prepare("
    SELECT 
        c.nama_pelanggan,
        COUNT(*) as invoice_count,
        SUM(i.total) as total_spent,
        MAX(i.invoice_date) as last_visit
    FROM invoices i
    JOIN customers c ON i.customer_id = c.id
    WHERE i.invoice_date BETWEEN ? AND ?
    GROUP BY c.id, c.nama_pelanggan
    ORDER BY total_spent DESC
    LIMIT 10
");
$stmt->execute([$start_date, $end_date]);
$top_customers = $stmt->fetchAll();

// Daily Revenue (current month)
$stmt = $pdo->prepare("
    SELECT 
        DAY(invoice_date) as day,
        COUNT(*) as invoice_count,
        SUM(total) as daily_revenue
    FROM invoices 
    WHERE MONTH(invoice_date) = MONTH(?) AND YEAR(invoice_date) = YEAR(?)
    GROUP BY DAY(invoice_date)
    ORDER BY day ASC
");
$stmt->execute([$end_date, $end_date]);
$daily_revenue = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alfina AC Mobil - Laporan</title>
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
        .report-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .report-card:hover {
            transform: translateY(-2px);
        }
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .chart-container {
            height: 300px;
            display: flex;
            align-items: end;
            justify-content: space-between;
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .chart-bar {
            background: linear-gradient(to top, #667eea, #764ba2);
            border-radius: 4px 4px 0 0;
            min-height: 10px;
            flex: 1;
            margin: 0 2px;
            display: flex;
            align-items: end;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-light">
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
                            <a href="reports.php" class="nav-link text-white active">
                                <i class="fas fa-chart-bar me-2"></i>
                                Laporan
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0">Laporan & Analisa</h1>
                        <button onclick="window.print()" class="btn btn-outline-primary">
                            <i class="fas fa-print me-2"></i>Cetak Laporan
                        </button>
                    </div>

                    <!-- Date Filter -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row align-items-center">
                                <div class="col-md-3">
                                    <label class="form-label">Tanggal Mulai</label>
                                    <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Tanggal Akhir</label>
                                    <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-filter me-1"></i>Filter
                                        </button>
                                        <a href="reports.php" class="btn btn-outline-secondary ms-2">Reset</a>
                                    </div>
                                </div>
                                <div class="col-md-3 text-end">
                                    <small class="text-muted">
                                        Periode: <?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?>
                                    </small>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Revenue Summary -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="metric-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Total Invoice</h6>
                                        <h3 class="mb-0"><?= $revenue_summary['total_invoices'] ?></h3>
                                    </div>
                                    <i class="fas fa-file-invoice fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="metric-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Total Revenue</h6>
                                        <h5 class="mb-0"><?= formatRupiah($revenue_summary['total_revenue']) ?></h5>
                                    </div>
                                    <i class="fas fa-money-bill-wave fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="metric-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Revenue Lunas</h6>
                                        <h5 class="mb-0"><?= formatRupiah($revenue_summary['paid_revenue']) ?></h5>
                                    </div>
                                    <i class="fas fa-check-circle fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="metric-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Rata-rata Invoice</h6>
                                        <h5 class="mb-0"><?= formatRupiah($revenue_summary['avg_invoice_value']) ?></h5>
                                    </div>
                                    <i class="fas fa-calculator fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Monthly Trend -->
                        <div class="col-lg-8 mb-4">
                            <div class="card report-card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Tren Revenue 6 Bulan Terakhir</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($monthly_trend)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-chart-line fa-3x mb-3"></i>
                                        <p>Belum ada data revenue</p>
                                    </div>
                                    <?php else: ?>
                                    <div class="chart-container">
                                        <?php 
                                        $max_revenue = max(array_column($monthly_trend, 'monthly_revenue'));
                                        foreach ($monthly_trend as $month): 
                                            $height = $max_revenue > 0 ? ($month['monthly_revenue'] / $max_revenue) * 250 : 10;
                                        ?>
                                        <div class="chart-bar" style="height: <?= $height ?>px;" title="<?= date('M Y', strtotime($month['month'] . '-01')) ?>: <?= formatRupiah($month['monthly_revenue']) ?>">
                                            <small style="writing-mode: vertical-rl; text-orientation: mixed;">
                                                <?= date('M', strtotime($month['month'] . '-01')) ?>
                                            </small>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="row text-center">
                                        <?php foreach ($monthly_trend as $month): ?>
                                        <div class="col">
                                            <small class="text-muted"><?= date('M Y', strtotime($month['month'] . '-01')) ?></small><br>
                                            <strong><?= formatRupiah($month['monthly_revenue']) ?></strong><br>
                                            <small class="text-muted"><?= $month['invoice_count'] ?> invoice</small>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Top Customers -->
                        <div class="col-lg-4 mb-4">
                            <div class="card report-card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Top Customers</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($top_customers)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-users fa-2x mb-2"></i>
                                        <p>Belum ada data customer</p>
                                    </div>
                                    <?php else: ?>
                                    <?php foreach ($top_customers as $index => $customer): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars($customer['nama_pelanggan']) ?></div>
                                            <small class="text-muted">
                                                <?= $customer['invoice_count'] ?> invoice | 
                                                Terakhir: <?= date('d/m/Y', strtotime($customer['last_visit'])) ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold text-success"><?= formatRupiah($customer['total_spent']) ?></div>
                                            <span class="badge bg-primary">#<?= $index + 1 ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Top Services -->
                        <div class="col-lg-6 mb-4">
                            <div class="card report-card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Layanan Terpopuler</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($top_services)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-tools fa-2x mb-2"></i>
                                        <p>Belum ada data layanan</p>
                                    </div>
                                    <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Layanan</th>
                                                    <th>Digunakan</th>
                                                    <th>Revenue</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($top_services as $service): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($service['nama_service']) ?></td>
                                                    <td><?= $service['usage_count'] ?>x</td>
                                                    <td><?= formatRupiah($service['total_revenue']) ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Top Parts -->
                        <div class="col-lg-6 mb-4">
                            <div class="card report-card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Parts Terlaris</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($top_parts)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-cogs fa-2x mb-2"></i>
                                        <p>Belum ada data parts</p>
                                    </div>
                                    <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Part</th>
                                                    <th>Qty Terjual</th>
                                                    <th>Revenue</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($top_parts as $part): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($part['nama_part']) ?></td>
                                                    <td><?= $part['total_qty'] ?> pcs</td>
                                                    <td><?= formatRupiah($part['total_revenue']) ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php endif; ?>
                           <?php
require_once 'config.php';

// Require admin role for reports
requireMinimumRole('admin');

$pdo = getDBConnection();

// Get date range parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month

// Revenue Summary
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_invoices,
        SUM(total) as total_revenue,
        SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END) as paid_revenue,
        SUM(CASE WHEN status = 'posted' THEN total ELSE 0 END) as pending_revenue,
        AVG(total) as avg_invoice_value
    FROM invoices 
    WHERE invoice_date BETWEEN ? AND ?
");
$stmt->execute([$start_date, $end_date]);
$revenue_summary = $stmt->fetch();

// Monthly Revenue Trend (last 6 months)
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(invoice_date, '%Y-%m') as month,
        COUNT(*) as invoice_count,
        SUM(total) as monthly_revenue,
        SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END) as paid_revenue
    FROM invoices 
    WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(invoice_date, '%Y-%m')
    ORDER BY month ASC
");
$monthly_trend = $stmt->fetchAll();

// Top Services
$stmt = $pdo->prepare("
    SELECT 
        s.nama_service,
        COUNT(*) as usage_count,
        SUM(ins.total) as total_revenue,
        AVG(ins.harga) as avg_price
    FROM invoice_services ins
    JOIN services s ON ins.service_id = s.id
    JOIN invoices i ON ins.invoice_id = i.id
    WHERE i.invoice_date BETWEEN ? AND ?
    GROUP BY s.id, s.nama_service
    ORDER BY total_revenue DESC
    LIMIT 5
");
$stmt->execute([$start_date, $end_date]);
$top_services = $stmt->fetchAll();

// Top Parts
$stmt = $pdo->prepare("
    SELECT 
        p.nama_part,
        SUM(inp.qty) as total_qty,
        SUM(inp.total) as total_revenue,
        COUNT(DISTINCT inp.invoice_id) as usage_count
    FROM invoice_parts inp
    JOIN parts p ON inp.part_id = p.id
    JOIN invoices i ON inp.invoice_id = i.id
    WHERE i.invoice_date BETWEEN ? AND ?
    GROUP BY p.id, p.nama_part
    ORDER BY total_revenue DESC
    LIMIT 5
");
$stmt->execute([$start_date, $end_date]);
$top_parts = $stmt->fetchAll();

// Customer Analysis
$stmt = $pdo->prepare("
    SELECT 
        c.nama_pelanggan,
        COUNT(*) as invoice_count,
        SUM(i.total) as total_spent,
        MAX(i.invoice_date) as last_visit
    FROM invoices i
    JOIN customers c ON i.customer_id = c.id
    WHERE i.invoice_date BETWEEN ? AND ?
    GROUP BY c.id, c.nama_pelanggan
    ORDER BY total_spent DESC
    LIMIT 10
");
$stmt->execute([$start_date, $end_date]);
$top_customers = $stmt->fetchAll();

// Daily Revenue (current month)
$stmt = $pdo->prepare("
    SELECT 
        DAY(invoice_date) as day,
        COUNT(*) as invoice_count,
        SUM(total) as daily_revenue
    FROM invoices 
    WHERE MONTH(invoice_date) = MONTH(?) AND YEAR(invoice_date) = YEAR(?)
    GROUP BY DAY(invoice_date)
    ORDER BY day ASC
");
$stmt->execute([$end_date, $end_date]);
$daily_revenue = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alfina AC Mobil - Laporan</title>
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
        .report-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .report-card:hover {
            transform: translateY(-2px);
        }
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .chart-container {
            height: 300px;
            display: flex;
            align-items: end;
            justify-content: space-between;
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .chart-bar {
            background: linear-gradient(to top, #667eea, #764ba2);
            border-radius: 4px 4px 0 0;
            min-height: 10px;
            flex: 1;
            margin: 0 2px;
            display: flex;
            align-items: end;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-light">
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
                            <a href="reports.php" class="nav-link text-white active">
                                <i class="fas fa-chart-bar me-2"></i>
                                Laporan
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0">Laporan & Analisa</h1>
                        <button onclick="window.print()" class="btn btn-outline-primary">
                            <i class="fas fa-print me-2"></i>Cetak Laporan
                        </button>
                    </div>

                    <!-- Date Filter -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row align-items-center">
                                <div class="col-md-3">
                                    <label class="form-label">Tanggal Mulai</label>
                                    <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Tanggal Akhir</label>
                                    <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-filter me-1"></i>Filter
                                        </button>
                                        <a href="reports.php" class="btn btn-outline-secondary ms-2">Reset</a>
                                    </div>
                                </div>
                                <div class="col-md-3 text-end">
                                    <small class="text-muted">
                                        Periode: <?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?>
                                    </small>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Revenue Summary -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="metric-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Total Invoice</h6>
                                        <h3 class="mb-0"><?= $revenue_summary['total_invoices'] ?></h3>
                                    </div>
                                    <i class="fas fa-file-invoice fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="metric-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Total Revenue</h6>
                                        <h5 class="mb-0"><?= formatRupiah($revenue_summary['total_revenue']) ?></h5>
                                    </div>
                                    <i class="fas fa-money-bill-wave fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="metric-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Revenue Lunas</h6>
                                        <h5 class="mb-0"><?= formatRupiah($revenue_summary['paid_revenue']) ?></h5>
                                    </div>
                                    <i class="fas fa-check-circle fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="metric-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Rata-rata Invoice</h6>
                                        <h5 class="mb-0"><?= formatRupiah($revenue_summary['avg_invoice_value']) ?></h5>
                                    </div>
                                    <i class="fas fa-calculator fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Monthly Trend -->
                        <div class="col-lg-8 mb-4">
                            <div class="card report-card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Tren Revenue 6 Bulan Terakhir</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($monthly_trend)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-chart-line fa-3x mb-3"></i>
                                        <p>Belum ada data revenue</p>
                                    </div>
                                    <?php else: ?>
                                    <div class="chart-container">
                                        <?php 
                                        $max_revenue = max(array_column($monthly_trend, 'monthly_revenue'));
                                        foreach ($monthly_trend as $month): 
                                            $height = $max_revenue > 0 ? ($month['monthly_revenue'] / $max_revenue) * 250 : 10;
                                        ?>
                                        <div class="chart-bar" style="height: <?= $height ?>px;" title="<?= date('M Y', strtotime($month['month'] . '-01')) ?>: <?= formatRupiah($month['monthly_revenue']) ?>">
                                            <small style="writing-mode: vertical-rl; text-orientation: mixed;">
                                                <?= date('M', strtotime($month['month'] . '-01')) ?>
                                            </small>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="row text-center">
                                        <?php foreach ($monthly_trend as $month): ?>
                                        <div class="col">
                                            <small class="text-muted"><?= date('M Y', strtotime($month['month'] . '-01')) ?></small><br>
                                            <strong><?= formatRupiah($month['monthly_revenue']) ?></strong><br>
                                            <small class="text-muted"><?= $month['invoice_count'] ?> invoice</small>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Top Customers -->
                        <div class="col-lg-4 mb-4">
                            <div class="card report-card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Top Customers</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($top_customers)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-users fa-2x mb-2"></i>
                                        <p>Belum ada data customer</p>
                                    </div>
                                    <?php else: ?>
                                    <?php foreach ($top_customers as $index => $customer): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars($customer['nama_pelanggan']) ?></div>
                                            <small class="text-muted">
                                                <?= $customer['invoice_count'] ?> invoice | 
                                                Terakhir: <?= date('d/m/Y', strtotime($customer['last_visit'])) ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold text-success"><?= formatRupiah($customer['total_spent']) ?></div>
                                            <span class="badge bg-primary">#<?= $index + 1 ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Top Services -->
                        <div class="col-lg-6 mb-4">
                            <div class="card report-card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Layanan Terpopuler</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($top_services)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-tools fa-2x mb-2"></i>
                                        <p>Belum ada data layanan</p>
                                    </div>
                                    <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Layanan</th>
                                                    <th>Digunakan</th>
                                                    <th>Revenue</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($top_services as $service): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($service['nama_service']) ?></td>
                                                    <td><?= $service['usage_count'] ?>x</td>
                                                    <td><?= formatRupiah($service['total_revenue']) ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Top Parts -->
                        <div class="col-lg-6 mb-4">
                            <div class="card report-card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Parts Terlaris</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($top_parts)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-cogs fa-2x mb-2"></i>
                                        <p>Belum ada data parts</p>
                                    </div>
                                    <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Part</th>
                                                    <th>Qty Terjual</th>
                                                    <th>Revenue</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($top_parts as $part): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($part['nama_part']) ?></td>
                                                    <td><?= $part['total_qty'] ?> pcs</td>
                                                    <td><?= formatRupiah($part['total_revenue']) ?></td>
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
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>