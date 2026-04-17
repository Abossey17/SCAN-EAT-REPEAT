<?php
// restaurant/reports.php
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
$report_type = $_GET['type'] ?? 'daily';
$date_filter = $_GET['date'] ?? date('Y-m-d');

// Get report data based on type
$report_data = [];
$chart_data = [];

switch ($report_type) {
    case 'daily':
        // Get daily report
        $query = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN payment_status = 'completed' THEN total_amount ELSE 0 END) as total_revenue,
                    SUM(CASE WHEN order_type = 'dine-in' THEN 1 ELSE 0 END) as dine_in_orders,
                    SUM(CASE WHEN order_type = 'takeaway' THEN 1 ELSE 0 END) as takeaway_orders,
                    SUM(CASE WHEN payment_method = 'visa' THEN 1 ELSE 0 END) as visa_payments,
                    SUM(CASE WHEN payment_method = 'mobile_money' THEN 1 ELSE 0 END) as momo_payments
                  FROM orders
                  WHERE restaurant_id = :restaurant_id
                  AND DATE(created_at) = :date
                  GROUP BY DATE(created_at)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':restaurant_id', $restaurant_id);
        $stmt->bindParam(':date', $date_filter);
        $stmt->execute();
        $report_data = $stmt->fetch();
        
        // Get hourly breakdown for chart
        $query = "SELECT 
                    HOUR(created_at) as hour,
                    COUNT(*) as orders,
                    SUM(CASE WHEN payment_status = 'completed' THEN total_amount ELSE 0 END) as revenue
                  FROM orders
                  WHERE restaurant_id = :restaurant_id
                  AND DATE(created_at) = :date
                  GROUP BY HOUR(created_at)
                  ORDER BY hour";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':restaurant_id', $restaurant_id);
        $stmt->bindParam(':date', $date_filter);
        $stmt->execute();
        $chart_data = $stmt->fetchAll();
        break;
        
    case 'weekly':
        // Get current week report
        $week_start = date('Y-m-d', strtotime('monday this week', strtotime($date_filter)));
        $week_end = date('Y-m-d', strtotime('sunday this week', strtotime($date_filter)));
        
        $query = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN payment_status = 'completed' THEN total_amount ELSE 0 END) as total_revenue,
                    SUM(CASE WHEN order_type = 'dine-in' THEN 1 ELSE 0 END) as dine_in_orders,
                    SUM(CASE WHEN order_type = 'takeaway' THEN 1 ELSE 0 END) as takeaway_orders
                  FROM orders
                  WHERE restaurant_id = :restaurant_id
                  AND DATE(created_at) BETWEEN :week_start AND :week_end";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':restaurant_id', $restaurant_id);
        $stmt->bindParam(':week_start', $week_start);
        $stmt->bindParam(':week_end', $week_end);
        $stmt->execute();
        $report_data = $stmt->fetch();
        $report_data['week_start'] = $week_start;
        $report_data['week_end'] = $week_end;
        
        // Get daily breakdown for chart
        $query = "SELECT 
                    DATE(created_at) as date,
                    DAYNAME(created_at) as day_name,
                    COUNT(*) as orders,
                    SUM(CASE WHEN payment_status = 'completed' THEN total_amount ELSE 0 END) as revenue
                  FROM orders
                  WHERE restaurant_id = :restaurant_id
                  AND DATE(created_at) BETWEEN :week_start AND :week_end
                  GROUP BY DATE(created_at)
                  ORDER BY date";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':restaurant_id', $restaurant_id);
        $stmt->bindParam(':week_start', $week_start);
        $stmt->bindParam(':week_end', $week_end);
        $stmt->execute();
        $chart_data = $stmt->fetchAll();
        break;
        
    case 'monthly':
        // Get monthly report
        $month = date('Y-m', strtotime($date_filter));
        
        $query = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN payment_status = 'completed' THEN total_amount ELSE 0 END) as total_revenue,
                    SUM(CASE WHEN order_type = 'dine-in' THEN 1 ELSE 0 END) as dine_in_orders,
                    SUM(CASE WHEN order_type = 'takeaway' THEN 1 ELSE 0 END) as takeaway_orders,
                    AVG(CASE WHEN payment_status = 'completed' THEN total_amount ELSE NULL END) as avg_order_value
                  FROM orders
                  WHERE restaurant_id = :restaurant_id
                  AND DATE_FORMAT(created_at, '%Y-%m') = :month";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':restaurant_id', $restaurant_id);
        $stmt->bindParam(':month', $month);
        $stmt->execute();
        $report_data = $stmt->fetch();
        $report_data['month'] = $month;
        
        // Get daily breakdown for chart
        $query = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as orders,
                    SUM(CASE WHEN payment_status = 'completed' THEN total_amount ELSE 0 END) as revenue
                  FROM orders
                  WHERE restaurant_id = :restaurant_id
                  AND DATE_FORMAT(created_at, '%Y-%m') = :month
                  GROUP BY DATE(created_at)
                  ORDER BY date";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':restaurant_id', $restaurant_id);
        $stmt->bindParam(':month', $month);
        $stmt->execute();
        $chart_data = $stmt->fetchAll();
        break;
        
    case 'yearly':
        // Get yearly report
        $year = date('Y', strtotime($date_filter));
        
        $query = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN payment_status = 'completed' THEN total_amount ELSE 0 END) as total_revenue,
                    SUM(CASE WHEN order_type = 'dine-in' THEN 1 ELSE 0 END) as dine_in_orders,
                    SUM(CASE WHEN order_type = 'takeaway' THEN 1 ELSE 0 END) as takeaway_orders,
                    AVG(CASE WHEN payment_status = 'completed' THEN total_amount ELSE NULL END) as avg_order_value
                  FROM orders
                  WHERE restaurant_id = :restaurant_id
                  AND YEAR(created_at) = :year";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':restaurant_id', $restaurant_id);
        $stmt->bindParam(':year', $year);
        $stmt->execute();
        $report_data = $stmt->fetch();
        $report_data['year'] = $year;
        
        // Get monthly breakdown for chart
        $query = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    MONTHNAME(created_at) as month_name,
                    COUNT(*) as orders,
                    SUM(CASE WHEN payment_status = 'completed' THEN total_amount ELSE 0 END) as revenue
                  FROM orders
                  WHERE restaurant_id = :restaurant_id
                  AND YEAR(created_at) = :year
                  GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                  ORDER BY month";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':restaurant_id', $restaurant_id);
        $stmt->bindParam(':year', $year);
        $stmt->execute();
        $chart_data = $stmt->fetchAll();
        break;
}

