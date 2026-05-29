<?php
require_once 'config/functions.php';
requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : null;

if (!$appointment_id || $appointment_id <= 0) {
    header('Location: dashboard.php');
    exit;
}

// Check if appointment belongs to user and is completed
$stmt = $conn->prepare("SELECT * FROM appointments WHERE id = ? AND user_id = ? AND status = 'completed'");
$stmt->execute([$appointment_id, $user_id]);
$appointment = $stmt->fetch();

if (!$appointment) {
    header('Location: dashboard.php');
    exit;
}

// Check if review already exists
$stmt = $conn->prepare("SELECT * FROM testimonials WHERE appointment_id = ?");
$stmt->execute([$appointment_id]);
$existing_review = $stmt->fetch();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (int)($_POST['rating'] ?? 0);
    $review_text = sanitize($_POST['review_text'] ?? '');
    
    if ($rating >= 1 && $rating <= 5 && $review_text) {
        if ($existing_review) {
            $stmt = $conn->prepare("UPDATE testimonials SET rating = ?, review_text = ?, status = 'pending', staff_id = ? WHERE id = ?");
            $stmt->execute([$rating, $review_text, $appointment['staff_id'], $existing_review['id']]);
        } else {
            $stmt = $conn->prepare("INSERT INTO testimonials (user_id, appointment_id, rating, review_text, staff_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $appointment_id, $rating, $review_text, $appointment['staff_id']]);
        }
        $success = 'Thank you for your review! It will be reviewed before being published.';
    } else {
        $error = 'Please provide a rating and review text';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave a Review - Beaute Aesthetic Studio</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <section class="dashboard-section">
        <div class="container">
            <div class="auth-card">
                <h2><i class="fas fa-star"></i> Leave a Review</h2>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <a href="dashboard.php" class="btn btn-primary btn-block">Back to Dashboard</a>
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label>Rating</label>
                            <div class="rating-input">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" name="rating" value="<?php echo $i; ?>" id="rating<?php echo $i; ?>" 
                                           <?php echo ($existing_review && $existing_review['rating'] == $i) ? 'checked' : ($i == 5 ? 'checked' : ''); ?> required>
                                    <label for="rating<?php echo $i; ?>" class="star-label">
                                        <i class="fas fa-star"></i>
                                    </label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Your Review</label>
                            <textarea name="review_text" rows="6" placeholder="Share your experience..." required><?php echo htmlspecialchars($existing_review['review_text'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">Submit Review</button>
                    </form>
                    
                    <a href="dashboard.php" class="btn btn-outline btn-block" style="margin-top: 1rem;">Cancel</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <style>
        .rating-input {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .rating-input input {
            display: none;
        }
        .star-label {
            font-size: 2rem;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
        }
        .rating-input input:checked ~ .star-label,
        .rating-input .star-label:hover,
        .rating-input .star-label:hover ~ .star-label {
            color: var(--warning-color);
        }
        .rating-input input:checked ~ .star-label {
            color: var(--warning-color);
        }
    </style>
</body>
</html>
