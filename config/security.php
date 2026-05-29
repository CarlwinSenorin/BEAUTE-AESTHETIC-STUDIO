<?php
/**
 * Security Helper Functions
 * 
 * Provides CSRF protection, rate limiting, and secure file upload validation
 * 
 * @package BeauteAestheticStudio
 * @version 1.0.0
 */

// CSRF Protection Functions

/**
 * Generate a CSRF token and store it in the session
 * 
 * @return string The generated CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token from form submission
 * 
 * @param string $token The token to validate
 * @return bool True if valid, false otherwise
 */
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Output a hidden CSRF token input field
 */
function csrfTokenField() {
    $token = generateCSRFToken();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Require valid CSRF token or die with error
 */
function requireCSRFToken() {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        http_response_code(403);
        die('CSRF validation failed. Please refresh the page and try again.');
    }
}

// Rate Limiting Functions

/**
 * Check if rate limit has been exceeded
 * 
 * @param string $identifier Unique identifier (email, IP, etc.)
 * @param int $maxAttempts Maximum allowed attempts
 * @param int $timeWindow Time window in seconds
 * @return array ['allowed' => bool, 'remaining' => int, 'reset_time' => int]
 */
function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 900) {
    $conn = getDBConnection();
    
    // Clean old attempts
    $cutoffTime = date('Y-m-d H:i:s', time() - $timeWindow);
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE identifier = ? AND attempt_time < ?");
    $stmt->execute([$identifier, $cutoffTime]);
    
    // Count recent attempts
    $stmt = $conn->prepare("SELECT COUNT(*) as count, MIN(attempt_time) as first_attempt 
                           FROM login_attempts 
                           WHERE identifier = ? AND success = 0");
    $stmt->execute([$identifier]);
    $result = $stmt->fetch();
    
    $attempts = (int)$result['count'];
    $remaining = max(0, $maxAttempts - $attempts);
    $resetTime = $result['first_attempt'] ? strtotime($result['first_attempt']) + $timeWindow : time();
    
    return [
        'allowed' => $attempts < $maxAttempts,
        'remaining' => $remaining,
        'reset_time' => $resetTime,
        'attempts' => $attempts
    ];
}

/**
 * Record a login attempt
 * 
 * @param string $identifier Unique identifier (email, IP, etc.)
 * @param bool $success Whether the login was successful
 */
function recordLoginAttempt($identifier, $success = false) {
    $conn = getDBConnection();
    
    try {
        $stmt = $conn->prepare("INSERT INTO login_attempts (identifier, success) VALUES (?, ?)");
        $stmt->execute([$identifier, $success ? 1 : 0]);
        
        // If successful, clear failed attempts
        if ($success) {
            $stmt = $conn->prepare("DELETE FROM login_attempts WHERE identifier = ? AND success = 0");
            $stmt->execute([$identifier]);
        }
    } catch (PDOException $e) {
        // Silently fail if table doesn't exist yet
        error_log("Failed to record login attempt: " . $e->getMessage());
    }
}

/**
 * Get user's IP address (handles proxies)
 * 
 * @return string IP address
 */
function getUserIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // Check for proxy headers
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

// Session Security Functions

/**
 * Initialize secure session configuration
 */
function initSecureSession() {
    // Only configure if session hasn't started
    if (session_status() === PHP_SESSION_NONE) {
        // Set secure session parameters
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        // Enable secure flag if using HTTPS
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', 1);
        }
        
        // Set session timeout (30 minutes)
        ini_set('session.gc_maxlifetime', 1800);
        ini_set('session.cookie_lifetime', 1800);
    }
}

/**
 * Regenerate session ID for security
 */
function regenerateSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/**
 * Check if session has timed out
 * 
 * @return bool True if session is valid, false if timed out
 */
function checkSessionTimeout() {
    $timeout = 1800; // 30 minutes
    
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > $timeout) {
            // Session timed out
            session_unset();
            session_destroy();
            return false;
        }
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}

// File Upload Security Functions

/**
 * Validate uploaded image file
 * 
 * @param array $file $_FILES array element
 * @param int $maxSize Maximum file size in bytes (default 5MB)
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validateImageUpload($file, $maxSize = 5242880) {
    // Check if file was uploaded
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'No file uploaded or upload error occurred'];
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        $maxMB = round($maxSize / 1048576, 1);
        return ['valid' => false, 'error' => "File size exceeds maximum of {$maxMB}MB"];
    }
    
    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mimeType, $allowedMimes)) {
        return ['valid' => false, 'error' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed'];
    }
    
    // Validate file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extension, $allowedExtensions)) {
        return ['valid' => false, 'error' => 'Invalid file extension'];
    }
    
    // Verify it's actually an image
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return ['valid' => false, 'error' => 'File is not a valid image'];
    }
    
    // Check image dimensions (optional - max 4000x4000)
    if ($imageInfo[0] > 4000 || $imageInfo[1] > 4000) {
        return ['valid' => false, 'error' => 'Image dimensions too large (max 4000x4000)'];
    }
    
    return ['valid' => true, 'error' => null];
}

/**
 * Generate a safe filename
 * 
 * @param string $originalName Original filename
 * @param string $prefix Optional prefix
 * @return string Safe filename
 */
function generateSafeFilename($originalName, $prefix = 'upload_') {
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $safeName = $prefix . uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    return $safeName;
}

/**
 * Securely move uploaded file
 * 
 * @param array $file $_FILES array element
 * @param string $uploadDir Upload directory path
 * @param string $prefix Filename prefix
 * @return array ['success' => bool, 'path' => string|null, 'error' => string|null]
 */
function secureFileUpload($file, $uploadDir, $prefix = 'upload_') {
    // Validate the upload
    $validation = validateImageUpload($file);
    if (!$validation['valid']) {
        return ['success' => false, 'path' => null, 'error' => $validation['error']];
    }
    
    // Create directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            return ['success' => false, 'path' => null, 'error' => 'Failed to create upload directory'];
        }
    }
    
    // Generate safe filename
    $filename = generateSafeFilename($file['name'], $prefix);
    $targetPath = $uploadDir . $filename;
    
    // Move the file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Set proper permissions
        chmod($targetPath, 0644);
        return ['success' => true, 'path' => $filename, 'error' => null];
    }
    
    return ['success' => false, 'path' => null, 'error' => 'Failed to move uploaded file'];
}

// Input Validation Functions

/**
 * Validate email address
 * 
 * @param string $email Email to validate
 * @return bool True if valid
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (basic validation)
 * 
 * @param string $phone Phone number to validate
 * @return bool True if valid
 */
function validatePhone($phone) {
    // Remove common formatting characters
    $cleaned = preg_replace('/[^0-9+]/', '', $phone);
    // Check if it's between 10-15 digits
    return strlen($cleaned) >= 10 && strlen($cleaned) <= 15;
}

/**
 * Sanitize string with length limit
 * 
 * @param string $input Input string
 * @param int $maxLength Maximum length
 * @return string Sanitized string
 */
function sanitizeWithLimit($input, $maxLength = 255) {
    $sanitized = htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    return substr($sanitized, 0, $maxLength);
}
