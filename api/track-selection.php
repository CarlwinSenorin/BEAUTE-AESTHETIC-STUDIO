<?php
require_once '../config/functions.php';
header('Content-Type: application/json');

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_POST['action'] ?? '';
$staff_id = isset($_POST['staff_id']) ? ($_POST['staff_id'] !== '' ? (int)$_POST['staff_id'] : null) : null;
$date = $_POST['date'] ?? null;
$time = $_POST['time'] ?? null;
$identifier = $_POST['identifier'] ?? null;
$session_id = session_id();

if (!$session_id) {
    jsonResponse(['success' => false, 'message' => 'No active session'], 400);
}

if ($action === 'select') {
    if (!$date || !$time || !$identifier) {
        jsonResponse(['success' => false, 'message' => 'Missing required data'], 400);
    }
    
    $duration = (int)($_POST['duration'] ?? 60);
    $success = addTemporarySelection($staff_id, $date, $time, $session_id, $identifier, $duration);
    jsonResponse(['success' => $success]);

} elseif ($action === 'release') {
    $success = removeTemporarySelection(null, null, null, $session_id, $identifier);
    jsonResponse(['success' => $success]);

} elseif ($action === 'release_all') {
    // Clear ALL temporary selections for this session (used when starting a new booking)
    $success = removeTemporarySelection(null, null, null, $session_id);
    jsonResponse(['success' => $success]);

} else {
    jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}
