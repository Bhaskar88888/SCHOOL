-- MySQL Initialization Script for EduGlass School ERP
-- This script is run automatically on first MySQL container startup

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS school_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user if not exists
CREATE USER IF NOT EXISTS 'school_erp_user'@'%' IDENTIFIED BY 'school_erp_password';

-- Grant privileges
GRANT ALL PRIVILEGES ON school_erp.* TO 'school_erp_user'@'%';

-- Flush privileges
FLUSH PRIVILEGES;
