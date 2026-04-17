<?php
// process_order_with_cash.php - Order processing with cash payment and waiting numbers
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/payment_split.php';
require_once 'includes/waiting_number.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$payment = new PaymentSplit($db);
$waitingNumber = new WaitingNumber($db);

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$restaurant_id = $data['restaurant_id'] ?? 0;
$customer_name = $data['customer_name'] ?? '';
$customer_phone = $data['customer_phone'] ?? '';
$customer_email = $data['customer_email'] ?? '';
$order_type = $data['order_type'] ?? 'dine-in';
$table_number = $data['table_number'] ?? null;
$payment_method = $data['payment_method'] ?? 'cash';
$items = $data['items'] ?? [];
$momo_data = $data['momo_data'] ?? null;

// Validate data
if (!$restaurant_id || !$customer_name || !$customer_phone || empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate payment method
if (!in_array($payment_method, ['visa', 'mobile_money', 'cash'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment method']);
    exit;
}

// Check restaurant
$query = "SELECT * FROM restaurants WHERE id = :id AND status = 'active'";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $restaurant_id);
$stmt->execute();
$restaurant = $stmt->fetch();

if (!$restaurant) {
    echo json_encode(['success' => false, 'message' => 'Restaurant not found or inactive']);
    exit;
}

// Check if restaurant has payment setup for visa
if ($payment_method === 'visa' && !$restaurant['paystack_subaccount_code']) {
    echo json_encode(['success' => false, 'message' => 'Restaurant payment account not configured']);
    exit;
}

try {
    $db->beginTransaction();
    
    // Calculate total
    $total_amount = 0;
    foreach ($items as $item) {
        $total_amount += $item['price'] * $item['quantity'];
    }
    
    // Calculate commissions (cash has 0% commission)
    $commissions = $payment->calculateCommissions($total_amount, $payment_method);
    
    // Generate order number
    $order_number = 'ORD-' . time() . '-' . rand(1000, 9999);
    
    // Generate waiting number
    $waiting_number = $waitingNumber->generateWaitingNumber($restaurant_id);
    
    // Determine initial payment status
    if ($payment_method === 'cash') {
        $payment_status = 'pending'; // Will be marked as completed when restaurant receives cash
        $order_status = 'confirmed'; // Cash orders are auto-confirmed
    } else {
        $payment_status = 'pending';
        $order_status = 'pending';
    }
    
    // Insert order with commission details and waiting number
    $query = "INSERT INTO orders 
              (restaurant_id, order_number, waiting_number, customer_name, customer_phone, 
               customer_email, order_type, table_number, 
               total_amount, platform_commission, developer_commission, restaurant_amount,
               payment_method, payment_status, order_status)
              VALUES 
              (:restaurant_id, :order_number, :waiting_number, :customer_name, :customer_phone, 
               :customer_email, :order_type, :table_number,
               :total_amount, :platform_commission, :developer_commission, :restaurant_amount,
               :payment_method, :payment_status, :order_status)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':restaurant_id', $restaurant_id);
    $stmt->bindParam(':order_number', $order_number);
    $stmt->bindParam(':waiting_number', $waiting_number);
    $stmt->bindParam(':customer_name', $customer_name);
    $stmt->bindParam(':customer_phone', $customer_phone);
    $stmt->bindParam(':customer_email', $customer_email);
    $stmt->bindParam(':order_type', $order_type);
    $stmt->bindParam(':table_number', $table_number);
    $stmt->bindParam(':total_amount', $total_amount);
    $stmt->bindParam(':platform_commission', $commissions['platform_commission']);
    $stmt->bindParam(':developer_commission', $commissions['developer_commission']);
    $stmt->bindParam(':restaurant_amount', $commissions['restaurant_amount']);
    $stmt->bindParam(':payment_method', $payment_method);
    $stmt->bindParam(':payment_status', $payment_status);
    $stmt->bindParam(':order_status', $order_status);
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
    
    // Process payment based on method
    if ($payment_method === 'visa') {
        // Initialize Paystack payment with split
        $callback_url = SITE_URL . '/payment_callback.php?order_id=' . $order_id;
        
        if (!$customer_email) {
            $customer_email = $customer_phone . '@customer.com';
        }
        
        $result = $payment->initializePaystackPayment($order_id, $total_amount, $customer_email, $callback_url, $restaurant_id);
        
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
                'order_number' => $order_number,
                'waiting_number' => $waiting_number,
                'payment_method' => 'visa',
                'payment_url' => $result['data']['authorization_url'],
                'message' => 'Order created successfully. Please complete payment.',
                'commissions' => $commissions
            ]);
        } else {
            throw new Exception('Payment initialization failed: ' . ($result['message'] ?? 'Unknown error'));
        }
        
    } else if ($payment_method === 'mobile_money') {
        // Process mobile money payment
        $momo_number = $momo_data['number'] ?? '';
        $momo_network = $momo_data['network'] ?? '';
        
        if (!$momo_number) {
            throw new Exception('Mobile money number is required');
        }
        
        $result = $payment->initializeMoMoPayment($order_id, $total_amount, $momo_number, $momo_network, $restaurant_id);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'order_id' => $order_id,
            'order_number' => $order_number,
            'waiting_number' => $waiting_number,
            'payment_method' => 'mobile_money',
            'payment_reference' => $result['reference'],
            'message' => $result['message'] ?? 'Please approve the payment prompt on your phone',
            'commissions' => $commissions
        ]);
        
    } else if ($payment_method === 'cash') {
        // Cash payment - no online payment processing needed
        $query = "INSERT INTO payments (order_id, amount, payment_method, status)
                  VALUES (:order_id, :amount, 'cash', 'pending')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->bindParam(':amount', $total_amount);
        $stmt->execute();
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'order_id' => $order_id,
            'order_number' => $order_number,
            'waiting_number' => $waiting_number,
            'payment_method' => 'cash',
            'message' => 'Order placed successfully! Pay with cash when you receive your order.',
            'redirect_url' => SITE_URL . '/receipt.php?order_id=' . $order_id,
            'commissions' => $commissions
        ]);
    }
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
