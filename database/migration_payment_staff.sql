-- Migration: Add payment tracking to appointments
-- Run this migration once: mysql -u root beaute_aesthetic_studio < database/migration_payment_staff.sql
-- Or run the SQL below in phpMyAdmin

USE beaute_aesthetic_studio;

-- Add payment columns to appointments (skip if already applied)
ALTER TABLE appointments 
ADD COLUMN payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending' AFTER status,
ADD COLUMN payment_method VARCHAR(50) DEFAULT 'pay_on_arrival' COMMENT 'pay_on_arrival, cash, card, gcash' AFTER payment_status;
