<?php
// admin/add_restaurant_split.php - Add restaurant with bank account for split payment
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/qr_generator.php';
require_once '../includes/payment_split.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isAdminLoggedIn()) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

// Get Ghana banks
$banks_query = "SELECT * FROM ghana_banks WHERE is_active = TRUE ORDER BY bank_name";
$banks = $db->query($banks_query)->fetchAll();

// Handle add restaurant with bank account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_restaurant'])) {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $description = $_POST['description'] ?? '';
    
    // Bank account details
    $bank_code = $_POST['bank_code'] ?? '';
    $account_number = $_POST['account_number'] ?? '';
    $account_name = $_POST['account_name'] ?? '';
    
    if ($name && $email && $password && $bank_code && $account_number && $account_name) {
        // Check if email already exists
        $query = "SELECT id FROM restaurants WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $error = 'Email already exists';
        } else {
            try {
                $db->beginTransaction();
                
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Get bank name
                $bank_query = "SELECT bank_name FROM ghana_banks WHERE bank_code = :code";
                $bank_stmt = $db->prepare($bank_query);
                $bank_stmt->bindParam(':code', $bank_code);
                $bank_stmt->execute();
                $bank = $bank_stmt->fetch();
                $bank_name = $bank['bank_name'];
                
                // Insert restaurant
                $query = "INSERT INTO restaurants 
                          (name, email, password, phone, address, description, 
                           bank_name, bank_code, account_number, account_name, status) 
                          VALUES 
                          (:name, :email, :password, :phone, :address, :description,
                           :bank_name, :bank_code, :account_number, :account_name, 'active')";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':address', $address);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':bank_name', $bank_name);
                $stmt->bindParam(':bank_code', $bank_code);
                $stmt->bindParam(':account_number', $account_number);
                $stmt->bindParam(':account_name', $account_name);
                $stmt->execute();
                
                $restaurant_id = $db->lastInsertId();
                
                // Create Paystack subaccount
                $payment = new PaymentSplit($db);
                $subaccount_result = $payment->createPaystackSubaccount(
                    $restaurant_id,
                    $name,
                    $bank_code,
                    $account_number
                );
                
                if ($subaccount_result['success']) {
                    // Update restaurant with subaccount details
                    $query = "UPDATE restaurants SET 
                              paystack_subaccount_code = :subaccount_code,
                              paystack_subaccount_id = :subaccount_id 
                              WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':subaccount_code', $subaccount_result['subaccount_code']);
                    $stmt->bindParam(':subaccount_id', $subaccount_result['subaccount_id']);
                    $stmt->bindParam(':id', $restaurant_id);
                    $stmt->execute();
                } else {
                    throw new Exception('Failed to create Paystack subaccount: ' . $subaccount_result['message']);
                }
                
                // Generate QR code
                try {
                    $qr_filename = QRGenerator::generateQRCode($restaurant_id, $name);
                    
                    // Update restaurant with QR code
                    $query = "UPDATE restaurants SET qr_code = :qr_code WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':qr_code', $qr_filename);
                    $stmt->bindParam(':id', $restaurant_id);
                    $stmt->execute();
                } catch (Exception $e) {
                    // QR generation failed but continue
                }
                
                $db->commit();
                $message = 'Restaurant added successfully with split payment configured (15% commission: 10% platform + 5% developer)';
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Failed to add restaurant: ' . $e->getMessage();
            }
        }
    } else {
        $error = 'Please fill in all required fields including bank account details';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Restaurant - Split Payment - <?php echo SITE_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
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
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .info-box h3 {
            color: #1976D2;
            margin-bottom: 8px;
            font-size: 16px;
        }
        .commission-breakdown {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 10px;
        }
        .commission-item {
            background: white;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
        }
        .commission-item strong {
            display: block;
            font-size: 18px;
            color: #1976D2;
            margin-top: 5px;
        }
        .form-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .form-section h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 8px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .required { color: #dc3545; }
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #667eea;
            color: white;
            width: 100%;
        }
        .btn-primary:hover {
            background: #5568d3;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
        }
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Add Restaurant - Split Payment</h1>
        <p class="subtitle">Configure restaurant with automatic payment splitting</p>

        <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="info-box">
            <h3>💰 Commission Structure</h3>
            <p style="color: #555; margin-bottom: 10px;">
                Automatic payment splitting is configured for this restaurant:
            </p>
            <div class="commission-breakdown">
                <div class="commission-item">
                    <small style="color: #666;">Visa Payments</small>
                    <strong>15% Total</strong>
                    <small style="color: #666;">10% Platform + 5% Developer</small>
                </div>
                <div class="commission-item">
                    <small style="color: #666;">Mobile Money</small>
                    <strong>11% Total</strong>
                    <small style="color: #666;">10% Platform + 1% Developer</small>
                </div>
                <div class="commission-item">
                    <small style="color: #666;">Restaurant Gets</small>
                    <strong>85-89%</strong>
                    <small style="color: #666;">Instant Settlement</small>
                </div>
            </div>
        </div>

        <form method="POST">
            <div class="form-section">
                <h2>Restaurant Information</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Restaurant Name <span class="required">*</span></label>
                        <input type="text" name="name" required placeholder="e.g., Tasty Bites">
                    </div>
                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" required placeholder="restaurant@example.com">
                    </div>
                    <div class="form-group">
                        <label>Password <span class="required">*</span></label>
                        <input type="password" name="password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" placeholder="0244123456">
                    </div>
                    <div class="form-group full-width">
                        <label>Address</label>
                        <textarea name="address" placeholder="Restaurant address"></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label>Description</label>
                        <textarea name="description" placeholder="Brief description"></textarea>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2>Bank Account Details (For Payment Settlement)</h2>
                <p style="color: #666; margin-bottom: 15px; font-size: 14px;">
                    Restaurant will receive their portion (85% for Visa, 89% for MoMo) directly to this account.
                </p>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Bank Name <span class="required">*</span></label>
                        <select name="bank_code" required>
                            <option value="">Select Bank</option>
                            <?php foreach ($banks as $bank): ?>
                            <option value="<?php echo htmlspecialchars($bank['bank_code']); ?>">
                                <?php echo htmlspecialchars($bank['bank_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Account Number <span class="required">*</span></label>
                        <input type="text" name="account_number" required 
                               placeholder="Enter account number" maxlength="20">
                    </div>
                    <div class="form-group">
                        <label>Account Name <span class="required">*</span></label>
                        <input type="text" name="account_name" required 
                               placeholder="Account holder name">
                    </div>
                </div>
            </div>

            <div class="actions">
                <button type="submit" name="add_restaurant" class="btn btn-primary">
                    Add Restaurant & Configure Split Payment
                </button>
            </div>
            
            <div style="margin-top: 15px;">
                <a href="restaurants.php" class="btn btn-secondary">← Back to Restaurants</a>
            </div>
        </form>
    </div>
</body>
</html>
