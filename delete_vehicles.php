<?php
require_once 'config.php';

if (isset($_GET['id'])) {
    $vehicle_id = $_GET['id'];
    $pdo = getDBConnection();
    
    try {
        // Check if vehicle has invoices
        $stmt = $pdo->prepare("SELECT COUNT(*) as invoice_count FROM invoices WHERE vehicle_id = ?");
        $stmt->execute([$vehicle_id]);
        $invoiceCount = $stmt->fetch()['invoice_count'];
        
        if ($invoiceCount > 0) {
            // Cannot delete - has invoices
            header("Location: vehicles.php?error=Tidak dapat menghapus kendaraan yang sudah memiliki invoice");
            exit;
        }
        
        // Safe to delete
        $stmt = $pdo->prepare("DELETE FROM vehicles WHERE id = ?");
        $stmt->execute([$vehicle_id]);
        
        header("Location: vehicles.php?success=Kendaraan berhasil dihapus");
        exit;
        
    } catch (Exception $e) {
        header("Location: vehicles.php?error=Gagal menghapus kendaraan: " . $e->getMessage());
        exit;
    }
} else {
    header("Location: vehicles.php");
    exit;
}
?>