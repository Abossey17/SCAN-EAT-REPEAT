<?php
// restaurant/generate_qr.php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/qr_generator.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isRestaurantLoggedIn()) {
    header('Location: login.php');
    exit();
}

$restaurant_id = $auth->getRestaurantId();

// Get restaurant info
$query = "SELECT * FROM restaurants WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $restaurant_id);
$stmt->execute();
$restaurant = $stmt->fetch();

try {
    // Generate QR code
    $qr_filename = QRGenerator::regenerateQRCode($restaurant_id, $restaurant['name']);
    
    // Update restaurant with new QR code
    $query = "UPDATE restaurants SET qr_code = :qr_code WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':qr_code', $qr_filename);
    $stmt->bindParam(':id', $restaurant_id);
    $stmt->execute();
    
    header('Location: qr_code.php?success=1');
} catch (Exception $e) {
    header('Location: qr_code.php?error=' . urlencode($e->getMessage()));
}
exit();
