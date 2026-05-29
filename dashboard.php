<?php
require_once 'config/functions.php';
requireLogin();

// Admins can access user dashboard to book appointments or view their own history

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get user statistics
$stats = [
    'total_appointments' => 0,
    'upcoming_appointments' => 0,
    'completed_appointments' => 0,
    'total_spent' => 0
];

$stmt = $conn->prepare("SELECT COUNT(*) FROM appointments WHERE user_id = ? AND status NOT IN ('cancelled', 'no_show')");
$stmt->execute([$user_id]);
$stats['total_appointments'] = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM appointments 
                        WHERE user_id = ? 
                        AND appointment_date >= CURDATE()
                        AND status IN ('pending', 'confirmed', 'reserved')");
$stmt->execute([$user_id]);
$stats['upcoming_appointments'] = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM appointments 
                        WHERE user_id = ? 
                        AND status = 'completed'");
$stmt->execute([$user_id]);
$stats['completed_appointments'] = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT SUM(final_price) FROM appointments 
                        WHERE user_id = ? 
                        AND status = 'completed'");
$stmt->execute([$user_id]);
$stats['total_spent'] = $stmt->fetchColumn() ?: 0;

// Get user appointments
$stmt = $conn->prepare("SELECT a.* 
                        FROM appointments a
                        WHERE a.user_id = ?
                        ORDER BY a.appointment_date DESC, a.appointment_time DESC
                        LIMIT 10");
$stmt->execute([$user_id]);
$appointments = $stmt->fetchAll();

// Get service names for appointments
foreach ($appointments as &$apt) {
    $service_ids = json_decode($apt['services'], true);
    if (!empty($service_ids) && is_array($service_ids)) {
        $placeholders = str_repeat('?,', count($service_ids) - 1) . '?';
        $stmt = $conn->prepare("SELECT name FROM services WHERE id IN ($placeholders)");
        $stmt->execute($service_ids);
        $service_names = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $apt['service_names'] = implode(', ', $service_names);
    } else {
        $apt['service_names'] = 'No services';
    }
}
unset($apt); // Break reference

// Get upcoming appointments
$stmt = $conn->prepare("SELECT a.* 
                        FROM appointments a
                        WHERE a.user_id = ? 
                        AND a.appointment_date >= CURDATE()
                        AND a.status IN ('pending', 'confirmed', 'reserved')
                        ORDER BY a.appointment_date ASC, a.appointment_time ASC
                        LIMIT 5");
$stmt->execute([$user_id]);
$upcoming = $stmt->fetchAll();

// Get service names for upcoming appointments
foreach ($upcoming as &$apt) {
    $service_ids = json_decode($apt['services'], true);
    if (!empty($service_ids) && is_array($service_ids)) {
        $placeholders = str_repeat('?,', count($service_ids) - 1) . '?';
        $stmt = $conn->prepare("SELECT name FROM services WHERE id IN ($placeholders)");
        $stmt->execute($service_ids);
        $service_names = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $apt['service_names'] = implode(', ', $service_names);
    } else {
        $apt['service_names'] = 'No services';
    }
}
unset($apt); // Break reference

// Get next appointment
$stmt = $conn->prepare("SELECT a.* 
                        FROM appointments a
                        WHERE a.user_id = ? 
                        AND a.appointment_date >= CURDATE()
                        AND a.status IN ('pending', 'confirmed', 'reserved')
                        ORDER BY a.appointment_date ASC, a.appointment_time ASC
                        LIMIT 1");
$stmt->execute([$user_id]);
$next_appointment = $stmt->fetch();

if ($next_appointment) {
    $service_ids = json_decode($next_appointment['services'], true);
    if (!empty($service_ids) && is_array($service_ids)) {
        $placeholders = str_repeat('?,', count($service_ids) - 1) . '?';
        $stmt = $conn->prepare("SELECT name FROM services WHERE id IN ($placeholders)");
        $stmt->execute($service_ids);
        $service_names = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $next_appointment['service_names'] = implode(', ', $service_names);
    } else {
        $next_appointment['service_names'] = 'No services';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - Beaute Aesthetic Studio</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <section class="dashboard-section">
        <div class="container">
            <div class="dashboard-header">
                <div>
                    <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>
                    <p class="dashboard-subtitle">Manage your appointments and track your beauty journey</p>
                </div>
                <a href="booking.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Book New Appointment
                </a>
            </div>

            <?php if (isset($_GET['cancelled'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Appointment cancelled successfully.
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['rescheduled'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Appointment rescheduled successfully.
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <!-- AI Recommendations Section -->
            <?php 
            $recommendations = getAIRecommendations($_SESSION['user_id']);
            if (!empty($recommendations['services'])): 
            ?>
            <div class="dashboard-section-title">
                <h2><i class="fas fa-magic"></i> <?php echo $recommendations['reason']; ?></h2>
                <span class="badge-ai">AI Recommended</span>
            </div>
            
            <div class="recommendations-grid">
                <?php foreach ($recommendations['services'] as $service): ?>
                <div class="service-card recommendation-card">
                    <div class="service-image">
                        <img src="<?php echo $service['image_url'] ? $service['image_url'] : 'assets/images/placeholder.jpg'; ?>" alt="<?php echo htmlspecialchars($service['name']); ?>">
                        <span class="category-tag"><?php echo htmlspecialchars(ucfirst($service['category'])); ?></span>
                    </div>
                    <div class="service-info">
                        <h3><?php echo htmlspecialchars($service['name']); ?></h3>
                        <p class="price-duration">
                            <span class="price"><?php echo formatPrice($service['base_price']); ?></span>
                            <span class="duration"><i class="far fa-clock"></i> <?php echo $service['duration']; ?> min</span>
                        </p>
                        <a href="booking.php?service=<?php echo $service['id']; ?>" class="btn btn-sm btn-outline-primary">Book Now</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <style>
                .badge-ai {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 4px 8px;
                    border-radius: 12px;
                    font-size: 0.7rem;
                    margin-left: 10px;
                    vertical-align: middle;
                }
                .recommendations-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                    gap: 20px;
                    margin-bottom: 40px;
                }
                .recommendation-card {
                    background: #fff;
                    border-radius: 10px;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
                    overflow: hidden;
                    transition: transform 0.2s;
                    border: 1px solid #eee;
                }
                .recommendation-card:hover {
                    transform: translateY(-5px);
                    box-shadow: 0 10px 15px rgba(0,0,0,0.1);
                }
                .recommendation-card .service-image {
                    height: 150px;
                    overflow: hidden;
                    position: relative;
                }
                .recommendation-card .service-image img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }
                .recommendation-card .service-info {
                    padding: 15px;
                }
                .recommendation-card h3 {
                    font-size: 1.1rem;
                    margin-bottom: 10px;
                }
                .recommendation-card .price-duration {
                    display: flex;
                    justify-content: space-between;
                    color: #666;
                    font-size: 0.9rem;
                    margin-bottom: 15px;
                }
            </style>
            <?php endif; ?>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-total">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_appointments']); ?></h3>
                        <p>Total Appointments</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon stat-icon-upcoming">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['upcoming_appointments']); ?></h3>
                        <p>Upcoming</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon stat-icon-completed">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['completed_appointments']); ?></h3>
                        <p>Completed</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon stat-icon-spent">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo formatPrice($stats['total_spent']); ?></h3>
                        <p>Total Spent</p>
                    </div>
                </div>
            </div>

            <!-- Next Appointment Highlight -->
            <?php if ($next_appointment): ?>
                <div class="next-appointment-card">
                    <div class="next-appointment-header">
                        <h2><i class="fas fa-clock"></i> Your Next Appointment</h2>
                    </div>
                    <div class="next-appointment-content">
                        <div class="next-appointment-date">
                            <div class="date-display">
                                <span class="date-day"><?php echo date('d', strtotime($next_appointment['appointment_date'])); ?></span>
                                <span class="date-month"><?php echo date('M', strtotime($next_appointment['appointment_date'])); ?></span>
                            </div>
                            <div class="time-display">
                                <i class="fas fa-clock"></i>
                                <?php echo formatTime($next_appointment['appointment_time']); ?>
                            </div>
                        </div>
                        <div class="next-appointment-details">
                            <h3><?php echo htmlspecialchars($next_appointment['service_names']); ?></h3>
                            <p class="appointment-status">
                                <span class="status-badge status-<?php echo $next_appointment['status']; ?>">
                                    <?php echo ucfirst($next_appointment['status']); ?>
                                </span>
                            </p>
                            <div class="next-appointment-actions">
                                <a href="reschedule.php?id=<?php echo $next_appointment['id']; ?>" class="btn btn-sm btn-outline">
                                    <i class="fas fa-calendar-alt"></i> Reschedule
                                </a>
                                <a href="cancel-appointment.php?id=<?php echo $next_appointment['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="dashboard-grid">
                <!-- Upcoming Appointments -->
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2><i class="fas fa-calendar-check"></i> Upcoming Appointments</h2>
                        <a href="profile.php" class="view-all-link">View All <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <?php if (empty($upcoming)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <p>No upcoming appointments. <a href="booking.php">Book one now!</a></p>
                        </div>
                    <?php else: ?>
                        <div class="appointments-list">
                            <?php foreach ($upcoming as $apt): ?>
                                <div class="appointment-item">
                                    <div class="appointment-date">
                                        <strong><?php echo formatDate($apt['appointment_date']); ?></strong>
                                        <span><?php echo formatTime($apt['appointment_time']); ?></span>
                                    </div>
                                    <div class="appointment-details">
                                        <h4><?php echo htmlspecialchars($apt['service_names']); ?></h4>
                                        <span class="status-badge status-<?php echo htmlspecialchars($apt['status']); ?>">
                                            <?php echo ucfirst($apt['status']); ?>
                                        </span>
                                    </div>
                                    <div class="appointment-actions">
                                        <?php if ($apt['status'] !== 'cancelled'): ?>
                                            <a href="reschedule.php?id=<?php echo $apt['id']; ?>" class="btn btn-sm btn-outline">Reschedule</a>
                                            <a href="cancel-appointment.php?id=<?php echo $apt['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to cancel this appointment?')">Cancel</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Appointments -->
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2><i class="fas fa-history"></i> Recent Appointments</h2>
                        <a href="profile.php" class="view-all-link">View All <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <?php if (empty($appointments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No appointments yet. Start your beauty journey today!</p>
                        </div>
                    <?php else: ?>
                        <div class="appointments-list">
                            <?php foreach (array_slice($appointments, 0, 5) as $apt): ?>
                                <div class="appointment-item">
                                    <div class="appointment-date">
                                        <strong><?php echo formatDate($apt['appointment_date']); ?></strong>
                                        <span><?php echo formatTime($apt['appointment_time']); ?></span>
                                    </div>
                                    <div class="appointment-details">
                                        <h4><?php echo htmlspecialchars($apt['service_names']); ?></h4>
                                        <span class="status-badge status-<?php echo htmlspecialchars($apt['status']); ?>">
                                            <?php echo ucfirst($apt['status']); ?>
                                        </span>
                                    </div>
                                    <?php if ($apt['status'] === 'completed'): ?>
                                        <a href="review.php?appointment_id=<?php echo $apt['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-star"></i> Review
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h2>Quick Actions</h2>
                <div class="quick-actions-grid">
                    <a href="calendar.php" class="quick-action-card">
                        <i class="fas fa-calendar-alt"></i>
                        <span>View Calendar</span>
                    </a>
                    <a href="booking.php" class="quick-action-card">
                        <i class="fas fa-calendar-plus"></i>
                        <span>Book Appointment</span>
                    </a>
                    <a href="profile.php" class="quick-action-card">
                        <i class="fas fa-user-edit"></i>
                        <span>Edit Profile</span>
                    </a>
                    <a href="services.php" class="quick-action-card">
                        <i class="fas fa-spa"></i>
                        <span>View Services</span>
                    </a>
                    <a href="index.php#testimonials" class="quick-action-card">
                        <i class="fas fa-star"></i>
                        <span>Read Reviews</span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>
