-- Migration: Add 'reserved' to appointments status ENUM
ALTER TABLE appointments MODIFY COLUMN status ENUM('pending', 'reserved', 'confirmed', 'completed', 'cancelled', 'no_show') DEFAULT 'pending';
