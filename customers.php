<?php
require_once 'config.php';

// Require minimum operator role for customer management
requireMinimumRole('operator');

$pdo = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'add_customer') {
        $nama = $_POST['nama_pelanggan'];
        $no_hp = $_POST['no_hp'];
        $alamat = $_POST['alamat'];
        
        $stmt = $pdo->prepare("INSERT INTO customers (nama_pelanggan, no_hp, alamat) VALUES (?, ?, ?)");
        $stmt->execute([$nama, $no_hp, $alamat]);
        
        $success = "Pelanggan berhasil ditambahkan!";
    }
    
    if ($_POST['action'] === 'edit_customer') {
        $id = $_POST['customer_id'];
        $nama = $_POST['nama_pelanggan'];
        $no_hp = $_POST['no_hp'];
        $alamat = $_POST['alamat'];
        
        $stmt = $pdo->prepare("UPDATE customers SET nama_pelanggan = ?, no_hp = ?, alamat = ? WHERE id = ?");
        $stmt->execute([$nama, $no_hp, $alamat, $id]);
        
        $success = "Data pelanggan berhasil diupdate!";
    }
}

// Get all customers with their vehicles count
$stmt = $pdo->query("
    SELECT c.*, COUNT(v.id) as vehicle_count 
    FROM customers c 
    LEFT JOIN vehicles v ON c.id = v.customer_id 
    GROUP BY c.id 
    ORDER BY c.nama_pelanggan
");
$customers = $stmt->fetchAll();

// Get customer for edit if ID provided
$editCustomer = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editCustomer = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alfina AC Mobil - Manajemen Pelanggan</title>
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
                            <a href="customers.php" class="nav-link text-white active">
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
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0">Manajemen Pelanggan</h1>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal">
                            <i class="fas fa-plus me-2"></i>Tambah Pelanggan
                        </button>
                    </div>

                    <!-- Success Message -->
                    <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?= $success ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Customer List -->
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Daftar Pelanggan</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Nama Pelanggan</th>
                                            <th>No. HP</th>
                                            <th>Alamat</th>
                                            <th>Jumlah Kendaraan</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no = 1; foreach ($customers as $customer): ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($customer['nama_pelanggan']) ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($customer['no_hp']): ?>
                                                <a href="tel:<?= $customer['no_hp'] ?>" class="text-decoration-none">
                                                    <i class="fas fa-phone me-1"></i><?= htmlspecialchars($customer['no_hp']) ?>
                                                </a>
                                                <?php else: ?>
                                                <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= $customer['alamat'] ? htmlspecialchars($customer['alamat']) : '<span class="text-muted">-</span>' ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?= $customer['vehicle_count'] ?> kendaraan</span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="vehicles.php?customer_id=<?= $customer['id'] ?>" class="btn btn-outline-info" title="Lihat Kendaraan">
                                                        <i class="fas fa-car"></i>
                                                    </a>
                                                    <a href="?edit=<?= $customer['id'] ?>" class="btn btn-outline-warning" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button class="btn btn-outline-danger" onclick="deleteCustomer(<?= $customer['id'] ?>)" title="Hapus">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Modal -->
    <div class="modal fade" id="customerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?= $editCustomer ? 'Edit Pelanggan' : 'Tambah Pelanggan Baru' ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="<?= $editCustomer ? 'edit_customer' : 'add_customer' ?>">
                        <?php if ($editCustomer): ?>
                        <input type="hidden" name="customer_id" value="<?= $editCustomer['id'] ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Nama Pelanggan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama_pelanggan" 
                                   value="<?= $editCustomer ? htmlspecialchars($editCustomer['nama_pelanggan']) : '' ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">No. HP/WhatsApp</label>
                            <input type="text" class="form-control" name="no_hp" 
                                   value="<?= $editCustomer ? htmlspecialchars($editCustomer['no_hp']) : '' ?>" 
                                   placeholder="08xxxxxxxxx">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Alamat</label>
                            <textarea class="form-control" name="alamat" rows="3" 
                                      placeholder="Alamat lengkap pelanggan"><?= $editCustomer ? htmlspecialchars($editCustomer['alamat']) : '' ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            <?= $editCustomer ? 'Update' : 'Simpan' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto show modal if editing
        <?php if ($editCustomer): ?>
        var customerModal = new bootstrap.Modal(document.getElementById('customerModal'));
        customerModal.show();
        <?php endif; ?>

        function deleteCustomer(id) {
            if (confirm('Yakin ingin menghapus pelanggan ini?')) {
                window.location.href = 'delete_customer.php?id=' + id;
            }
        }
    </script>
</body>
</html>