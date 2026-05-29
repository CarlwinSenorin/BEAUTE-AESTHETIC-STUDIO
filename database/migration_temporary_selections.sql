-- Create temporary_selections table to prevent double-booking during selection phase
CREATE TABLE IF NOT EXISTS temporary_selections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    session_id VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    INDEX (staff_id),
    INDEX (appointment_date),
    INDEX (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
