-- Cash Payment and Waiting Number Migration

-- Update payment_method enum to include cash
ALTER TABLE orders 
MODIFY COLUMN payment_method ENUM('visa', 'mobile_money', 'cash') NOT NULL;

ALTER TABLE payments
MODIFY COLUMN payment_method ENUM('visa', 'mobile_money', 'cash') NOT NULL;

ALTER TABLE commission_records
MODIFY COLUMN payment_method ENUM('visa', 'mobile_money', 'cash') NOT NULL;

-- Add waiting number to orders
ALTER TABLE orders
ADD COLUMN waiting_number VARCHAR(10) AFTER order_number,
ADD COLUMN is_printed BOOLEAN DEFAULT FALSE AFTER waiting_number,
ADD UNIQUE INDEX idx_waiting_number (restaurant_id, waiting_number, DATE(created_at));

-- Create waiting number sequences table
CREATE TABLE waiting_number_sequences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    restaurant_id INT NOT NULL,
    date DATE NOT NULL,
    last_number INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_restaurant_date (restaurant_id, date),
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
);

-- Update commission config to include cash (0% commission)
INSERT INTO commission_config (payment_method, platform_percentage, developer_percentage, total_percentage, effective_from) 
VALUES ('cash', 0.00, 0.00, 0.00, CURDATE());

-- Create receipts table for tracking printed receipts
CREATE TABLE receipts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    restaurant_id INT NOT NULL,
    receipt_number VARCHAR(50) NOT NULL UNIQUE,
    receipt_type ENUM('customer', 'restaurant', 'kitchen') NOT NULL,
    printed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    printed_by INT,
    ip_address VARCHAR(45),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
    INDEX idx_order (order_id),
    INDEX idx_receipt_number (receipt_number),
    INDEX idx_printed_at (printed_at)
);
