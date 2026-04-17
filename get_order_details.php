<?php
// restaurant/get_order_details.php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isRestaurantLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$restaurant_id = $auth->getRestaurantId();
$order_id = $_GET['id'] ?? 0;

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit();
}

// Get order details
$query = "SELECT * FROM orders WHERE id = :id AND restaurant_id = :restaurant_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $order_id);
$stmt->bindParam(':restaurant_id', $restaurant_id);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit();
}

$order = $stmt->fetch();

// Get order items
$query = "SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id";
$stmt = $db->prepare($query);
$stmt->bindParam(':order_id', $order_id);
$stmt->execute();
$items = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'order' => $order,
    'items' => $items
]);
