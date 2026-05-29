<?php
require_once '../config/functions.php';
header('Content-Type: application/json');

$name = sanitize($_POST['name'] ?? '');
$email = sanitize($_POST['email'] ?? '');
$phone = sanitize($_POST['phone'] ?? '');
$message = sanitize($_POST['message'] ?? '');

// Validate email
if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse([
        'success' => false,
        'message' => 'Please provide a valid email address.'
    ], 400);
}

if ($name && $email && $message) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, phone, message, status) VALUES (?, ?, ?, ?, 'new')");
        $stmt->execute([$name, $email, $phone, $message]);

        jsonResponse([
            'success' => true,
            'message' => 'Thank you for your message! We will get back to you soon.'
        ]);
    } catch (PDOException $e) {
        // Log error and return failure
        error_log("Contact form error: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'message' => 'Sorry, there was an error sending your message. Please try again later.'
        ], 500);
    }
} else {
    jsonResponse([
        'success' => false,
        'message' => 'Please fill in all required fields.'
    ], 400);
}
