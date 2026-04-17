<?php
// restaurant/menu.php
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

// Handle add menu item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $category_id = $_POST['category_id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? 0;
    $status = $_POST['status'] ?? 'available';
    
    if ($name && $price && $category_id) {
        // Handle image upload
        $image = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $new_filename = 'menu_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $upload_path = MENU_IMG_DIR . $new_filename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    $image = $new_filename;
                }
            }
        }
        
        $query = "INSERT INTO menu_items (restaurant_id, category_id, name, description, price, image, status) 
                  VALUES (:restaurant_id, :category_id, :name, :description, :price, :image, :status)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':restaurant_id', $restaurant_id);
        $stmt->bindParam(':category_id', $category_id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':image', $image);
        $stmt->bindParam(':status', $status);
        
        if ($stmt->execute()) {
            $message = 'Menu item added successfully';
        } else {
            $error = 'Failed to add menu item';
        }
    } else {
        $error = 'Please fill in all required fields';
    }
}

// Handle update menu item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_item'])) {
    $item_id = $_POST['item_id'] ?? 0;
    $category_id = $_POST['category_id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? 0;
    $status = $_POST['status'] ?? 'available';
    
    if ($item_id && $name && $price && $category_id) {
        // Handle image upload
        $image = $_POST['current_image'] ?? null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $new_filename = 'menu_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $upload_path = MENU_IMG_DIR . $new_filename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    // Delete old image
                    if ($image && file_exists(MENU_IMG_DIR . $image)) {
                        unlink(MENU_IMG_DIR . $image);
                    }
                    $image = $new_filename;
                }
            }
        }
        
        $query = "UPDATE menu_items SET category_id = :category_id, name = :name, 
                  description = :description, price = :price, image = :image, status = :status 
                  WHERE id = :id AND restaurant_id = :restaurant_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':category_id', $category_id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':image', $image);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $item_id);
        $stmt->bindParam(':restaurant_id', $restaurant_id);
        
        if ($stmt->execute()) {
            $message = 'Menu item updated successfully';
        } else {
            $error = 'Failed to update menu item';
        }
    }
}

// Handle delete menu item
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $item_id = intval($_GET['id']);
    
    // Get image filename before deleting
    $query = "SELECT image FROM menu_items WHERE id = :id AND restaurant_id = :restaurant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $item_id);
    $stmt->bindParam(':restaurant_id', $restaurant_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $item = $stmt->fetch();
        
        $query = "DELETE FROM menu_items WHERE id = :id AND restaurant_id = :restaurant_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $item_id);
        $stmt->bindParam(':restaurant_id', $restaurant_id);
        
        if ($stmt->execute()) {
            // Delete image file
            if ($item['image'] && file_exists(MENU_IMG_DIR . $item['image'])) {
                unlink(MENU_IMG_DIR . $item['image']);
            }
            $message = 'Menu item deleted successfully';
        } else {
            $error = 'Failed to delete menu item';
        }
    }
}

// Get categories
$query = "SELECT * FROM categories WHERE restaurant_id = :restaurant_id AND status = 'active' ORDER BY display_order, name";
$stmt = $db->prepare($query);
$stmt->bindParam(':restaurant_id', $restaurant_id);
$stmt->execute();
$categories = $stmt->fetchAll();

// Get menu items
$category_filter = $_GET['category'] ?? '';
$query = "SELECT mi.*, c.name as category_name 
          FROM menu_items mi 
          JOIN categories c ON mi.category_id = c.id 
          WHERE mi.restaurant_id = :restaurant_id";

if ($category_filter) {
    $query .= " AND mi.category_id = :category_id";
}

$query .= " ORDER BY c.display_order, c.name, mi.name";

