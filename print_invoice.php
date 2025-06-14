<?php
require_once 'config.php';

// Get invoice ID
$invoice_id = $_GET['id'] ?? null;
if (!$invoice_id) {
    die("Invoice tidak ditemukan");
}

$pdo = getDBConnection();

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
    die("Invoice tidak ditemukan");
}

// Get invoice services
$stmt = $pdo->prepare("SELECT * FROM invoice_services WHERE invoice_id = ?");
$stmt->execute([$invoice_id]);
$invoice_services = $stmt->fetchAll();

// Get invoice parts
$stmt = $pdo->prepare("SELECT * FROM invoice_parts WHERE invoice_id = ?");
$stmt->execute([$invoice_id]);
$invoice_parts = $stmt->fetchAll();

// Function to convert number to words (Terbilang)
function terbilangInvoice($angka) {
    $angka = abs($angka);
    $huruf = array('', 'SATU', 'DUA', 'TIGA', 'EMPAT', 'LIMA', 'ENAM', 'TUJUH', 'DELAPAN', 'SEMBILAN', 'SEPULUH', 'SEBELAS');
    $temp = '';
    
    if ($angka < 12) {
        $temp = ' ' . $huruf[$angka];
    } else if ($angka < 20) {
        $temp = terbilangInvoice($angka - 10) . ' BELAS';
    } else if ($angka < 100) {
        $temp = terbilangInvoice($angka / 10) . ' PULUH' . terbilangInvoice($angka % 10);
    } else if ($angka < 200) {
        $temp = ' SERATUS' . terbilangInvoice($angka - 100);
    } else if ($angka < 1000) {
        $temp = terbilangInvoice($angka / 100) . ' RATUS' . terbilangInvoice($angka % 100);
    } else if ($angka < 2000) {
        $temp = ' SERIBU' . terbilangInvoice($angka - 1000);
    } else if ($angka < 1000000) {
        $temp = terbilangInvoice($angka / 1000) . ' RIBU' . terbilangInvoice($angka % 1000);
    } else if ($angka < 1000000000) {
        $temp = terbilangInvoice($angka / 1000000) . ' JUTA' . terbilangInvoice($angka % 1000000);
    } else if ($angka < 1000000000000) {
        $temp = terbilangInvoice($angka / 1000000000) . ' MILYAR' . terbilangInvoice(fmod($angka, 1000000000));
    }
    
    return $temp . ' RUPIAH';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Invoice - <?= htmlspecialchars($invoice['invoice_number']) ?></title>
    <style>
        /* Print settings for dot matrix 9.5" x 11" */
        @page {
            size: 9.5in 11in;
            margin: 0.3in 0.3in 0.3in 0.3in;
        }
        
        body {
            font-family: 'Courier New', monospace;
            font-size: 10px;
            line-height: 1.1;
            margin: 0;
            padding: 0;
            color: #000;
            width: 100%;
        }
        
        .invoice-container {
            width: 100%;
            max-width: 8.9in;
        }
        
        /* Header with logo space */
        .header-section {
            margin-bottom: 10px;
        }
        
        .logo-area {
            float: left;
            width: 60px;
            height: 60px;
            margin-right: 10px;
        }
        
        .company-info {
            margin-left: 70px;
        }
        
        .company-name {
            font-size: 16px;
            font-weight: bold;
            margin: 0;
            letter-spacing: 1px;
        }
        
        .company-subtitle {
            font-size: 9px;
            margin: 1px 0;
        }
        
        .clear { clear: both; }
        
        /* Customer and invoice info in two columns */
        .info-section {
            margin: 15px 0;
        }
        
        .info-left {
            float: left;
            width: 60%;
        }
        
        .info-right {
            float: right;
            width: 35%;
        }
        
        .info-line {
            margin: 1px 0;
            font-size: 9px;
        }
        
        /* Table with exact format like original */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            clear: both;
        }
        
        .items-table th,
        .items-table td {
            border: 1px solid #000;
            padding: 2px 3px;
            font-size: 9px;
            vertical-align: top;
        }
        
        .items-table th {
            text-align: center;
            font-weight: bold;
        }
        
        .col-no { width: 5%; text-align: center; }
        .col-service { width: 60%; }
        .col-qty { width: 8%; text-align: center; }
        .col-harga { width: 13%; text-align: right; }
        .col-total { width: 14%; text-align: right; }
        
        /* Bottom section */
        .terbilang-section {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 3px 0;
            margin: 10px 0;
            font-size: 9px;
        }
        
        .terbilang-left {
            float: left;
            width: 70%;
        }
        
        .terbilang-right {
            float: right;
            width: 25%;
            text-align: right;
        }
        
        .bank-section {
            margin: 8px 0;
            font-size: 9px;
        }
        
        .signature-area {
            margin-top: 15px;
        }
        
        .sig-left {
            float: left;
            width: 30%;
            text-align: center;
        }
        
        .sig-center {
            float: left;
            width: 40%;
            text-align: center;
        }
        
        .sig-right {
            float: right;
            width: 30%;
            text-align: center;
        }
        
        .signature-line {
            height: 40px;
            margin: 8px 0;
        }
        
        .garansi-box {
            border: 1px solid #000;
            padding: 3px 8px;
            display: inline-block;
            font-size: 8px;
        }
        
        /* Print specific */
        @media print {
            .no-print { display: none !important; }
            body { font-size: 9px; }
        }
        
        .print-button {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <!-- Print Button -->
    <div class="print-button no-print">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 14px;">
            🖨️ Cetak Invoice
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 14px; margin-left: 5px;">
            ❌ Tutup
        </button>
    </div>

    <div class="invoice-container">
        <!-- Header with Logo Area -->
        <div class="header-section">
            <div class="logo-area">
                <!-- Space for logo - can add <img> tag here if needed -->
                <div style="border: 1px solid #ccc; width: 50px; height: 50px; text-align: center; line-height: 50px; font-size: 8px;">LOGO</div>
            </div>
            <div class="company-info">
                <div class="company-name">ALFINA AC</div>
                <div class="company-subtitle">AUTO SPARE PART - AIR CONDITIONING</div>
                <div class="company-subtitle">Jl Pahlawan Revolusi No. 8 Pondok Bambu, Jakarta Timur</div>
                <div class="company-subtitle">Telp : 08128730954 / 085163220996 | WA : 085163220996</div>
            </div>
            <div class="clear"></div>
        </div>

        <!-- Customer and Invoice Info -->
        <div class="info-section">
            <div class="info-left">
                <div class="info-line">KEPADA&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <?= $invoice['nama_pelanggan'] ? htmlspecialchars($invoice['nama_pelanggan']) : '-' ?></div>
                <div class="info-line">KENDARAAN&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <?= strtoupper(htmlspecialchars($invoice['merek_kendaraan'] . ' ' . $invoice['tipe_kendaraan'])) ?></div>
                <div class="info-line">NO. POLISI&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <?= strtoupper(htmlspecialchars($invoice['no_polisi'])) ?></div>
            </div>
            <div class="info-right">
                <div class="info-line">NO. FAKTUR&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <?= htmlspecialchars($invoice['invoice_number']) ?></div>
                <div class="info-line">TGL. FAKTUR&nbsp;&nbsp;&nbsp;&nbsp;: <?= date('d-m-Y', strtotime($invoice['invoice_date'])) ?></div>
            </div>
            <div class="clear"></div>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th class="col-no">NO</th>
                    <th class="col-service">SERVICE</th>
                    <th class="col-qty">QTY</th>
                    <th class="col-harga">HARGA</th>
                    <th class="col-total">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                
                // Services first
                foreach ($invoice_services as $service): 
                ?>
                <tr>
                    <td class="col-no"><?= $no ?></td>
                    <td class="col-service"><?= htmlspecialchars($service['nama_service']) ?></td>
                    <td class="col-qty"><?= $service['qty'] ?></td>
                    <td class="col-harga"><?= number_format($service['harga'], 0, ',', '.') ?></td>
                    <td class="col-total"><?= number_format($service['total'], 0, ',', '.') ?></td>
                </tr>
                <?php 
                $no++;
                endforeach;
                
                // Then parts
                foreach ($invoice_parts as $part): 
                ?>
                <tr>
                    <td class="col-no"><?= $no ?></td>
                    <td class="col-service"><?= htmlspecialchars($part['nama_part']) ?></td>
                    <td class="col-qty"><?= $part['qty'] ?></td>
                    <td class="col-harga"><?= number_format($part['harga'], 0, ',', '.') ?></td>
                    <td class="col-total"><?= number_format($part['total'], 0, ',', '.') ?></td>
                </tr>
                <?php 
                $no++;
                endforeach;
                
                // Add empty rows if needed (minimum 5 rows total)
                while ($no <= 5): 
                ?>
                <tr>
                    <td class="col-no">&nbsp;</td>
                    <td class="col-service">&nbsp;</td>
                    <td class="col-qty">&nbsp;</td>
                    <td class="col-harga">&nbsp;</td>
                    <td class="col-total">&nbsp;</td>
                </tr>
                <?php 
                $no++;
                endwhile; 
                ?>
            </tbody>
        </table>

        <!-- Terbilang Section -->
        <div class="terbilang-section">
            <div class="terbilang-left">
                <strong>TERBILANG: <?= strtoupper(trim(terbilangInvoice($invoice['total']))) ?></strong>
            </div>
            <div class="terbilang-right">
                <strong>Subtotal <?= number_format($invoice['total'], 0, ',', '.') ?></strong>
            </div>
            <div class="clear"></div>
        </div>

        <!-- Bank Information -->
        <div class="bank-section">
            <strong>TRANSFER VIA</strong><br>
            <strong>BCA 5272014302 A/N Aldi Setiawan</strong>
        </div>

        <!-- Signature Section -->
        <div class="signature-area">
            <div class="sig-left">
                <div style="font-size: 9px;">Diterima Oleh,</div>
                <div class="signature-line"></div>
                <div style="font-size: 8px;">(.....................)</div>
            </div>
            
            <div class="sig-center">
                <div style="margin-top: 10px;">
                    <div class="garansi-box">Garansi:.....Bulan</div>
                </div>
            </div>
            
            <div class="sig-right">
                <div style="font-size: 9px;">Jakarta,<?= date('d M Y', strtotime($invoice['invoice_date'])) ?></div>
                <div style="font-size: 9px;">Hormat Kami,</div>
                <div class="signature-line"></div>
                <div style="font-size: 8px;">(.....................)</div>
            </div>
            <div class="clear"></div>
        </div>
    </div>

    <script>
        // Auto print when page loads (optional)
        // window.onload = function() {
        //     window.print();
        // };
        
        // Print function
        function printInvoice() {
            window.print();
        }
    </script>
</body>
</html>