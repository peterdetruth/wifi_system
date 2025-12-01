-- Create database
CREATE DATABASE IF NOT EXISTS wifi_system CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE wifi_system;

-- Admins
CREATE TABLE admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('superadmin','admin') NOT NULL DEFAULT 'admin',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE admins ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;


-- Packages
CREATE TABLE packages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  type ENUM('hotspot','pppoe') NOT NULL,
  duration ENUM('daily','weekly','monthly') NOT NULL,-- delete this its irrelevant
  price DECIMAL(10,2) NOT NULL,
  devices INT NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE packages ENGINE=InnoDB;
ALTER TABLE packages ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE packages
ADD COLUMN duration_length INT DEFAULT 1 AFTER type,
ADD COLUMN duration_unit ENUM('minutes','hours','days','months') DEFAULT 'days' AFTER duration_length,
ADD COLUMN router_id INT NULL AFTER devices;

-- Add a name uniqueness constraint to avoid duplicate plans:
ALTER TABLE packages ADD UNIQUE (name, type, duration);
ALTER TABLE packages ADD UNIQUE KEY unique_package_name (name);
ALTER TABLE packages ADD COLUMN account_type ENUM('personal', 'business') NOT NULL;

-- Run the below at once
ALTER TABLE `packages`
ADD COLUMN `bandwidth_value` DECIMAL(10,2) DEFAULT NULL,
ADD COLUMN `bandwidth_unit` ENUM('Kbps','Mbps') DEFAULT 'Mbps',
ADD COLUMN `enable_burst` TINYINT(1) DEFAULT 0,
ADD COLUMN `upload_burst_rate_value` DECIMAL(10,2) DEFAULT NULL,
ADD COLUMN `upload_burst_rate_unit` ENUM('Kbps','Mbps') DEFAULT 'Mbps',
ADD COLUMN `download_burst_rate_value` DECIMAL(10,2) DEFAULT NULL,
ADD COLUMN `download_burst_rate_unit` ENUM('Kbps','Mbps') DEFAULT 'Mbps',
ADD COLUMN `upload_burst_threshold_value` DECIMAL(10,2) DEFAULT NULL,
ADD COLUMN `upload_burst_threshold_unit` ENUM('Kbps','Mbps') DEFAULT 'Mbps',
ADD COLUMN `download_burst_threshold_value` DECIMAL(10,2) DEFAULT NULL,
ADD COLUMN `download_burst_threshold_unit` ENUM('Kbps','Mbps') DEFAULT 'Mbps',
ADD COLUMN `upload_burst_time` INT DEFAULT NULL,
ADD COLUMN `download_burst_time` INT DEFAULT NULL,
ADD COLUMN `hotspot_plan_type` ENUM('unlimited','data') DEFAULT NULL,
ADD COLUMN `hotspot_devices` INT DEFAULT NULL;

ALTER TABLE `packages`
-- Bandwidth
ADD COLUMN `bandwidth_value` INT(11) NULL DEFAULT NULL,
ADD COLUMN `bandwidth_unit` ENUM('Kbps','Mbps') NULL DEFAULT 'Mbps',

-- Burst toggle
ADD COLUMN `burst_enabled` TINYINT(1) NOT NULL DEFAULT 0,

-- Burst rates & thresholds
ADD COLUMN `upload_burst_rate_value` INT(11) NULL DEFAULT NULL,
ADD COLUMN `upload_burst_rate_unit` ENUM('Kbps','Mbps') NULL DEFAULT 'Mbps',
ADD COLUMN `download_burst_rate_value` INT(11) NULL DEFAULT NULL,
ADD COLUMN `download_burst_rate_unit` ENUM('Kbps','Mbps') NULL DEFAULT 'Mbps',

ADD COLUMN `upload_burst_threshold_value` INT(11) NULL DEFAULT NULL,
ADD COLUMN `upload_burst_threshold_unit` ENUM('Kbps','Mbps') NULL DEFAULT 'Mbps',
ADD COLUMN `download_burst_threshold_value` INT(11) NULL DEFAULT NULL,
ADD COLUMN `download_burst_threshold_unit` ENUM('Kbps','Mbps') NULL DEFAULT 'Mbps',

ADD COLUMN `upload_burst_time` INT(11) NULL DEFAULT NULL COMMENT 'seconds',
ADD COLUMN `download_burst_time` INT(11) NULL DEFAULT NULL COMMENT 'seconds',

-- Hotspot
ADD COLUMN `hotspot_plan_type` ENUM('Unlimited','Data Plans') NULL DEFAULT NULL,
ADD COLUMN `hotspot_device_limit` INT(11) NULL DEFAULT NULL;