// Get top selling items for the period
$top_items_query = "SELECT 
                        mi.name,
                        SUM(oi.quantity) as total_quantity,
                        SUM(oi.subtotal) as total_revenue
                    FROM order_items oi
                    JOIN menu_items mi ON oi.menu_item_id = mi.id
                    JOIN orders o ON oi.order_id = o.id
                    WHERE o.restaurant_id = :restaurant_id
                    AND o.payment_status = 'completed'";

if ($report_type === 'daily') {
    $top_items_query .= " AND DATE(o.created_at) = :date_filter";
} elseif ($report_type === 'weekly') {
    $top_items_query .= " AND DATE(o.created_at) BETWEEN :week_start AND :week_end";
} elseif ($report_type === 'monthly') {
    $top_items_query .= " AND DATE_FORMAT(o.created_at, '%Y-%m') = :month_filter";
} elseif ($report_type === 'yearly') {
    $top_items_query .= " AND YEAR(o.created_at) = :year_filter";
}

$top_items_query .= " GROUP BY mi.id ORDER BY total_quantity DESC LIMIT 10";

$stmt = $db->prepare($top_items_query);
$stmt->bindParam(':restaurant_id', $restaurant_id);

if ($report_type === 'daily') {
    $stmt->bindParam(':date_filter', $date_filter);
} elseif ($report_type === 'weekly') {
    $stmt->bindParam(':week_start', $week_start);
    $stmt->bindParam(':week_end', $week_end);
} elseif ($report_type === 'monthly') {
    $stmt->bindParam(':month_filter', $month);
} elseif ($report_type === 'yearly') {
    $stmt->bindParam(':year_filter', $year);
}

