-- Migration Script: Update Service Categories
-- Run this if you have an existing database with old categories

USE beaute_aesthetic_studio;

-- First, update the ENUM type (MySQL requires ALTER TABLE)
ALTER TABLE services MODIFY COLUMN category ENUM('nails', 'eyebrows', 'lashes', 'wax', 'massages', 'facial', 'skin_slimming') NOT NULL;

-- Update existing records to new category names
UPDATE services SET category = 'nails' WHERE category = 'nail';
UPDATE services SET category = 'massages' WHERE category = 'massage';
UPDATE services SET category = 'skin_slimming' WHERE category = 'relaxation';

-- Note: If you have existing services, you may need to manually categorize them:
-- - Old 'nail' services -> 'nails'
-- - Old 'massage' services -> 'massages'  
-- - Old 'relaxation' services -> 'skin_slimming' or 'massages' depending on the service
-- - 'lashes' remains the same
