<?php
// restaurant/orders.php
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

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'] ?? 0;
    $new_status = $_POST['order_status'] ?? '';
    
    if ($order_id && $new_status) {
        $query = "UPDATE orders SET order_status = :status 
                  WHERE id = :id AND restaurant_id = :restaurant_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':status', $new_status);
        $stmt->bindParam(':id', $order_id);
        $stmt->bindParam(':restaurant_id', $restaurant_id);
        
        if ($stmt->execute()) {
            $message = 'Order status updated successfully';
        }
    }
}

// Filter parameters
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT * FROM orders WHERE restaurant_id = :restaurant_id";

if ($status_filter) {
    $query .= " AND order_status = :status";
}

if ($date_filter) {
    $query .= " AND DATE(created_at) = :date";
}

if ($search) {
    $query .= " AND (order_number LIKE :search OR customer_name LIKE :search OR customer_phone LIKE :search)";
}

$query .= " ORDER BY created_at DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':restaurant_id', $restaurant_id);

if ($status_filter) {
    $stmt->bindParam(':status', $status_filter);
}

if ($date_filter) {
    $stmt->bindParam(':date', $date_filter);
}

if ($search) {
    $search_param = "%$search%";
    $stmt->bindParam(':search', $search_param);
}

$stmt->execute();
$orders = $stmt->fetchAll();

// Get order statistics
$stats = [];
$query = "SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN payment_status = 'completed' THEN total_amount ELSE 0 END) as total_revenue
          FROM orders WHERE restaurant_id = :restaurant_id";

if ($date_filter) {
    $query .= " AND DATE(created_at) = :date";
}

