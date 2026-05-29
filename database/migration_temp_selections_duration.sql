-- Add duration column to temporary_selections for proper overlap detection
ALTER TABLE temporary_selections 
    ADD COLUMN IF NOT EXISTS duration INT NOT NULL DEFAULT 60 AFTER appointment_time,
    ADD COLUMN IF NOT EXISTS identifier VARCHAR(255) NULL AFTER duration;

-- Re-add index on identifier if it doesn't already exist
ALTER TABLE temporary_selections 
    ADD INDEX IF NOT EXISTS idx_identifier (identifier);
