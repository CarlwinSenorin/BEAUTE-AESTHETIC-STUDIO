-- Security Enhancement Migration
-- Adds login_attempts table for rate limiting

USE beaute_aesthetic_studio;

-- Create login_attempts table for rate limiting
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    identifier VARCHAR(255) NOT NULL COMMENT 'Email or IP address',
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success BOOLEAN DEFAULT FALSE,
    ip_address VARCHAR(45) NULL COMMENT 'IPv4 or IPv6 address',
    user_agent VARCHAR(500) NULL COMMENT 'Browser user agent',
    INDEX idx_identifier (identifier),
    INDEX idx_attempt_time (attempt_time),
    INDEX idx_success (success)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes to existing tables for better performance
ALTER TABLE appointments 
    ADD INDEX IF NOT EXISTS idx_appointment_date (appointment_date),
    ADD INDEX IF NOT EXISTS idx_status (status),
    ADD INDEX IF NOT EXISTS idx_user_id (user_id);

ALTER TABLE services 
    ADD INDEX IF NOT EXISTS idx_category (category),
    ADD INDEX IF NOT EXISTS idx_status (status);

ALTER TABLE users 
    ADD INDEX IF NOT EXISTS idx_email (email),
    ADD INDEX IF NOT EXISTS idx_role (role);

-- Add session tracking columns to users table (optional)
ALTER TABLE users 
    ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS last_ip VARCHAR(45) NULL;

SELECT 'Security migration completed successfully' as status;
