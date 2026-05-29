<?php
require_once '../config/functions.php';

// Detect context
$context = $_POST['context'] ?? 'client'; // default to client for backward compatibility
$session_user_key = 'user_id';
$session_pic_key = 'profile_picture';

if ($context === 'staff') {
    $session_user_key = 'staff_user_id';
    $session_pic_key = 'staff_profile_pic';
} elseif ($context === 'admin') {
    $session_user_key = 'admin_id';
    $session_pic_key = 'admin_profile_pic';
} else {
    $session_pic_key = 'user_profile_pic'; // Specific key for client
}

// Ensure the user is logged in for the given context
if (!isset($_SESSION[$session_user_key])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$conn = getDBConnection();
$user_id = $_SESSION[$session_user_key];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        if (!isset($_FILES['profile_pic']) || $_FILES['profile_pic']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
            exit;
        }

        $file = $_FILES['profile_pic'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.']);
            exit;
        }

        $upload_dir = '../uploads/profile_pics/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $file_name = 'profile_' . $user_id . '_' . time() . '.' . $file_ext;
        $target_path = $upload_dir . $file_name;

        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            // Delete old profile picture if exists
            $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $old_pic = $stmt->fetchColumn();
            if ($old_pic && file_exists('../' . $old_pic) && strpos($old_pic, 'default') === false) {
                unlink('../' . $old_pic);
            }

            // Update database
            $db_path = 'uploads/profile_pics/' . $file_name;
            $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            $stmt->execute([$db_path, $user_id]);

            $_SESSION[$session_pic_key] = $db_path;

            echo json_encode(['success' => true, 'message' => 'Profile picture updated successfully.', 'path' => $db_path]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
        }
    } elseif ($action === 'remove') {
        // Delete profile picture
        $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $old_pic = $stmt->fetchColumn();
        
        if ($old_pic && file_exists('../' . $old_pic) && strpos($old_pic, 'default') === false) {
            unlink('../' . $old_pic);
        }

        $stmt = $conn->prepare("UPDATE users SET profile_picture = NULL WHERE id = ?");
        $stmt->execute([$user_id]);

        $_SESSION[$session_pic_key] = null;

        echo json_encode(['success' => true, 'message' => 'Profile picture removed successfully.']);
    }
}
