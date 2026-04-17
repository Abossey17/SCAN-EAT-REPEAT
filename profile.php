<?php
// restaurant/profile.php
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
$message = '';
$error = '';

// Get restaurant details
$query = "SELECT * FROM restaurants WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $restaurant_id);
$stmt->execute();
$restaurant = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if ($name && $email) {
        // Handle logo upload
        $logo = $restaurant['logo'];
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png'];
            $filename = $_FILES['logo']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $new_filename = 'logo_' . $restaurant_id . '_' . time() . '.' . $ext;
                $upload_path = LOGO_DIR . $new_filename;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
                    // Delete old logo
                    if ($logo && file_exists(LOGO_DIR . $logo)) {
                        unlink(LOGO_DIR . $logo);
                    }
                    $logo = $new_filename;
                }
            }
        }
        
        $query = "UPDATE restaurants SET name = :name, email = :email, phone = :phone, 
                  address = :address, description = :description, logo = :logo 
                  WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':logo', $logo);
        $stmt->bindParam(':id', $restaurant_id);
        
        if ($stmt->execute()) {
            $_SESSION['restaurant_name'] = $name;
            $_SESSION['restaurant_email'] = $email;
            $message = 'Profile updated successfully';
            
            // Refresh restaurant data
            $query = "SELECT * FROM restaurants WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $restaurant_id);
            $stmt->execute();
            $restaurant = $stmt->fetch();
        } else {
            $error = 'Failed to update profile';
        }
    } else {
        $error = 'Please fill in all required fields';
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if ($current_password && $new_password && $confirm_password) {
        if (password_verify($current_password, $restaurant['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    $query = "UPDATE restaurants SET password = :password WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':password', $hashed_password);
                    $stmt->bindParam(':id', $restaurant_id);
                    
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
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo SITE_NAME; ?></title>
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
            border-bottom: 2px solid #f5576c;
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
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        .btn-primary {
            background: #f5576c;
            color: white;
            width: 100%;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        .logo-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin: 10px 0;
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
                <li><a href="qr_code.php">📱 QR Code</a></li>
                <li><a href="profile.php" class="active">⚙️ Profile</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="header">
                <h1>Restaurant Profile</h1>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="grid">
                <div class="card">
                    <h2>Restaurant Information</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <?php if ($restaurant['logo']): ?>
                        <img src="<?php echo SITE_URL; ?>/uploads/logos/<?php echo $restaurant['logo']; ?>" 
                             class="logo-preview" alt="Logo">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label>Restaurant Logo</label>
                            <input type="file" name="logo" accept="image/*">
                        </div>

                        <div class="form-group">
                            <label>Restaurant Name *</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($restaurant['name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($restaurant['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($restaurant['phone'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="address"><?php echo htmlspecialchars($restaurant['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description"><?php echo htmlspecialchars($restaurant['description'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" name="update_profile" class="btn-primary">Update Profile</button>
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
                            <small style="color: #666;">Minimum 6 characters</small>
                        </div>

                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" required minlength="6">
                        </div>

                        <button type="submit" name="change_password" class="btn-primary">Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
