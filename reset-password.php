<?php
require_once 'config/functions.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$valid_token = false;
$user_id = null;

if ($token) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW() AND status = 'active'");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        $valid_token = true;
        $user_id = $user['id'];
    } else {
        $error = 'Invalid or expired reset token.';
    }
} else {
    $error = 'No reset token provided.';
}

if ($valid_token && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Security validation failed. Please refresh the page and try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if ($password && $confirm_password) {
            if ($password !== $confirm_password) {
                $error = 'Passwords do not match';
            } elseif (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters long';
            } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
                $error = 'Password must contain at least one uppercase letter, one lowercase letter, and one number';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
                
                if ($stmt->execute([$hashed_password, $user_id])) {
                    $success = 'Password successfully reset! You can now login.';
                } else {
                    $error = 'Failed to reset password. Please try again.';
                }
            }
        } else {
            $error = 'Please fill in all fields';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Beaute Aesthetic Studio</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <section class="auth-section">
        <div class="container">
            <div class="auth-card">
                <h2><i class="fas fa-lock-open"></i> Reset Password</h2>
                <p>Set a new secure password for your account</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <p class="auth-link">
                        <a href="login.php" class="btn btn-primary btn-block">Go to Login</a>
                    </p>
                <?php elseif ($valid_token): ?>
                    <form method="POST" action="reset-password.php">
                        <?php csrfTokenField(); ?>
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> New Password</label>
                            <div class="password-input">
                                <input type="password" name="password" id="password" required minlength="8" 
                                       pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" 
                                       title="Must contain at least one number and one uppercase and lowercase letter, and at least 8 or more characters">
                                <i class="fas fa-eye" onclick="togglePassword('password')"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Confirm New Password</label>
                            <div class="password-input">
                                <input type="password" name="confirm_password" id="confirm_password" required minlength="8"
                                       pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" 
                                       title="Must contain at least one number and one uppercase and lowercase letter, and at least 8 or more characters">
                                <i class="fas fa-eye" onclick="togglePassword('confirm_password')"></i>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
                    </form>
                <?php endif; ?>
                
                <p class="auth-link">
                    <a href="login.php">Back to Login</a>
                </p>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>
