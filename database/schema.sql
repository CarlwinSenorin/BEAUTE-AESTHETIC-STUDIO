-- Beaute Aesthetic Studio - Database Schema

CREATE DATABASE IF NOT EXISTS beaute_aesthetic_studio;
USE beaute_aesthetic_studio;

-- Users Table (Clients and Admins)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('client', 'admin', 'staff') DEFAULT 'client',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Staff Table
CREATE TABLE staff (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    specialization TEXT,
    bio TEXT,
    availability JSON,
    rating DECIMAL(3,2) DEFAULT 0.00,
    total_reviews INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Services Table
CREATE TABLE services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    category ENUM('nails', 'eyebrows', 'lashes', 'wax', 'massages', 'facial', 'skin_slimming') NOT NULL,
    description TEXT,
    duration INT NOT NULL COMMENT 'Duration in minutes',
    base_price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(500),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Packages Table
CREATE TABLE packages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    services JSON NOT NULL COMMENT 'Array of service IDs',
    original_price DECIMAL(10,2) NOT NULL,
    discounted_price DECIMAL(10,2) NOT NULL,
    discount_percentage DECIMAL(5,2),
    image_url VARCHAR(500),
    valid_from DATE,
    valid_until DATE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Appointments Table
CREATE TABLE appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    staff_id INT,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    end_time TIME NOT NULL,
    services JSON NOT NULL COMMENT 'Array of service IDs',
    total_price DECIMAL(10,2) NOT NULL,
    discount_applied DECIMAL(10,2) DEFAULT 0.00,
    final_price DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'reserved', 'confirmed', 'completed', 'cancelled', 'no_show') DEFAULT 'pending',
    notes TEXT,
    reminder_sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL
);

-- Testimonials Table
CREATE TABLE testimonials (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    appointment_id INT,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review_text TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
);

