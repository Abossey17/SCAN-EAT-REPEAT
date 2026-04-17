<?php
// order_confirmation.php
require_once 'config/config.php';
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$order_id = $_GET['order'] ?? 0;

if (!$order_id) {
    die('Invalid order');
}

// Get order details
$query = "SELECT o.*, r.name as restaurant_name, r.phone as restaurant_phone, r.address as restaurant_address
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--primary-gradient);
            min-height: 100vh;
            padding: 20px;
        }

        .confirmation-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
        }

        .confirmation-header {
            background: var(--primary-gradient);
            color: white;
            padding: 60px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .confirmation-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
            background-size: 40px 40px;
            opacity: 0.3;
        }

        .success-icon {
            font-size: 80px;
            margin-bottom: 20px;
            display: block;
            position: relative;
            z-index: 1;
        }

        .confirmation-header h1 {
            font-size: 36px;
            margin-bottom: 15px;
            font-weight: 700;
            position: relative;
            z-index: 1;
        }

        .confirmation-header p {
            font-size: 18px;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .confirmation-body {
            padding: 40px;
        }

        .order-summary {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            border: 2px solid #e2e8f0;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .summary-item {
            padding: 15px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        .summary-label {
            font-weight: 600;
            color: #475569;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .summary-value {
            color: #1e293b;
            font-size: 16px;
            font-weight: 500;
        }

        .badge {
            padding: 6px 12px;
            font-weight: 600;
            border-radius: 20px;
            font-size: 12px;
        }

        .items-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .section-title {
            color: #334155;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
            font-weight: 700;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: #f8fafc;
            margin-bottom: 15px;
            border-radius: 12px;
            transition: transform 0.2s;
        }

        .order-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
            font-size: 18px;
        }

        .item-price {
            color: #64748b;
            font-size: 15px;
        }

        .item-total {
            font-weight: 700;
            color: #667eea;
            font-size: 20px;
        }

        .total-section {
            background: #667eea;
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 28px;
            font-weight: 700;
        }

        .instructions-box {
            background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            border-left: 4px solid #0ea5e9;
        }

        .instructions-title {
            color: #0369a1;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .instruction-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .instruction-item:last-child {
            margin-bottom: 0;
        }

        .instruction-item i {
            color: #0ea5e9;
            font-size: 20px;
            margin-right: 15px;
            margin-top: 2px;
        }

        .instruction-text {
            color: #1e293b;
            line-height: 1.6;
        }

        .restaurant-info-box {
            background: #f8fafc;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            border: 2px solid #e2e8f0;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 16px 32px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            border: none;
        }

        .btn-print {
            background: #667eea;
            color: white;
        }

        .btn-print:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            color: white;
        }

        .btn-back {
            background: #475569;
            color: white;
        }

        .btn-back:hover {
            background: #334155;
            transform: translateY(-2px);
            color: white;
        }

        @media print {
            body {
                background: white !important;
            }

            .confirmation-container {
                box-shadow: none !important;
                margin: 0 !important;
            }

            .action-buttons {
                display: none !important;
            }

            .confirmation-header {
                padding: 30px 20px !important;
            }

            .confirmation-body {
                padding: 20px !important;
            }
        }

        @media (max-width: 768px) {
            .confirmation-header {
                padding: 40px 20px;
            }

            .confirmation-header h1 {
                font-size: 28px;
            }

            .confirmation-body {
                padding: 20px;
            }

            .order-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .item-total {
                align-self: flex-end;
            }

            .action-buttons {
                flex-direction: column;
                width: 100%;
            }

            .btn-action {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <div class="confirmation-header">
            <div class="success-icon">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <h1>Order Confirmed!</h1>
            <p>Thank you for your order</p>
        </div>

        <div class="confirmation-body">
            <!-- Order Summary -->
            <div class="order-summary">
                <h3 class="mb-4 fw-bold">Order Summary</h3>
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-label">Order Number</div>
                        <div class="summary-value fw-bold"><?php echo htmlspecialchars($order['order_number']); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Restaurant</div>
                        <div class="summary-value"><?php echo htmlspecialchars($order['restaurant_name']); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Customer Name</div>
                        <div class="summary-value"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Phone Number</div>
                        <div class="summary-value"><?php echo htmlspecialchars($order['customer_phone']); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Order Type</div>
                        <div class="summary-value">
                            <?php echo ucfirst($order['order_type']); ?>
                        </div>
                    </div>
                    <?php if ($order['table_number']): ?>
                    <div class="summary-item">
                        <div class="summary-label">Table Number</div>
                        <div class="summary-value"><?php echo htmlspecialchars($order['table_number']); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="summary-item">
                        <div class="summary-label">Order Status</div>
                        <div class="summary-value">
                            <?php if ($order['order_status'] == 'pending'): ?>
                                <span class="badge bg-warning">Pending</span>
                            <?php elseif ($order['order_status'] == 'confirmed'): ?>
                                <span class="badge bg-info">Confirmed</span>
                            <?php elseif ($order['order_status'] == 'completed'): ?>
                                <span class="badge bg-success">Completed</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?php echo ucfirst($order['order_status']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Payment Status</div>
                        <div class="summary-value">
                            <?php if ($order['payment_status'] == 'pending'): ?>
                                <span class="badge bg-warning">Pending</span>
                            <?php elseif ($order['payment_status'] == 'completed'): ?>
                                <span class="badge bg-success">Completed</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?php echo ucfirst($order['payment_status']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Order Date & Time</div>
                        <div class="summary-value">
                            <?php echo date('F d, Y - h:i A', strtotime($order['created_at'])); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="items-section">
                <h3 class="section-title">Order Items</h3>
                <?php foreach ($items as $item): ?>
                <div class="order-item">
                    <div class="item-details">
                        <div class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                        <div class="item-price">
                            <?php echo $item['quantity']; ?> × <?php echo CURRENCY_SYMBOL . number_format($item['item_price'], 2); ?>
                        </div>
                    </div>
                    <div class="item-total">
                        <?php echo CURRENCY_SYMBOL . number_format($item['subtotal'], 2); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Total Section -->
            <div class="total-section">
                <div class="total-row">
                    <span>Total Amount:</span>
                    <span><?php echo CURRENCY_SYMBOL . number_format($order['total_amount'], 2); ?></span>
                </div>
            </div>

            <!-- Instructions -->
            <div class="instructions-box">
                <h3 class="instructions-title">What happens next?</h3>
                <?php if ($order['order_type'] === 'dine-in'): ?>
                <div class="instruction-item">
                    <i class="bi bi-check-circle-fill"></i>
                    <div class="instruction-text">Your order has been sent to the kitchen</div>
                </div>
                <div class="instruction-item">
                    <i class="bi bi-check-circle-fill"></i>
                    <div class="instruction-text">Please remain at your table (Table <?php echo htmlspecialchars($order['table_number']); ?>)</div>
                </div>
                <div class="instruction-item">
                    <i class="bi bi-check-circle-fill"></i>
                    <div class="instruction-text">Your food will be served to you shortly</div>
                </div>
                <div class="instruction-item">
                    <i class="bi bi-check-circle-fill"></i>
                    <div class="instruction-text">Estimated preparation time: 15-30 minutes</div>
                </div>
                <?php else: ?>
                <div class="instruction-item">
                    <i class="bi bi-check-circle-fill"></i>
                    <div class="instruction-text">Your order is being prepared</div>
                </div>
                <div class="instruction-item">
                    <i class="bi bi-check-circle-fill"></i>
                    <div class="instruction-text">You will be notified when it's ready for pickup</div>
                </div>
                <div class="instruction-item">
                    <i class="bi bi-check-circle-fill"></i>
                    <div class="instruction-text">Please show this order number when collecting</div>
                </div>
                <div class="instruction-item">
                    <i class="bi bi-check-circle-fill"></i>
                    <div class="instruction-text">Estimated preparation time: 20-35 minutes</div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Restaurant Information -->
            <div class="restaurant-info-box">
                <h3 class="section-title">Restaurant Information</h3>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Name:</strong><br>
                        <?php echo htmlspecialchars($order['restaurant_name']); ?>
                    </div>
                    <?php if ($order['restaurant_phone']): ?>
                    <div class="col-md-6 mb-3">
                        <strong>Phone:</strong><br>
                        <a href="tel:<?php echo htmlspecialchars($order['restaurant_phone']); ?>" class="text-decoration-none">
                            <?php echo htmlspecialchars($order['restaurant_phone']); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if ($order['restaurant_address']): ?>
                    <div class="col-12">
                        <strong>Address:</strong><br>
                        <?php echo htmlspecialchars($order['restaurant_address']); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button onclick="window.print()" class="btn-action btn-print">
                    <i class="bi bi-printer"></i> Print Receipt
                </button>
                <a href="menu.php?r=<?php echo $order['restaurant_id']; ?>" class="btn-action btn-back">
                    <i class="bi bi-arrow-left"></i> Back to Menu
                </a>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>