$stmt = $db->prepare($query);
$stmt->bindParam(':restaurant_id', $restaurant_id);
if ($date_filter) {
    $stmt->bindParam(':date', $date_filter);
}
$stmt->execute();
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - <?php echo SITE_NAME; ?></title>
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

        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            color: #666;
            font-size: 12px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .stat-card .value {
            font-size: 28px;
            font-weight: bold;
            color: #f5576c;
        }

        .filters-bar {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .filters-bar form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filters-bar input,
        .filters-bar select {
            padding: 10px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .filters-bar input[type="text"] {
            flex: 1;
            min-width: 200px;
        }

        .btn {
            padding: 10px 20px;
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
            background: #f5576c;
            color: white;
        }

        .btn-primary:hover {
            background: #e04658;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-warning {
            background: #ffa502;
            color: white;
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

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge.pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge.confirmed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .badge.preparing {
            background: #cce5ff;
            color: #004085;
        }

        .badge.ready {
            background: #d4edda;
            color: #155724;
        }

        .badge.completed {
            background: #d4edda;
            color: #155724;
        }

        .badge.cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f5576c;
        }

        .close-btn {
            font-size: 30px;
            cursor: pointer;
            color: #999;
        }

        .order-detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .order-detail-label {
            font-weight: 600;
            color: #666;
        }

        .order-items {
            margin: 20px 0;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            background: #f8f9fa;
            margin-bottom: 10px;
            border-radius: 5px;
        }

        .form-group {
            margin: 20px 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
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
                <li><a href="orders.php" class="active">📦 Orders</a></li>
                <li><a href="reports.php">📈 Reports</a></li>
                <li><a href="qr_code.php">📱 QR Code</a></li>
                <li><a href="profile.php">⚙️ Profile</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="header">
                <h1>Orders Management</h1>
                <div class="user-info">
                    <a href="logout.php" class="logout-btn">Logout</a>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Orders</h3>
                    <div class="value"><?php echo $stats['total_orders']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Pending Orders</h3>
                    <div class="value"><?php echo $stats['pending']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Completed Orders</h3>
                    <div class="value"><?php echo $stats['completed']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Revenue</h3>
                    <div class="value"><?php echo CURRENCY_SYMBOL . number_format($stats['total_revenue'], 2); ?></div>
                </div>
            </div>

            <div class="filters-bar">
                <form method="GET">
                    <input type="text" name="search" placeholder="Search by order number, customer..." value="<?php echo htmlspecialchars($search); ?>">
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="preparing" <?php echo $status_filter === 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                        <option value="ready" <?php echo $status_filter === 'ready' ? 'selected' : ''; ?>>Ready</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                    <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="orders.php" class="btn btn-warning">Clear</a>
                </form>
            </div>

            <div class="card">
                <h2>All Orders (<?php echo count($orders); ?>)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Date/Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($orders) > 0): ?>
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($order['customer_name'] ?? 'Guest'); ?><br>
                                    <small style="color: #666;"><?php echo htmlspecialchars($order['customer_phone'] ?? ''); ?></small>
                                </td>
                                <td><?php echo ucfirst($order['order_type']); ?></td>
                                <td><strong><?php echo CURRENCY_SYMBOL . number_format($order['total_amount'], 2); ?></strong></td>
                                <td>
                                    <?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?><br>
                                    <small class="badge" style="background: <?php echo $order['payment_status'] === 'completed' ? '#d4edda' : '#fff3cd'; ?>">
                                        <?php echo ucfirst($order['payment_status']); ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge <?php echo $order['order_status']; ?>">
                                        <?php echo ucfirst($order['order_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-primary" onclick="viewOrder(<?php echo $order['id']; ?>)">View</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                                    No orders found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Order Detail Modal -->
    <div class="modal" id="orderModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Order Details</h2>
                <span class="close-btn" onclick="closeOrderModal()">&times;</span>
            </div>
            <div id="orderDetails"></div>
        </div>
    </div>

    <script>
        function viewOrder(orderId) {
            // Fetch order details via AJAX
            fetch('get_order_details.php?id=' + orderId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayOrderDetails(data.order, data.items);
                        document.getElementById('orderModal').classList.add('active');
                    } else {
                        alert('Failed to load order details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load order details');
                });
        }

        function displayOrderDetails(order, items) {
            let html = `
                <div class="order-detail-row">
                    <span class="order-detail-label">Order Number:</span>
                    <strong>${order.order_number}</strong>
                </div>
                <div class="order-detail-row">
                    <span class="order-detail-label">Customer:</span>
                    <span>${order.customer_name || 'Guest'}</span>
                </div>
                <div class="order-detail-row">
                    <span class="order-detail-label">Phone:</span>
                    <span>${order.customer_phone || 'N/A'}</span>
                </div>
                <div class="order-detail-row">
                    <span class="order-detail-label">Order Type:</span>
                    <span>${order.order_type}</span>
                </div>
                ${order.table_number ? `
                <div class="order-detail-row">
                    <span class="order-detail-label">Table Number:</span>
                    <span>${order.table_number}</span>
                </div>
                ` : ''}
                
                <h3 style="margin-top: 20px; margin-bottom: 10px;">Order Items</h3>
                <div class="order-items">
            `;
            
            items.forEach(item => {
                html += `
                    <div class="order-item">
                        <div>
                            <strong>${item.item_name}</strong><br>
                            <small>${item.quantity} × <?php echo CURRENCY_SYMBOL; ?>${parseFloat(item.item_price).toFixed(2)}</small>
                        </div>
                        <strong><?php echo CURRENCY_SYMBOL; ?>${parseFloat(item.subtotal).toFixed(2)}</strong>
                    </div>
                `;
            });
            
            html += `
                </div>
                <div class="order-detail-row" style="font-size: 18px; font-weight: bold; border-top: 2px solid #333; padding-top: 15px;">
                    <span>Total Amount:</span>
                    <span><?php echo CURRENCY_SYMBOL; ?>${parseFloat(order.total_amount).toFixed(2)}</span>
                </div>
                
                <form method="POST" action="" style="margin-top: 20px;">
                    <input type="hidden" name="order_id" value="${order.id}">
                    <div class="form-group">
                        <label>Update Order Status:</label>
                        <select name="order_status" required>
                            <option value="pending" ${order.order_status === 'pending' ? 'selected' : ''}>Pending</option>
                            <option value="confirmed" ${order.order_status === 'confirmed' ? 'selected' : ''}>Confirmed</option>
                            <option value="preparing" ${order.order_status === 'preparing' ? 'selected' : ''}>Preparing</option>
                            <option value="ready" ${order.order_status === 'ready' ? 'selected' : ''}>Ready</option>
                            <option value="completed" ${order.order_status === 'completed' ? 'selected' : ''}>Completed</option>
                            <option value="cancelled" ${order.order_status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                        </select>
                    </div>
                    <button type="submit" name="update_status" class="btn btn-success" style="width: 100%;">
                        Update Status
                    </button>
                </form>
            `;
            
            document.getElementById('orderDetails').innerHTML = html;
        }

        function closeOrderModal() {
            document.getElementById('orderModal').classList.remove('active');
        }

        window.onclick = function(event) {
            if (event.target.id === 'orderModal') {
                closeOrderModal();
            }
        }

        // Auto-refresh orders every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
