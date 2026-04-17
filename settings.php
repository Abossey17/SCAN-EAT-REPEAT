<?php
// admin/settings.php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isAdminLoggedIn()) {
    header('Location: login.php');
    exit();
}

$admin_id = $auth->getAdminId();
$message = '';
$error = '';

// Get admin details
$query = "SELECT * FROM admins WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $admin_id);
$stmt->execute();
$admin = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    
    if ($full_name && $email) {
        // Check if email is already used by another admin
        $query = "SELECT id FROM admins WHERE email = :email AND id != :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':id', $admin_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $error = 'Email already in use by another admin';
        } else {
            $query = "UPDATE admins SET full_name = :full_name, email = :email WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':id', $admin_id);
            
            if ($stmt->execute()) {
                $_SESSION['admin_name'] = $full_name;
                $_SESSION['admin_email'] = $email;
                $message = 'Profile updated successfully';
                
                // Refresh admin data
                $query = "SELECT * FROM admins WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $admin_id);
                $stmt->execute();
                $admin = $stmt->fetch();
            } else {
                $error = 'Failed to update profile';
            }
        }
    } else {
        $error = 'Please fill in all fields';
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if ($current_password && $new_password && $confirm_password) {
        // Verify current password
        if (password_verify($current_password, $admin['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    $query = "UPDATE admins SET password = :password WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':password', $hashed_password);
                    $stmt->bindParam(':id', $admin_id);
                    
                    if ($stmt->execute()) {
                        $message = 'Password changed successfully';
                    } else {
                        $error = 'Failed to change password';
                    }
                } else {
                    $error = 'New password must be at least 6 characters';
                }
            } else {
                $error = 'New passwords do not match';
            }
        } else {
            $error = 'Current password is incorrect';
        }
    } else {
        $error = 'Please fill in all password fields';
    }
}

// Get system statistics
$query = "SELECT 
            (SELECT COUNT(*) FROM restaurants WHERE status != 'deleted') as total_restaurants,
            (SELECT COUNT(*) FROM orders) as total_orders,
            (SELECT COUNT(*) FROM menu_items) as total_menu_items,
            (SELECT SUM(total_amount) FROM orders WHERE payment_status = 'completed') as total_revenue";
$system_stats = $db->query($query)->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo SITE_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #667eea;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-menu li {
            margin-bottom: 10px;
        }

        .nav-menu a {
            display: block;
            padding: 12px 15px;
            color: #333;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .nav-menu a:hover,
        .nav-menu a.active {
            background: #667eea;
            color: white;
            transform: translateX(5px);
        }

        .main-content {
            padding: 30px;
        }

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

        .header h1 {
            color: #333;
            font-size: 28px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logout-btn {
            background: #ff4757;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
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

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .card h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-weight: 600;
            font-size: 14px;
        }

        .btn-primary {
            background: #667eea;
            color: white;
            width: 100%;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }

        .stat-item h3 {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .stat-item .value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }

        .info-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .info-section h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
        }

        .info-row:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="sidebar">
            <div class="logo"><?php echo SITE_NAME; ?></div>
            <ul class="nav-menu">
                <li><a href="index.php">📊 Dashboard</a></li>
                <li><a href="restaurants.php">🏪 Restaurants</a></li>
                <li><a href="orders.php">📦 All Orders</a></li>
                <li><a href="reports.php">📈 Reports</a></li>
                <li><a href="settings.php" class="active">⚙️ Settings</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="header">
                <h1>System Settings</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
                    <a href="logout.php" class="logout-btn">Logout</a>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="card" style="margin-bottom: 30px;">
                <h2>System Statistics</h2>
                <div class="stats-grid">
                    <div class="stat-item">
                        <h3>Total Restaurants</h3>
                        <div class="value"><?php echo $system_stats['total_restaurants']; ?></div>
                    </div>
                    <div class="stat-item">
                        <h3>Total Orders</h3>
                        <div class="value"><?php echo $system_stats['total_orders']; ?></div>
                    </div>
                    <div class="stat-item">
                        <h3>Total Menu Items</h3>
                        <div class="value"><?php echo $system_stats['total_menu_items']; ?></div>
                    </div>
                    <div class="stat-item">
                        <h3>Total Revenue</h3>
                        <div class="value"><?php echo CURRENCY_SYMBOL . number_format($system_stats['total_revenue'], 2); ?></div>
                    </div>
                </div>
            </div>

            <div class="grid">
                <div class="card">
                    <h2>Admin Profile</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($admin['full_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" value="<?php echo htmlspecialchars($admin['username']); ?>" disabled style="background: #f0f0f0;">
                            <small style="color: #666; font-size: 12px;">Username cannot be changed</small>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary">
                            Update Profile
                        </button>
                    </form>
                </div>

                <div class="card">
                    <h2>Change Password</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="current_password" required>
                        </div>

                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" required minlength="6">
                            <small style="color: #666; font-size: 12px;">Minimum 6 characters</small>
                        </div>

                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" required minlength="6">
                        </div>

                        <button type="submit" name="change_password" class="btn btn-primary">
                            Change Password
                        </button>
                    </form>
                </div>
            </div>

            <div class="card" style="margin-top: 30px;">
                <h2>System Information</h2>
                <div class="info-section">
                    <h3>Application Details</h3>
                    <div class="info-row">
                        <span>System Name:</span>
                        <strong><?php echo SITE_NAME; ?></strong>
                    </div>
                    <div class="info-row">
                        <span>Site URL:</span>
                        <strong><?php echo SITE_URL; ?></strong>
                    </div>
                    <div class="info-row">
                        <span>Currency:</span>
                        <strong><?php echo CURRENCY; ?> (<?php echo CURRENCY_SYMBOL; ?>)</strong>
                    </div>
                    <div class="info-row">
                        <span>Timezone:</span>
                        <strong><?php echo date_default_timezone_get(); ?></strong>
                    </div>
                    <div class="info-row">
                        <span>Current Date/Time:</span>
                        <strong><?php echo date('F d, Y - H:i:s'); ?></strong>
                    </div>
                </div>

                <div class="info-section">
                    <h3>Payment Gateway Configuration</h3>
                    <div class="info-row">
                        <span>Paystack Integration:</span>
                        <strong style="color: <?php echo PAYSTACK_PUBLIC_KEY ? '#28a745' : '#dc3545'; ?>">
                            <?php echo PAYSTACK_PUBLIC_KEY ? 'Configured' : 'Not Configured'; ?>
                        </strong>
                    </div>
                    <div class="info-row">
                        <span>Mobile Money Integration:</span>
                        <strong style="color: <?php echo MOMO_API_KEY ? '#28a745' : '#dc3545'; ?>">
                            <?php echo MOMO_API_KEY ? 'Configured' : 'Not Configured'; ?>
                        </strong>
                    </div>
                </div>

                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 5px; margin-top: 20px;">
                    <strong>⚠️ Configuration Note:</strong>
                    <p style="margin-top: 10px; color: #856404;">
                        To configure payment gateways, edit the <code>config/config.php</code> file and add your API keys.
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
