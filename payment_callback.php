<?php
// payment_callback.php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/payment.php';

$database = new Database();
$db = $database->getConnection();
$payment = new Payment($db);

// Get order ID from URL
$order_id = $_GET['order_id'] ?? 0;

// Get reference from Paystack
$reference = $_GET['reference'] ?? '';

if (!$order_id || !$reference) {
    die('Invalid payment callback');
}

// Verify the payment with Paystack
$result = $payment->verifyPaystackPayment($reference);

if ($result && isset($result['status']) && $result['status'] === true) {
    // Payment was successful
    $payment_data = $result['data'];
    
    if ($payment_data['status'] === 'success') {
        // Update payment record
        $payment->updatePaymentStatus($order_id, 'success', $reference, $payment_data);
        
        // Update order payment status
        $payment->updateOrderPaymentStatus($order_id, 'completed', 'confirmed');
        
        // Get order details for display
        $query = "SELECT * FROM orders WHERE id = :order_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->execute();
        $order = $stmt->fetch();
        
        $success_message = "Payment successful! Your order has been confirmed.";
        $payment_status = 'success';
    } else {
        $payment->updatePaymentStatus($order_id, 'failed', $reference, $payment_data);
        $payment->updateOrderPaymentStatus($order_id, 'failed');
        
        $success_message = "Payment failed. Please try again.";
        $payment_status = 'failed';
    }
} else {
    $payment->updatePaymentStatus($order_id, 'failed', $reference);
    $payment->updateOrderPaymentStatus($order_id, 'failed');
    
    $success_message = "Payment verification failed. Please contact support.";
    $payment_status = 'failed';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status - <?php echo SITE_NAME; ?></title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .payment-container {
            width: 100%;
            max-width: 500px;
        }

        .payment-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 25px;
            padding: 50px 40px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .payment-icon {
            font-size: 100px;
            margin-bottom: 30px;
            display: block;
        }

        .payment-icon.success {
            color: #10b981;
        }

        .payment-icon.failed {
            color: #ef4444;
        }

        .payment-title {
            color: #1f2937;
            margin-bottom: 20px;
            font-size: 32px;
            font-weight: 700;
        }

        .payment-message {
            color: #6b7280;
            margin-bottom: 40px;
            font-size: 18px;
            line-height: 1.6;
        }

        .payment-details {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            text-align: left;
            border: 2px solid #e2e8f0;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #475569;
            font-size: 14px;
        }

        .detail-value {
            color: #1e293b;
            font-weight: 500;
            text-align: right;
        }

        .btn-payment {
            padding: 16px 32px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
            border: none;
            width: 100%;
            margin-bottom: 15px;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #0da271;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
            color: white;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3);
            color: white;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            color: white;
        }

        @media (max-width: 576px) {
            .payment-card {
                padding: 40px 25px;
            }

            .payment-icon {
                font-size: 80px;
            }

            .payment-title {
                font-size: 28px;
            }

            .payment-message {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-card">
            <?php if ($payment_status === 'success'): ?>
                <div class="payment-icon success">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <h1 class="payment-title">Payment Successful!</h1>
                <p class="payment-message"><?php echo htmlspecialchars($success_message); ?></p>
                
                <?php if (isset($order)): ?>
                <div class="payment-details">
                    <div class="detail-item">
                        <span class="detail-label">Order Number</span>
                        <span class="detail-value fw-bold"><?php echo htmlspecialchars($order['order_number']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Amount Paid</span>
                        <span class="detail-value fw-bold"><?php echo CURRENCY_SYMBOL . number_format($order['total_amount'], 2); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Order Type</span>
                        <span class="detail-value"><?php echo ucfirst($order['order_type']); ?></span>
                    </div>
                    <?php if ($order['table_number']): ?>
                    <div class="detail-item">
                        <span class="detail-label">Table Number</span>
                        <span class="detail-value"><?php echo htmlspecialchars($order['table_number']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-item">
                        <span class="detail-label">Payment Reference</span>
                        <span class="detail-value" style="font-size: 12px; word-break: break-all;">
                            <?php echo htmlspecialchars($reference); ?>
                        </span>
                    </div>
                </div>
                
                <p class="text-muted mb-4">
                    <small>
                        <?php if ($order['order_type'] === 'dine-in'): ?>
                        Your order has been confirmed and the restaurant has been notified. 
                        Please wait at your table and your order will be served shortly.
                        <?php else: ?>
                        Please wait for your order to be prepared. You will be notified when it's ready for pickup.
                        <?php endif; ?>
                    </small>
                </p>
                <?php endif; ?>
                
                <a href="order_confirmation.php?order=<?php echo $order_id; ?>" class="btn-payment btn-success">
                    <i class="bi bi-receipt"></i> View Order Details
                </a>
                
            <?php else: ?>
                <div class="payment-icon failed">
                    <i class="bi bi-x-circle-fill"></i>
                </div>
                <h1 class="payment-title">Payment Failed</h1>
                <p class="payment-message"><?php echo htmlspecialchars($success_message); ?></p>
                
                <p class="text-muted mb-4">
                    <small>
                        Your payment could not be processed. Please try again or contact support if the problem persists.
                    </small>
                </p>
                
                <?php if (isset($order)): ?>
                <a href="menu.php?r=<?php echo $order['restaurant_id']; ?>" class="btn-payment btn-danger">
                    <i class="bi bi-arrow-left"></i> Return to Menu
                </a>
                <?php else: ?>
                <a href="index.php" class="btn-payment btn-primary">
                    <i class="bi bi-house"></i> Back to Home
                </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>