<?php
// register.php - Note: Customer registration is optional for this system
// Customers can order as guests without registration
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Registration - <?php echo SITE_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            max-width: 500px;
            width: 100%;
        }
        h1 {
            color: #667eea;
            text-align: center;
            margin-bottom: 10px;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .info-box h3 {
            color: #1976D2;
            margin-bottom: 10px;
        }
        .info-box p {
            color: #555;
            line-height: 1.6;
            margin-bottom: 8px;
        }
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            width: 100%;
            text-align: center;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #667eea;
            color: white;
            margin-bottom: 10px;
        }
        .btn-primary:hover {
            background: #5568d3;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo SITE_NAME; ?></h1>
        <p class="subtitle">Customer Information</p>
        
        <div class="info-box">
            <h3>📱 No Registration Required!</h3>
            <p>This restaurant ordering system allows you to order as a guest without creating an account.</p>
            <p><strong>How to order:</strong></p>
            <p>1. Scan the QR code at your restaurant table</p>
            <p>2. Browse the menu and add items to your cart</p>
            <p>3. Enter your name and phone number at checkout</p>
            <p>4. Complete payment and enjoy your meal!</p>
        </div>

        <a href="index.php" class="btn btn-primary">Back to Home</a>
        
        <div style="text-align: center; margin-top: 20px; color: #666;">
            <p>Are you a restaurant owner?</p>
            <a href="restaurant/login.php" style="color: #667eea; font-weight: 600;">Login Here</a>
        </div>
    </div>
</body>
</html>
