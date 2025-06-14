<?php
require_once 'config.php';

// Require minimum operator role for creating invoices
requireMinimumRole('operator');

$pdo = getDBConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'save_invoice') {
    try {
        $pdo->beginTransaction();
        
        // Generate invoice number
        $invoice_number = generateInvoiceNumber();
        
        // Basic invoice data
        $customer_id = $_POST['customer_id'];
        $vehicle_id = $_POST['vehicle_id'];
        $invoice_date = $_POST['invoice_date'];
        $catatan = $_POST['catatan'];
        $discount_percent = floatval($_POST['discount_percent'] ?? 0);
        $status = $_POST['save_type'] === 'draft' ? 'draft' : 'posted';
        
        // Calculate totals
        $subtotal = 0;
        
        // Calculate services total
        if (!empty($_POST['services'])) {
            foreach ($_POST['services'] as $service) {
                if (!empty($service['service_id']) && $service['qty'] > 0) {
                    $subtotal += floatval($service['qty']) * floatval($service['harga']);
                }
            }
        }
        
        // Calculate parts total
        if (!empty($_POST['parts'])) {
            foreach ($_POST['parts'] as $part) {
                if (!empty($part['part_id']) && $part['qty'] > 0) {
                    $subtotal += floatval($part['qty']) * floatval($part['harga']);
                }
            }
        }
        
        $discount_amount = ($subtotal * $discount_percent) / 100;
        $total = $subtotal - $discount_amount;
        
        // Insert invoice
        $stmt = $pdo->prepare("
            INSERT INTO invoices (invoice_number, customer_id, vehicle_id, invoice_date, status, subtotal, discount_percent, discount_amount, total, catatan) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$invoice_number, $customer_id, $vehicle_id, $invoice_date, $status, $subtotal, $discount_percent, $discount_amount, $total, $catatan]);
        
        $invoice_id = $pdo->lastInsertId();
        
        // Insert services
        if (!empty($_POST['services'])) {
            foreach ($_POST['services'] as $service) {
                if (!empty($service['service_id']) && $service['qty'] > 0) {
                    $service_total = floatval($service['qty']) * floatval($service['harga']);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO invoice_services (invoice_id, service_id, nama_service, qty, harga, total) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$invoice_id, $service['service_id'], $service['nama_service'], $service['qty'], $service['harga'], $service_total]);
                }
            }
        }
        
        // Insert parts and update stock
        if (!empty($_POST['parts'])) {
            foreach ($_POST['parts'] as $part) {
                if (!empty($part['part_id']) && $part['qty'] > 0) {
                    $part_total = floatval($part['qty']) * floatval($part['harga']);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO invoice_parts (invoice_id, part_id, nama_part, qty, harga, total) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$invoice_id, $part['part_id'], $part['nama_part'], $part['qty'], $part['harga'], $part_total]);
                    
                    // Update stock only if invoice is posted
                    if ($status === 'posted') {
                        updateStock($part['part_id'], $part['qty'], 'out', $invoice_id, 'Digunakan dalam invoice ' . $invoice_number);
                    }
                }
            }
        }
        
        $pdo->commit();
        
        header("Location: invoice_detail.php?id=$invoice_id&success=Invoice berhasil " . ($status === 'draft' ? 'disimpan sebagai draft' : 'diposting'));
        exit;
        
    } catch (Exception $e) {
        $pdo->rollback();
        $error = "Error: " . $e->getMessage();
    }
}

// Get customers and their vehicles
$stmt = $pdo->query("SELECT * FROM customers ORDER BY nama_pelanggan");
$customers = $stmt->fetchAll();

// Get active services
$stmt = $pdo->query("SELECT * FROM services WHERE is_active = 1 ORDER BY nama_service");
$services = $stmt->fetchAll();

// Get active parts
$stmt = $pdo->query("SELECT * FROM parts WHERE is_active = 1 ORDER BY nama_part");
$parts = $stmt->fetchAll();

// Pre-select vehicle if provided
$selectedVehicleId = $_GET['vehicle_id'] ?? '';
$selectedCustomerId = '';

