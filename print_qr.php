<?php
// restaurant/print_qr.php
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

// Get restaurant info
$query = "SELECT * FROM restaurants WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $restaurant_id);
$stmt->execute();
$restaurant = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print QR Code - <?php echo htmlspecialchars($restaurant['name']); ?></title>
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                margin: 0;
                padding: 0;
            }
            
            .qr-print-page {
                page-break-after: always;
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
        }

        .toolbar {
            background: white;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .toolbar-content {
            max-width: 800px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #f5576c;
            color: white;
        }

        .btn-primary:hover {
            background: #e04658;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .qr-print-page {
            background: white;
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .qr-header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 3px solid #f5576c;
            padding-bottom: 30px;
        }

        .restaurant-logo {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 20px;
            border: 4px solid #f5576c;
        }

        .qr-header h1 {
            font-size: 42px;
            color: #333;
            margin-bottom: 10px;
        }

        .qr-header p {
            font-size: 20px;
            color: #666;
        }

        .qr-main {
            text-align: center;
            margin: 60px 0;
        }

        .qr-main h2 {
            font-size: 32px;
            color: #333;
            margin-bottom: 30px;
        }

        .qr-code-container {
            background: linear-gradient(135deg, #f5576c 0%, #764ba2 100%);
            padding: 40px;
            border-radius: 20px;
            display: inline-block;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .qr-code-container img {
            display: block;
            max-width: 400px;
            background: white;
            padding: 20px;
            border-radius: 15px;
        }

        .qr-instructions {
            margin-top: 50px;
            background: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
            border-left: 5px solid #f5576c;
        }

        .qr-instructions h3 {
            color: #333;
            font-size: 24px;
            margin-bottom: 20px;
        }

        .instructions-list {
            list-style: none;
            padding: 0;
        }

        .instructions-list li {
            font-size: 18px;
            color: #555;
            margin-bottom: 15px;
            padding-left: 40px;
            position: relative;
        }

        .instructions-list li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #f5576c;
            font-size: 24px;
            font-weight: bold;
        }

        .qr-footer {
            text-align: center;
            margin-top: 50px;
            padding-top: 30px;
            border-top: 2px solid #eee;
            color: #999;
            font-size: 16px;
        }

        /* Additional printable pages for tables */
        .table-qr {
            background: white;
            width: 148mm;
            height: 148mm;
            margin: 20px auto;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .table-qr h2 {
            font-size: 36px;
            margin-bottom: 20px;
            color: #f5576c;
        }

        .table-qr img {
            max-width: 300px;
            border: 3px solid #f5576c;
            border-radius: 10px;
        }

        .table-qr p {
            margin-top: 20px;
            font-size: 20px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <div class="toolbar-content">
            <h2>QR Code Print Preview</h2>
            <div>
                <button onclick="window.print()" class="btn btn-primary">🖨️ Print Now</button>
                <a href="index.php" class="btn btn-secondary">← Back to Dashboard</a>
            </div>
        </div>
    </div>

    <!-- Main QR Code Page -->
    <div class="qr-print-page">
        <div class="qr-header">
            <?php if ($restaurant['logo']): ?>
            <img src="<?php echo SITE_URL; ?>/uploads/logos/<?php echo $restaurant['logo']; ?>" 
                 alt="Restaurant Logo" class="restaurant-logo">
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($restaurant['name']); ?></h1>
            <p><?php echo htmlspecialchars($restaurant['address'] ?? 'Welcome to our restaurant'); ?></p>
        </div>

        <div class="qr-main">
            <h2>Scan to View Our Menu & Order</h2>
            
            <div class="qr-code-container">
                <?php if ($restaurant['qr_code']): ?>
                <img src="<?php echo SITE_URL; ?>/uploads/qr_codes/<?php echo $restaurant['qr_code']; ?>" 
                     alt="QR Code">
                <?php endif; ?>
            </div>
        </div>

        <div class="qr-instructions">
            <h3>How to Order:</h3>
            <ul class="instructions-list">
                <li>Open your phone's camera app</li>
                <li>Point it at the QR code above</li>
                <li>Tap the notification to view our menu</li>
                <li>Select your items and place your order</li>
                <li>Choose dine-in or takeaway</li>
                <li>Pay securely with card or mobile money</li>
            </ul>
        </div>

        <div class="qr-footer">
            <p>Powered by <?php echo SITE_NAME; ?></p>
        </div>
    </div>

    <!-- Individual Table QR Codes (for printing on smaller cards) -->
    <?php for ($i = 1; $i <= 6; $i++): ?>
    <div class="table-qr qr-print-page">
        <h2><?php echo htmlspecialchars($restaurant['name']); ?></h2>
        <?php if ($restaurant['qr_code']): ?>
        <img src="<?php echo SITE_URL; ?>/uploads/qr_codes/<?php echo $restaurant['qr_code']; ?>" 
             alt="Table <?php echo $i; ?> QR Code">
        <?php endif; ?>
        <p>📱 Scan to Order</p>
    </div>
    <?php endfor; ?>

    <script>
        // Auto-print when page loads (optional - can be disabled)
        // window.onload = function() {
        //     window.print();
        // };
    </script>
</body>
</html>