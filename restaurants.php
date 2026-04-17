<?php
// admin/restaurants.php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/qr_generator.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isAdminLoggedIn()) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

// Handle restaurant actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $restaurant_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    switch ($action) {
        case 'suspend':
            $query = "UPDATE restaurants SET status = 'suspended' WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $restaurant_id);
            if ($stmt->execute()) {
                $message = 'Restaurant suspended successfully';
            }
            break;
            
        case 'activate':
            $query = "UPDATE restaurants SET status = 'active' WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $restaurant_id);
            if ($stmt->execute()) {
                $message = 'Restaurant activated successfully';
            }
            break;
            
        case 'delete':
            $query = "UPDATE restaurants SET status = 'deleted' WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $restaurant_id);
            if ($stmt->execute()) {
                $message = 'Restaurant deleted successfully';
            }
            break;
    }
}

// Handle add new restaurant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_restaurant'])) {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if ($name && $email && $password) {
        // Check if email already exists
        $query = "SELECT id FROM restaurants WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $error = 'Email already exists';
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert restaurant
            $query = "INSERT INTO restaurants (name, email, password, phone, address, description, status) 
                      VALUES (:name, :email, :password, :phone, :address, :description, 'active')";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':description', $description);
            
            if ($stmt->execute()) {
                $restaurant_id = $db->lastInsertId();
                
                // Generate QR code
                try {
                    $qr_filename = QRGenerator::generateQRCode($restaurant_id, $name);
                    
                    // Update restaurant with QR code
                    $query = "UPDATE restaurants SET qr_code = :qr_code WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':qr_code', $qr_filename);
                    $stmt->bindParam(':id', $restaurant_id);
                    $stmt->execute();
                    
                    $message = 'Restaurant added successfully with QR code';
                } catch (Exception $e) {
                    $message = 'Restaurant added but QR code generation failed: ' . $e->getMessage();
                }
            } else {
                $error = 'Failed to add restaurant';
            }
        }
    } else {
        $error = 'Please fill in all required fields';
    }
}

// Get all restaurants
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$query = "SELECT * FROM restaurants WHERE status != 'deleted'";

if ($search) {
    $query .= " AND (name LIKE :search OR email LIKE :search OR phone LIKE :search)";
}

if ($status_filter) {
    $query .= " AND status = :status";
}

$query .= " ORDER BY created_at DESC";

$stmt = $db->prepare($query);

if ($search) {
    $search_param = "%$search%";
    $stmt->bindParam(':search', $search_param);
}

if ($status_filter) {
    $stmt->bindParam(':status', $status_filter);
}

