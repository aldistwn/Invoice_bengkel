<?php
require_once 'config.php';

// Require admin role for services management
requireMinimumRole('admin');

$pdo = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'add_service') {
        $nama = $_POST['nama_service'];
        $deskripsi = $_POST['deskripsi'];
        $harga = $_POST['harga_default'];
        
        $stmt = $pdo->prepare("INSERT INTO services (nama_service, deskripsi, harga_default) VALUES (?, ?, ?)");
        $stmt->execute([$nama, $deskripsi, $harga]);
        
        $success = "Layanan berhasil ditambahkan!";
    }
    
    if ($_POST['action'] === 'edit_service') {
        $id = $_POST['service_id'];
        $nama = $_POST['nama_service'];
        $deskripsi = $_POST['deskripsi'];
        $harga = $_POST['harga_default'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $pdo->prepare("UPDATE services SET nama_service = ?, deskripsi = ?, harga_default = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$nama, $deskripsi, $harga, $is_active, $id]);
        
        $success = "Layanan berhasil diupdate!";
    }
    
    if ($_POST['action'] === 'toggle_status') {
        $id = $_POST['service_id'];
        $stmt = $pdo->prepare("UPDATE services SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        
        $success = "Status layanan berhasil diubah!";
    }
}

// Get all services
$stmt = $pdo->query("SELECT * FROM services ORDER BY is_active DESC, nama_service ASC");
$services = $stmt->fetchAll();

// Get service for edit if ID provided
$editService = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editService = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alfina AC Mobil - Manajemen Layanan</title>
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
        .service-card {
            transition: transform 0.2s ease;
        }
        .service-card:hover {
            transform: translateY(-2px);
        }
        .inactive-service {
            opacity: 0.6;
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
                            <a href="services.php" class="nav-link text-white active">
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
                        <h1 class="h3 mb-0">Manajemen Layanan AC Mobil</h1>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#serviceModal">
                            <i class="fas fa-plus me-2"></i>Tambah Layanan
                        </button>
                    </div>

                    <!-- Success Message -->
                    <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?= $success ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Services Grid -->
                    <?php if (empty($services)): ?>
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Belum ada layanan</h5>
                            <p class="text-muted">Tambahkan layanan AC mobil yang tersedia</p>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach ($services as $service): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card service-card h-100 <?= !$service['is_active'] ? 'inactive-service' : '' ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="flex-grow-1">
                                            <h5 class="card-title">
                                                <i class="fas fa-tools me-2 text-primary"></i>
                                                <?= htmlspecialchars($service['nama_service']) ?>
                                            </h5>
                                            <?php if (!$service['is_active']): ?>
                                            <span class="badge bg-secondary mb-2">Nonaktif</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="?edit=<?= $service['id'] ?>">
                                                    <i class="fas fa-edit me-2"></i>Edit
                                                </a></li>
                                                <li>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="service_id" value="<?= $service['id'] ?>">
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="fas fa-power-off me-2"></i>
                                                            <?= $service['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                                                        </button>
                                                    </form>
                                                </li>
                                                <li><a class="dropdown-item text-danger" href="#" onclick="deleteService(<?= $service['id'] ?>)">
                                                    <i class="fas fa-trash me-2"></i>Hapus
                                                </a></li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <?php if ($service['deskripsi']): ?>
                                    <p class="text-muted mb-3"><?= htmlspecialchars($service['deskripsi']) ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted">Harga Default:</small><br>
                                            <strong class="text-success h5"><?= formatRupiah($service['harga_default']) ?></strong>
                                        </div>
                                        
                                        <?php if ($service['is_active']): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check me-1"></i>Aktif
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            Dibuat: <?= date('d/m/Y', strtotime($service['created_at'])) ?>
                                        </small>
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

    <!-- Service Modal -->
    <div class="modal fade" id="serviceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?= $editService ? 'Edit Layanan' : 'Tambah Layanan Baru' ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="<?= $editService ? 'edit_service' : 'add_service' ?>">
                        <?php if ($editService): ?>
                        <input type="hidden" name="service_id" value="<?= $editService['id'] ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Nama Layanan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama_service" 
                                   value="<?= $editService ? htmlspecialchars($editService['nama_service']) : '' ?>" 
                                   placeholder="Contoh: Vacuum + Isi Freon + Oli Compressor" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Deskripsi Layanan</label>
                            <textarea class="form-control" name="deskripsi" rows="3" 
                                      placeholder="Deskripsi detail layanan yang diberikan"><?= $editService ? htmlspecialchars($editService['deskripsi']) : '' ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Harga Default <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" name="harga_default" 
                                           value="<?= $editService ? $editService['harga_default'] : '' ?>" 
                                           placeholder="250000" min="0" required>
                                </div>
                                <div class="form-text">Harga ini bisa diubah saat membuat invoice</div>
                            </div>

                            <?php if ($editService): ?>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Status</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="is_active" 
                                           <?= $editService['is_active'] ? 'checked' : '' ?>>
                                    <label class="form-check-label">Aktif</label>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            <?= $editService ? 'Update' : 'Simpan' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto show modal if editing
        <?php if ($editService): ?>
        var serviceModal = new bootstrap.Modal(document.getElementById('serviceModal'));
        serviceModal.show();
        <?php endif; ?>

        function deleteService(id) {
            if (confirm('Yakin ingin menghapus layanan ini?')) {
                window.location.href = 'delete_service.php?id=' + id;
            }
        }

        // Format number input
        document.querySelector('input[name="harga_default"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^\d]/g, '');
            if (value) {
                e.target.value = value;
            }
        });
    </script>
</body>
</html>