$stmt->execute();
$top_items = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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
            background: #f5576c;
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
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 20px;
        }

        .report-filters {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .filter-group label {
            font-weight: 600;
            color: #666;
        }

        .report-tabs {
            display: flex;
            gap: 10px;
        }

        .tab-btn {
            padding: 10px 20px;
            border: 2px solid #f5576c;
            background: white;
            color: #f5576c;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
        }

        .tab-btn:hover,
        .tab-btn.active {
            background: #f5576c;
            color: white;
        }

        input[type="date"] {
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
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
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
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
            color: #f5576c;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .card h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f5576c;
        }

        .chart-container {
            position: relative;
            height: 400px;
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
            color: #333;
            font-weight: 600;
        }

        .btn {
            padding: 10px 20px;
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

        .btn-primary:hover {
            background: #e04658;
        }

        @media print {
            .sidebar, .report-filters, .btn {
                display: none !important;
            }
            
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            body {
                background: white;
            }
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
                <li><a href="reports.php" class="active">📈 Reports</a></li>
                <li><a href="qr_code.php">📱 QR Code</a></li>
                <li><a href="profile.php">⚙️ Profile</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="header">
                <h1>Sales Reports</h1>
                
                <div class="report-filters">
                    <div class="report-tabs">
                        <a href="?type=daily&date=<?php echo $date_filter; ?>" class="tab-btn <?php echo $report_type === 'daily' ? 'active' : ''; ?>">Daily</a>
                        <a href="?type=weekly&date=<?php echo $date_filter; ?>" class="tab-btn <?php echo $report_type === 'weekly' ? 'active' : ''; ?>">Weekly</a>
                        <a href="?type=monthly&date=<?php echo $date_filter; ?>" class="tab-btn <?php echo $report_type === 'monthly' ? 'active' : ''; ?>">Monthly</a>
                        <a href="?type=yearly&date=<?php echo $date_filter; ?>" class="tab-btn <?php echo $report_type === 'yearly' ? 'active' : ''; ?>">Yearly</a>
                    </div>
                    
                    <div class="filter-group">
                        <label>Date:</label>
                        <input type="date" id="dateFilter" value="<?php echo $date_filter; ?>" 
                               onchange="window.location.href='?type=<?php echo $report_type; ?>&date=' + this.value">
                    </div>
                    
                    <button class="btn btn-primary" onclick="window.print()">🖨️ Print Report</button>
                </div>
            </div>

            <?php if ($report_data): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Orders</h3>
                    <div class="value"><?php echo $report_data['total_orders'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Revenue</h3>
                    <div class="value"><?php echo CURRENCY_SYMBOL . number_format($report_data['total_revenue'] ?? 0, 2); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Dine-In Orders</h3>
                    <div class="value"><?php echo $report_data['dine_in_orders'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Takeaway Orders</h3>
                    <div class="value"><?php echo $report_data['takeaway_orders'] ?? 0; ?></div>
                </div>
                <?php if (isset($report_data['avg_order_value'])): ?>
                <div class="stat-card">
                    <h3>Avg Order Value</h3>
                    <div class="value"><?php echo CURRENCY_SYMBOL . number_format($report_data['avg_order_value'] ?? 0, 2); ?></div>
                </div>
                <?php endif; ?>
            </div>

            <?php if (count($chart_data) > 0): ?>
            <div class="card">
                <h2><?php echo ucfirst($report_type); ?> Sales Chart</h2>
                <div class="chart-container">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
            <?php endif; ?>

            <?php if (count($top_items) > 0): ?>
            <div class="card">
                <h2>Top Selling Items</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Quantity Sold</th>
                            <th>Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo $item['total_quantity']; ?></td>
                            <td><?php echo CURRENCY_SYMBOL . number_format($item['total_revenue'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="card">
                <p style="text-align: center; padding: 40px; color: #666;">No data available for this period</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        <?php if (count($chart_data) > 0): ?>
        const ctx = document.getElementById('salesChart').getContext('2d');
        
        const labels = <?php echo json_encode(array_column($chart_data, 
            $report_type === 'daily' ? 'hour' : 
            ($report_type === 'weekly' ? 'day_name' : 
            ($report_type === 'monthly' ? 'date' : 'month_name')))); ?>;
        
        const orderData = <?php echo json_encode(array_column($chart_data, 'orders')); ?>;
        const revenueData = <?php echo json_encode(array_column($chart_data, 'revenue')); ?>;
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Orders',
                        data: orderData,
                        backgroundColor: 'rgba(245, 87, 108, 0.5)',
                        borderColor: 'rgba(245, 87, 108, 1)',
                        borderWidth: 2,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Revenue (<?php echo CURRENCY_SYMBOL; ?>)',
                        data: revenueData,
                        backgroundColor: 'rgba(102, 126, 234, 0.5)',
                        borderColor: 'rgba(102, 126, 234, 1)',
                        borderWidth: 2,
                        type: 'line',
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Number of Orders'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Revenue (<?php echo CURRENCY_SYMBOL; ?>)'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>