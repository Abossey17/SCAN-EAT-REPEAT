<?php
// restaurant/categories.php
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

// Handle add category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $display_order = $_POST['display_order'] ?? 0;
    
    if ($name) {
        $query = "INSERT INTO categories (restaurant_id, name, description, display_order, status) 
                  VALUES (:restaurant_id, :name, :description, :display_order, 'active')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':restaurant_id', $restaurant_id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':display_order', $display_order);
        
        if ($stmt->execute()) {
            $message = 'Category added successfully';
        } else {
            $error = 'Failed to add category';
        }
    } else {
        $error = 'Category name is required';
    }
}

// Handle update category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $category_id = $_POST['category_id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $display_order = $_POST['display_order'] ?? 0;
    $status = $_POST['status'] ?? 'active';
    
    if ($category_id && $name) {
        $query = "UPDATE categories SET name = :name, description = :description, 
                  display_order = :display_order, status = :status 
                  WHERE id = :id AND restaurant_id = :restaurant_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':display_order', $display_order);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $category_id);
        $stmt->bindParam(':restaurant_id', $restaurant_id);
        
        if ($stmt->execute()) {
            $message = 'Category updated successfully';
        } else {
            $error = 'Failed to update category';
        }
    }
}

// Handle delete category
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $category_id = intval($_GET['id']);
    
    // Check if category has menu items
    $query = "SELECT COUNT(*) as count FROM menu_items WHERE category_id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $category_id);
    $stmt->execute();
    $item_count = $stmt->fetch()['count'];
    
    if ($item_count > 0) {
        $error = "Cannot delete category with $item_count menu items. Please move or delete items first.";
    } else {
        $query = "DELETE FROM categories WHERE id = :id AND restaurant_id = :restaurant_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $category_id);
        $stmt->bindParam(':restaurant_id', $restaurant_id);
        
        if ($stmt->execute()) {
            $message = 'Category deleted successfully';
        } else {
            $error = 'Failed to delete category';
        }
    }
}

// Get all categories
$query = "SELECT c.*, COUNT(mi.id) as item_count 
          FROM categories c 
          LEFT JOIN menu_items mi ON c.id = mi.category_id 
          WHERE c.restaurant_id = :restaurant_id 
          GROUP BY c.id 
          ORDER BY c.display_order, c.name";
$stmt = $db->prepare($query);
$stmt->bindParam(':restaurant_id', $restaurant_id);
$stmt->execute();
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Categories - <?php echo SITE_NAME; ?></title>
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
            transition: all 0.3s;
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
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
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

        .btn-primary:hover {
            background: #e04658;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-danger {
            background: #ff4757;
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
            margin-bottom: 30px;
        }

        .card h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f5576c;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .category-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }

        .category-card:hover {
            border-color: #f5576c;
            transform: translateY(-2px);
        }

        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .category-name {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }

        .category-order {
            background: #f5576c;
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }

        .category-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .category-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }

        .item-count {
            color: #666;
            font-size: 14px;
        }

        .category-actions {
            display: flex;
            gap: 5px;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge.active {
            background: #d4edda;
            color: #155724;
        }

        .badge.inactive {
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
            max-width: 500px;
            width: 90%;
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
                <li><a href="menu.php">📋 Menu Management</a></li>
                <li><a href="categories.php" class="active">📁 Categories</a></li>
                <li><a href="orders.php">📦 Orders</a></li>
                <li><a href="reports.php">📈 Reports</a></li>
                <li><a href="qr_code.php">📱 QR Code</a></li>
                <li><a href="profile.php">⚙️ Profile</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="header">
                <h1>Menu Categories</h1>
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

            <div class="actions-bar">
                <div>
                    <h3>Organize your menu into categories</h3>
                    <p style="color: #666; font-size: 14px; margin-top: 5px;">
                        Categories help customers navigate your menu easily
                    </p>
                </div>
                <button class="btn btn-success" onclick="openAddModal()">+ Add Category</button>
            </div>

            <div class="card">
                <h2>All Categories (<?php echo count($categories); ?>)</h2>
                
                <?php if (count($categories) > 0): ?>
                <div class="categories-grid">
                    <?php foreach ($categories as $category): ?>
                    <div class="category-card">
                        <div class="category-header">
                            <div class="category-name"><?php echo htmlspecialchars($category['name']); ?></div>
                            <div class="category-order">Order: <?php echo $category['display_order']; ?></div>
                        </div>
                        
                        <?php if ($category['description']): ?>
                        <div class="category-description"><?php echo htmlspecialchars($category['description']); ?></div>
                        <?php endif; ?>
                        
                        <div class="category-stats">
                            <div>
                                <span class="item-count">📋 <?php echo $category['item_count']; ?> items</span>
                                <span class="badge <?php echo $category['status']; ?>" style="margin-left: 10px;">
                                    <?php echo ucfirst($category['status']); ?>
                                </span>
                            </div>
                            <div class="category-actions">
                                <button class="btn btn-primary" onclick='openEditModal(<?php echo json_encode($category); ?>)'>Edit</button>
                                <a href="?action=delete&id=<?php echo $category['id']; ?>" 
                                   class="btn btn-danger" 
                                   onclick="return confirm('Delete this category?')">Delete</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p style="text-align: center; padding: 40px; color: #666;">
                    No categories yet. Add your first category to organize your menu.
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Category</h2>
                <span class="close-btn" onclick="closeAddModal()">&times;</span>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>Category Name *</label>
                    <input type="text" name="name" required placeholder="e.g., Appetizers, Main Course">
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Brief description (optional)"></textarea>
                </div>

                <div class="form-group">
                    <label>Display Order</label>
                    <input type="number" name="display_order" value="0" min="0" placeholder="0">
                    <small style="color: #666;">Lower numbers appear first</small>
                </div>

                <button type="submit" name="add_category" class="btn btn-success" style="width: 100%;">
                    Add Category
                </button>
            </form>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Category</h2>
                <span class="close-btn" onclick="closeEditModal()">&times;</span>
            </div>
            
            <form method="POST">
                <input type="hidden" name="category_id" id="edit_category_id">
                
                <div class="form-group">
                    <label>Category Name *</label>
                    <input type="text" name="name" id="edit_name" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description"></textarea>
                </div>

                <div class="form-group">
                    <label>Display Order</label>
                    <input type="number" name="display_order" id="edit_display_order" min="0">
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <button type="submit" name="update_category" class="btn btn-primary" style="width: 100%;">
                    Update Category
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

        function openEditModal(category) {
            document.getElementById('edit_category_id').value = category.id;
            document.getElementById('edit_name').value = category.name;
            document.getElementById('edit_description').value = category.description || '';
            document.getElementById('edit_display_order').value = category.display_order;
            document.getElementById('edit_status').value = category.status;
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