UPDATE `packages`
SET
  upload_burst_rate_value = NULL,
  upload_burst_rate_unit = NULL,
  download_burst_rate_value = NULL,
  download_burst_rate_unit = NULL,
  upload_burst_threshold_value = NULL,
  upload_burst_threshold_unit = NULL,
  download_burst_threshold_value = NULL,
  download_burst_threshold_unit = NULL,
  upload_burst_time = NULL,
  download_burst_time = NULL
WHERE burst_enabled = 0;

ALTER TABLE `packages`
ADD COLUMN `validity_days` INT(11) NOT NULL DEFAULT 7 AFTER `price`;

UPDATE packages
SET validity_days = 
    CASE duration_unit
        WHEN 'day' THEN duration_length
        WHEN 'days' THEN duration_length
        WHEN 'week' THEN duration_length * 7
        WHEN 'weeks' THEN duration_length * 7
        WHEN 'month' THEN duration_length * 30
        WHEN 'months' THEN duration_length * 30
        WHEN 'year' THEN duration_length * 365
        WHEN 'years' THEN duration_length * 365
        ELSE 7
    END;
    

-- Clients (customers)
CREATE TABLE clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(200) NOT NULL,
  username VARCHAR(100) NOT NULL UNIQUE,
  account_type ENUM('personal','business') DEFAULT 'personal',
  email VARCHAR(150),
  password VARCHAR(255) NOT NULL,
  phone VARCHAR(20),
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE clients ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE clients ADD INDEX (phone);
ALTER TABLE clients ENGINE=InnoDB;


-- Routers
CREATE TABLE routers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  status ENUM('active','inactive') DEFAULT 'active',
  last_seen DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE routers ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
-- Maybe index ip_address:
ALTER TABLE routers ADD UNIQUE (ip_address);

