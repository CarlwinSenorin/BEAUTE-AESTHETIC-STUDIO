<?php
require_once 'config/functions.php';
requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

$error = '';
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = sanitize($_POST['first_name'] ?? '');
    $last_name  = sanitize($_POST['last_name'] ?? '');
    $phone      = sanitize($_POST['phone'] ?? '');

    if ($first_name && $last_name && $phone) {
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE id = ?");
        if ($stmt->execute([$first_name, $last_name, $phone, $user_id])) {
            $_SESSION['user_name'] = $first_name . ' ' . $last_name;
            $success = 'Profile updated successfully.';
        } else {
            $error = 'Failed to update profile. Please try again.';
        }
    } else {
        $error = 'Please fill in all required fields.';
    }
}

// Fetch user info
$stmt = $conn->prepare("SELECT first_name, last_name, email, phone, profile_picture, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Upcoming bookings
$stmt = $conn->prepare("SELECT * FROM appointments 
                        WHERE user_id = ? 
                          AND appointment_date >= CURDATE()
                          AND status IN ('pending', 'confirmed')
                        ORDER BY appointment_date ASC, appointment_time ASC");
$stmt->execute([$user_id]);
$upcoming = $stmt->fetchAll();

// Completed bookings
$stmt = $conn->prepare("SELECT * FROM appointments 
                        WHERE user_id = ? 
                          AND status = 'completed'
                        ORDER BY appointment_date DESC, appointment_time DESC");
$stmt->execute([$user_id]);
$completed = $stmt->fetchAll();

// Cancelled / no-show bookings
$stmt = $conn->prepare("SELECT * FROM appointments 
                        WHERE user_id = ? 
                          AND status IN ('cancelled', 'no_show')
                        ORDER BY appointment_date DESC, appointment_time DESC");
$stmt->execute([$user_id]);
$cancelled = $stmt->fetchAll();

// Attach service names to bookings
function attachServiceNames(&$appointments, $conn) {
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
}

attachServiceNames($upcoming, $conn);
attachServiceNames($completed, $conn);
attachServiceNames($cancelled, $conn);

// User testimonials (as messages / feedback)
$stmt = $conn->prepare("SELECT * FROM testimonials WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$testimonials = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Beaute Aesthetic Studio</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <section class="dashboard-section">
        <div class="container">
            <div class="dashboard-header">
                <h1>My Profile</h1>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="dashboard-grid">
                <!-- Profile Picture Section -->
                <div class="dashboard-card profile-pic-card" style="text-align: center;">
                    <h2><i class="fas fa-camera"></i> Profile Picture</h2>
                    <div class="profile-pic-preview">
                        <?php if ($user['profile_picture']): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture" id="profilePicImg">
                        <?php else: ?>
                            <div class="initials-avatar"><?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="profile-pic-actions">
                        <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('profile_pic_input').click()">
                            <i class="fas fa-upload"></i> Change Photo
                        </button>
                        <?php if ($user['profile_picture']): ?>
                            <button type="button" class="btn btn-sm btn-danger" onclick="removeProfilePic()">
                                <i class="fas fa-trash"></i> Remove
                            </button>
                        <?php endif; ?>
                        <input type="file" id="profile_pic_input" style="display: none;" accept="image/*" onchange="uploadProfilePic(this)">
                    </div>
                </div>

                <!-- Profile Info & Edit -->
                <div class="dashboard-card">
                    <h2><i class="fas fa-user-circle"></i> Profile Information</h2>
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="form-row">
                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Email (read-only)</label>
                            <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                        </div>
                        <p style="font-size: 0.9rem; color: #666;">Member since <?php echo formatDate($user['created_at']); ?></p>
                        <button type="submit" class="btn btn-primary btn-block">Save Profile</button>
                    </form>
                </div>

                <!-- Bookings Overview -->
                <div class="dashboard-card">
                    <h2><i class="fas fa-calendar-alt"></i> My Bookings</h2>
                    <div class="appointments-tabs">
                        <button type="button" class="tab-btn active" data-tab="upcoming">Upcoming</button>
                        <button type="button" class="tab-btn" data-tab="completed">Completed</button>
                        <button type="button" class="tab-btn" data-tab="cancelled">Cancelled</button>
                    </div>

                    <div class="appointments-tab-content active" id="tab-upcoming">
                        <?php if (empty($upcoming)): ?>
                            <p class="empty-state">No upcoming appointments.</p>
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
                                            <span class="status-badge status-<?php echo $apt['status']; ?>">
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

                    <div class="appointments-tab-content" id="tab-completed">
                        <?php if (empty($completed)): ?>
                            <p class="empty-state">No completed appointments yet.</p>
                        <?php else: ?>
                            <div class="appointments-list">
                                <?php foreach ($completed as $apt): ?>
                                    <div class="appointment-item">
                                        <div class="appointment-date">
                                            <strong><?php echo formatDate($apt['appointment_date']); ?></strong>
                                            <span><?php echo formatTime($apt['appointment_time']); ?></span>
                                        </div>
                                        <div class="appointment-details">
                                            <h4><?php echo htmlspecialchars($apt['service_names']); ?></h4>
                                            <span class="status-badge status-<?php echo $apt['status']; ?>">
                                                <?php echo ucfirst($apt['status']); ?>
                                            </span>
                                        </div>
                                        <a href="review.php?appointment_id=<?php echo $apt['id']; ?>" class="btn btn-sm btn-primary">Leave Review</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="appointments-tab-content" id="tab-cancelled">
                        <?php if (empty($cancelled)): ?>
                            <p class="empty-state">No cancelled or missed appointments.</p>
                        <?php else: ?>
                            <div class="appointments-list">
                                <?php foreach ($cancelled as $apt): ?>
                                    <div class="appointment-item">
                                        <div class="appointment-date">
                                            <strong><?php echo formatDate($apt['appointment_date']); ?></strong>
                                            <span><?php echo formatTime($apt['appointment_time']); ?></span>
                                        </div>
                                        <div class="appointment-details">
                                            <h4><?php echo htmlspecialchars($apt['service_names']); ?></h4>
                                            <span class="status-badge status-<?php echo $apt['status']; ?>">
                                                <?php echo ucfirst($apt['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Messages / Reviews -->
            <div class="dashboard-card" style="margin-top: 2rem;">
                <h2><i class="fas fa-comments"></i> My Messages & Reviews</h2>
                <?php if (empty($testimonials)): ?>
                    <p class="empty-state">You have not submitted any reviews yet.</p>
                <?php else: ?>
                    <div class="appointments-list">
                        <?php foreach ($testimonials as $t): ?>
                            <div class="appointment-item">
                                <div class="appointment-details">
                                    <h4>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star" style="color: <?php echo $i <= $t['rating'] ? '#ffc107' : '#ddd'; ?>"></i>
                                        <?php endfor; ?>
                                    </h4>
                                    <p><?php echo htmlspecialchars($t['review_text']); ?></p>
                                </div>
                                <div class="appointment-date">
                                    <span><?php echo formatDate($t['created_at']); ?></span>
                                    <span class="status-badge status-<?php echo $t['status']; ?>"><?php echo ucfirst($t['status']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <script>
        // Simple tab switching for bookings
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.appointments-tab-content');

            tabButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const target = this.getAttribute('data-tab');

                    tabButtons.forEach(b => b.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));

                    this.classList.add('active');
                    document.getElementById('tab-' + target).classList.add('active');
                });
            });
        });
    </script>
    <style>
        .appointments-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .tab-btn {
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid #ddd;
            background: #f8f9fa;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .tab-btn.active {
            background: var(--primary-color);
            color: var(--white);
            border-color: var(--primary-color);
        }
        .appointments-tab-content {
            display: none;
        }
        .appointments-tab-content.active {
            display: block;
        }
        .profile-pic-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 1.5rem;
            border: 4px solid var(--primary-color);
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .profile-pic-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .initials-avatar {
            font-size: 4rem;
            color: var(--primary-color);
            font-weight: bold;
        }
        .profile-pic-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }
    </style>
    <script>
        function uploadProfilePic(input) {
            if (input.files && input.files[0]) {
                const formData = new FormData();
                formData.append('action', 'upload');
                formData.append('context', 'client');
                formData.append('profile_pic', input.files[0]);

                fetch('api/upload-profile-pic.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while uploading the profile picture.');
                });
            }
        }

        function removeProfilePic() {
            if (confirm('Are you sure you want to remove your profile picture?')) {
                const formData = new FormData();
                formData.append('action', 'remove');
                formData.append('context', 'client');

                fetch('api/upload-profile-pic.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while removing the profile picture.');
                });
            }
        }
    </script>
</body>
</html>
