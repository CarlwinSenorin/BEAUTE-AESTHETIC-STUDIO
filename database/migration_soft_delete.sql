-- Recovery Bin - Soft Delete Migration
-- This adds soft delete functionality to appointments

-- Add soft delete columns to appointments table
ALTER TABLE appointments 
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL,
ADD COLUMN deleted_by INT NULL DEFAULT NULL,
ADD COLUMN deletion_reason TEXT NULL,
ADD INDEX idx_deleted_at (deleted_at),
ADD FOREIGN KEY (deleted_by) REFERENCES users(id);

-- Create index for faster queries on non-deleted appointments
CREATE INDEX idx_active_appointments ON appointments(deleted_at, appointment_date, staff_id);
