<?php
// receipt.php - Customer receipt with waiting number
require_once 'config/config.php';
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$order_id = $_GET['order_id'] ?? 0;

if (!$order_id) {
    header('Location: index.php');
    exit;
}

// Get order details
$query = "SELECT o.*, r.name as restaurant_name, r.phone as restaurant_phone, 
          r.address as restaurant_address, r.logo
          FROM orders o
          JOIN restaurants r ON o.restaurant_id = r.id
          WHERE o.id = :order_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':order_id', $order_id);
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

// Track receipt generation
$receipt_number = 'RCP-' . $order['order_number'] . '-CUST';
$query = "INSERT INTO receipts (order_id, restaurant_id, receipt_number, receipt_type, ip_address)
          VALUES (:order_id, :restaurant_id, :receipt_number, 'customer', :ip)
          ON DUPLICATE KEY UPDATE printed_at = NOW()";
$stmt = $db->prepare($query);
$stmt->execute([
    'order_id' => $order_id,
    'restaurant_id' => $order['restaurant_id'],
    'receipt_number' => $receipt_number,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - Order <?php echo htmlspecialchars($order['order_number']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .receipt-container {
            max-width: 400px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            border-radius: 10px;
            overflow: hidden;
        }
        .receipt {
            padding: 30px;
        }
        .header {
            text-align: center;
            border-bottom: 2px dashed #333;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 15px;
            border-radius: 50%;
            object-fit: cover;
        }
        .restaurant-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .restaurant-info {
            font-size: 12px;
            color: #666;
            line-height: 1.6;
        }
        .waiting-number-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            text-align: center;
            margin: -30px -30px 20px -30px;
        }
        .waiting-label {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        .waiting-number {
            font-size: 72px;
            font-weight: bold;
            letter-spacing: 5px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
            margin: 10px 0;
        }
        .waiting-instruction {
            font-size: 13px;
            margin-top: 10px;
            opacity: 0.95;
        }
        .section {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #ddd;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 10px;
            color: #666;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 13px;
        }
        .info-label {
            color: #666;
        }
        .info-value {
            font-weight: bold;
        }
        .items-table {
            width: 100%;
            margin: 15px 0;
        }
        .items-table th {
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            color: #666;
            padding-bottom: 8px;
            border-bottom: 1px solid #ddd;
        }
        .items-table td {
            padding: 8px 0;
            font-size: 13px;
            border-bottom: 1px dotted #eee;
        }
        .item-qty {
            width: 30px;
            text-align: center;
        }
        .item-name {
            flex: 1;
        }
        .item-price {
            text-align: right;
            width: 80px;
        }
        .total-section {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid #333;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .total-row.grand-total {
            font-size: 20px;
            font-weight: bold;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }
        .payment-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }
        .badge.pending { background: #fff3cd; color: #856404; }
        .badge.completed { background: #d4edda; color: #155724; }
        .badge.cash { background: #d1ecf1; color: #0c5460; }
        .footer {
            text-align: center;
            padding-top: 20px;
            border-top: 2px dashed #333;
            color: #666;
            font-size: 12px;
        }
        .qr-code {
            margin: 15px 0;
        }
        .actions {
            display: flex;
            gap: 10px;
            margin: 20px -30px -30px -30px;
        }
        .btn {
            flex: 1;
            padding: 15px;
            border: none;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            display: block;
        }
        .btn-print {
            background: #667eea;
            color: white;
        }
        .btn-back {
            background: #6c757d;
            color: white;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .receipt-container {
                box-shadow: none;
                max-width: 100%;
            }
            .actions {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="waiting-number-section">
            <div class="waiting-label">Your Waiting Number</div>
            <div class="waiting-number"><?php echo htmlspecialchars($order['waiting_number']); ?></div>
            <div class="waiting-instruction">
                <?php if ($order['order_type'] === 'dine-in'): ?>
                    Please show this number to the waitress
                <?php else: ?>
                    Show this number when collecting your order
                <?php endif; ?>
            </div>
        </div>
        
        <div class="receipt">
            <div class="header">
                <?php if ($order['logo']): ?>
                <img src="<?php echo SITE_URL; ?>/uploads/logos/<?php echo $order['logo']; ?>" 
                     alt="Logo" class="logo">
                <?php endif; ?>
                <div class="restaurant-name"><?php echo htmlspecialchars($order['restaurant_name']); ?></div>
                <div class="restaurant-info">
                    <?php if ($order['restaurant_address']): ?>
                    <?php echo htmlspecialchars($order['restaurant_address']); ?><br>
                    <?php endif; ?>
                    <?php if ($order['restaurant_phone']): ?>
                    Tel: <?php echo htmlspecialchars($order['restaurant_phone']); ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="section">
                <div class="section-title">Order Information</div>
                <div class="info-row">
                    <span class="info-label">Order Number:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['order_number']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date & Time:</span>
                    <span class="info-value"><?php echo date('M d, Y - h:i A', strtotime($order['created_at'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Order Type:</span>
                    <span class="info-value"><?php echo ucfirst($order['order_type']); ?></span>
                </div>
                <?php if ($order['table_number']): ?>
                <div class="info-row">
                    <span class="info-label">Table Number:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['table_number']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="section">
                <div class="section-title">Customer Information</div>
                <div class="info-row">
                    <span class="info-label">Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['customer_phone']); ?></span>
                </div>
            </div>
            
            <div class="section">
                <div class="section-title">Order Items</div>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Qty</th>
                            <th>Item</th>
                            <th style="text-align: right;">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="item-qty"><?php echo $item['quantity']; ?>x</td>
                            <td class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></td>
                            <td class="item-price"><?php echo CURRENCY_SYMBOL . number_format($item['subtotal'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="total-section">
                <div class="total-row grand-total">
                    <span>TOTAL</span>
                    <span><?php echo CURRENCY_SYMBOL . number_format($order['total_amount'], 2); ?></span>
                </div>
            </div>
            
            <div class="payment-info">
                <div class="info-row">
                    <span class="info-label">Payment Method:</span>
                    <span class="info-value"><?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payment Status:</span>
                    <span>
                        <span class="badge <?php echo $order['payment_status']; ?>">
                            <?php echo ucfirst($order['payment_status']); ?>
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Order Status:</span>
                    <span>
                        <span class="badge <?php echo $order['order_status']; ?>">
                            <?php echo ucfirst($order['order_status']); ?>
                        </span>
                    </span>
                </div>
            </div>
            
            <?php if ($order['payment_method'] === 'cash'): ?>
            <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #ffc107;">
                <strong style="color: #856404;">💵 Cash Payment</strong>
                <p style="font-size: 12px; color: #856404; margin-top: 8px;">
                    Please pay <strong><?php echo CURRENCY_SYMBOL . number_format($order['total_amount'], 2); ?></strong> 
                    in cash when you receive your order.
                </p>
            </div>
            <?php endif; ?>
            
            <div class="footer">
                <p style="margin-bottom: 10px;">Thank you for your order!</p>
                <p style="font-size: 11px;">Receipt #: <?php echo $receipt_number; ?></p>
                <p style="font-size: 11px; margin-top: 5px;">Powered by <?php echo SITE_NAME; ?></p>
            </div>
        </div>
        
        <div class="actions">
            <button onclick="window.print()" class="btn btn-print">🖨️ Print Receipt</button>
            <a href="menu.php?r=<?php echo $order['restaurant_id']; ?>" class="btn btn-back">← Back to Menu</a>
        </div>
    </div>
</body>
</html>
