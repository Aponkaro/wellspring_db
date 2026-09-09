CREATE DATABASE IF NOT EXISTS wellspring_db;
USE wellspring_db;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('Admin','Manager','Staff','Technician','Billing Officer','Maintenance Officer','Assistant Admin','Customer') NOT NULL,
    user_type ENUM('staff','customer') DEFAULT 'staff',
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- User Sessions Table
CREATE TABLE IF NOT EXISTS user_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    token VARCHAR(255) NOT NULL,
    login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    logout_time DATETIME NULL,
    ip_address VARCHAR(45) NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- 2. Customers Table
CREATE TABLE IF NOT EXISTS customers (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20) NULL,
    email VARCHAR(100) NULL,
    address_line1 VARCHAR(255) NULL,
    address_line2 VARCHAR(255) NULL,
    city VARCHAR(50) NULL,
    state VARCHAR(50) NULL,
    postal_code VARCHAR(20) NULL,
    meter_number VARCHAR(50) UNIQUE NULL,
    connection_date DATE NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- 3. Water Supply & Distribution
CREATE TABLE IF NOT EXISTS supply_zones (
    zone_id INT AUTO_INCREMENT PRIMARY KEY,
    zone_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL
);

CREATE TABLE IF NOT EXISTS distribution_schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    zone_id INT,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    status ENUM('Pending','Scheduled','Ongoing','Completed','Cancelled') NOT NULL DEFAULT 'Scheduled',
    assigned_crew VARCHAR(100) NULL,
    FOREIGN KEY (zone_id) REFERENCES supply_zones(zone_id) ON DELETE CASCADE
);

-- 4. Meters & Meter Readings
CREATE TABLE IF NOT EXISTS meters (
    meter_id INT AUTO_INCREMENT PRIMARY KEY,
    meter_number VARCHAR(50) UNIQUE NOT NULL,
    customer_id INT,
    installation_date DATE NULL,
    meter_type VARCHAR(50) NULL,
    current_reading DECIMAL(10,3) DEFAULT 0.000,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS meter_readings (
    reading_id INT AUTO_INCREMENT PRIMARY KEY,
    meter_id INT,
    reading_value DECIMAL(10,3) NOT NULL,
    reading_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    reading_source ENUM('Manual','Smart','Estimated') NOT NULL DEFAULT 'Manual',
    notes TEXT NULL,
    FOREIGN KEY (meter_id) REFERENCES meters(meter_id) ON DELETE CASCADE
);

-- 5. Bills
CREATE TABLE IF NOT EXISTS bills (
    bill_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    meter_id INT NULL,
    billing_period_start DATE NOT NULL,
    billing_period_end DATE NOT NULL,
    previous_reading DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    current_reading DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    units_consumed DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    rate_per_unit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL,
    tax DECIMAL(10,2) DEFAULT 0.00,
    due_date DATE NOT NULL,
    status ENUM('Draft','Issued','Paid','Partially Paid','Overdue','Cancelled') DEFAULT 'Draft',
    issued_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE
);

-- 6. Payments
CREATE TABLE IF NOT EXISTS payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT,
    customer_id INT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    payment_method VARCHAR(50) NOT NULL,
    transaction_ref VARCHAR(100) NULL,
    balance_after_payment DECIMAL(10,2) DEFAULT 0.00,
    receipt_number VARCHAR(50) NULL,
    FOREIGN KEY (bill_id) REFERENCES bills(bill_id) ON DELETE CASCADE
);

-- 7. Inventory
CREATE TABLE IF NOT EXISTS suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) NULL
);

CREATE TABLE IF NOT EXISTS inventory_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NULL,
    quantity_in_stock INT DEFAULT 0,
    reorder_level INT NOT NULL,
    unit_price DECIMAL(10,2) NULL,
    supplier_id INT NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE SET NULL
);

-- 8. Fault Reports
CREATE TABLE IF NOT EXISTS fault_reports (
    fault_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NULL,
    description TEXT NOT NULL,
    priority ENUM('Low','Medium','High','Critical') NOT NULL DEFAULT 'Low',
    status ENUM('Reported','Assigned','In Progress','Resolved','Closed') DEFAULT 'Reported',
    reported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE SET NULL
);

-- 9. AI Chatbot Tables
CREATE TABLE IF NOT EXISTS chatbot_training_data (
    entry_id INT AUTO_INCREMENT PRIMARY KEY,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    category VARCHAR(50) NULL,
    is_active BOOLEAN DEFAULT TRUE
);

-- SEED DATA
INSERT INTO users (username, password_hash, email, full_name, role, user_type, is_active) 
VALUES ('Edmond', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'admin@wellspring.com', 'Edmond Admin', 'Admin', 'staff', 1)
ON DUPLICATE KEY UPDATE user_id=user_id;

INSERT INTO supply_zones (zone_name, description) VALUES ('North Zone', 'Northern Sector Water Lines') ON DUPLICATE KEY UPDATE zone_id=zone_id;
INSERT INTO suppliers (supplier_name, phone, email) VALUES ('AquaTech Supplies', '0555123456', 'sales@aquatech.com') ON DUPLICATE KEY UPDATE supplier_id=supplier_id;
INSERT INTO inventory_items (item_name, category, quantity_in_stock, reorder_level, unit_price, supplier_id) VALUES ('PVC Pipe 2-inch', 'Pipes', 120, 20, 15.50, 1) ON DUPLICATE KEY UPDATE item_id=item_id;
INSERT INTO chatbot_training_data (question, answer, category) VALUES ('how to pay bill', 'You can pay your bill via Mobile Money, Bank Transfer, or Cash at our local office.', 'billing') ON DUPLICATE KEY UPDATE entry_id=entry_id;