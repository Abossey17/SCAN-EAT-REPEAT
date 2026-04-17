<?php
// forgot_password.php
require_once 'config/config.php';

$message = '';
$error = '';

// In a production system, this would send a reset email
// For now, it's a placeholder showing the intended functionality
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo SITE_NAME; ?></title>
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
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .info-box h3 {
            color: #856404;
            margin-bottom: 10px;
        }
        .info-box p {
            color: #856404;
            line-height: 1.6;
            margin-bottom: 8px;
        }
        .contact-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .contact-box h3 {
            color: #1976D2;
            margin-bottom: 10px;
        }
        .contact-box p {
            color: #555;
            margin-bottom: 8px;
        }
        .contact-box strong {
            color: #1976D2;
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
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .links {
            text-align: center;
            margin-top: 20px;
        }
        .links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Forgot Password</h1>
        <p class="subtitle">Password Recovery Assistance</p>
        
        <div class="info-box">
            <h3>⚠️ Password Reset</h3>
            <p>For security reasons, password resets must be handled by system administrators.</p>
        </div>

        <div class="contact-box">
            <h3>📧 Contact Support</h3>
            <p>Please contact your system administrator to reset your password:</p>
            <p><strong>Admin Email:</strong> <?php echo ADMIN_EMAIL; ?></p>
            <p style="margin-top: 15px;">Include the following in your email:</p>
            <p>• Your registered email address</p>
            <p>• Your restaurant name (if applicable)</p>
            <p>• Reason for password reset</p>
        </div>

        <a href="index.php" class="btn btn-secondary">Back to Home</a>
        
        <div class="links">
            <p>Remembered your password?</p>
            <a href="admin/login.php">Admin Login</a> | 
            <a href="restaurant/login.php">Restaurant Login</a>
        </div>
    </div>
</body>
</html>
