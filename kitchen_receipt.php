<?php
// restaurant/kitchen_receipt.php - Kitchen receipt for restaurant staff
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isRestaurantLoggedIn()) {
    header('Location: login.php');
    exit();
}

$restaurant_id = $auth->getRestaurantId();
$order_id = $_GET['order_id'] ?? 0;

if (!$order_id) {
    die('Invalid order ID');
}

// Get order details
$query = "SELECT o.*, r.name as restaurant_name, r.address as restaurant_address
          FROM orders o
          JOIN restaurants r ON o.restaurant_id = r.id
          WHERE o.id = :order_id AND o.restaurant_id = :restaurant_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':order_id', $order_id);
$stmt->bindParam(':restaurant_id', $restaurant_id);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    die('Order not found');
}

$order = $stmt->fetch();

// Get order items
$query = "SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id";
$stmt = $db->prepare($query);
$stmt->bindParam(':order_id', $order_id);
$stmt->execute();
$items = $stmt->fetchAll();

// Track kitchen receipt generation
$receipt_number = 'RCP-' . $order['order_number'] . '-KTCH';
$query = "INSERT INTO receipts (order_id, restaurant_id, receipt_number, receipt_type, printed_by, ip_address)
          VALUES (:order_id, :restaurant_id, :receipt_number, 'kitchen', :user_id, :ip)
          ON DUPLICATE KEY UPDATE printed_at = NOW()";
$stmt = $db->prepare($query);
$stmt->execute([
    'order_id' => $order_id,
    'restaurant_id' => $restaurant_id,
    'receipt_number' => $receipt_number,
    'user_id' => $restaurant_id,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
]);

// Mark order as printed
$query = "UPDATE orders SET is_printed = TRUE WHERE id = :order_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':order_id', $order_id);
$stmt->execute();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kitchen Order - <?php echo htmlspecialchars($order['waiting_number']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            background: #f5f5f5;
            padding: 10px;
        }
        .kitchen-receipt {
            max-width: 300px;
            margin: 0 auto;
            background: white;
            padding: 15px;
            border: 3px solid #333;
        }
        .waiting-number-header {
            background: #000;
            color: white;
            padding: 20px;
            text-align: center;
            margin: -15px -15px 15px -15px;
        }
        .waiting-label {
            font-size: 12px;
            letter-spacing: 3px;
            margin-bottom: 5px;
        }
        .waiting-number {
            font-size: 60px;
            font-weight: bold;
            letter-spacing: 8px;
        }
        .order-type {
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            margin-bottom: 15px;
            border: 2px solid #333;
        }
        .order-type.dine-in {
            background: #d4edda;
            border-color: #28a745;
        }
        .order-type.takeaway {
            background: #fff3cd;
            border-color: #ffc107;
        }
        .time-info {
            text-align: center;
            font-size: 14px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px dashed #333;
        }
        .time-label {
            font-size: 11px;
            color: #666;
        }
        .time-value {
            font-size: 16px;
            font-weight: bold;
            margin-top: 3px;
        }
        .customer-info {
            background: #f8f9fa;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
        }
        .customer-info div {
            font-size: 13px;
            margin-bottom: 5px;
        }
        .customer-info strong {
            display: inline-block;
            width: 60px;
        }
        .items-section {
            margin-bottom: 15px;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid #333;
        }
        .item {
            margin-bottom: 12px;
            padding: 8px;
            background: #f8f9fa;
            border-left: 4px solid #333;
        }
        .item-qty {
            font-size: 24px;
            font-weight: bold;
            display: inline-block;
            width: 40px;
        }
        .item-name {
            font-size: 16px;
            font-weight: bold;
        }
        .notes {
            background: #fff3cd;
            padding: 10px;
            margin-top: 15px;
            border: 2px dashed #ffc107;
        }
        .footer {
            text-align: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px dashed #333;
            font-size: 11px;
        }
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn {
            flex: 1;
            padding: 12px;
            border: none;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            text-align: center;
        }
        .btn-print {
            background: #28a745;
            color: white;
        }
        .btn-close {
            background: #6c757d;
            color: white;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .kitchen-receipt {
                border: none;
                max-width: 100%;
            }
            .actions {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="kitchen-receipt">
        <div class="waiting-number-header">
            <div class="waiting-label">ORDER #</div>
            <div class="waiting-number"><?php echo htmlspecialchars($order['waiting_number']); ?></div>
        </div>
        
        <div class="order-type <?php echo $order['order_type']; ?>">
            <?php echo strtoupper($order['order_type']); ?>
            <?php if ($order['table_number']): ?>
            - TABLE <?php echo htmlspecialchars($order['table_number']); ?>
            <?php endif; ?>
        </div>
        
        <div class="time-info">
            <div class="time-label">ORDER TIME</div>
            <div class="time-value"><?php echo date('h:i A', strtotime($order['created_at'])); ?></div>
        </div>
        
        <div class="customer-info">
            <div><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></div>
            <div><strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?></div>
        </div>
        
        <div class="items-section">
            <div class="section-title">Items to Prepare</div>
            <?php foreach ($items as $item): ?>
            <div class="item">
                <span class="item-qty"><?php echo $item['quantity']; ?>x</span>
                <span class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($order['special_instructions'] ?? ''): ?>
        <div class="notes">
            <strong>Special Instructions:</strong><br>
            <?php echo nl2br(htmlspecialchars($order['special_instructions'])); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($order['payment_method'] === 'cash'): ?>
        <div class="notes">
            <strong>⚠️ CASH PAYMENT</strong><br>
            Collect <?php echo CURRENCY_SYMBOL . number_format($order['total_amount'], 2); ?> in cash
        </div>
        <?php endif; ?>
        
        <div class="footer">
            <div>Order: <?php echo htmlspecialchars($order['order_number']); ?></div>
            <div>Printed: <?php echo date('M d, Y - h:i A'); ?></div>
        </div>
    </div>
    
    <div class="actions">
        <button onclick="window.print()" class="btn btn-print">🖨️ Print</button>
        <button onclick="window.close()" class="btn btn-close">✖ Close</button>
    </div>
    
    <script>
        // Auto-print when page loads
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
