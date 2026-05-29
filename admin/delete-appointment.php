<?php
require_once '../config/functions.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    
    if ($id) {
        // Use soft delete instead of hard delete
        $result = softDeleteAppointment($id, 'Deleted by Admin');
        
        if ($result['success']) {
            header('Location: appointments.php?msg=deleted');
        } else {
            header('Location: appointments.php?error=' . urlencode($result['message']));
        }
    } else {
        header('Location: appointments.php?error=invalid_id');
    }
} else {
    // Redirect if accessed directly
    header('Location: appointments.php');
}
exit;
