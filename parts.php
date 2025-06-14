<?php
require_once 'config.php';

// Require admin role for parts & inventory management
requireMinimumRole('admin');

$pdo = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'add_part') {
        $nama = $_POST['nama_part'];
        $kode = $_POST['kode_part'];
        $stok = $_POST['stok_gudang'];
        $harga_beli = $_POST['harga_beli'];
        $harga_jual = $_POST['harga_jual'];
        $satuan = $_POST['satuan'];
        $minimum_stok = $_POST['minimum_stok'];
        
        $stmt = $pdo->prepare("INSERT INTO parts (nama_part, kode_part, stok_gudang, harga_beli, harga_jual, satuan, minimum_stok) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nama, $kode, $stok, $harga_beli, $harga_jual, $satuan, $minimum_stok]);
        
        // Log stock movement untuk stok awal
        if ($stok > 0) {
            $part_id = $pdo->lastInsertId();
            updateStock($part_id, $stok, 'in', null, 'Stok awal');
        }
        
        $success = "Part berhasil ditambahkan!";
    }
    
    if ($_POST['action'] === 'edit_part') {
        $id = $_POST['part_id'];
        $nama = $_POST['nama_part'];
        $kode = $_POST['kode_part'];
        $harga_beli = $_POST['harga_beli'];
        $harga_jual = $_POST['harga_jual'];
        $satuan = $_POST['satuan'];
        $minimum_stok = $_POST['minimum_stok'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $pdo->prepare("UPDATE parts SET nama_part = ?, kode_part = ?, harga_beli = ?, harga_jual = ?, satuan = ?, minimum_stok = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$nama, $kode, $harga_beli, $harga_jual, $satuan, $minimum_stok, $is_active, $id]);
        
        $success = "Part berhasil diupdate!";
    }
    
    if ($_POST['action'] === 'adjust_stock') {
        $part_id = $_POST['part_id'];
        $adjustment = $_POST['adjustment'];
        $keterangan = $_POST['keterangan'];
        
        if ($adjustment != 0) {
            $movement_type = $adjustment > 0 ? 'in' : 'out';
            $qty = abs($adjustment);
            
            updateStock($part_id, $qty, $movement_type, null, $keterangan);
            
            $success = "Stok berhasil disesuaikan!";
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$low_stock_only = isset($_GET['low_stock']);

// Build query based on filters
$where_conditions = [];
$params = [];

if ($status_filter === 'active') {
    $where_conditions[] = "is_active = 1";
} elseif ($status_filter === 'inactive') {
    $where_conditions[] = "is_active = 0";
}

if ($low_stock_only) {
    $where_conditions[] = "stok_gudang <= minimum_stok";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$stmt = $pdo->prepare("SELECT * FROM parts $where_clause ORDER BY nama_part");
$stmt->execute($params);
$parts = $stmt->fetchAll();

// Get part for edit if ID provided
$editPart = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM parts WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editPart = $stmt->fetch();
}

// Get recent stock movements
$stmt = $pdo->query("
    SELECT sm.*, p.nama_part, p.satuan 
    FROM stock_movements sm 
    JOIN parts p ON sm.part_id = p.id 
    ORDER BY sm.created_at DESC 
    LIMIT 10
");
$recentMovements = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alfina AC Mobil - Parts & Stok</title>
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
        .low-stock {
            border-left: 4px solid #dc3545;
        }
        .good-stock {
            border-left: 4px solid #28a745;
        }
        .warning-stock {
            border-left: 4px solid #ffc107;
        }
        .stock-badge {
            font-size: 0.9rem;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
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
                            <a href="parts.php" class="nav-link text-white active">
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
                        <h1 class="h3 mb-0">Parts & Manajemen Stok</h1>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#partModal">
                            <i class="fas fa-plus me-2"></i>Tambah Part
                        </button>
                    </div>

                    <!-- Success Message -->
                    <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?= $success ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <select class="form-select" onchange="filterByStatus(this.value)">
                                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Semua Status</option>
                                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Aktif</option>
                                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" <?= $low_stock_only ? 'checked' : '' ?> 
                                               onchange="filterLowStock(this.checked)">
                                        <label class="form-check-label">Stok Rendah Saja</label>
                                    </div>
                                </div>
                                <div class="col-md-4 text-end">
                                    <span class="text-muted">Total: <?= count($parts) ?> parts</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Parts List -->
                        <div class="col-lg-8">
                            <?php if (empty($parts)): ?>
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-cogs fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Belum ada data parts</h5>
                                    <p class="text-muted">Tambahkan parts dan komponen AC mobil</p>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="row">
                                <?php foreach ($parts as $part): 
                                    $stockClass = '';
                                    $stockStatus = '';
                                    $stockBadgeClass = '';
                                    
                                    if ($part['stok_gudang'] <= 0) {
                                        $stockClass = 'low-stock';
                                        $stockStatus = 'Habis';
                                        $stockBadgeClass = 'bg-danger';
                                    } elseif ($part['stok_gudang'] <= $part['minimum_stok']) {
                                        $stockClass = 'warning-stock';
                                        $stockStatus = 'Rendah';
                                        $stockBadgeClass = 'bg-warning text-dark';
                                    } else {
                                        $stockClass = 'good-stock';
                                        $stockStatus = 'Aman';
                                        $stockBadgeClass = 'bg-success';
                                    }
                                ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card <?= $stockClass ?> h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div class="flex-grow-1">
                                                    <h6 class="card-title mb-1">
                                                        <?= htmlspecialchars($part['nama_part']) ?>
                                                        <?php if (!$part['is_active']): ?>
                                                        <span class="badge bg-secondary ms-2">Nonaktif</span>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <small class="text-muted"><?= htmlspecialchars($part['kode_part']) ?></small>
                                                </div>
                                                
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" href="?edit=<?= $part['id'] ?>">
                                                            <i class="fas fa-edit me-2"></i>Edit
                                                        </a></li>
                                                        <li><a class="dropdown-item" href="#" onclick="adjustStock(<?= $part['id'] ?>, '<?= htmlspecialchars($part['nama_part']) ?>')">
                                                            <i class="fas fa-plus-minus me-2"></i>Sesuaikan Stok
                                                        </a></li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><a class="dropdown-item text-danger" href="#" onclick="deletePart(<?= $part['id'] ?>)">
                                                            <i class="fas fa-trash me-2"></i>Hapus
                                                        </a></li>
                                                    </ul>
                                                </div>
                                            </div>
                                            
                                            <div class="row text-sm mb-3">
                                                <div class="col-6">
                                                    <small class="text-muted">Stok:</small><br>
                                                    <strong><?= $part['stok_gudang'] ?> <?= $part['satuan'] ?></strong>
                                                    <span class="badge <?= $stockBadgeClass ?> stock-badge ms-1"><?= $stockStatus ?></span>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Min. Stok:</small><br>
                                                    <span><?= $part['minimum_stok'] ?> <?= $part['satuan'] ?></span>
                                                </div>
                                            </div>
                                            
                                            <div class="row text-sm">
                                                <div class="col-6">
                                                    <small class="text-muted">Harga Beli:</small><br>
                                                    <span><?= $part['harga_beli'] ? formatRupiah($part['harga_beli']) : '-' ?></span>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Harga Jual:</small><br>
                                                    <strong class="text-success"><?= formatRupiah($part['harga_jual']) ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Recent Stock Movements -->
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-history me-2"></i>
                                        Pergerakan Stok Terbaru
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recentMovements)): ?>
                                    <div class="text-center text-muted">
                                        <i class="fas fa-inbox mb-2"></i>
                                        <p class="mb-0">Belum ada pergerakan stok</p>
                                    </div>
                                    <?php else: ?>
                                    <?php foreach ($recentMovements as $movement): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                        <div class="flex-grow-1">
                                            <div class="fw-bold small"><?= htmlspecialchars($movement['nama_part']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($movement['keterangan']) ?></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-<?= $movement['movement_type'] === 'in' ? 'success' : 'danger' ?>">
                                                <?= $movement['movement_type'] === 'in' ? '+' : '-' ?><?= $movement['qty'] ?> <?= $movement['satuan'] ?>
                                            </span>
                                            <br>
                                            <small class="text-muted"><?= date('d/m H:i', strtotime($movement['created_at'])) ?></small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Part Modal -->
    <div class="modal fade" id="partModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?= $editPart ? 'Edit Part' : 'Tambah Part Baru' ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="<?= $editPart ? 'edit_part' : 'add_part' ?>">
                        <?php if ($editPart): ?>
                        <input type="hidden" name="part_id" value="<?= $editPart['id'] ?>">
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Nama Part <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nama_part" 
                                       value="<?= $editPart ? htmlspecialchars($editPart['nama_part']) : '' ?>" 
                                       placeholder="Freon R134a, Filter Cabin, dll" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Kode Part</label>
                                <input type="text" class="form-control" name="kode_part" 
                                       value="<?= $editPart ? htmlspecialchars($editPart['kode_part']) : '' ?>" 
                                       placeholder="FRN134A">
                            </div>
                        </div>

                        <div class="row">
                            <?php if (!$editPart): ?>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Stok Awal</label>
                                <input type="number" class="form-control" name="stok_gudang" 
                                       value="0" min="0">
                            </div>
                            <?php endif; ?>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Satuan <span class="text-danger">*</span></label>
                                <select class="form-select" name="satuan" required>
                                    <option value="">Pilih Satuan</option>
                                    <option value="pcs" <?= $editPart && $editPart['satuan'] === 'pcs' ? 'selected' : '' ?>>Pcs</option>
                                    <option value="kaleng" <?= $editPart && $editPart['satuan'] === 'kaleng' ? 'selected' : '' ?>>Kaleng</option>
                                    <option value="botol" <?= $editPart && $editPart['satuan'] === 'botol' ? 'selected' : '' ?>>Botol</option>
                                    <option value="meter" <?= $editPart && $editPart['satuan'] === 'meter' ? 'selected' : '' ?>>Meter</option>
                                    <option value="liter" <?= $editPart && $editPart['satuan'] === 'liter' ? 'selected' : '' ?>>Liter</option>
                                    <option value="kg" <?= $editPart && $editPart['satuan'] === 'kg' ? 'selected' : '' ?>>Kg</option>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Minimum Stok <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="minimum_stok" 
                                       value="<?= $editPart ? $editPart['minimum_stok'] : '5' ?>" min="0" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Harga Beli</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" name="harga_beli" 
                                           value="<?= $editPart ? $editPart['harga_beli'] : '' ?>" min="0">
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Harga Jual <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" name="harga_jual" 
                                           value="<?= $editPart ? $editPart['harga_jual'] : '' ?>" min="0" required>
                                </div>
                            </div>
                        </div>

                        <?php if ($editPart): ?>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" 
                                       <?= $editPart['is_active'] ? 'checked' : '' ?>>
                                <label class="form-check-label">Part Aktif</label>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            <?= $editPart ? 'Update' : 'Simpan' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Stock Adjustment Modal -->
    <div class="modal fade" id="stockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Sesuaikan Stok</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="adjust_stock">
                        <input type="hidden" name="part_id" id="adjust_part_id">

                        <div class="mb-3">
                            <label class="form-label">Part</label>
                            <input type="text" class="form-control" id="adjust_part_name" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Penyesuaian Stok</label>
                            <input type="number" class="form-control" name="adjustment" 
                                   placeholder="Masukkan angka positif (tambah) atau negatif (kurang)" required>
                            <div class="form-text">Contoh: +10 untuk menambah, -5 untuk mengurangi</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Keterangan <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="keterangan" rows="2" 
                                      placeholder="Alasan penyesuaian stok" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-plus-minus me-2"></i>Sesuaikan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto show modal if editing
        <?php if ($editPart): ?>
        var partModal = new bootstrap.Modal(document.getElementById('partModal'));
        partModal.show();
        <?php endif; ?>

        function filterByStatus(status) {
            const url = new URL(window.location);
            if (status === 'all') {
                url.searchParams.delete('status');
            } else {
                url.searchParams.set('status', status);
            }
            window.location.href = url.toString();
        }

        function filterLowStock(checked) {
            const url = new URL(window.location);
            if (checked) {
                url.searchParams.set('low_stock', '1');
            } else {
                url.searchParams.delete('low_stock');
            }
            window.location.href = url.toString();
        }

        function adjustStock(partId, partName) {
            document.getElementById('adjust_part_id').value = partId;
            document.getElementById('adjust_part_name').value = partName;
            var stockModal = new bootstrap.Modal(document.getElementById('stockModal'));
            stockModal.show();
        }

        function deletePart(id) {
            if (confirm('Yakin ingin menghapus part ini?')) {
                window.location.href = 'delete_part.php?id=' + id;
            }
        }
    </script>
</body>
</html>