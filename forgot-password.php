<?php
require_once 'config/functions.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Security validation failed. Please refresh the page and try again.';
    } else {
        $email = sanitize($_POST['email'] ?? '');
        
        if ($email) {
            if (!validateEmail($email)) {
                $error = 'Please enter a valid email address';
            } else {
                $conn = getDBConnection();
                $stmt = $conn->prepare("SELECT id, first_name FROM users WHERE email = ? AND status = 'active'");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
                    if ($stmt->execute([$token, $expires, $user['id']])) {
                        $reset_link = BASE_URL . "reset-password.php?token=" . $token;
                        
                        $subject = "Confirm your Password Reset - " . APP_NAME;
                        $message = "<h1>Hi " . htmlspecialchars($user['first_name']) . "</h1>";
                        $message .= "<p>We received a request to change your password for your <b>" . APP_NAME . "</b> account.</p>";
                        $message .= "<p>Please click the button below to <b>confirm</b> this change and set a new password:</p>";
                        $message .= '<p style="margin: 30px 0;"><a href="' . $reset_link . '" style="background-color: #d4a373; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;">Confirm and Reset Password</a></p>';
                        $message .= "<p>This link will expire in 1 hour.</p>";
                        $message .= "<p>If you did not request this, please ignore this email.</p>";
                        $message .= "<hr><p>Thank you,<br>" . APP_NAME . "</p>";
                        
                        if (sendSMTPPHPMail($email, $subject, $message, true)) {
                            $success = 'If that email is registered, a password reset link has been sent.';
                        } else {
                            // Fallback for development if email fails but we want to know the link
                            error_log("Failed to send reset email to $email. Link: $reset_link");
                            $success = 'If that email is registered, a password reset link has been sent.';
                        }
                    } else {
                        $error = 'An error occurred. Please try again.';
                    }
                } else {
                    // Don't reveal if email exists or not for security
                    $success = 'If that email is registered, a password reset link has been sent.';
                }
            }
        } else {
            $error = 'Please enter your email address';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Beaute Aesthetic Studio</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <section class="auth-section">
        <div class="container">
            <div class="auth-card">
                <h2><i class="fas fa-key"></i> Forgot Password</h2>
                <p>Enter your email to receive a reset link</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php else: ?>
                    <form method="POST" action="forgot-password.php">
                        <?php csrfTokenField(); ?>
                        
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email Address</label>
                            <input type="email" name="email" required autofocus placeholder="your@email.com">
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
                    </form>
                <?php endif; ?>
                
                <p class="auth-link">
                    Remembered your password? <a href="login.php">Back to Login</a>
                </p>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>
