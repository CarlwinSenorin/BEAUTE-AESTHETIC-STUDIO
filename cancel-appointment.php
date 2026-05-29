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

// Check cancellation policy (24 hours advance OR within 1 hour of booking)
$appointment_datetime = strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
$created_at = strtotime($appointment['created_at']);
$hours_until = ($appointment_datetime - time()) / 3600;
$minutes_since_booking = (time() - $created_at) / 60;

if ($hours_until < 24 && $minutes_since_booking > 60) {
    $error = 'Appointments can only be cancelled at least 24 hours in advance (unless booked within the last hour). Please contact us for assistance.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$appointment_id]);
    
    header('Location: dashboard.php?cancelled=1');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Appointment - Beaute Aesthetic Studio</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <section class="dashboard-section">
        <div class="container">
            <div class="auth-card">
                <h2><i class="fas fa-times-circle"></i> Cancel Appointment</h2>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                    <a href="dashboard.php" class="btn btn-primary btn-block">Back to Dashboard</a>
                <?php else: ?>
                    <div class="appointment-details-cancel">
                        <p><strong>Date:</strong> <?php echo formatDate($appointment['appointment_date']); ?></p>
                        <p><strong>Time:</strong> <?php echo formatTime($appointment['appointment_time']); ?></p>
                        <p><strong>Status:</strong> <?php echo ucfirst($appointment['status']); ?></p>
                    </div>
                    
                    <p class="warning-text">Are you sure you want to cancel this appointment? This action cannot be undone.</p>
                    
                    <form method="POST" action="cancel-appointment.php?id=<?php echo $appointment_id; ?>">
                        <button type="submit" class="btn btn-danger btn-block">Yes, Cancel Appointment</button>
                    </form>
                    
                    <a href="dashboard.php" class="btn btn-outline btn-block" style="margin-top: 1rem;">No, Keep Appointment</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>
