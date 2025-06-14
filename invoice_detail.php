<?php
require_once 'config.php';

$pdo = getDBConnection();

// Get invoice ID
$invoice_id = $_GET['id'] ?? null;
if (!$invoice_id) {
    header("Location: invoices.php");
    exit;
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'update_status') {
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
}

// Get invoice details
$stmt = $pdo->prepare("
    SELECT i.*, c.nama_pelanggan, c.no_hp, c.alamat,
           v.no_polisi, v.merek_kendaraan, v.tipe_kendaraan, v.tahun_kendaraan, v.warna
    FROM invoices i
    JOIN customers c ON i.customer_id = c.id
    JOIN vehicles v ON i.vehicle_id = v.id
    WHERE i.id = ?
");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    header("Location: invoices.php?error=Invoice tidak ditemukan");
    exit;
}

// Get invoice services
$stmt = $pdo->prepare("SELECT * FROM invoice_services WHERE invoice_id = ?");
$stmt->execute([$invoice_id]);
$invoice_services = $stmt->fetchAll();

// Get invoice parts
$stmt = $pdo->prepare("SELECT * FROM invoice_parts WHERE invoice_id = ?");
$stmt->execute([$invoice_id]);
$invoice_parts = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alfina AC Mobil - Invoice <?= htmlspecialchars($invoice['invoice_number']) ?></title>
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
        .invoice-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .invoice-content {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        .status-badge {
            font-size: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 25px;
        }
        @media print {
            .sidebar, .no-print { display: none !important; }
            .col-md-9, .col-lg-10 { width: 100% !important; }
            .invoice-content { box-shadow: none; }
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 no-print">
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
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <!-- Header Actions -->
                    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                        <div>
                            <h1 class="h3 mb-0">Detail Invoice</h1>
                            <small class="text-muted">Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?></small>
                        </div>
                        <div class="btn-group">
                            <a href="invoices.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Kembali
                            </a>
                            <button onclick="window.print()" class="btn btn-outline-primary">
                                <i class="fas fa-print me-2"></i>Preview
                            </button>
                            <a href="print_invoice.php?id=<?= $invoice['id'] ?>" target="_blank" class="btn btn-primary">
                                <i class="fas fa-print me-2"></i>Print Dot Matrix
                            </a>
                            <?php if ($invoice['status'] === 'draft'): ?>
                            <a href="invoice_edit.php?id=<?= $invoice['id'] ?>" class="btn btn-warning">
                                <i class="fas fa-edit me-2"></i>Edit
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Success Message -->
                    <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?= $success ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_GET['success']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Invoice Header -->
                    <div class="invoice-header">
                        <div class="row">
                            <div class="col-md-6">
                                <h2 class="mb-3">
                                    <i class="fas fa-snowflake me-2"></i>
                                    ALFINA AC MOBIL
                                </h2>
                                <p class="mb-1">Jasa Service AC Mobil Profesional</p>
                                <p class="mb-0">Jakarta, Indonesia</p>
                            </div>
                            <div class="col-md-6 text-end">
                                <h3 class="mb-3">INVOICE</h3>
                                <h4 class="mb-2"><?= htmlspecialchars($invoice['invoice_number']) ?></h4>
                                <div class="mb-3">
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
                                    <span class="badge <?= $statusClass ?> status-badge"><?= $statusText ?></span>
                                </div>
                                <p class="mb-0">Tanggal: <?= formatDateIndo($invoice['invoice_date']) ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Invoice Content -->
                    <div class="invoice-content">
                        <!-- Customer & Vehicle Info -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5 class="mb-3">Informasi Pelanggan</h5>
                                <div class="mb-2">
                                    <strong><?= htmlspecialchars($invoice['nama_pelanggan']) ?></strong>
                                </div>
                                <?php if ($invoice['no_hp']): ?>
                                <div class="mb-2">
                                    <i class="fas fa-phone me-2 text-muted"></i>
                                    <?= htmlspecialchars($invoice['no_hp']) ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($invoice['alamat']): ?>
                                <div class="mb-2">
                                    <i class="fas fa-map-marker-alt me-2 text-muted"></i>
                                    <?= htmlspecialchars($invoice['alamat']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h5 class="mb-3">Informasi Kendaraan</h5>
                                <div class="mb-2">
                                    <strong><?= htmlspecialchars($invoice['no_polisi']) ?></strong>
                                </div>
                                <div class="mb-2">
                                    <?= htmlspecialchars($invoice['merek_kendaraan']) ?> 
                                    <?= htmlspecialchars($invoice['tipe_kendaraan']) ?>
                                </div>
                                <div class="mb-2">
                                    <span class="text-muted">Tahun:</span> <?= $invoice['tahun_kendaraan'] ?>
                                    <?php if ($invoice['warna']): ?>
                                    | <span class="text-muted">Warna:</span> <?= htmlspecialchars($invoice['warna']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Services -->
                        <?php if (!empty($invoice_services)): ?>
                        <div class="mb-4">
                            <h5 class="mb-3">Layanan</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Layanan</th>
                                            <th width="10%">Qty</th>
                                            <th width="20%">Harga</th>
                                            <th width="20%">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invoice_services as $service): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($service['nama_service']) ?></td>
                                            <td class="text-center"><?= $service['qty'] ?></td>
                                            <td class="text-end"><?= formatRupiah($service['harga']) ?></td>
                                            <td class="text-end"><?= formatRupiah($service['total']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Parts -->
                        <?php if (!empty($invoice_parts)): ?>
                        <div class="mb-4">
                            <h5 class="mb-3">Parts & Komponen</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Part</th>
                                            <th width="10%">Qty</th>
                                            <th width="20%">Harga</th>
                                            <th width="20%">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invoice_parts as $part): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($part['nama_part']) ?></td>
                                            <td class="text-center"><?= $part['qty'] ?></td>
                                            <td class="text-end"><?= formatRupiah($part['harga']) ?></td>
                                            <td class="text-end"><?= formatRupiah($part['total']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Totals -->
                        <div class="row">
                            <div class="col-md-6">
                                <?php if ($invoice['catatan']): ?>
                                <div class="mb-3">
                                    <h6>Catatan:</h6>
                                    <p class="text-muted"><?= nl2br(htmlspecialchars($invoice['catatan'])) ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Subtotal:</span>
                                            <strong><?= formatRupiah($invoice['subtotal']) ?></strong>
                                        </div>
                                        <?php if ($invoice['discount_percent'] > 0): ?>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Diskon (<?= $invoice['discount_percent'] ?>%):</span>
                                            <strong class="text-danger">-<?= formatRupiah($invoice['discount_amount']) ?></strong>
                                        </div>
                                        <?php endif; ?>
                                        <hr>
                                        <div class="d-flex justify-content-between">
                                            <span class="h5">Total:</span>
                                            <strong class="h4 text-primary"><?= formatRupiah($invoice['total']) ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Status Update (only for non-print) -->
                        <?php if ($invoice['status'] !== 'paid'): ?>
                        <div class="mt-4 no-print">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Update Status</h6>
                                </div>
                                <div class="card-body">
                                    <form method="POST" class="d-flex align-items-center gap-3">
                                        <input type="hidden" name="action" value="update_status">
                                        <select name="status" class="form-select" style="width: auto;">
                                            <option value="draft" <?= $invoice['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                                            <option value="posted" <?= $invoice['status'] === 'posted' ? 'selected' : '' ?>>Posted</option>
                                            <option value="paid" <?= $invoice['status'] === 'paid' ? 'selected' : '' ?>>Lunas</option>
                                        </select>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Update Status
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>