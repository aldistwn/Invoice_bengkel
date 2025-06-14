<?php
require_once 'config.php';

if (isset($_GET['id'])) {
    $service_id = $_GET['id'];
    $pdo = getDBConnection();
    
    try {
        // Check if service is used in invoices
        $stmt = $pdo->prepare("SELECT COUNT(*) as usage_count FROM invoice_services WHERE service_id = ?");
        $stmt->execute([$service_id]);
        $usageCount = $stmt->fetch()['usage_count'];
        
        if ($usageCount > 0) {
            // Cannot delete - service is used in invoices
            header("Location: services.php?error=Tidak dapat menghapus layanan yang sudah digunakan dalam invoice");
            exit;
        }
        
        // Safe to delete
        $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
        $stmt->execute([$service_id]);
        
        header("Location: services.php?success=Layanan berhasil dihapus");
        exit;
        
    } catch (Exception $e) {
        header("Location: services.php?error=Gagal menghapus layanan: " . $e->getMessage());
        exit;
    }
} else {
    header("Location: services.php");
    exit;
}
?>