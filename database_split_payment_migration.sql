-- Split Payment Model Migration
-- Adds bank account and commission structure for restaurants

-- Add bank account fields to restaurants table
ALTER TABLE restaurants 
ADD COLUMN bank_name VARCHAR(100) AFTER description,
ADD COLUMN bank_code VARCHAR(10) AFTER bank_name,
ADD COLUMN account_number VARCHAR(20) AFTER bank_code,
ADD COLUMN account_name VARCHAR(100) AFTER account_number,
ADD COLUMN paystack_subaccount_code VARCHAR(100) AFTER account_name,
ADD COLUMN paystack_subaccount_id VARCHAR(100) AFTER paystack_subaccount_code;

-- Add commission tracking to orders table
ALTER TABLE orders
ADD COLUMN platform_commission DECIMAL(10, 2) DEFAULT 0 AFTER total_amount,
ADD COLUMN developer_commission DECIMAL(10, 2) DEFAULT 0 AFTER platform_commission,
ADD COLUMN restaurant_amount DECIMAL(10, 2) DEFAULT 0 AFTER developer_commission;

-- Create commission records table for tracking
CREATE TABLE commission_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    restaurant_id INT NOT NULL,
    order_amount DECIMAL(10, 2) NOT NULL,
    platform_commission DECIMAL(10, 2) NOT NULL,
    developer_commission DECIMAL(10, 2) NOT NULL,
    restaurant_amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('visa', 'mobile_money') NOT NULL,
    commission_type VARCHAR(50),
    status ENUM('pending', 'settled', 'failed') DEFAULT 'pending',
    settled_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
    INDEX idx_restaurant (restaurant_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);

-- Create developer commission summary view
CREATE VIEW developer_commission_summary AS
SELECT 
    DATE(created_at) as date,
    payment_method,
    COUNT(*) as total_orders,
    SUM(order_amount) as total_order_value,
    SUM(developer_commission) as total_developer_commission,
    SUM(platform_commission) as total_platform_commission,
    SUM(restaurant_amount) as total_restaurant_amount
FROM commission_records
WHERE status = 'settled'
GROUP BY DATE(created_at), payment_method;

-- Ghana banks reference data
CREATE TABLE ghana_banks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bank_name VARCHAR(100) NOT NULL,
    bank_code VARCHAR(10) NOT NULL UNIQUE,
    sort_code VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert Ghana banks
INSERT INTO ghana_banks (bank_name, bank_code) VALUES
('Access Bank Ghana', '280100'),
('Agricultural Development Bank', '080100'),
('ARB Apex Bank Limited', '340100'),
('Bank of Africa Ghana', '210100'),
('Bank of Baroda Ghana Limited', '250100'),
('Barclays Bank Ghana (Absa)', '030100'),
('CAL Bank Limited', '140100'),
('Consolidated Bank Ghana', '180100'),
('Ecobank Ghana Limited', '130100'),
('FBN Bank Ghana Limited', '290100'),
('Fidelity Bank Ghana Limited', '240100'),
('First Atlantic Bank Limited', '170100'),
('First National Bank Ghana Limited', '330100'),
('GCB Bank Limited', '040100'),
('Guaranty Trust Bank Ghana Limited', '230100'),
('National Investment Bank Limited', '050100'),
('OmniBSIC Bank Ghana Limited', '200100'),
('Prudential Bank Limited', '150100'),
('Republic Bank Ghana Limited', '090100'),
('Societe Generale Ghana Limited', '190100'),
('Stanbic Bank Ghana Limited', '190200'),
('Standard Chartered Bank Ghana Limited', '020100'),
('United Bank for Africa Ghana Limited', '060100'),
('Universal Merchant Bank Limited', '100100'),
('Zenith Bank Ghana Limited', '120100');

-- Create commission configuration table
CREATE TABLE commission_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payment_method ENUM('visa', 'mobile_money') NOT NULL,
    platform_percentage DECIMAL(5, 2) NOT NULL,
    developer_percentage DECIMAL(5, 2) NOT NULL,
    total_percentage DECIMAL(5, 2) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    effective_from DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default commission rates
-- Visa: 10% platform + 5% developer = 15% total
INSERT INTO commission_config (payment_method, platform_percentage, developer_percentage, total_percentage, effective_from) 
VALUES ('visa', 10.00, 5.00, 15.00, CURDATE());

-- Mobile Money: 10% platform + 1% developer = 11% total
INSERT INTO commission_config (payment_method, platform_percentage, developer_percentage, total_percentage, effective_from) 
VALUES ('mobile_money', 10.00, 1.00, 11.00, CURDATE());
