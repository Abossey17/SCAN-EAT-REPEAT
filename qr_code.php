<?php
// restaurant/qr_code.php
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

$message = isset($_GET['success']) ? 'QR Code generated successfully!' : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code - <?php echo SITE_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            min-height: 100vh;
        }
        .dashboard {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }
        .sidebar {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .logo {
            font-size: 20px;
            font-weight: bold;
            color: #f5576c;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f5576c;
        }
        .nav-menu { list-style: none; }
        .nav-menu li { margin-bottom: 10px; }
        .nav-menu a {
            display: block;
            padding: 12px 15px;
            color: #333;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .nav-menu a:hover, .nav-menu a.active {
            background: #f5576c;
            color: white;
            transform: translateX(5px);
        }
        .main-content { padding: 30px; }
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header h1 { color: #333; font-size: 28px; }
        .logout-btn {
            background: #ff4757;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
        }
        .card h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f5576c;
        }
        .qr-display {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .qr-display img {
            max-width: 400px;
            border: 3px solid #f5576c;
            border-radius: 10px;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
            transition: all 0.3s;
            font-weight: 600;
        }
        .btn-primary {
            background: #f5576c;
            color: white;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="sidebar">
            <div class="logo"><?php echo SITE_NAME; ?></div>
            <ul class="nav-menu">
                <li><a href="index.php">📊 Dashboard</a></li>
                <li><a href="menu.php">📋 Menu Management</a></li>
                <li><a href="categories.php">📁 Categories</a></li>
                <li><a href="orders.php">📦 Orders</a></li>
                <li><a href="reports.php">📈 Reports</a></li>
                <li><a href="qr_code.php" class="active">📱 QR Code</a></li>
                <li><a href="profile.php">⚙️ Profile</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="header">
                <h1>Restaurant QR Code</h1>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="card">
                <h2>Your QR Code Menu</h2>
                
                <?php if ($restaurant['qr_code']): ?>
                <div class="qr-display">
                    <img src="<?php echo SITE_URL; ?>/uploads/qr_codes/<?php echo $restaurant['qr_code']; ?>" alt="QR Code">
                </div>
                
                <div>
                    <a href="print_qr.php" class="btn btn-success" target="_blank">🖨️ Print QR Code</a>
                    <a href="<?php echo SITE_URL; ?>/uploads/qr_codes/<?php echo $restaurant['qr_code']; ?>" 
                       download class="btn btn-primary">⬇️ Download</a>
                    <a href="generate_qr.php" class="btn btn-primary" 
                       onclick="return confirm('Regenerate QR code?')">🔄 Regenerate</a>
                </div>
                
                <div class="info-box">
                    <h3 style="color: #1976D2; margin-bottom: 10px;">📱 How to Use</h3>
                    <p style="color: #555; margin-bottom: 8px;">1. Download or print this QR code</p>
                    <p style="color: #555; margin-bottom: 8px;">2. Display it at your restaurant entrance and tables</p>
                    <p style="color: #555; margin-bottom: 8px;">3. Customers scan to view menu and place orders</p>
                    <p style="color: #555;"><strong>Menu Link:</strong> <?php echo SITE_URL; ?>/menu.php?r=<?php echo $restaurant_id; ?></p>
                </div>
                <?php else: ?>
                <p style="margin: 20px 0;">No QR code generated yet.</p>
                <a href="generate_qr.php" class="btn btn-success">Generate QR Code</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
