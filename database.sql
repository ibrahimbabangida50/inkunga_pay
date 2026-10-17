CREATE DATABASE IF NOT EXISTS inkunga_pay CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE inkunga_pay;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(150) UNIQUE NOT NULL,
  phone VARCHAR(20) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  kyc_status ENUM('pending','verified','rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS campaigns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  slug VARCHAR(220) UNIQUE NOT NULL,
  category ENUM('medical','education','community','emergency','business','other') DEFAULT 'community',
  story TEXT NOT NULL,
  goal_amount DECIMAL(12,2) NOT NULL,
  raised_amount DECIMAL(12,2) DEFAULT 0.00,
  beneficiary_name VARCHAR(150),
  location VARCHAR(100) DEFAULT 'Rwanda',
  status ENUM('draft','pending','active','completed','blocked') DEFAULT 'pending',
  image VARCHAR(255) DEFAULT 'default.jpg',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS donations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  campaign_id INT NOT NULL,
  donor_name VARCHAR(100),
  donor_phone VARCHAR(20),
  amount DECIMAL(12,2) NOT NULL,
  payment_method ENUM('mtn_momo','airtel_money','card') DEFAULT 'mtn_momo',
  transaction_id VARCHAR(100),
  status ENUM('pending','success','failed') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
);