-- Promotions Table
CREATE TABLE promotions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    min_purchase DECIMAL(10,2),
    applicable_services JSON COMMENT 'Array of service IDs, NULL for all',
    valid_from DATE NOT NULL,
    valid_until DATE NOT NULL,
    usage_limit INT,
    used_count INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Settings Table (for system configuration)
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin user (password: admin123)
INSERT INTO users (first_name, last_name, email, phone, password, role) VALUES
('Admin', 'User', 'admin@beauteaesthetic.com', '1234567890', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert sample services
-- Nails Services
INSERT INTO services (name, category, description, duration, base_price, image_url) VALUES
('Classic Manicure', 'nails', 'Beautiful nail care and polish application', 45, 35.00, 'images/services/manicure.jpg'),
('Gel Manicure', 'nails', 'Long-lasting gel polish manicure', 60, 50.00, 'images/services/gel-manicure.jpg'),
('Classic Pedicure', 'nails', 'Relaxing foot care and polish', 60, 45.00, 'images/services/pedicure.jpg'),
('Nail Art Design', 'nails', 'Custom nail art and designs', 75, 60.00, 'images/services/nail-art.jpg'),
('Acrylic Nails', 'nails', 'Full set of acrylic nails', 90, 70.00, 'images/services/acrylic.jpg'),

-- Eyebrows Services
('Eyebrow Threading', 'eyebrows', 'Precise eyebrow shaping with threading', 20, 15.00, 'images/services/threading.jpg'),
('Eyebrow Waxing', 'eyebrows', 'Clean eyebrow shaping with wax', 25, 18.00, 'images/services/eyebrow-wax.jpg'),
('Eyebrow Tinting', 'eyebrows', 'Eyebrow color enhancement', 30, 25.00, 'images/services/eyebrow-tint.jpg'),
('Microblading', 'eyebrows', 'Semi-permanent eyebrow enhancement', 120, 300.00, 'images/services/microblading.jpg'),

-- Lashes Services
('Classic Lash Extensions', 'lashes', 'Full set of classic lash extensions', 120, 150.00, 'images/services/lashes.jpg'),
('Volume Lash Extensions', 'lashes', 'Full set of volume lash extensions', 150, 200.00, 'images/services/volume-lashes.jpg'),
('Lash Fill', 'lashes', 'Touch-up for existing lash extensions', 60, 75.00, 'images/services/lash-fill.jpg'),
('Lash Lift & Tint', 'lashes', 'Lash curling and tinting treatment', 45, 65.00, 'images/services/lash-lift.jpg'),

-- Wax Services
('Full Leg Wax', 'wax', 'Complete leg hair removal', 60, 55.00, 'images/services/leg-wax.jpg'),
('Bikini Wax', 'wax', 'Bikini area hair removal', 30, 40.00, 'images/services/bikini-wax.jpg'),
('Brazilian Wax', 'wax', 'Complete Brazilian waxing', 45, 60.00, 'images/services/brazilian-wax.jpg'),
('Underarm Wax', 'wax', 'Underarm hair removal', 20, 25.00, 'images/services/underarm-wax.jpg'),
('Full Body Wax', 'wax', 'Complete body waxing service', 120, 150.00, 'images/services/full-body-wax.jpg'),

-- Massages Services
('Swedish Massage', 'massages', 'Relaxing full body Swedish massage', 60, 80.00, 'images/services/swedish-massage.jpg'),
('Deep Tissue Massage', 'massages', 'Therapeutic deep tissue massage', 90, 120.00, 'images/services/deep-tissue.jpg'),
('Hot Stone Massage', 'massages', 'Soothing hot stone therapy', 90, 140.00, 'images/services/hot-stone.jpg'),
('Aromatherapy Massage', 'massages', 'Relaxing aromatherapy massage', 60, 95.00, 'images/services/aromatherapy.jpg'),
('Thai Massage', 'massages', 'Traditional Thai massage therapy', 90, 110.00, 'images/services/thai-massage.jpg'),

-- Facial Services
('Classic Facial', 'facial', 'Deep cleansing and hydrating facial', 60, 75.00, 'images/services/classic-facial.jpg'),
('Anti-Aging Facial', 'facial', 'Rejuvenating anti-aging treatment', 75, 120.00, 'images/services/anti-aging.jpg'),
('Acne Treatment Facial', 'facial', 'Specialized acne clearing treatment', 60, 90.00, 'images/services/acne-facial.jpg'),
('Hydrating Facial', 'facial', 'Intensive hydration facial treatment', 60, 85.00, 'images/services/hydrating-facial.jpg'),

-- Skin & Slimming Treatments
('Body Contouring', 'skin_slimming', 'Non-invasive body contouring treatment', 60, 150.00, 'images/services/contouring.jpg'),
('Cellulite Reduction', 'skin_slimming', 'Cellulite reduction therapy', 45, 120.00, 'images/services/cellulite.jpg'),
('Body Wrap', 'skin_slimming', 'Detoxifying body wrap treatment', 60, 100.00, 'images/services/body-wrap.jpg'),
('RF Skin Tightening', 'skin_slimming', 'Radio frequency skin tightening', 60, 180.00, 'images/services/rf-tightening.jpg');

-- Insert sample packages
INSERT INTO packages (name, description, services, original_price, discounted_price, discount_percentage, valid_from, valid_until) VALUES
('Beauty Combo', 'Manicure + Pedicure combo', '[1, 3]', 80.00, 65.00, 18.75, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 MONTH)),
('Lash & Relax', 'Lash extensions + Aromatherapy', '[4, 10]', 240.00, 200.00, 16.67, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 MONTH)),
('Ultimate Spa Day', 'Full service package', '[1, 4, 7, 10]', 365.00, 299.00, 18.08, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 MONTH));

-- Insert default settings
INSERT INTO settings (setting_key, setting_value) VALUES
('business_hours_start', '09:00'),
('business_hours_end', '18:00'),
('appointment_interval', '15'),
('booking_advance_days', '60'),
('cancellation_hours', '24'),
('sms_enabled', 'true'),
('email_enabled', 'true'),
('reminder_hours_before', '24');