if ($selectedVehicleId) {
    $stmt = $pdo->prepare("SELECT customer_id FROM vehicles WHERE id = ?");
    $stmt->execute([$selectedVehicleId]);
    $vehicle = $stmt->fetch();
    if ($vehicle) {
        $selectedCustomerId = $vehicle['customer_id'];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alfina AC Mobil - Buat Invoice Baru</title>
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
        .invoice-form {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .item-row {
            background: #f8f9fa;
            border-radius: 5px;
            margin-bottom: 10px;
            padding: 15px;
        }
        .total-section {
            background: #e3f2fd;
            border-radius: 8px;
            padding: 20px;
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
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 mb-0">Buat Invoice Baru</h1>
                            <small class="text-muted">Buat invoice untuk layanan AC mobil</small>
                        </div>
                        <a href="invoices.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Kembali
                        </a>
                    </div>

                    <!-- Error Message -->
                    <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Invoice Form -->
                    <form method="POST" id="invoiceForm">
                        <input type="hidden" name="action" value="save_invoice">
                        
                        <div class="invoice-form p-4 mb-4">
                            <!-- Header Information -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h5 class="mb-3">Informasi Pelanggan</h5>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Pelanggan <span class="text-danger">*</span></label>
                                        <select class="form-select" name="customer_id" id="customerSelect" required onchange="loadCustomerVehicles()">
                                            <option value="">Pilih Pelanggan</option>
                                            <?php foreach ($customers as $customer): ?>
                                            <option value="<?= $customer['id'] ?>" <?= $selectedCustomerId == $customer['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($customer['nama_pelanggan']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Kendaraan <span class="text-danger">*</span></label>
                                        <select class="form-select" name="vehicle_id" id="vehicleSelect" required>
                                            <option value="">Pilih Kendaraan</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <h5 class="mb-3">Informasi Invoice</h5>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Tanggal Invoice <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="invoice_date" value="<?= date('Y-m-d') ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Catatan</label>
                                        <textarea class="form-control" name="catatan" rows="3" placeholder="Catatan khusus untuk invoice ini"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Services Section -->
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Layanan</h5>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="addServiceRow()">
                                        <i class="fas fa-plus me-1"></i>Tambah Layanan
                                    </button>
                                </div>
                                <div id="servicesContainer">
                                    <!-- Service rows will be added here -->
                                </div>
                            </div>

                            <!-- Parts Section -->
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Parts & Komponen</h5>
                                    <button type="button" class="btn btn-sm btn-success" onclick="addPartRow()">
                                        <i class="fas fa-plus me-1"></i>Tambah Part
                                    </button>
                                </div>
                                <div id="partsContainer">
                                    <!-- Part rows will be added here -->
                                </div>
                            </div>

                            <!-- Total Section -->
                            <div class="total-section">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label class="form-label">Diskon (%)</label>
                                            <input type="number" class="form-control" name="discount_percent" id="discountPercent" 
                                                   value="0" min="0" max="100" step="0.1" onchange="calculateTotal()">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-end">
                                            <div class="mb-2">
                                                <span class="text-muted">Subtotal:</span>
                                                <strong id="subtotalDisplay">Rp 0</strong>
                                            </div>
                                            <div class="mb-2">
                                                <span class="text-muted">Diskon:</span>
                                                <strong id="discountDisplay">Rp 0</strong>
                                            </div>
                                            <hr>
                                            <div class="h4 text-primary">
                                                <span class="text-muted">Total:</span>
                                                <strong id="totalDisplay">Rp 0</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <button type="submit" name="save_type" value="draft" class="btn btn-outline-primary">
                                    <i class="fas fa-save me-2"></i>Simpan Draft
                                </button>
                                <button type="submit" name="save_type" value="posted" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>Post Invoice
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Data from PHP
        const customers = <?= json_encode($customers) ?>;
        const services = <?= json_encode($services) ?>;
        const parts = <?= json_encode($parts) ?>;
        
        let serviceRowCount = 0;
        let partRowCount = 0;

        // Load customer vehicles
        function loadCustomerVehicles() {
            const customerId = document.getElementById('customerSelect').value;
            const vehicleSelect = document.getElementById('vehicleSelect');
            
            vehicleSelect.innerHTML = '<option value="">Pilih Kendaraan</option>';
            
            if (customerId) {
                fetch(`get_customer_vehicles.php?customer_id=${customerId}`)
                    .then(response => response.json())
                    .then(vehicles => {
                        vehicles.forEach(vehicle => {
                            const option = document.createElement('option');
                            option.value = vehicle.id;
                            option.textContent = `${vehicle.no_polisi} - ${vehicle.merek_kendaraan} ${vehicle.tipe_kendaraan}`;
                            if (vehicle.id == '<?= $selectedVehicleId ?>') {
                                option.selected = true;
                            }
                            vehicleSelect.appendChild(option);
                        });
                    });
            }
        }

        // Add service row
        function addServiceRow() {
            serviceRowCount++;
            const container = document.getElementById('servicesContainer');
            const row = document.createElement('div');
            row.className = 'item-row';
            row.id = `serviceRow${serviceRowCount}`;
            
            row.innerHTML = `
                <div class="row align-items-center">
                    <div class="col-md-5">
                        <select class="form-select" name="services[${serviceRowCount}][service_id]" onchange="setServicePrice(this, ${serviceRowCount})">
                            <option value="">Pilih Layanan</option>
                            ${services.map(service => `<option value="${service.id}" data-price="${service.harga_default}">${service.nama_service}</option>`).join('')}
                        </select>
                        <input type="hidden" name="services[${serviceRowCount}][nama_service]" id="serviceName${serviceRowCount}">
                    </div>
                    <div class="col-md-2">
                        <input type="number" class="form-control" name="services[${serviceRowCount}][qty]" 
                               placeholder="Qty" min="1" value="1" onchange="calculateTotal()">
                    </div>
                    <div class="col-md-3">
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control" name="services[${serviceRowCount}][harga]" 
                                   id="servicePrice${serviceRowCount}" placeholder="Harga" onchange="calculateTotal()">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="removeRow('serviceRow${serviceRowCount}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            
            container.appendChild(row);
        }

        // Add part row
        function addPartRow() {
            partRowCount++;
            const container = document.getElementById('partsContainer');
            const row = document.createElement('div');
            row.className = 'item-row';
            row.id = `partRow${partRowCount}`;
            
            row.innerHTML = `
                <div class="row align-items-center">
                    <div class="col-md-5">
                        <select class="form-select" name="parts[${partRowCount}][part_id]" onchange="setPartPrice(this, ${partRowCount})">
                            <option value="">Pilih Part</option>
                            ${parts.map(part => `<option value="${part.id}" data-price="${part.harga_jual}" data-stock="${part.stok_gudang}">${part.nama_part} (Stok: ${part.stok_gudang})</option>`).join('')}
                        </select>
                        <input type="hidden" name="parts[${partRowCount}][nama_part]" id="partName${partRowCount}">
                    </div>
                    <div class="col-md-2">
                        <input type="number" class="form-control" name="parts[${partRowCount}][qty]" 
                               placeholder="Qty" min="1" value="1" onchange="calculateTotal()">
                    </div>
                    <div class="col-md-3">
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control" name="parts[${partRowCount}][harga]" 
                                   id="partPrice${partRowCount}" placeholder="Harga" onchange="calculateTotal()">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="removeRow('partRow${partRowCount}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            
            container.appendChild(row);
        }

        // Set service price
        function setServicePrice(select, rowId) {
            const selectedOption = select.options[select.selectedIndex];
            const priceInput = document.getElementById(`servicePrice${rowId}`);
            const nameInput = document.getElementById(`serviceName${rowId}`);
            
            if (selectedOption.value) {
                priceInput.value = selectedOption.dataset.price;
                nameInput.value = selectedOption.text;
            } else {
                priceInput.value = '';
                nameInput.value = '';
            }
            calculateTotal();
        }

        // Set part price
        function setPartPrice(select, rowId) {
            const selectedOption = select.options[select.selectedIndex];
            const priceInput = document.getElementById(`partPrice${rowId}`);
            const nameInput = document.getElementById(`partName${rowId}`);
            
            if (selectedOption.value) {
                priceInput.value = selectedOption.dataset.price;
                nameInput.value = selectedOption.text.split(' (Stok:')[0];
            } else {
                priceInput.value = '';
                nameInput.value = '';
            }
            calculateTotal();
        }

        // Remove row
        function removeRow(rowId) {
            document.getElementById(rowId).remove();
            calculateTotal();
        }

        // Calculate total
        function calculateTotal() {
            let subtotal = 0;
            
            // Calculate services
            const serviceInputs = document.querySelectorAll('input[name*="[qty]"]');
            serviceInputs.forEach(qtyInput => {
                if (qtyInput.name.includes('services')) {
                    const rowNum = qtyInput.name.match(/\[(\d+)\]/)[1];
                    const priceInput = document.querySelector(`input[name="services[${rowNum}][harga]"]`);
                    if (qtyInput.value && priceInput.value) {
                        subtotal += parseFloat(qtyInput.value) * parseFloat(priceInput.value);
                    }
                }
            });
            
            // Calculate parts
            serviceInputs.forEach(qtyInput => {
                if (qtyInput.name.includes('parts')) {
                    const rowNum = qtyInput.name.match(/\[(\d+)\]/)[1];
                    const priceInput = document.querySelector(`input[name="parts[${rowNum}][harga]"]`);
                    if (qtyInput.value && priceInput.value) {
                        subtotal += parseFloat(qtyInput.value) * parseFloat(priceInput.value);
                    }
                }
            });
            
            const discountPercent = parseFloat(document.getElementById('discountPercent').value) || 0;
            const discountAmount = (subtotal * discountPercent) / 100;
            const total = subtotal - discountAmount;
            
            // Update display
            document.getElementById('subtotalDisplay').textContent = formatRupiah(subtotal);
            document.getElementById('discountDisplay').textContent = formatRupiah(discountAmount);
            document.getElementById('totalDisplay').textContent = formatRupiah(total);
        }

        // Format rupiah
        function formatRupiah(amount) {
            return 'Rp ' + amount.toLocaleString('id-ID');
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadCustomerVehicles();
            addServiceRow(); // Add one service row by default
        });
    </script>
</body>
</html>