-- Vouchers / Access Codes
CREATE TABLE `vouchers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `package_id` INT NOT NULL,
  `client_id` INT NULL,
  `expires_on` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE vouchers
ADD COLUMN purpose VARCHAR(50) AFTER code,
ADD COLUMN router_id INT AFTER purpose,
ADD COLUMN phone VARCHAR(20) AFTER package_id,
ADD COLUMN status ENUM('unused','used','expired') DEFAULT 'unused' AFTER phone,
ADD COLUMN expires_on DATETIME NULL AFTER status,
ADD COLUMN used_by_client_id INT NULL AFTER expires_on,
ADD COLUMN used_at DATETIME NULL AFTER used_by_client_id,
ADD COLUMN created_by INT AFTER used_at;

UPDATE vouchers v
JOIN packages p ON p.id = v.package_id
SET v.expires_on = 
  CASE
    WHEN p.duration_unit IN ('minute','minutes') THEN DATE_ADD(v.created_at, INTERVAL p.duration_length MINUTE)
    WHEN p.duration_unit IN ('hour','hours') THEN DATE_ADD(v.created_at, INTERVAL p.duration_length HOUR)
    WHEN p.duration_unit IN ('day','days') THEN DATE_ADD(v.created_at, INTERVAL p.duration_length DAY)
    WHEN p.duration_unit IN ('week','weeks') THEN DATE_ADD(v.created_at, INTERVAL (p.duration_length * 7) DAY)
    WHEN p.duration_unit IN ('month','months') THEN DATE_ADD(v.created_at, INTERVAL p.duration_length MONTH)
    ELSE DATE_ADD(v.created_at, INTERVAL 7 DAY)
  END
WHERE v.expires_on IS NULL;


-- Payment transactions (user purchases)
CREATE TABLE transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NULL,
  client_username VARCHAR(100) NULL,
  client_fullname VARCHAR(200) NULL,
  package_type ENUM('hotspot','pppoe'),
  package_length VARCHAR(50), -- e.g. '2 hours' or '30 days'
  package_id INT NULL,
  created_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_on DATETIME NULL,
  method ENUM('mpesa') DEFAULT 'mpesa',
  mpesa_code VARCHAR(100) NULL,
  router_id INT NULL,
  router_status ENUM('active','inactive') DEFAULT 'active',
  online_status ENUM('online','offline') DEFAULT 'offline',
  status ENUM('pending','success','failed') DEFAULT 'pending',
  amount DECIMAL(10,2) DEFAULT 0,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
  FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE SET NULL,
  FOREIGN KEY (router_id) REFERENCES routers(id) ON DELETE SET NULL
);
-- Drop redundant text columns (client_username, client_fullname) — they’re already available from the clients table.
-- Ensure you cascade deletes only when safe
ALTER TABLE transactions
  DROP COLUMN client_username,
  DROP COLUMN client_fullname;
-- Then modify foreign keys to maintain consistency:
-- That way, if a client is deleted, their old transactions go too.
ALTER TABLE transactions
  DROP FOREIGN KEY transactions_ibfk_1,
  ADD CONSTRAINT fk_transactions_client
    FOREIGN KEY (client_id) REFERENCES clients(id)
    ON DELETE CASCADE;


-- Mpesa transaction log
CREATE TABLE mpesa_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_id VARCHAR(100) DEFAULT NULL,
    merchant_request_id VARCHAR(100) DEFAULT NULL,
    checkout_request_id VARCHAR(100) NOT NULL UNIQUE,
    amount DECIMAL(10,2) DEFAULT NULL,
    mpesa_receipt_number VARCHAR(50) DEFAULT NULL,
    phone_number VARCHAR(20) DEFAULT NULL,
    transaction_date DATETIME DEFAULT NULL,
    result_code INT DEFAULT NULL,
    result_desc VARCHAR(255) DEFAULT NULL,
    status ENUM('Pending','Success','Failed') DEFAULT 'Pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE mpesa_transactions ADD COLUMN mpesa_receipt VARCHAR(50) GENERATED ALWAYS AS (mpesa_receipt_number) VIRTUAL;


-- Subscribers view helper table (optional) - we'll infer active/expired from transactions
-- Insert a sample superadmin (password is '123456' - hashed)
INSERT INTO admins (username, email, password, role) VALUES (
  'Super Admin',
  'super@wifi.com',
  -- replace the hash below by running php -r "echo password_hash('123456', PASSWORD_DEFAULT);"
  '$2y$10$nQZi6jUbi62Ukmrtjv/KpObEeW5psXw4swSfUW2ealU8LGq4JO1LW',
  'superadmin'
);

-- Step 1 — Create Migrations
-- Run these commands inside your project root (C:\xampp\htdocs\wifi_system)

-- php spark make:migration CreateAdminsTable
-- php spark make:migration CreatePackagesTable
-- php spark make:migration CreateClientsTable
-- php spark make:migration CreateRoutersTable
-- php spark make:migration CreateVouchersTable
-- php spark make:migration CreateTransactionsTable
-- php spark make:migration CreateMpesaTransactionsTable

-- Create an Auth Filter (to protect admin pages)
-- Run this command:
-- php spark make:filter AuthFilter

-- SHOW CREATE TABLE admins\G
-- SHOW CREATE TABLE clients\G
-- SHOW CREATE TABLE packages\G
-- SHOW CREATE TABLE routers\G
-- SHOW CREATE TABLE vouchers\G
-- SHOW CREATE TABLE transactions\G
-- SHOW CREATE TABLE mpesa_transactions\G

-- below is a clean SQL migration script that safely improves your current schema while preserving all existing data.
-- You can run this directly in phpMyAdmin, the MySQL console, or even convert it into a CodeIgniter migration file later.
-- ==============================================
-- WiFi System Database Schema Enhancement Script
-- ==============================================
-- Run this after confirming your current schema matches
-- the one you shared earlier.
-- ==============================================
-- This script won’t delete any existing data.

-- It adds missing foreign keys, indexes, and unique constraints.

-- It fixes future referential integrity issues (like deleting a client who has transactions).

-- It aligns your schema for better performance and cleaner joins later.

-- 🚀 To run it:

-- Open phpMyAdmin.

-- Select your database (wifi_system).

-- Go to the SQL tab.

-- Paste the full script above.

-- Click Go ✅

-- 1️⃣  CLIENTS TABLE: add index for phone
ALTER TABLE clients
  ADD INDEX idx_phone (phone);

-- 2️⃣  PACKAGES TABLE: ensure uniqueness across name, type, and duration
ALTER TABLE packages
  ADD UNIQUE uq_package_name_type_duration (name, type, duration);

-- 3️⃣  ROUTERS TABLE: make IP addresses unique
ALTER TABLE routers
  ADD UNIQUE uq_router_ip (ip_address);

-- 4️⃣  VOUCHERS TABLE: add foreign keys for creator and used_by
-- ✅ FIXED VOUCHERS FOREIGN KEYS (unique constraint names)
ALTER TABLE vouchers
  ADD CONSTRAINT fk_vouchers_created_by_admin
    FOREIGN KEY (created_by) REFERENCES admins(id)
    ON DELETE SET NULL,
  ADD CONSTRAINT fk_vouchers_used_by_client
    FOREIGN KEY (used_by_client_id) REFERENCES clients(id)
    ON DELETE SET NULL;


-- 5️⃣  TRANSACTIONS TABLE CLEANUP
-- Remove redundant client fields
ALTER TABLE transactions
  DROP COLUMN client_username,
  DROP COLUMN client_fullname;

-- Drop old client foreign key (if exists)
ALTER TABLE transactions
  DROP FOREIGN KEY transactions_ibfk_1;

-- Recreate with CASCADE delete for clients
ALTER TABLE transactions
  ADD CONSTRAINT fk_transactions_client
    FOREIGN KEY (client_id) REFERENCES clients(id)
    ON DELETE CASCADE;

-- (Optional but useful) Make sure packages and routers stay consistent
ALTER TABLE transactions
  DROP FOREIGN KEY IF EXISTS transactions_ibfk_2,
  DROP FOREIGN KEY IF EXISTS transactions_ibfk_3;

ALTER TABLE transactions
  ADD CONSTRAINT fk_transactions_package
    FOREIGN KEY (package_id) REFERENCES packages(id)
    ON DELETE SET NULL,
  ADD CONSTRAINT fk_transactions_router
    FOREIGN KEY (router_id) REFERENCES routers(id)
    ON DELETE SET NULL;

-- 6️⃣  MPESA TRANSACTIONS TABLE: add relationships + unique constraint
ALTER TABLE mpesa_transactions
  ADD CONSTRAINT fk_mpesa_client
    FOREIGN KEY (client_id) REFERENCES clients(id)
    ON DELETE SET NULL,
  ADD CONSTRAINT fk_mpesa_package
    FOREIGN KEY (package_id) REFERENCES packages(id)
    ON DELETE SET NULL,
  ADD UNIQUE uq_transaction_id (transaction_id);

-- ==============================================
-- ✅ Done! Schema is now more robust and relationally sound.
-- ==============================================




-- 4️⃣  VOUCHERS TABLE: add foreign keys for creator and used_byALTER TABLE vouchers  ADD CONSTRAINT fk_voucher_created_by    FOREIGN KEY (created_by) REFERENCES admins(id)    ON DELETE SET NULL,  ADD CONSTRAINT fk_voucher_used_by    FOREIGN KEY (used_by_client_id) REFERENCES clients(id)    ON DELETE SET NULL;

CREATE TABLE subscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  package_id INT NOT NULL,
  router_id INT DEFAULT NULL,
  start_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  end_date DATETIME NOT NULL,
  status ENUM('active', 'expired', 'cancelled') DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_sub_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_sub_package FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE,
  CONSTRAINT fk_sub_router FOREIGN KEY (router_id) REFERENCES routers(id) ON DELETE SET NULL
);
ALTER TABLE subscriptions ADD COLUMN expires_on DATETIME NOT NULL;

CREATE TABLE mpesa_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    raw_callback TEXT,
    created_at DATETIME
);

CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mpesa_transaction_id INT UNSIGNED,
    client_id INT UNSIGNED,
    package_id INT UNSIGNED,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('mpesa', 'cash', 'card', 'bank') DEFAULT 'mpesa',
    status ENUM('pending', 'completed', 'failed') DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mpesa_transaction_id) REFERENCES mpesa_transactions(id) ON DELETE SET NULL ON UPDATE CASCADE
);
ALTER TABLE payments 
ADD COLUMN mpesa_receipt_number VARCHAR(50);

ALTER TABLE payments 
ADD COLUMN phone VARCHAR(50);

ALTER TABLE payments 
ADD COLUMN transaction_date DATETIME NOT NULL;


CREATE TABLE IF NOT EXISTS client_packages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    package_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) DEFAULT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    status ENUM('active','expired','pending') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE `feature_requests` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `status` ENUM('pending', 'complete') NOT NULL DEFAULT 'pending',
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`)
);

-- NEW UPDATES
ALTER TABLE mpesa_transactions
MODIFY COLUMN status ENUM('pending','success','failed') DEFAULT 'pending';

INSERT INTO payments (
    mpesa_transaction_id,
    client_id,
    package_id,
    amount,
    payment_method,
    status,
    mpesa_receipt_number,
    phone,
    transaction_date,
    mpesa_receipt
) VALUES (
    999999,         -- fake mpesa_transaction_id
    1,              -- CHANGE THIS to your client_id from session()
    3,              -- CHANGE THIS to the package you want to test
    200.00,         -- CHANGE THIS to the correct package price
    'mpesa',
    'failed',       -- this is key for reconnect
    'TKK00APFAV',   -- this is the reconnect code you will test
    '0700000000',   -- fake phone
    NOW(),
    'TKK00APFAV'    -- duplicate for mpesa_receipt (your system uses both)
);

UPDATE payments
SET status = 'failed'
WHERE mpesa_receipt_number = 'TKRQSBBVPA';

CREATE TABLE system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_type VARCHAR(50),
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


