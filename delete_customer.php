<?php
require_once 'config.php';

if (isset($_GET['id'])) {
    $customer_id = $_GET['id'];
    $pdo = getDBConnection();
    
    try {
        // Check if customer has vehicles or invoices
        $stmt = $pdo->prepare("SELECT COUNT(*) as vehicle_count FROM vehicles WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        $vehicleCount = $stmt->fetch()['vehicle_count'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as invoice_count FROM invoices WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        $invoiceCount = $stmt->fetch()['invoice_count'];
        
        if ($vehicleCount > 0 || $invoiceCount > 0) {
            // Cannot delete - has related data
            header("Location: customers.php?error=Tidak dapat menghapus pelanggan yang sudah memiliki kendaraan atau invoice");
            exit;
        }
        
        // Safe to delete
        $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->execute([$customer_id]);
        
        header("Location: customers.php?success=Pelanggan berhasil dihapus");
        exit;
        
    } catch (Exception $e) {
        header("Location: customers.php?error=Gagal menghapus pelanggan: " . $e->getMessage());
        exit;
    }
} else {
    header("Location: customers.php");
    exit;
}
?>