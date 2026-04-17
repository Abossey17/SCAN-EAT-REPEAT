-- QR Restaurant Management System Database Schema

CREATE DATABASE IF NOT EXISTS qr_restaurant_system;
USE qr_restaurant_system;

-- Admin table
CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Restaurants table
CREATE TABLE restaurants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    description TEXT,
    logo VARCHAR(255),
    status ENUM('active', 'suspended', 'deleted') DEFAULT 'active',
    qr_code VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_email (email)
);

-- Categories table
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    restaurant_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    display_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
    INDEX idx_restaurant (restaurant_id)
);

-- Menu items table
CREATE TABLE menu_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    restaurant_id INT NOT NULL,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    image VARCHAR(255),
    status ENUM('available', 'unavailable') DEFAULT 'available',
    is_featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_restaurant (restaurant_id),
    INDEX idx_category (category_id),
    INDEX idx_status (status)
);

-- Orders table
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    restaurant_id INT NOT NULL,
    order_number VARCHAR(20) UNIQUE NOT NULL,
    customer_name VARCHAR(100),
    customer_phone VARCHAR(20),
    order_type ENUM('dine-in', 'takeaway') NOT NULL,
    table_number VARCHAR(20),
    total_amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('visa', 'mobile_money') NOT NULL,
    payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    payment_reference VARCHAR(100),
    order_status ENUM('pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
    INDEX idx_restaurant (restaurant_id),
    INDEX idx_order_number (order_number),
    INDEX idx_created_at (created_at),
    INDEX idx_payment_status (payment_status)
);

-- Order items table
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    menu_item_id INT NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    item_price DECIMAL(10, 2) NOT NULL,
    quantity INT NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    special_instructions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE RESTRICT,
    INDEX idx_order (order_id)
);

-- Payments table
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('visa', 'mobile_money') NOT NULL,
    transaction_id VARCHAR(100),
    mobile_money_number VARCHAR(20),
    mobile_money_network VARCHAR(50),
    card_last_four VARCHAR(4),
    status ENUM('pending', 'success', 'failed', 'refunded') DEFAULT 'pending',
    response_data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order (order_id),
    INDEX idx_transaction (transaction_id)
);

-- Insert default admin
INSERT INTO admins (username, email, password, full_name) 
VALUES ('admin', 'admin@restaurant.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator');
-- Default password is 'password' - CHANGE THIS IN PRODUCTION!

-- Create views for reporting
CREATE VIEW daily_sales AS
SELECT 
    restaurant_id,
    DATE(created_at) as sale_date,
    COUNT(*) as total_orders,
    SUM(total_amount) as total_revenue,
    SUM(CASE WHEN order_type = 'dine-in' THEN total_amount ELSE 0 END) as dine_in_revenue,
    SUM(CASE WHEN order_type = 'takeaway' THEN total_amount ELSE 0 END) as takeaway_revenue
FROM orders
WHERE payment_status = 'completed'
GROUP BY restaurant_id, DATE(created_at);

CREATE VIEW weekly_sales AS
SELECT 
    restaurant_id,
    YEARWEEK(created_at) as week_year,
    COUNT(*) as total_orders,
    SUM(total_amount) as total_revenue
FROM orders
WHERE payment_status = 'completed'
GROUP BY restaurant_id, YEARWEEK(created_at);

CREATE VIEW monthly_sales AS
SELECT 
    restaurant_id,
    DATE_FORMAT(created_at, '%Y-%m') as month_year,
    COUNT(*) as total_orders,
    SUM(total_amount) as total_revenue
FROM orders
WHERE payment_status = 'completed'
GROUP BY restaurant_id, DATE_FORMAT(created_at, '%Y-%m');

CREATE VIEW yearly_sales AS
SELECT 
    restaurant_id,
    YEAR(created_at) as year,
    COUNT(*) as total_orders,
    SUM(total_amount) as total_revenue
FROM orders
WHERE payment_status = 'completed'
GROUP BY restaurant_id, YEAR(created_at);