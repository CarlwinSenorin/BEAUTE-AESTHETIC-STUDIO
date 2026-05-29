-- Migration: Add identifier and duration columns to temporary_selections table
-- These columns support per-service temp selection tracking and duration-aware overlap checking

ALTER TABLE temporary_selections 
ADD COLUMN IF NOT EXISTS identifier VARCHAR(255) DEFAULT NULL;

ALTER TABLE temporary_selections 
ADD COLUMN IF NOT EXISTS duration INT DEFAULT 60;

-- Add index on identifier for faster lookups
ALTER TABLE temporary_selections 
ADD INDEX IF NOT EXISTS idx_identifier (identifier);