$stmt = $db->prepare($query);
$stmt->bindParam(':restaurant_id', $restaurant_id);
if ($category_filter) {
    $stmt->bindParam(':category_id', $category_filter);
}
$stmt->execute();
$menu_items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Management - <?php echo SITE_NAME; ?></title>
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

        .actions-bar {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .filter-box {
            display: flex;
            gap: 10px;
        }

        .filter-box select {
            padding: 10px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
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
            background: #f5576c;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-warning {
            background: #ffa502;
            color: white;
        }

        .btn-danger {
            background: #ff4757;
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

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .menu-card {
            background: #f8f9fa;
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }

        .menu-card:hover {
            border-color: #f5576c;
            transform: translateY(-2px);
        }

        .menu-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #e0e0e0;
        }

        .menu-image-placeholder {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #e0e0e0 0%, #f0f0f0 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            color: #999;
        }

        .menu-body {
            padding: 15px;
        }

        .menu-category {
            font-size: 11px;
            color: white;
            background: #f5576c;
            padding: 3px 10px;
            border-radius: 10px;
            display: inline-block;
            margin-bottom: 10px;
        }

        .menu-name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
        }

        .menu-description {
            font-size: 13px;
            color: #666;
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .menu-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid #ddd;
        }

        .menu-price {
            font-size: 20px;
            font-weight: bold;
            color: #f5576c;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge.available {
            background: #d4edda;
            color: #155724;
        }

        .badge.unavailable {
            background: #f8d7da;
            color: #721c24;
        }

        .menu-actions {
            display: flex;
            gap: 5px;
            margin-top: 10px;
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
            max-width: 500px;
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
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="sidebar">
            <div class="logo"><?php echo SITE_NAME; ?></div>
            <ul class="nav-menu">
                <li><a href="index.php">📊 Dashboard</a></li>
                <li><a href="menu.php" class="active">📋 Menu Management</a></li>
                <li><a href="categories.php">📁 Categories</a></li>
                <li><a href="orders.php">📦 Orders</a></li>
                <li><a href="reports.php">📈 Reports</a></li>
                <li><a href="qr_code.php">📱 QR Code</a></li>
                <li><a href="profile.php">⚙️ Profile</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="header">
                <h1>Menu Management</h1>
                <div class="user-info">
                    <a href="logout.php" class="logout-btn">Logout</a>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (count($categories) === 0): ?>
            <div class="alert alert-error">
                Please <a href="categories.php" style="color: #721c24; font-weight: bold;">create categories</a> first before adding menu items.
            </div>
            <?php endif; ?>

            <div class="actions-bar">
                <form class="filter-box" method="GET">
                    <select name="category" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <button class="btn btn-success" onclick="openAddModal()" <?php echo count($categories) === 0 ? 'disabled' : ''; ?>>
                    + Add Menu Item
                </button>
            </div>

            <div class="card">
                <h2>Menu Items (<?php echo count($menu_items); ?>)</h2>
                
                <?php if (count($menu_items) > 0): ?>
                <div class="menu-grid">
                    <?php foreach ($menu_items as $item): ?>
                    <div class="menu-card">
                        <?php if ($item['image']): ?>
                        <img src="<?php echo SITE_URL; ?>/uploads/menu_images/<?php echo $item['image']; ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>" class="menu-image">
                        <?php else: ?>
                        <div class="menu-image-placeholder">🍴</div>
                        <?php endif; ?>
                        
                        <div class="menu-body">
                            <div class="menu-category"><?php echo htmlspecialchars($item['category_name']); ?></div>
                            <div class="menu-name"><?php echo htmlspecialchars($item['name']); ?></div>
                            <?php if ($item['description']): ?>
                            <div class="menu-description"><?php echo htmlspecialchars($item['description']); ?></div>
                            <?php endif; ?>
                            
                            <div class="menu-footer">
                                <div class="menu-price"><?php echo CURRENCY_SYMBOL . number_format($item['price'], 2); ?></div>
                                <span class="badge <?php echo $item['status']; ?>">
                                    <?php echo ucfirst($item['status']); ?>
                                </span>
                            </div>
                            
                            <div class="menu-actions">
                                <button class="btn btn-primary" onclick='openEditModal(<?php echo json_encode($item); ?>)'>Edit</button>
                                <a href="?action=delete&id=<?php echo $item['id']; ?>" 
                                   class="btn btn-danger" 
                                   onclick="return confirm('Delete this menu item?')">Delete</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p style="text-align: center; padding: 40px; color: #666;">
                    No menu items yet. Add your first menu item to get started.
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Menu Item Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Menu Item</h2>
                <span class="close-btn" onclick="closeAddModal()">&times;</span>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category_id" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Item Name *</label>
                    <input type="text" name="name" required placeholder="e.g., Grilled Chicken">
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Brief description of the item"></textarea>
                </div>

                <div class="form-group">
                    <label>Price *</label>
                    <input type="number" name="price" step="0.01" required placeholder="0.00">
                </div>

                <div class="form-group">
                    <label>Image</label>
                    <input type="file" name="image" accept="image/*">
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="available">Available</option>
                        <option value="unavailable">Unavailable</option>
                    </select>
                </div>

                <button type="submit" name="add_item" class="btn btn-success" style="width: 100%;">
                    Add Menu Item
                </button>
            </form>
        </div>
    </div>

    <!-- Edit Menu Item Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Menu Item</h2>
                <span class="close-btn" onclick="closeEditModal()">&times;</span>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="item_id" id="edit_item_id">
                <input type="hidden" name="current_image" id="edit_current_image">
                
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category_id" id="edit_category_id" required>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Item Name *</label>
                    <input type="text" name="name" id="edit_name" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description"></textarea>
                </div>

                <div class="form-group">
                    <label>Price *</label>
                    <input type="number" name="price" id="edit_price" step="0.01" required>
                </div>

                <div class="form-group">
                    <label>Change Image</label>
                    <input type="file" name="image" accept="image/*">
                    <small style="color: #666;">Leave empty to keep current image</small>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status">
                        <option value="available">Available</option>
                        <option value="unavailable">Unavailable</option>
                    </select>
                </div>

                <button type="submit" name="update_item" class="btn btn-primary" style="width: 100%;">
                    Update Menu Item
                </button>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
        }

        function openEditModal(item) {
            document.getElementById('edit_item_id').value = item.id;
            document.getElementById('edit_category_id').value = item.category_id;
            document.getElementById('edit_name').value = item.name;
            document.getElementById('edit_description').value = item.description || '';
            document.getElementById('edit_price').value = item.price;
            document.getElementById('edit_current_image').value = item.image || '';
            document.getElementById('edit_status').value = item.status;
            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>
