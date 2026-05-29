-- Migration: New Features (Inventory, Peak-Hour Pricing, SMS API)
-- Run this after existing schema is in place

USE beaute_aesthetic_studio;

-- ============================================
-- 1. Inventory Table
-- ============================================
CREATE TABLE IF NOT EXISTS inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'General',
    quantity INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 5,
    unit VARCHAR(50) NOT NULL DEFAULT 'pcs',
    cost_per_unit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    linked_service_id INT DEFAULT NULL,
    status ENUM('in_stock', 'low_stock', 'out_of_stock') DEFAULT 'in_stock',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (linked_service_id) REFERENCES services(id) ON DELETE SET NULL
);

-- ============================================
-- 2. Inventory Log Table
-- ============================================
CREATE TABLE IF NOT EXISTS inventory_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    inventory_id INT NOT NULL,
    change_type ENUM('restock', 'deduct', 'adjust') NOT NULL,
    quantity_change INT NOT NULL,
    quantity_after INT NOT NULL DEFAULT 0,
    notes TEXT,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================
-- 3. New Settings (Peak-Hour Pricing & SMS API)
-- ============================================
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('peak_hour_start', '11:00'),
('peak_hour_end', '14:00'),
('peak_hour_surcharge', '10'),
('sms_api_key', ''),
('sms_sender_name', 'BeauteStudio'),
('sms_from_number', ''),
('follow_up_hours_after', '2');

-- ============================================
-- 4. Add follow_up_sent column to appointments if not exists
-- ============================================
ALTER TABLE appointments ADD COLUMN IF NOT EXISTS follow_up_sent BOOLEAN DEFAULT FALSE;
