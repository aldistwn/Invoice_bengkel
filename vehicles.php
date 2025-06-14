<?php
require_once 'config.php';

$pdo = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'add_vehicle') {
        $customer_id = $_POST['customer_id'];
        $no_polisi = strtoupper($_POST['no_polisi']);
        $merek = $_POST['merek_kendaraan'];
        $tipe = $_POST['tipe_kendaraan'];
        $tahun = $_POST['tahun_kendaraan'];
        $warna = $_POST['warna'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO vehicles (customer_id, no_polisi, merek_kendaraan, tipe_kendaraan, tahun_kendaraan, warna) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$customer_id, $no_polisi, $merek, $tipe, $tahun, $warna]);
            $success = "Kendaraan berhasil ditambahkan!";
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
    
    if ($_POST['action'] === 'edit_vehicle') {
        $id = $_POST['vehicle_id'];
        $customer_id = $_POST['customer_id'];
        $no_polisi = strtoupper($_POST['no_polisi']);
        $merek = $_POST['merek_kendaraan'];
        $tipe = $_POST['tipe_kendaraan'];
        $tahun = $_POST['tahun_kendaraan'];
        $warna = $_POST['warna'];
        
        try {
            $stmt = $pdo->prepare("UPDATE vehicles SET customer_id = ?, no_polisi = ?, merek_kendaraan = ?, tipe_kendaraan = ?, tahun_kendaraan = ?, warna = ? WHERE id = ?");
            $stmt->execute([$customer_id, $no_polisi, $merek, $tipe, $tahun, $warna, $id]);
            $success = "Data kendaraan berhasil diupdate!";
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Filter by customer if provided
$customerFilter = isset($_GET['customer_id']) ? $_GET['customer_id'] : '';

// Get vehicles with customer info
$sql = "
    SELECT v.*, c.nama_pelanggan 
    FROM vehicles v 
    JOIN customers c ON v.customer_id = c.id
";
$params = [];

if ($customerFilter) {
    $sql .= " WHERE v.customer_id = ?";
    $params[] = $customerFilter;
}

$sql .= " ORDER BY v.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vehicles = $stmt->fetchAll();

// Get all customers for dropdown
$stmt = $pdo->query("SELECT * FROM customers ORDER BY nama_pelanggan");
$customers = $stmt->fetchAll();

// Get vehicle for edit if ID provided
$editVehicle = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editVehicle = $stmt->fetch();
}

// Get customer name for filter display
$customerName = '';
if ($customerFilter) {
    $stmt = $pdo->prepare("SELECT nama_pelanggan FROM customers WHERE id = ?");
    $stmt->execute([$customerFilter]);
    $customerName = $stmt->fetch()['nama_pelanggan'] ?? '';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alfina AC Mobil - Manajemen Kendaraan</title>
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
        .vehicle-card {
            border-left: 4px solid #007bff;
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
                            <a href="vehicles.php" class="nav-link text-white active">
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
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 mb-0">Manajemen Kendaraan</h1>
                            <?php if ($customerFilter): ?>
                            <small class="text-muted">
                                Kendaraan milik: <strong><?= htmlspecialchars($customerName) ?></strong>
                                <a href="vehicles.php" class="ms-2 text-decoration-none">(Lihat Semua)</a>
                            </small>
                            <?php endif; ?>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#vehicleModal">
                            <i class="fas fa-plus me-2"></i>Tambah Kendaraan
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

                    <!-- Filter & Search -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <select class="form-select" onchange="filterByCustomer(this.value)">
                                        <option value="">Semua Pelanggan</option>
                                        <?php foreach ($customers as $customer): ?>
                                        <option value="<?= $customer['id'] ?>" <?= $customerFilter == $customer['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($customer['nama_pelanggan']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 text-end">
                                    <span class="text-muted">Total: <?= count($vehicles) ?> kendaraan</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vehicle List -->
                    <?php if (empty($vehicles)): ?>
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-car fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Belum ada data kendaraan</h5>
                            <p class="text-muted">Tambahkan kendaraan pertama dengan klik tombol "Tambah Kendaraan"</p>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach ($vehicles as $vehicle): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card vehicle-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-car me-2 text-primary"></i>
                                            <?= htmlspecialchars($vehicle['no_polisi']) ?>
                                        </h5>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="?edit=<?= $vehicle['id'] ?>">
                                                    <i class="fas fa-edit me-2"></i>Edit
                                                </a></li>
                                                <li><a class="dropdown-item text-danger" href="#" onclick="deleteVehicle(<?= $vehicle['id'] ?>)">
                                                    <i class="fas fa-trash me-2"></i>Hapus
                                                </a></li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <strong><?= htmlspecialchars($vehicle['merek_kendaraan']) ?></strong>
                                        <br>
                                        <span class="text-muted"><?= htmlspecialchars($vehicle['tipe_kendaraan']) ?></span>
                                    </div>
                                    
                                    <div class="row text-sm">
                                        <div class="col-6">
                                            <small class="text-muted">Tahun:</small><br>
                                            <span><?= $vehicle['tahun_kendaraan'] ?></span>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Warna:</small><br>
                                            <span><?= htmlspecialchars($vehicle['warna']) ?></span>
                                        </div>
                                    </div>
                                    
                                    <hr class="my-3">
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i>
                                            <?= htmlspecialchars($vehicle['nama_pelanggan']) ?>
                                        </small>
                                        <a href="invoice_create.php?vehicle_id=<?= $vehicle['id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-file-invoice me-1"></i>Invoice
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Vehicle Modal -->
    <div class="modal fade" id="vehicleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?= $editVehicle ? 'Edit Kendaraan' : 'Tambah Kendaraan Baru' ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="<?= $editVehicle ? 'edit_vehicle' : 'add_vehicle' ?>">
                        <?php if ($editVehicle): ?>
                        <input type="hidden" name="vehicle_id" value="<?= $editVehicle['id'] ?>">
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Pelanggan <span class="text-danger">*</span></label>
                                <select class="form-select" name="customer_id" required>
                                    <option value="">Pilih Pelanggan</option>
                                    <?php foreach ($customers as $customer): ?>
                                    <option value="<?= $customer['id'] ?>" 
                                            <?= ($editVehicle && $editVehicle['customer_id'] == $customer['id']) || $customerFilter == $customer['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($customer['nama_pelanggan']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">No. Polisi <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="no_polisi" 
                                       value="<?= $editVehicle ? htmlspecialchars($editVehicle['no_polisi']) : '' ?>" 
                                       placeholder="B1234ABC" style="text-transform: uppercase;" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Merek Kendaraan <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="merek_kendaraan" 
                                       value="<?= $editVehicle ? htmlspecialchars($editVehicle['merek_kendaraan']) : '' ?>" 
                                       placeholder="Toyota, Honda, Daihatsu, dll" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tipe Kendaraan <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="tipe_kendaraan" 
                                       value="<?= $editVehicle ? htmlspecialchars($editVehicle['tipe_kendaraan']) : '' ?>" 
                                       placeholder="Avanza 1.3 G, Jazz RS, dll" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tahun Kendaraan</label>
                                <select class="form-select" name="tahun_kendaraan">
                                    <option value="">Pilih Tahun</option>
                                    <?php for ($year = date('Y'); $year >= 1990; $year--): ?>
                                    <option value="<?= $year ?>" <?= $editVehicle && $editVehicle['tahun_kendaraan'] == $year ? 'selected' : '' ?>>
                                        <?= $year ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Warna</label>
                                <input type="text" class="form-control" name="warna" 
                                       value="<?= $editVehicle ? htmlspecialchars($editVehicle['warna']) : '' ?>" 
                                       placeholder="Putih, Hitam, Silver, dll">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            <?= $editVehicle ? 'Update' : 'Simpan' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto show modal if editing
        <?php if ($editVehicle): ?>
        var vehicleModal = new bootstrap.Modal(document.getElementById('vehicleModal'));
        vehicleModal.show();
        <?php endif; ?>

        function filterByCustomer(customerId) {
            if (customerId) {
                window.location.href = 'vehicles.php?customer_id=' + customerId;
            } else {
                window.location.href = 'vehicles.php';
            }
        }

        function deleteVehicle(id) {
            if (confirm('Yakin ingin menghapus kendaraan ini?')) {
                window.location.href = 'delete_vehicle.php?id=' + id;
            }
        }
    </script>
</body>
</html>