<?php
/**
 * Staff / Admin Login Page
 */

require_once '../config/functions.php';

// If already logged in as admin, redirect to admin panel
if (isAdmin()) {
    header('Location: index.php');
    exit;
}

// If already logged in as staff, redirect to staff panel
if (isStaffLoggedIn()) {
    header('Location: ../staff/index.php');
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
                // Stricter rate limiting for admin (3 attempts per 15 minutes)
                $identifier = 'admin_' . $email . '|' . getUserIP();
                $rateLimit = checkRateLimit($identifier, 3, 900);
                
                if (!$rateLimit['allowed']) {
                    $minutesLeft = ceil(($rateLimit['reset_time'] - time()) / 60);
                    $lockoutMessage = "Too many failed login attempts. Please try again in {$minutesLeft} minute(s).";
                    $error = $lockoutMessage;
                    
                    // Log suspicious admin login attempts
                    error_log("Admin login lockout for email: {$email} from IP: " . getUserIP());
                } else {
                    $conn = getDBConnection();
                    
                    // First check if user exists at all
                    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch();
                    
                    if (!$user) {
                        recordLoginAttempt($identifier, false);
                        $error = 'No account found with this email address';
                    } elseif (!in_array($user['role'], ['admin', 'staff'])) {
                        recordLoginAttempt($identifier, false);
                        $error = 'This account does not have staff or admin privileges. Please use the regular login page.';
                    } elseif ($user['status'] !== 'active') {
                        recordLoginAttempt($identifier, false);
                        $error = 'This account is inactive. Please contact the administrator.';
                    } else {
                        // User exists, is admin, and is active - check password
                        if (password_verify($password, $user['password'])) {
                            recordLoginAttempt($identifier, true);
                            
                            // Regenerate session ID to prevent session fixation
                            regenerateSession();
                            
                            $_SESSION['last_activity'] = time();
                            
                            // Update last login time
                            try {
                                $stmt = $conn->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?");
                                $stmt->execute([getUserIP(), $user['id']]);
                            } catch (PDOException $e) {
                                // Silently fail if columns don't exist
                            }
                            
                            if ($user['role'] === 'admin') {
                                // Admin session
                                $_SESSION['admin_id'] = $user['id'];
                                $_SESSION['admin_name'] = $user['first_name'] . ' ' . $user['last_name'];
                                $_SESSION['admin_role'] = $user['role'];
                                $_SESSION['admin_email'] = $user['email'];
                                $_SESSION['admin_logged_in'] = true;
                                error_log("Admin login successful for: {$email} from IP: " . getUserIP());
                                header('Location: index.php');
                            } else {
                                // Staff session
                                // Get staff record id
                                $stmtStaff = $conn->prepare("SELECT id FROM staff WHERE user_id = ?");
                                $stmtStaff->execute([$user['id']]);
                                $staffRecord = $stmtStaff->fetch();
                                
                                $_SESSION['staff_user_id'] = $user['id'];
                                $_SESSION['staff_id'] = $staffRecord ? $staffRecord['id'] : null;
                                $_SESSION['staff_name'] = $user['first_name'] . ' ' . $user['last_name'];
                                $_SESSION['staff_role'] = $user['role'];
                                $_SESSION['staff_email'] = $user['email'];
                                $_SESSION['staff_logged_in'] = true;
                                error_log("Staff login successful for: {$email} from IP: " . getUserIP());
                                header('Location: ../staff/index.php');
                            }
                            exit;
                        } else {
                            recordLoginAttempt($identifier, false);
                            $remaining = $rateLimit['remaining'] - 1;
                            
                            // Log failed admin login attempt
                            error_log("Failed admin login attempt for: {$email} from IP: " . getUserIP());
                            
                            if ($remaining > 0) {
                                $error = "Invalid password. {$remaining} attempt(s) remaining.";
                            } else {
                                $error = 'Invalid password. Account temporarily locked.';
                            }
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
    <title>Staff / Admin Login - Beaute Aesthetic Studio</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .admin-login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #d4a574 0%, #8b6f47 100%);
            padding: 20px;
        }
        .admin-login-card {
            background: white;
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 450px;
            width: 100%;
        }
        .admin-login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .admin-login-header h1 {
            color: #d4a574;
            margin-bottom: 0.5rem;
        }
        .admin-login-header .icon {
            font-size: 3rem;
            color: #d4a574;
            margin-bottom: 1rem;
        }
        .admin-login-header p {
            color: #666;
        }
        .security-notice {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: #856404;
        }
        .security-notice i {
            margin-right: 0.5rem;
        }
        .back-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #ddd;
        }
        .back-link a {
            color: #666;
            text-decoration: none;
        }
        .back-link a:hover {
            color: #d4a574;
        }
    </style>
</head>
<body>
    <div class="admin-login-container">
        <div class="admin-login-card">
            <div class="admin-login-header">
                <div class="icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1>Staff Portal</h1>
                <p>Staff &amp; Administrator Login</p>
            </div>
            
            <div class="security-notice">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Restricted Area:</strong> Only authorized staff and administrators may log in here.
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="login.php">
                <?php csrfTokenField(); ?>
                
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="email" required autofocus placeholder="admin@beauteaesthetic.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <div class="password-input">
                        <input type="password" name="password" id="password" required placeholder="Enter your password">
                        <i class="fas fa-eye" onclick="togglePassword('password')"></i>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            
            <div class="back-link">
                <a href="../index.php"><i class="fas fa-arrow-left"></i> Back to Website</a>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
