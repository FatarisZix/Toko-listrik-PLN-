CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50),
    email VARCHAR(100),
    password VARCHAR(255),
    full_name VARCHAR(100),
    role VARCHAR(50),
    created_at DATETIME
);

CREATE TABLE tariffs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tariff_name VARCHAR(100),
    price_per_kwh DECIMAL(10,2),
    admin_fee DECIMAL(10,2),
    description TEXT
);

CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    customer_number VARCHAR(50),
    name VARCHAR(100),
    address TEXT,
    tariff_type VARCHAR(50),
    power_capacity INT,
    phone VARCHAR(20),
    created_at DATETIME
);

CREATE TABLE bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    bill_month VARCHAR(20),
    kwh_usage INT,
    amount DECIMAL(10,2),
    admin_fee DECIMAL(10,2),
    total_amount DECIMAL(10,2),
    due_date DATE,
    admin_note TEXT,
    status VARCHAR(50),
    created_at DATETIME
);

CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT,
    user_id INT,
    payment_method VARCHAR(50),
    payment_amount DECIMAL(10,2),
    payment_date DATETIME,
    transaction_id VARCHAR(100),
    status VARCHAR(50)
);

CREATE TABLE meter_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    period_month VARCHAR(20),
    meter_reading INT,
    meter_start INT,
    meter_end INT,
    kwh_usage INT,
    user_note TEXT,
    admin_note TEXT,
    photo_path VARCHAR(255),
    report_date DATE,
    status VARCHAR(50),
    approved_by VARCHAR(100),
    approved_date DATE
);

CREATE TABLE usage_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    period_month VARCHAR(20),
    kwh_usage INT,
    meter_start INT,
    meter_end INT,
    created_at DATETIME
);
