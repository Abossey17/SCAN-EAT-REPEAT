<?php
// developer_dashboard.php - Developer commission tracking
require_once 'config/config.php';
require_once 'config/database.php';

// Simple authentication for developer
$developer_password = 'developer2026'; // Change this!
session_start();

if (!isset($_SESSION['developer_auth'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if ($_POST['password'] === $developer_password) {
            $_SESSION['developer_auth'] = true;
        } else {
            $error = 'Invalid password';
        }
    }
    
    if (!isset($_SESSION['developer_auth'])) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Developer Dashboard Login</title>
            <style>
                body { font-family: Arial; background: #667eea; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
                .login-box { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
                input { width: 100%; padding: 12px; margin: 10px 0; border: 2px solid #ddd; border-radius: 5px; }
                button { width: 100%; padding: 12px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; }
                .error { color: red; margin-bottom: 10px; }
            </style>
        </head>
        <body>
            <div class="login-box">
                <h2>Developer Dashboard</h2>
                <?php if (isset($error)): ?>
                <p class="error"><?php echo $error; ?></p>
                <?php endif; ?>
                <form method="POST">
                    <input type="password" name="password" placeholder="Developer Password" required>
                    <button type="submit">Login</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

$database = new Database();
$db = $database->getConnection();

// Get date range
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Get commission summary
$query = "SELECT 
            payment_method,
            COUNT(*) as total_transactions,
            SUM(order_amount) as total_order_value,
            SUM(platform_commission) as total_platform_commission,
            SUM(developer_commission) as total_developer_commission,
            SUM(restaurant_amount) as total_restaurant_amount
          FROM commission_records
          WHERE status = 'settled'
          AND DATE(created_at) BETWEEN :start_date AND :end_date
          GROUP BY payment_method";

$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$commission_summary = $stmt->fetchAll();

// Calculate totals
$total_developer_commission = 0;
$total_platform_commission = 0;
$total_transactions = 0;

foreach ($commission_summary as $summary) {
    $total_developer_commission += $summary['total_developer_commission'];
    $total_platform_commission += $summary['total_platform_commission'];
    $total_transactions += $summary['total_transactions'];
}

// Get daily breakdown
$query = "SELECT 
            DATE(created_at) as date,
            payment_method,
            COUNT(*) as transactions,
            SUM(developer_commission) as developer_commission
          FROM commission_records
          WHERE status = 'settled'
          AND DATE(created_at) BETWEEN :start_date AND :end_date
          GROUP BY DATE(created_at), payment_method
          ORDER BY date DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$daily_breakdown = $stmt->fetchAll();

// Get top restaurants
$query = "SELECT 
            r.name as restaurant_name,
            COUNT(cr.id) as transactions,
            SUM(cr.developer_commission) as developer_commission
          FROM commission_records cr
          JOIN restaurants r ON cr.restaurant_id = r.id
          WHERE cr.status = 'settled'
          AND DATE(cr.created_at) BETWEEN :start_date AND :end_date
          GROUP BY cr.restaurant_id
          ORDER BY developer_commission DESC
          LIMIT 10";

$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$top_restaurants = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer Commission Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        .header h1 { font-size: 32px; margin-bottom: 10px; }
        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .filters input {
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
        }
        .btn {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }
        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .card h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .commission-breakdown {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 15px;
        }
        .commission-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .commission-item h4 {
            color: #666;
            font-size: 12px;
            margin-bottom: 8px;
        }
        .commission-item .amount {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        .commission-item .details {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        .logout {
            float: right;
            color: white;
            text-decoration: none;
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="?logout=1" class="logout">Logout</a>
            <h1>💰 Developer Commission Dashboard</h1>
            <p>Track your commission earnings from the platform</p>
        </div>

        <div class="filters">
            <form method="GET" style="display: flex; gap: 15px; align-items: center;">
                <label>Start Date:</label>
                <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                <label>End Date:</label>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                <button type="submit" class="btn">Filter</button>
            </form>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Transactions</h3>
                <div class="value"><?php echo number_format($total_transactions); ?></div>
            </div>
            <div class="stat-card">
                <h3>Developer Commission</h3>
                <div class="value"><?php echo CURRENCY_SYMBOL . number_format($total_developer_commission, 2); ?></div>
            </div>
            <div class="stat-card">
                <h3>Platform Commission</h3>
                <div class="value"><?php echo CURRENCY_SYMBOL . number_format($total_platform_commission, 2); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Commission</h3>
                <div class="value"><?php echo CURRENCY_SYMBOL . number_format($total_developer_commission + $total_platform_commission, 2); ?></div>
            </div>
        </div>

        <div class="card">
            <h2>Commission Breakdown by Payment Method</h2>
            <div class="commission-breakdown">
                <?php foreach ($commission_summary as $summary): ?>
                <div class="commission-item">
                    <h4><?php echo strtoupper(str_replace('_', ' ', $summary['payment_method'])); ?></h4>
                    <div class="amount"><?php echo CURRENCY_SYMBOL . number_format($summary['total_developer_commission'], 2); ?></div>
                    <div class="details">
                        <?php echo number_format($summary['total_transactions']); ?> transactions |
                        Total Value: <?php echo CURRENCY_SYMBOL . number_format($summary['total_order_value'], 2); ?>
                    </div>
                    <div class="details" style="margin-top: 10px;">
                        <strong>Rate:</strong> 
                        <?php if ($summary['payment_method'] === 'visa'): ?>
                            5% of each transaction
                        <?php else: ?>
                            1% of each transaction
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <h2>Top 10 Restaurants (by Developer Commission)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Restaurant</th>
                        <th>Transactions</th>
                        <th>Developer Commission</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_restaurants as $restaurant): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($restaurant['restaurant_name']); ?></td>
                        <td><?php echo number_format($restaurant['transactions']); ?></td>
                        <td><strong><?php echo CURRENCY_SYMBOL . number_format($restaurant['developer_commission'], 2); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>Daily Breakdown</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Payment Method</th>
                        <th>Transactions</th>
                        <th>Developer Commission</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daily_breakdown as $day): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($day['date'])); ?></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $day['payment_method'])); ?></td>
                        <td><?php echo number_format($day['transactions']); ?></td>
                        <td><strong><?php echo CURRENCY_SYMBOL . number_format($day['developer_commission'], 2); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
<?php
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: developer_dashboard.php');
    exit;
}
?>