$stmt->execute();
$restaurants = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Restaurants - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --dark-color: #1f2937;
            --light-color: #f9fafb;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fb;
            min-height: 100vh;
        }

        .sidebar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            min-height: 100vh;
            position: fixed;
            width: 250px;
            z-index: 1000;
            box-shadow: 3px 0 15px rgba(0,0,0,0.1);
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                overflow: hidden;
            }
            .sidebar.active {
                width: 250px;
            }
            .main-content {
                margin-left: 0;
            }
            .navbar-toggler {
                display: block !important;
            }
        }

        .logo {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .logo h2 {
            font-weight: 700;
            margin: 0;
            color: white;
        }

        .nav-menu {
            padding: 20px 0;
        }

        .nav-menu .nav-item {
            margin: 5px 15px;
        }

        .nav-menu .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 15px;
            border-radius: 8px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-menu .nav-link:hover,
        .nav-menu .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }

        .nav-menu .nav-link i {
            font-size: 18px;
            width: 24px;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }

        .card-header {
            background: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 20px 25px;
            border-radius: 12px 12px 0 0 !important;
        }

        .card-header h5 {
            margin: 0;
            font-weight: 600;
            color: var(--dark-color);
        }

        .badge {
            padding: 6px 12px;
            font-weight: 500;
            border-radius: 20px;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            border-top: none;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
            padding: 15px 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .table td {
            padding: 15px 20px;
            vertical-align: middle;
            border-bottom: 1px solid #e5e7eb;
        }

        .table tbody tr:hover {
            background-color: #f9fafb;
        }

        .form-control {
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px 15px;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .navbar-toggler {
            display: none;
            border: none;
            font-size: 24px;
            color: var(--dark-color);
            padding: 8px;
        }

        .navbar-toggler:focus {
            box-shadow: none;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .modal-header {
            border-bottom: 1px solid #e5e7eb;
            padding: 20px 25px;
        }

        .modal-body {
            padding: 25px;
        }

        .modal-footer {
            border-top: 1px solid #e5e7eb;
            padding: 20px 25px;
        }

        .search-box {
            max-width: 400px;
        }

        .action-dropdown {
            min-width: 150px;
        }

        .qr-thumb {
            width: 40px;
            height: 40px;
            background: #f3f4f6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .qr-thumb:hover {
            background: var(--primary-color);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <nav class="navbar navbar-light bg-white fixed-top d-md-none" style="z-index: 999;">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>
            <span class="navbar-brand mb-0 h6"><?php echo SITE_NAME; ?></span>
        </div>
    </nav>

    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="logo">
                <h2><i class="bi bi-shop"></i> <?php echo SITE_NAME; ?></h2>
            </div>
            
            <ul class="nav flex-column nav-menu">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="restaurants.php">
                        <i class="bi bi-shop"></i>
                        <span>Restaurants</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="orders.php">
                        <i class="bi bi-cart"></i>
                        <span>All Orders</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="bi bi-graph-up"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
                        <i class="bi bi-gear"></i>
                        <span>Settings</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 pt-3 pt-md-0">
                <div>
                    <h3 class="mb-0 fw-bold">Manage Restaurants</h3>
                    <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['admin_name']); ?></p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['admin_name'], 0, 1)); ?>
                    </div>
                    <a href="logout.php" class="btn btn-danger">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Action Bar -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8 mb-3 mb-md-0">
                            <form method="GET" class="row g-2">
                                <div class="col-md-5">
                                    <div class="input-group">
                                        <span class="input-group-text bg-transparent border-end-0">
                                            <i class="bi bi-search"></i>
                                        </span>
                                        <input type="text" class="form-control border-start-0" 
                                               name="search" placeholder="Search restaurants..." 
                                               value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <select class="form-select" name="status">
                                        <option value="">All Status</option>
                                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-filter"></i> Filter
                                    </button>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addRestaurantModal">
                                <i class="bi bi-plus-circle"></i> Add New Restaurant
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Restaurants Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        All Restaurants <span class="badge bg-primary"><?php echo count($restaurants); ?></span>
                    </h5>
                    <div class="text-muted">
                        Showing <?php echo count($restaurants); ?> restaurants
                    </div>
                </div>
                <div class="card-body">
                    <?php if (count($restaurants) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>QR Code</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($restaurants as $restaurant): ?>
                                <tr>
                                    <td><strong>#<?php echo $restaurant['id']; ?></strong></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" 
                                                     style="width: 36px; height: 36px;">
                                                    <i class="bi bi-shop text-primary"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($restaurant['name']); ?></strong>
                                                <?php if ($restaurant['description']): ?>
                                                <div class="text-muted small" style="max-width: 200px;">
                                                    <?php echo htmlspecialchars(substr($restaurant['description'], 0, 50)); ?>
                                                    <?php if (strlen($restaurant['description']) > 50): ?>...<?php endif; ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($restaurant['email']); ?></td>
                                    <td><?php echo htmlspecialchars($restaurant['phone'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($restaurant['status'] == 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php elseif ($restaurant['status'] == 'suspended'): ?>
                                            <span class="badge bg-warning">Suspended</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($restaurant['qr_code']): ?>
                                            <a href="../uploads/qr_codes/<?php echo $restaurant['qr_code']; ?>" 
                                               target="_blank" 
                                               class="qr-thumb"
                                               data-bs-toggle="tooltip" 
                                               title="View QR Code">
                                                <i class="bi bi-qr-code"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Not Generated</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($restaurant['created_at'])); ?></td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" 
                                                    type="button" 
                                                    data-bs-toggle="dropdown">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu action-dropdown">
                                                <li>
                                                    <a class="dropdown-item" href="view_restaurant.php?id=<?php echo $restaurant['id']; ?>">
                                                        <i class="bi bi-eye me-2"></i> View Details
                                                    </a>
                                                </li>
                                                <?php if ($restaurant['status'] == 'active'): ?>
                                                <li>
                                                    <a class="dropdown-item text-warning" 
                                                       href="?action=suspend&id=<?php echo $restaurant['id']; ?>" 
                                                       onclick="return confirm('Suspend this restaurant?')">
                                                        <i class="bi bi-pause me-2"></i> Suspend
                                                    </a>
                                                </li>
                                                <?php else: ?>
                                                <li>
                                                    <a class="dropdown-item text-success" 
                                                       href="?action=activate&id=<?php echo $restaurant['id']; ?>">
                                                        <i class="bi bi-play me-2"></i> Activate
                                                    </a>
                                                </li>
                                                <?php endif; ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger" 
                                                       href="?action=delete&id=<?php echo $restaurant['id']; ?>" 
                                                       onclick="return confirm('Delete this restaurant? This action cannot be undone.')">
                                                        <i class="bi bi-trash me-2"></i> Delete
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-shop" style="font-size: 48px; color: #9ca3af;"></i>
                        <h5 class="mt-3 mb-2">No Restaurants Found</h5>
                        <p class="text-muted mb-4">No restaurants match your search criteria</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRestaurantModal">
                            <i class="bi bi-plus-circle"></i> Add First Restaurant
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Restaurant Modal -->
    <div class="modal fade" id="addRestaurantModal" tabindex="-1" aria-labelledby="addRestaurantModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addRestaurantModalLabel">
                        <i class="bi bi-plus-circle me-2"></i> Add New Restaurant
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Restaurant Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" required 
                                       placeholder="Enter restaurant name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" required 
                                       placeholder="Enter email address">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="password" required 
                                       placeholder="Enter password">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" name="phone" 
                                       placeholder="Enter phone number">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="2" 
                                          placeholder="Enter restaurant address"></textarea>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3" 
                                          placeholder="Enter restaurant description"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_restaurant" class="btn btn-success">
                            <i class="bi bi-check-circle me-2"></i> Add Restaurant
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const isMobile = window.innerWidth <= 768;
            
            if (isMobile && sidebar.classList.contains('active') && 
                !sidebar.contains(event.target) && 
                !event.target.closest('.navbar-toggler')) {
                sidebar.classList.remove('active');
            }
        });

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>