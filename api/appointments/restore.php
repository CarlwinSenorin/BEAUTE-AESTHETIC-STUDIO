<?php
require_once '../../config/functions.php';
header('Content-Type: application/json');
requireLogin();

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$appointment_id = isset($data['appointment_id']) ? (int)$data['appointment_id'] : null;

if (!$appointment_id) {
    jsonResponse(['success' => false, 'message' => 'Appointment ID is required'], 400);
}

$result = restoreAppointment($appointment_id);
jsonResponse($result);
