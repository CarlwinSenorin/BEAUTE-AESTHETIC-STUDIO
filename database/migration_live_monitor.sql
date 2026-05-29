-- Live Monitor Enhancements
-- Adds support for real-time treatment tracking

USE beaute_aesthetic_studio;

-- Update appointments status enum to include 'in_progress'
ALTER TABLE appointments 
MODIFY COLUMN status ENUM('pending', 'confirmed', 'completed', 'cancelled', 'no_show', 'in_progress') DEFAULT 'pending';

-- Add checked_in_at timestamp to track when treatment started
-- This enables accurate progress bars
ALTER TABLE appointments
ADD COLUMN IF NOT EXISTS checked_in_at TIMESTAMP NULL AFTER appointment_time;

SELECT 'Live monitor migration completed successfully' as status;
