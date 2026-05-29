-- Migration: Add password reset token columns
ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) NULL AFTER status;
ALTER TABLE users ADD COLUMN reset_expires DATETIME NULL AFTER reset_token;
