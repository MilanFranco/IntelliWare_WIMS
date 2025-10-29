CREATE DATABASE warehouse_system;

USE warehouse_system;

-- Users
CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','manager','staff') NOT NULL,
  full_name VARCHAR(100),
  department VARCHAR(100),
  employee_id VARCHAR(50),
  email VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products
CREATE TABLE products (
  product_id INT AUTO_INCREMENT PRIMARY KEY,
  product_name VARCHAR(100) NOT NULL,
  category VARCHAR(50),
  supplier VARCHAR(100),
  quantity INT DEFAULT 0,
  reorder_point INT DEFAULT 10,
  unit VARCHAR(20),
  expiry_date DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Transactions
CREATE TABLE transactions (
  transaction_id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT,
  user_id INT,
  transaction_type ENUM('stock_in','stock_out') NOT NULL,
  quantity INT NOT NULL,
  transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  remarks VARCHAR(255),
  FOREIGN KEY (product_id) REFERENCES products(product_id),
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Logs
CREATE TABLE logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  action VARCHAR(255),
  timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Patterns (AI/Data Mining Results)
CREATE TABLE patterns (
  pattern_id INT AUTO_INCREMENT PRIMARY KEY,
  description VARCHAR(255),
  confidence DECIMAL(5,2),
  support DECIMAL(5,2),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Forecast Results
CREATE TABLE forecast (
  forecast_id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT,
  forecast_date DATE,
  predicted_qty INT,
  model_used VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(product_id)
);


