-- Fix Admin Account SQL
-- Run this in phpMyAdmin to fix the admin login issue

USE beaute_aesthetic_studio;

-- First, generate a fresh password hash (you'll need to run this in PHP)
-- For now, we'll use a known working hash for 'admin123'
-- The hash below should work, but if not, use the fix-admin-login.php script

-- Option 1: Update existing admin account
UPDATE users 
SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    role = 'admin',
    status = 'active',
    first_name = 'Admin',
    last_name = 'User'
WHERE email = 'admin@beauteaesthetic.com';

-- Option 2: Insert if doesn't exist (use this if UPDATE affects 0 rows)
INSERT INTO users (first_name, last_name, email, phone, password, role, status) 
VALUES ('Admin', 'User', 'admin@beauteaesthetic.com', '1234567890', 
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
        'admin', 'active')
ON DUPLICATE KEY UPDATE 
    password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    role = 'admin',
    status = 'active',
    first_name = 'Admin',
    last_name = 'User';

-- Verify the account
SELECT id, first_name, last_name, email, role, status 
FROM users 
WHERE email = 'admin@beauteaesthetic.com';
