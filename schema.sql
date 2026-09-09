-- PostgreSQL Version of WellSpring Database Schema

-- 1. Custom ENUM Types
DO $$ BEGIN
    CREATE TYPE user_role AS ENUM ('Admin','Manager','Staff','Technician','Billing Officer','Maintenance Officer','Assistant Admin','Customer');
EXCEPTION WHEN duplicate_object THEN null; END $$;

DO $$ BEGIN
    CREATE TYPE user_type_enum AS ENUM ('staff','customer');
EXCEPTION WHEN duplicate_object THEN null; END $$;

DO $$ BEGIN
    CREATE TYPE schedule_status AS ENUM ('Pending','Scheduled','Ongoing','Completed','Cancelled');
EXCEPTION WHEN duplicate_object THEN null; END $$;

DO $$ BEGIN
    CREATE TYPE reading_source_enum AS ENUM ('Manual','Smart','Estimated');
EXCEPTION WHEN duplicate_object THEN null; END $$;

DO $$ BEGIN
    CREATE TYPE bill_status AS ENUM ('Draft','Issued','Paid','Partially Paid','Overdue','Cancelled');
EXCEPTION WHEN duplicate_object THEN null; END $$;

DO $$ BEGIN
    CREATE TYPE priority_level AS ENUM ('Low','Medium','High','Critical');
EXCEPTION WHEN duplicate_object THEN null; END $$;

DO $$ BEGIN
    CREATE TYPE fault_status AS ENUM ('Reported','Assigned','In Progress','Resolved','Closed');
EXCEPTION WHEN duplicate_object THEN null; END $$;


-- 2. Users Table
CREATE TABLE IF NOT EXISTS users (
    user_id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    role user_role NOT NULL,
    user_type user_type_enum DEFAULT 'staff',
    is_active INT DEFAULT 1,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User Sessions Table
CREATE TABLE IF NOT EXISTS user_sessions (
    session_id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(user_id) ON DELETE CASCADE,
    token VARCHAR(255) NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    logout_time TIMESTAMP NULL,
    ip_address VARCHAR(45) NULL
);

-- 3. Customers Table
CREATE TABLE IF NOT EXISTS customers (
    customer_id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(user_id) ON DELETE SET NULL,
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
    connection_date DATE DEFAULT CURRENT_DATE,
    is_active INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL
);

-- 4. Water Supply & Distribution
CREATE TABLE IF NOT EXISTS supply_zones (
    zone_id SERIAL PRIMARY KEY,
    zone_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL
);

CREATE TABLE IF NOT EXISTS distribution_schedules (
    schedule_id SERIAL PRIMARY KEY,
    zone_id INT REFERENCES supply_zones(zone_id) ON DELETE CASCADE,
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NOT NULL,
    status schedule_status NOT NULL DEFAULT 'Scheduled',
    assigned_crew VARCHAR(100) NULL
);

-- 5. Meters & Meter Readings
CREATE TABLE IF NOT EXISTS meters (
    meter_id SERIAL PRIMARY KEY,
    meter_number VARCHAR(50) UNIQUE NOT NULL,
    customer_id INT REFERENCES customers(customer_id) ON DELETE CASCADE,
    installation_date DATE NULL,
    meter_type VARCHAR(50) NULL,
    current_reading NUMERIC(10,3) DEFAULT 0.000
);

CREATE TABLE IF NOT EXISTS meter_readings (
    reading_id SERIAL PRIMARY KEY,
    meter_id INT REFERENCES meters(meter_id) ON DELETE CASCADE,
    reading_value NUMERIC(10,3) NOT NULL,
    reading_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reading_source reading_source_enum NOT NULL DEFAULT 'Manual',
    notes TEXT NULL
);

-- 6. Bills Table
CREATE TABLE IF NOT EXISTS bills (
    bill_id SERIAL PRIMARY KEY,
    customer_id INT REFERENCES customers(customer_id) ON DELETE CASCADE,
    meter_id INT NULL,
    billing_period_start DATE NOT NULL,
    billing_period_end DATE NOT NULL,
    previous_reading NUMERIC(10,3) NOT NULL DEFAULT 0.000,
    current_reading NUMERIC(10,3) NOT NULL DEFAULT 0.000,
    units_consumed NUMERIC(10,3) NOT NULL DEFAULT 0.000,
    rate_per_unit NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    total_amount NUMERIC(10,2) NOT NULL,
    tax NUMERIC(10,2) DEFAULT 0.00,
    due_date DATE NOT NULL,
    status bill_status DEFAULT 'Draft',
    issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. Payments Table
CREATE TABLE IF NOT EXISTS payments (
    payment_id SERIAL PRIMARY KEY,
    bill_id INT REFERENCES bills(bill_id) ON DELETE CASCADE,
    customer_id INT NULL,
    amount_paid NUMERIC(10,2) NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_method VARCHAR(50) NOT NULL,
    transaction_ref VARCHAR(100) NULL,
    balance_after_payment NUMERIC(10,2) DEFAULT 0.00,
    receipt_number VARCHAR(50) NULL
);

-- 8. Inventory System
CREATE TABLE IF NOT EXISTS suppliers (
    supplier_id SERIAL PRIMARY KEY,
    supplier_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) NULL
);

CREATE TABLE IF NOT EXISTS inventory_items (
    item_id SERIAL PRIMARY KEY,
    item_name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NULL,
    quantity_in_stock INT DEFAULT 0,
    reorder_level INT NOT NULL,
    unit_price NUMERIC(10,2) NULL,
    supplier_id INT REFERENCES suppliers(supplier_id) ON DELETE SET NULL
);

-- 9. Fault Reports
CREATE TABLE IF NOT EXISTS fault_reports (
    fault_id SERIAL PRIMARY KEY,
    customer_id INT REFERENCES customers(customer_id) ON DELETE SET NULL,
    description TEXT NOT NULL,
    priority priority_level NOT NULL DEFAULT 'Low',
    status fault_status DEFAULT 'Reported',
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 10. AI Chatbot
CREATE TABLE IF NOT EXISTS chatbot_training_data (
    entry_id SERIAL PRIMARY KEY,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    category VARCHAR(50) NULL,
    is_active INT DEFAULT 1
);

-- SEED INITIAL DATA
INSERT INTO users (username, password_hash, email, full_name, role, user_type, is_active) 
VALUES ('Edmond', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'admin@wellspring.com', 'Edmond Admin', 'Admin', 'staff', 1)
ON CONFLICT (username) DO NOTHING;

INSERT INTO supply_zones (zone_name, description) 
VALUES ('North Zone', 'Northern Sector Water Lines') 
ON CONFLICT (zone_name) DO NOTHING;

INSERT INTO suppliers (supplier_name, phone, email) 
VALUES ('AquaTech Supplies', '0555123456', 'sales@aquatech.com') 
ON CONFLICT DO NOTHING;

INSERT INTO chatbot_training_data (question, answer, category) 
VALUES ('how to pay bill', 'You can pay your bill via Mobile Money, Bank Transfer, or Cash at our local office.', 'billing') 
ON CONFLICT DO NOTHING;
