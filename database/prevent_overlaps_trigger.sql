-- Database Trigger to Prevent Overlapping Appointments
-- This provides an additional safety layer at the database level

DELIMITER $$

-- Trigger for INSERT operations
CREATE TRIGGER prevent_overlap_insert
BEFORE INSERT ON appointments
FOR EACH ROW
BEGIN
    DECLARE overlap_count INT;
    
    -- Check for overlapping appointments with the same staff
    IF NEW.staff_id IS NOT NULL THEN
        SELECT COUNT(*) INTO overlap_count
        FROM appointments
        WHERE staff_id = NEW.staff_id
        AND appointment_date = NEW.appointment_date
        AND status NOT IN ('cancelled', 'no_show')
        AND NOT (end_time <= NEW.appointment_time OR appointment_time >= NEW.end_time);
        
        IF overlap_count > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cannot create appointment: Staff member has overlapping appointment';
        END IF;
    END IF;
END$$

-- Trigger for UPDATE operations
CREATE TRIGGER prevent_overlap_update
BEFORE UPDATE ON appointments
FOR EACH ROW
BEGIN
    DECLARE overlap_count INT;
    
    -- Only check if date, time, or staff is being changed
    IF (NEW.appointment_date != OLD.appointment_date 
        OR NEW.appointment_time != OLD.appointment_time 
        OR NEW.end_time != OLD.end_time
        OR NEW.staff_id != OLD.staff_id) THEN
        
        -- Check for overlapping appointments with the same staff
        IF NEW.staff_id IS NOT NULL THEN
            SELECT COUNT(*) INTO overlap_count
            FROM appointments
            WHERE id != NEW.id  -- Exclude current appointment
            AND staff_id = NEW.staff_id
            AND appointment_date = NEW.appointment_date
            AND status NOT IN ('cancelled', 'no_show')
            AND NOT (end_time <= NEW.appointment_time OR appointment_time >= NEW.end_time);
            
            IF overlap_count > 0 THEN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Cannot update appointment: Staff member has overlapping appointment';
            END IF;
        END IF;
    END IF;
END$$

DELIMITER ;

-- Test the triggers (optional - comment out in production)
-- This will fail as expected:
-- INSERT INTO appointments (user_id, staff_id, appointment_date, appointment_time, end_time, services, total_price, final_price, status)
-- VALUES (1, 1, '2026-02-15', '10:00:00', '11:00:00', '[]', 100, 100, 'pending');
-- 
-- INSERT INTO appointments (user_id, staff_id, appointment_date, appointment_time, end_time, services, total_price, final_price, status)
-- VALUES (1, 1, '2026-02-15', '10:30:00', '11:30:00', '[]', 100, 100, 'pending');
