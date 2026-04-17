<?php
// restaurant/waiting_board.php - Display board showing current waiting numbers
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/waiting_number.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isRestaurantLoggedIn()) {
    header('Location: login.php');
    exit();
}

$restaurant_id = $auth->getRestaurantId();
$waitingNumber = new WaitingNumber($db);

// Get current orders
$current_orders = $waitingNumber->getCurrentWaitingNumbers($restaurant_id, 20);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waiting Numbers - Display Board</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .board {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 30px;
            border-radius: 20px 20px 0 0;
            text-align: center;
        }
        .header h1 {
            font-size: 48px;
            color: #333;
            margin-bottom: 10px;
        }
        .header p {
            font-size: 24px;
            color: #666;
        }
        .status-tabs {
            display: flex;
            background: white;
            border-radius: 0 0 20px 20px;
            overflow: hidden;
            margin-bottom: 30px;
        }
        .status-tab {
            flex: 1;
            padding: 20px;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        .status-tab.preparing {
            background: #fff3cd;
            color: #856404;
        }
        .status-tab.ready {
            background: #d4edda;
            color: #155724;
        }
        .status-tab.completed {
            background: #d1ecf1;
            color: #0c5460;
        }
        .numbers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .number-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: all 0.3s;
            border: 3px solid transparent;
        }
        .number-card:hover {
            transform: translateY(-5px);
        }
        .number-card.pending {
            border-color: #ffc107;
        }
        .number-card.confirmed,
        .number-card.preparing {
            border-color: #ff9800;
            animation: pulse 2s infinite;
        }
        .number-card.ready {
            border-color: #28a745;
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            animation: glow 1s infinite alternate;
        }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 10px 30px rgba(255, 152, 0, 0.3); }
            50% { box-shadow: 0 10px 40px rgba(255, 152, 0, 0.6); }
        }
        @keyframes glow {
            0% { box-shadow: 0 10px 30px rgba(40, 167, 69, 0.4); }
            100% { box-shadow: 0 10px 50px rgba(40, 167, 69, 0.8); }
        }
        .waiting-number-display {
            font-size: 72px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
            letter-spacing: 5px;
        }
        .order-type-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .badge.dine-in {
            background: #d1ecf1;
            color: #0c5460;
        }
        .badge.takeaway {
            background: #fff3cd;
            color: #856404;
        }
        .table-info {
            font-size: 18px;
            color: #666;
            margin-bottom: 15px;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-badge.confirmed {
            background: #cce5ff;
            color: #004085;
        }
        .status-badge.preparing {
            background: #ffeaa7;
            color: #d63031;
        }
        .status-badge.ready {
            background: #55efc4;
            color: #00b894;
            animation: blink 1s infinite;
        }
        @keyframes blink {
            0%, 50%, 100% { opacity: 1; }
            25%, 75% { opacity: 0.6; }
        }
        .time-elapsed {
            font-size: 14px;
            color: #999;
            margin-top: 10px;
        }
        .no-orders {
            background: white;
            padding: 60px;
            border-radius: 20px;
            text-align: center;
            color: #666;
            font-size: 24px;
        }
        .refresh-info {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            padding: 15px 25px;
            border-radius: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="board">
        <div class="header">
            <h1>🎯 Waiting Numbers</h1>
            <p>Current Orders in Progress</p>
        </div>
        
        <div class="status-tabs">
            <div class="status-tab preparing">
                <div>🔥 Preparing</div>
                <div id="preparing-count" style="font-size: 24px; margin-top: 5px;">0</div>
            </div>
            <div class="status-tab ready">
                <div>✅ Ready</div>
                <div id="ready-count" style="font-size: 24px; margin-top: 5px;">0</div>
            </div>
            <div class="status-tab completed">
                <div>📦 Completed Today</div>
                <div id="completed-count" style="font-size: 24px; margin-top: 5px;">0</div>
            </div>
        </div>
        
        <div class="numbers-grid" id="ordersGrid">
            <?php if (count($current_orders) > 0): ?>
                <?php foreach ($current_orders as $order): ?>
                <div class="number-card <?php echo $order['order_status']; ?>">
                    <div class="waiting-number-display"><?php echo htmlspecialchars($order['waiting_number']); ?></div>
                    <div class="order-type-badge badge <?php echo $order['order_type']; ?>">
                        <?php echo ucfirst($order['order_type']); ?>
                    </div>
                    <?php if ($order['table_number']): ?>
                    <div class="table-info">Table <?php echo htmlspecialchars($order['table_number']); ?></div>
                    <?php endif; ?>
                    <div class="status-badge <?php echo $order['order_status']; ?>">
                        <?php echo ucfirst($order['order_status']); ?>
                    </div>
                    <div class="time-elapsed" data-time="<?php echo strtotime($order['created_at']); ?>">
                        <?php 
                        $elapsed = time() - strtotime($order['created_at']);
                        echo floor($elapsed / 60) . ' min ago';
                        ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-orders" style="grid-column: 1/-1;">
                    No active orders at the moment
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="refresh-info">
        🔄 Auto-refreshing every 10 seconds
    </div>
    
    <script>
        // Auto-refresh every 10 seconds
        setInterval(function() {
            location.reload();
        }, 10000);
        
        // Update time elapsed
        function updateTimeElapsed() {
            document.querySelectorAll('.time-elapsed').forEach(function(elem) {
                const orderTime = parseInt(elem.getAttribute('data-time'));
                const now = Math.floor(Date.now() / 1000);
                const elapsed = now - orderTime;
                const minutes = Math.floor(elapsed / 60);
                
                if (minutes < 1) {
                    elem.textContent = 'Just now';
                } else {
                    elem.textContent = minutes + ' min ago';
                }
            });
        }
        
        // Update counts
        function updateCounts() {
            const preparing = document.querySelectorAll('.number-card.preparing, .number-card.confirmed').length;
            const ready = document.querySelectorAll('.number-card.ready').length;
            
            document.getElementById('preparing-count').textContent = preparing;
            document.getElementById('ready-count').textContent = ready;
        }
        
        // Update time every second
        setInterval(updateTimeElapsed, 1000);
        updateCounts();
        updateTimeElapsed();
    </script>
</body>
</html>
