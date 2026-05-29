-- Migration: Scheduling Engine v2 (Rooms, Skills, Tiers, Operational Metrics)
USE beaute_aesthetic_studio;

-- 1. Rooms Table
CREATE TABLE IF NOT EXISTS rooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(100),
    capacity INT DEFAULT 1,
    is_accessible BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Skills Table
CREATE TABLE IF NOT EXISTS skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT
);

-- 3. Staff Skills Table (Relational mapping)
CREATE TABLE IF NOT EXISTS staff_skills (
    staff_id INT NOT NULL,
    skill_id INT NOT NULL,
    proficiency_level INT DEFAULT 1 COMMENT '1-5 scale',
    PRIMARY KEY (staff_id, skill_id),
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
);

-- 4. Add Customer Tier to Users
ALTER TABLE users ADD COLUMN IF NOT EXISTS tier ENUM('standard', 'premium', 'vip') DEFAULT 'standard';

-- 5. Staff Performance Metrics (for Operational Health scoring)
ALTER TABLE staff ADD COLUMN IF NOT EXISTS efficiency_score DECIMAL(3,2) DEFAULT 1.00;
ALTER TABLE staff ADD COLUMN IF NOT EXISTS current_load INT DEFAULT 0;

-- 6. Insert Core Skills
INSERT IGNORE INTO skills (name) VALUES 
('Manicure'), ('Pedicure'), ('Gel Polish'), ('Nail Art'), ('Acrylic'),
('Threading'), ('Waxing'), ('Tinting'), ('Microblading'),
('Lash Extensions'), ('Lash Lift'), ('Facial'), ('Massage'), ('Skin Rejuvenation');

-- 7. Insert Default Rooms
INSERT IGNORE INTO rooms (name, type, capacity, is_accessible) VALUES 
('Room A', 'Manicure/Pedicure', 3, TRUE),
('Room B', 'Skin/Facial', 1, TRUE),
('Room C', 'Lashes/Threading', 2, FALSE),
('Spa Room 1', 'Massage', 1, TRUE),
('Spa Room 2', 'Massage', 1, TRUE);
