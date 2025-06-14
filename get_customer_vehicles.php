<?php
require_once 'config.php';

header('Content-Type: application/json');

if (isset($_GET['customer_id'])) {
    $customer_id = $_GET['customer_id'];
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE customer_id = ? ORDER BY no_polisi");
    $stmt->execute([$customer_id]);
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($vehicles);
} else {
    echo json_encode([]);
}
?>