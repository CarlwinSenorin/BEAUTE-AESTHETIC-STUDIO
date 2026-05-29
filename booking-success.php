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

// Get appointment (allow all statuses for history viewing)
$stmt = $conn->prepare("SELECT a.* 
                        FROM appointments a
                        WHERE a.id = ? 
                          AND a.user_id = ?");
$stmt->execute([$appointment_id, $user_id]);
$appointment = $stmt->fetch();

// Get service names
if ($appointment) {
    $service_ids = json_decode($appointment['services'], true);
    if (!empty($service_ids)) {
        $placeholders = str_repeat('?,', count($service_ids) - 1) . '?';
        $stmt = $conn->prepare("SELECT name FROM services WHERE id IN ($placeholders)");
        $stmt->execute($service_ids);
        $service_names = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $appointment['service_names'] = implode(', ', $service_names);
    } else {
        $appointment['service_names'] = 'No services';
    }
}

if (!$appointment) {
    header('Location: dashboard.php');
    exit;
}

// Determine display based on status
$status_display = [
    'pending' => ['icon' => 'fa-clock', 'color' => '#ffc107', 'title' => 'Booking Pending'],
    'reserved' => ['icon' => 'fa-calendar-check', 'color' => '#fd7e14', 'title' => 'Booking Reserved!'],
    'confirmed' => ['icon' => 'fa-check-circle', 'color' => '#28a745', 'title' => 'Booking Confirmed!'],
    'completed' => ['icon' => 'fa-check-double', 'color' => '#17a2b8', 'title' => 'Booking Completed'],
    'cancelled' => ['icon' => 'fa-times-circle', 'color' => '#dc3545', 'title' => 'Booking Cancelled'],
    'no_show' => ['icon' => 'fa-user-slash', 'color' => '#6c757d', 'title' => 'No Show']
];
$display = $status_display[$appointment['status']] ?? $status_display['pending'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details - Beaute Aesthetic Studio</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <section class="dashboard-section">
        <div class="container">
            <div class="auth-card" style="text-align: center;">
                <div style="font-size: 4rem; color: <?php echo $display['color']; ?>; margin-bottom: 1rem;">
                    <i class="fas <?php echo $display['icon']; ?>"></i>
                </div>
                <h2><?php echo $display['title']; ?></h2>
                <p>Appointment reference: #<?php echo $appointment['id']; ?></p>
                
                <div class="booking-details-success">
                    <h3>Appointment Details</h3>
                    
                    <?php 
                    $details = json_decode($appointment['client_details'], true);
                    if ($details && is_array($details)): 
                        // Group by person_index
                        $persons = [];
                        foreach ($details as $item) {
                            $idx = $item['person_index'] ?? 1;
                            if (!isset($persons[$idx])) $persons[$idx] = [];
                            $persons[$idx][] = $item;
                        }
                        ksort($persons);

                        foreach ($persons as $idx => $items):
                            $personLabel = count($persons) > 1 ? "Person $idx" : "Your Appointment";
                    ?>
                        <div class="person-summary-group">
                            <div class="person-group-header">
                                <strong><i class="fas fa-user-circle"></i> <?php echo $personLabel; ?></strong>
                            </div>
                            <?php foreach ($items as $item): ?>
                                <div class="person-detail-card">
                                    <div class="detail-row">
                                        <span class="detail-label">Service:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($item['service_name']); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Date:</span>
                                        <span class="detail-value"><?php echo formatDate($item['date']); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Time:</span>
                                        <span class="detail-value"><?php echo formatTime($item['time']); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Specialist:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($item['staffName'] ?? 'Any Available'); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Fallback for old/simple bookings -->
                        <div class="detail-item">
                            <strong>Date:</strong> <?php echo formatDate($appointment['appointment_date']); ?>
                        </div>
                        <div class="detail-item">
                            <strong>Time:</strong> <?php echo formatTime($appointment['appointment_time']); ?>
                        </div>
                        <div class="detail-item">
                            <strong>Services:</strong> <?php echo htmlspecialchars($appointment['service_names']); ?>
                        </div>
                    <?php endif; ?>

                    <div class="booking-summary-total">
                        <div class="detail-item total-row">
                            <strong>Total Amount:</strong> 
                            <span class="total-price"><?php echo formatPrice($appointment['final_price']); ?></span>
                        </div>
                        <div class="detail-item status-row">
                            <strong>Status:</strong> 
                            <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                <?php echo ucfirst($appointment['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <style>
                    .person-summary-group {
                        margin-bottom: 1.5rem;
                        border: 1px solid #eee;
                        border-radius: 8px;
                        overflow: hidden;
                    }
                    .person-group-header {
                        background: #f8f9fa;
                        padding: 0.75rem 1rem;
                        border-bottom: 1px solid #eee;
                        color: var(--primary-color);
                    }
                    .person-detail-card {
                        padding: 1rem;
                        background: #fff;
                        border-bottom: 1px solid #f0f0f0;
                    }
                    .person-detail-card:last-child {
                        border-bottom: none;
                    }
                    .detail-row {
                        display: flex;
                        justify-content: space-between;
                        margin-bottom: 0.5rem;
                    }
                    .detail-row:last-child {
                        margin-bottom: 0;
                    }
                    .detail-label {
                        color: #666;
                        font-weight: 500;
                    }
                    .detail-value {
                        font-weight: 600;
                        color: var(--dark-color);
                    }
                    .booking-summary-total {
                        margin-top: 1.5rem;
                        padding-top: 1rem;
                        border-top: 2px dashed #ddd;
                    }
                    .total-row {
                        font-size: 1.25rem;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    }
                    .total-price {
                        color: var(--primary-color);
                        font-weight: bold;
                    }
                    .status-row {
                        margin-top: 0.5rem;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    }
                </style>
                
                <p style="margin-top: 2rem; color: #666;">
                    You will receive a confirmation email and SMS reminder 24 hours before your appointment.
                </p>
                
                <div style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: center;">
                    <a href="dashboard.php" class="btn btn-primary">View Dashboard</a>
                    <a href="booking.php" class="btn btn-outline">Book Another</a>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <style>
        .booking-details-success {
            background: var(--light-color);
            padding: 2rem;
            border-radius: 10px;
            margin: 2rem 0;
            text-align: left;
        }
        .booking-details-success h3 {
            margin-bottom: 1rem;
            color: var(--dark-color);
        }
        .detail-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #ddd;
        }
        .detail-item:last-child {
            border-bottom: none;
        }
    </style>
</body>
</html>
