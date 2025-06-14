<?php
require_once 'config.php';

if (isset($_GET['id'])) {
    $part_id = $_GET['id'];
    $pdo = getDBConnection();
    
    try {
        // Check if part is used in invoices
        $stmt = $pdo->prepare("SELECT COUNT(*) as usage_count FROM invoice_parts WHERE part_id = ?");
        $stmt->execute([$part_id]);
        $usageCount = $stmt->fetch()['usage_count'];
        
        if ($usageCount > 0) {
            // Cannot delete - part is used in invoices
            header("Location: parts.php?error=Tidak dapat menghapus part yang sudah digunakan dalam invoice");
            exit;
        }
        
        // Delete stock movements first
        $stmt = $pdo->prepare("DELETE FROM stock_movements WHERE part_id = ?");
        $stmt->execute([$part_id]);
        
        // Delete the part
        $stmt = $pdo->prepare("DELETE FROM parts WHERE id = ?");
        $stmt->execute([$part_id]);
        
        header("Location: parts.php?success=Part berhasil dihapus");
        exit;
        
    } catch (Exception $e) {
        header("Location: parts.php?error=Gagal menghapus part: " . $e->getMessage());
        exit;
    }
} else {
    header("Location: parts.php");
    exit;
}
?>