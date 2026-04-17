<?php
// process_order.php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/payment.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$payment = new Payment($db);

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$restaurant_id = $data['restaurant_id'] ?? 0;
$customer_name = $data['customer_name'] ?? '';
$customer_phone = $data['customer_phone'] ?? '';
$order_type = $data['order_type'] ?? 'dine-in';
$table_number = $data['table_number'] ?? null;
$payment_method = $data['payment_method'] ?? 'visa';
$items = $data['items'] ?? [];
$momo_data = $data['momo_data'] ?? null;

// Validate data
if (!$restaurant_id || !$customer_name || !$customer_phone || empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    $db->beginTransaction();
    
    // Calculate total
    $total_amount = 0;
    foreach ($items as $item) {
        $total_amount += $item['price'] * $item['quantity'];
    }
    
    // Generate order number
    $order_number = 'ORD-' . time() . '-' . rand(1000, 9999);
    
    // Insert order
    $query = "INSERT INTO orders (restaurant_id, order_number, customer_name, customer_phone, 
              order_type, table_number, total_amount, payment_method, payment_status, order_status)
              VALUES (:restaurant_id, :order_number, :customer_name, :customer_phone, 
              :order_type, :table_number, :total_amount, :payment_method, 'pending', 'pending')";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':restaurant_id', $restaurant_id);
    $stmt->bindParam(':order_number', $order_number);
    $stmt->bindParam(':customer_name', $customer_name);
    $stmt->bindParam(':customer_phone', $customer_phone);
    $stmt->bindParam(':order_type', $order_type);
    $stmt->bindParam(':table_number', $table_number);
    $stmt->bindParam(':total_amount', $total_amount);
    $stmt->bindParam(':payment_method', $payment_method);
    $stmt->execute();
    
    $order_id = $db->lastInsertId();
    
    // Insert order items
    foreach ($items as $item) {
        $subtotal = $item['price'] * $item['quantity'];
        
        $query = "INSERT INTO order_items (order_id, menu_item_id, item_name, item_price, quantity, subtotal)
                  VALUES (:order_id, :menu_item_id, :item_name, :item_price, :quantity, :subtotal)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->bindParam(':menu_item_id', $item['id']);
        $stmt->bindParam(':item_name', $item['name']);
        $stmt->bindParam(':item_price', $item['price']);
        $stmt->bindParam(':quantity', $item['quantity']);
        $stmt->bindParam(':subtotal', $subtotal);
        $stmt->execute();
    }
    
    // Process payment
    if ($payment_method === 'visa') {
        // Initialize Paystack payment
        $callback_url = SITE_URL . '/payment_callback.php?order_id=' . $order_id;
        $result = $payment->initializePaystackPayment($order_id, $total_amount, $customer_phone . '@customer.com', $callback_url);
        
        if ($result && isset($result['status']) && $result['status'] === true) {
            // Store payment reference
            $payment_reference = $result['data']['reference'];
            
            $query = "INSERT INTO payments (order_id, amount, payment_method, transaction_id, status)
                      VALUES (:order_id, :amount, 'visa', :transaction_id, 'pending')";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':order_id', $order_id);
            $stmt->bindParam(':amount', $total_amount);
            $stmt->bindParam(':transaction_id', $payment_reference);
            $stmt->execute();
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'order_id' => $order_id,
                'payment_url' => $result['data']['authorization_url'],
                'message' => 'Order created successfully'
            ]);
        } else {
            throw new Exception('Payment initialization failed');
        }
    } else if ($payment_method === 'mobile_money') {
        // Process mobile money payment
        $momo_number = $momo_data['number'] ?? '';
        $momo_network = $momo_data['network'] ?? '';
        
        if (!$momo_number) {
            throw new Exception('Mobile money number is required');
        }
        
        $result = $payment->initializeMoMoPayment($order_id, $total_amount, $momo_number, $momo_network);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'order_id' => $order_id,
            'payment_reference' => $result['reference'],
            'message' => $result['message'] ?? 'Please approve the payment prompt on your phone'
        ]);
    } else {
        throw new Exception('Invalid payment method');
    }
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}