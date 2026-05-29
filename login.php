<?php
require_once 'config/functions.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$lockoutMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Security validation failed. Please refresh the page and try again.';
    } else {
        $email = sanitizeWithLimit($_POST['email'] ?? '', 255);
        $password = $_POST['password'] ?? '';
        
        if ($email && $password) {
            // Validate email format
            if (!validateEmail($email)) {
                $error = 'Invalid email format';
            } else {
                // Rate limiting check
                $identifier = $email . '|' . getUserIP();
                $rateLimit = checkRateLimit($identifier, 5, 900); // 5 attempts per 15 minutes
                
                if (!$rateLimit['allowed']) {
                    $minutesLeft = ceil(($rateLimit['reset_time'] - time()) / 60);
                    $lockoutMessage = "Too many failed login attempts. Please try again in {$minutesLeft} minute(s).";
                    $error = $lockoutMessage;
                } else {
                    $conn = getDBConnection();
                    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch();
                    
                    if ($user && password_verify($password, $user['password'])) {
                        // Security: Prevent admins from logging in through user login
                        if ($user['role'] === 'admin') {
                            $error = 'Administrators must use the admin login page.';
                            recordLoginAttempt($identifier, false);
                        } else {
                            // Successful login
                            recordLoginAttempt($identifier, true);
                            
                            // Regenerate session ID to prevent session fixation
                            regenerateSession();
                            
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                            $_SESSION['role'] = $user['role'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['last_activity'] = time();
                            
                            // Update last login time
                            try {
                                $stmt = $conn->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?");
                                $stmt->execute([getUserIP(), $user['id']]);
                            } catch (PDOException $e) {
                                // Silently fail if columns don't exist
                            }
                            
                            $redirect = $_GET['redirect'] ?? 'dashboard.php';
                            // Basic safety check for redirect
                            if (strpos($redirect, 'http') === 0 && strpos($redirect, BASE_URL) !== 0) {
                                $redirect = 'dashboard.php';
                            }
                            header('Location: ' . $redirect);
                            exit;
                        }
                    } else {
                        recordLoginAttempt($identifier, false);
                        $remaining = $rateLimit['remaining'] - 1;
                        if ($remaining > 0) {
                            $error = "Invalid email or password. {$remaining} attempt(s) remaining.";
                        } else {
                            $error = 'Invalid email or password. Account temporarily locked.';
                        }
                    }
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
    <title>Login - Beaute Aesthetic Studio</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <section class="auth-section">
        <div class="container">
            <div class="auth-card">
                <h2><i class="fas fa-spa"></i> Welcome Back</h2>
                <p>Login to manage your appointments</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="login.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>">
                    <?php csrfTokenField(); ?>
                    
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" name="email" required autofocus value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password</label>
                        <div class="password-input">
                            <input type="password" name="password" id="password" required>
                            <i class="fas fa-eye" onclick="togglePassword('password')"></i>
                        </div>
                        <div class="form-help">
                            <a href="forgot-password.php" class="forgot-password-link">Forgot Password?</a>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">Login</button>
                </form>
                
                <p class="auth-link">
                    Don't have an account? <a href="register.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>">Sign up here</a>
                </p>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>
