<?php
require_once 'config/functions.php';
requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$appointment_id || $appointment_id <= 0) {
    header('Location: dashboard.php');
    exit;
}

// Get appointment
$stmt = $conn->prepare("SELECT * FROM appointments WHERE id = ? AND user_id = ?");
$stmt->execute([$appointment_id, $user_id]);
$appointment = $stmt->fetch();

if (!$appointment) {
    header('Location: dashboard.php');
    exit;
}

// Get services for duration calculation
$services = json_decode($appointment['services'], true);
if (empty($services) || !is_array($services)) {
    // Fallback duration if services are missing or invalid
    $duration = 60;
} else {
    $placeholders = str_repeat('?,', count($services) - 1) . '?';
    $stmt = $conn->prepare("SELECT SUM(duration) as duration FROM services WHERE id IN ($placeholders)");
    $stmt->execute($services);
    $result = $stmt->fetch();
    $duration = $result['duration'] ?? 60;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_date = sanitize($_POST['appointment_date'] ?? '');
    $new_time = sanitize($_POST['appointment_time'] ?? '');
    
    // Validate date format
    if ($new_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_date)) {
        $error = 'Invalid date format';
    } elseif ($new_date && $new_time) {
        // Prevent rescheduling to a past date
        $today = date('Y-m-d');
        if ($new_date < $today) {
            $error = 'You cannot reschedule to a past date';
        } else {
        // Check if new slot is available
        $start_time = strtotime($new_date . ' ' . $new_time);
        $end_time = date('H:i:s', $start_time + ($duration * 60));
        
        $stmt = $conn->prepare("SELECT COUNT(*) FROM appointments 
                                WHERE appointment_date = ? 
                                AND id != ?
                                AND status NOT IN ('cancelled', 'no_show')
                                    AND NOT (end_time <= ? OR appointment_time >= ?)");
        $stmt->execute([
            $new_date,
            $appointment_id,
                $new_time,
                $end_time
        ]);
        
        if ($stmt->fetchColumn() == 0) {
            $stmt = $conn->prepare("UPDATE appointments 
                                    SET appointment_date = ?, appointment_time = ?, end_time = ?, 
                                        status = 'confirmed', updated_at = NOW()
                                    WHERE id = ?");
            $stmt->execute([$new_date, $new_time, $end_time, $appointment_id]);
            
            header('Location: dashboard.php?rescheduled=1');
            exit;
            } else {
                $error = 'Selected time slot is not available';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reschedule Appointment - Beaute Aesthetic Studio</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/booking.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        .time-slot-label.disabled { cursor: not-allowed; }
        .time-slot-label.disabled .time-slot.unavailable {
            background: #f5f5f5;
            color: #aaa;
            border: 1px solid #e0e0e0;
            cursor: not-allowed;
            font-size: 0.82rem;
            text-align: center;
            line-height: 1.3;
        }
        .time-slot-label.disabled .time-slot.unavailable small {
            color: #e74c3c;
            font-size: 0.7rem;
            display: block;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <section class="page-header">
        <div class="container">
            <h1>Reschedule Appointment</h1>
            <p>Select a new date and time for your appointment</p>
        </div>
    </section>

    <section class="booking-section">
        <div class="container">
            <div class="booking-form">
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <div class="current-appointment">
                    <h3>Current Appointment</h3>
                    <p><strong>Date:</strong> <?php echo formatDate($appointment['appointment_date']); ?></p>
                    <p><strong>Time:</strong> <?php echo formatTime($appointment['appointment_time']); ?></p>
                </div>

                <form method="POST" action="reschedule.php?id=<?php echo $appointment_id; ?>">
                    <div class="form-group">
                        <label>New Date</label>
                        <input type="text" id="appointmentDate" name="appointment_date" class="datepicker" required>
                    </div>

                    <div id="timeSlotsContainer" style="display: none;">
                        <label>Available Time Slots</label>
                        <div id="timeSlots" class="time-slots-grid"></div>
                    </div>

                    <div class="step-buttons">
                        <a href="dashboard.php" class="btn btn-outline">Cancel</a>
                        <button type="submit" class="btn btn-primary">Reschedule Appointment</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    <script>
        const duration = <?php echo $duration; ?>;
        
        flatpickr('#appointmentDate', {
            minDate: 'today',
            maxDate: new Date().fp_incr(60),
            dateFormat: 'Y-m-d',
            onChange: function(selectedDates, dateStr) {
                loadTimeSlots(dateStr);
            }
        });

        function loadTimeSlots(date) {
            fetch(`api/get-time-slots.php?date=${date}&duration=${duration}`)
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('timeSlotsContainer');
                    const grid = document.getElementById('timeSlots');

                    const allSlots = data.slots || [];

                    if (allSlots.length === 0) {
                        grid.innerHTML = '<p style="color:#888; font-style:italic;">No time slots found for this date.</p>';
                        container.style.display = 'block';
                        return;
                    }

                    const availableCount = allSlots.filter(s => s.is_available).length;
                    if (availableCount === 0) {
                        grid.innerHTML = '<p style="color:#e74c3c; font-style:italic;">No available slots for this date. Please choose another date.</p>';
                        container.style.display = 'block';
                        return;
                    }

                    grid.innerHTML = allSlots.map(slot => {
                        if (slot.is_available) {
                            return `<label class="time-slot-label">
                                <input type="radio" name="appointment_time" value="${slot.start}" required>
                                <span class="time-slot">${slot.display}</span>
                            </label>`;
                        } else {
                            return `<label class="time-slot-label disabled" title="This slot is fully booked">
                                <input type="radio" name="appointment_time" value="${slot.start}" disabled>
                                <span class="time-slot unavailable">${slot.display}<br><small>Booked</small></span>
                            </label>`;
                        }
                    }).join('');

                    container.style.display = 'block';
                });
        }
    </script>
    <script src="assets/js/main.js"></script>
</body>
</html>
